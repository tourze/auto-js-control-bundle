<?php

namespace Tourze\AutoJsControlBundle\Controller\Device;

use DeviceBundle\Enum\DeviceStatus;
use DeviceBundle\Repository\DeviceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Tourze\AutoJsControlBundle\Controller\AbstractApiController;
use Tourze\AutoJsControlBundle\Controller\ValidatorAwareTrait;
use Tourze\AutoJsControlBundle\Dto\Request\DeviceHeartbeatRequest;
use Tourze\AutoJsControlBundle\Dto\Response\DeviceHeartbeatResponse;
use Tourze\AutoJsControlBundle\Entity\AutoJsDevice;
use Tourze\AutoJsControlBundle\Entity\DeviceMonitorData;
use Tourze\AutoJsControlBundle\Exception\DeviceAuthException;
use Tourze\AutoJsControlBundle\Repository\AutoJsDeviceRepository;
use Tourze\AutoJsControlBundle\Service\CacheStorageService;
use Tourze\AutoJsControlBundle\Service\StorageAdapterInterface;
use Tourze\AutoJsControlBundle\ValueObject\DeviceInstruction;
use Tourze\AutoJsControlBundle\ValueObject\RedisQueueKeys;

#[Autoconfigure(public: true)]
final class HeartbeatController extends AbstractApiController
{
    use ValidatorAwareTrait;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AutoJsDeviceRepository $autoJsDeviceRepository,
        private readonly DeviceRepository $deviceRepository,
        private readonly LoggerInterface $logger,
        private readonly StorageAdapterInterface $storage,
        private readonly CacheStorageService $cacheStorage,
        ValidatorInterface $validator,
    ) {
        $this->setValidator($validator);
    }

    #[Route(path: '/api/autojs/v1/device/heartbeat', name: 'auto_js_device_heartbeat', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $data = $this->getJsonData($request);
            $heartbeatRequest = $this->createHeartbeatRequest($data);
            $this->validateRequest($heartbeatRequest);

            $autoJsDevice = $this->authenticateDevice($heartbeatRequest);
            $this->processHeartbeat($autoJsDevice, $heartbeatRequest);

            $instructionObjects = $this->longPollForInstructions(
                $heartbeatRequest->getDeviceCode(),
                $heartbeatRequest->getPollTimeout()
            );

            $response = DeviceHeartbeatResponse::success($instructionObjects);

            return $this->successResponse($response->jsonSerialize());
        } catch (UnauthorizedHttpException $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_UNAUTHORIZED);
        } catch (\Exception $e) {
            $this->logger->error('设备心跳处理失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->errorResponse('心跳处理失败: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createHeartbeatRequest(array $data): DeviceHeartbeatRequest
    {
        $deviceCode = is_string($data['deviceCode'] ?? null) ? $data['deviceCode'] : '';
        $signature = is_string($data['signature'] ?? null) ? $data['signature'] : '';
        $timestamp = is_int($data['timestamp'] ?? null) ? $data['timestamp'] : 0;
        $autoJsVersion = isset($data['autoJsVersion']) && is_string($data['autoJsVersion']) ? $data['autoJsVersion'] : null;
        $deviceInfo = is_array($data['deviceInfo'] ?? null) ? $data['deviceInfo'] : [];
        $monitorData = is_array($data['monitorData'] ?? null) ? $data['monitorData'] : [];
        $pollTimeout = is_int($data['pollTimeout'] ?? null) ? $data['pollTimeout'] : 30;

        return new DeviceHeartbeatRequest(
            deviceCode: $deviceCode,
            signature: $signature,
            timestamp: $timestamp,
            autoJsVersion: $autoJsVersion,
            deviceInfo: $deviceInfo,
            monitorData: $monitorData,
            pollTimeout: $pollTimeout
        );
    }

    private function authenticateDevice(DeviceHeartbeatRequest $heartbeatRequest): AutoJsDevice
    {
        $autoJsDevice = $this->getAutoJsDeviceByCode($heartbeatRequest->getDeviceCode());
        if (null === $autoJsDevice) {
            throw new UnauthorizedHttpException('Device not found', '签名验证失败');
        }

        $certificate = $autoJsDevice->getCertificate();
        if (null === $certificate || !$heartbeatRequest->verifySignature($certificate)) {
            throw new UnauthorizedHttpException('Invalid signature', '签名验证失败');
        }

        return $autoJsDevice;
    }

    private function processHeartbeat(AutoJsDevice $autoJsDevice, DeviceHeartbeatRequest $heartbeatRequest): void
    {
        $this->updateDeviceStatus($autoJsDevice, $heartbeatRequest);

        if ([] !== $heartbeatRequest->getMonitorData()) {
            $this->saveMonitorData($autoJsDevice, $heartbeatRequest->getMonitorData());
        }
    }

    private function getAutoJsDeviceByCode(string $deviceCode): ?AutoJsDevice
    {
        $baseDevice = $this->deviceRepository->findOneBy(['code' => $deviceCode]);
        if (null === $baseDevice) {
            return null;
        }

        $autoJsDevice = $this->autoJsDeviceRepository->findOneBy(['baseDevice' => $baseDevice]);
        if (null === $autoJsDevice) {
            return null;
        }

        return $autoJsDevice;
    }

    private function updateDeviceStatus(AutoJsDevice $autoJsDevice, DeviceHeartbeatRequest $request): void
    {
        $baseDevice = $autoJsDevice->getBaseDevice();
        if (null === $baseDevice) {
            throw DeviceAuthException::invalidCertificate($request->getDeviceCode());
        }
        $baseDevice->setStatus(DeviceStatus::ONLINE);
        $baseDevice->setLastOnlineTime(new \DateTimeImmutable());

        if (null !== $request->getAutoJsVersion()) {
            $autoJsDevice->setAutoJsVersion($request->getAutoJsVersion());
        }

        $this->entityManager->persist($baseDevice);
        $this->entityManager->persist($autoJsDevice);
        $this->entityManager->flush();

        $this->updateDeviceOnlineStatus($request->getDeviceCode());
    }

    /**
     * @param array<string, mixed> $monitorData
     */
    private function saveMonitorData(AutoJsDevice $autoJsDevice, array $monitorData): void
    {
        $data = new DeviceMonitorData();
        $data->setAutoJsDevice($autoJsDevice);
        $data->setMonitorTime(new \DateTimeImmutable());

        $cpuUsage = $monitorData['cpuUsage'] ?? null;
        if (is_float($cpuUsage) || is_int($cpuUsage)) {
            $data->setCpuUsage((float) $cpuUsage);
        }

        $memoryUsage = $monitorData['memoryUsage'] ?? null;
        if (is_float($memoryUsage) || is_int($memoryUsage)) {
            $data->setMemoryUsage((float) $memoryUsage);
        }

        $availableStorage = $monitorData['availableStorage'] ?? null;
        if (is_int($availableStorage) || (is_string($availableStorage) && is_numeric($availableStorage))) {
            $data->setAvailableStorage((int) $availableStorage);
        }

        $batteryLevel = $monitorData['batteryLevel'] ?? null;
        if (is_float($batteryLevel) || is_int($batteryLevel) || (is_string($batteryLevel) && is_numeric($batteryLevel))) {
            $data->setBatteryLevel((float) $batteryLevel);
        }

        $networkType = $monitorData['networkType'] ?? null;
        if (is_string($networkType)) {
            $data->setNetworkType($networkType);
        }

        $data->setAdditionalData($monitorData);

        $this->entityManager->persist($data);
        $this->entityManager->flush();
    }

    /**
     * @return array<int, DeviceInstruction>
     */
    private function longPollForInstructions(string $deviceCode, int $timeout): array
    {
        $queueKey = RedisQueueKeys::getDeviceInstructionQueue($deviceCode);
        $instructions = $this->getExistingInstructions($queueKey);

        if ([] !== $instructions) {
            return $instructions;
        }

        return $this->waitForNewInstructions($deviceCode, $queueKey, $timeout);
    }

    /**
     * @return array<int, DeviceInstruction>
     */
    private function getExistingInstructions(string $queueKey): array
    {
        $instructions = [];
        while (null !== ($instructionData = $this->storage->rPop($queueKey))) {
            if (!is_string($instructionData)) {
                continue;
            }
            $decodedData = json_decode($instructionData, true);
            if (!is_array($decodedData)) {
                continue;
            }
            /** @var array<string, mixed> $arrayData */
            $arrayData = $decodedData;
            $instruction = DeviceInstruction::fromArray($arrayData);
            if (!$instruction->isExpired()) {
                $instructions[] = $instruction;
            }
        }

        return $instructions;
    }

    /**
     * @return array<int, DeviceInstruction>
     */
    private function waitForNewInstructions(string $deviceCode, string $queueKey, int $timeout): array
    {
        $notifyChannel = RedisQueueKeys::getDevicePollNotify($deviceCode);
        $instructions = [];

        $this->storage->subscribe([$notifyChannel], function ($redis, $channel, $message) use (&$instructions, $queueKey): void {
            if ('message' === $channel) {
                $instructions = $this->collectInstructionsFromQueue($queueKey);
            }
        });

        return $instructions;
    }

    /**
     * @return array<int, DeviceInstruction>
     */
    private function collectInstructionsFromQueue(string $queueKey): array
    {
        $instructions = [];
        while (null !== ($instructionData = $this->storage->rPop($queueKey))) {
            if (!is_string($instructionData)) {
                continue;
            }
            $decodedData = json_decode($instructionData, true);
            if (!is_array($decodedData)) {
                continue;
            }
            /** @var array<string, mixed> $arrayData */
            $arrayData = $decodedData;
            $instruction = DeviceInstruction::fromArray($arrayData);
            if (!$instruction->isExpired()) {
                $instructions[] = $instruction;
            }
        }

        return $instructions;
    }

    private function updateDeviceOnlineStatus(string $deviceCode): void
    {
        $this->cacheStorage->setDeviceOnline($deviceCode, true);
    }
}
