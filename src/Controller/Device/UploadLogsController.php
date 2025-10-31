<?php

namespace Tourze\AutoJsControlBundle\Controller\Device;

use DeviceBundle\Repository\DeviceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Tourze\AutoJsControlBundle\Controller\AbstractApiController;
use Tourze\AutoJsControlBundle\Controller\ValidatorAwareTrait;
use Tourze\AutoJsControlBundle\Dto\Request\DeviceLogRequest;
use Tourze\AutoJsControlBundle\Entity\AutoJsDevice;
use Tourze\AutoJsControlBundle\Entity\DeviceLog;
use Tourze\AutoJsControlBundle\Repository\AutoJsDeviceRepository;

#[Autoconfigure(public: true)]
final class UploadLogsController extends AbstractApiController
{
    use ValidatorAwareTrait;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AutoJsDeviceRepository $autoJsDeviceRepository,
        private readonly DeviceRepository $deviceRepository,
        private readonly LoggerInterface $logger,
        ValidatorInterface $validator,
    ) {
        $this->setValidator($validator);
    }

    #[Route(path: '/api/autojs/v1/device/logs', name: 'auto_js_device_upload_logs', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $data = $this->getJsonData($request);

            $logRequest = new DeviceLogRequest(
                deviceCode: $data['deviceCode'] ?? '',
                signature: $data['signature'] ?? '',
                timestamp: $data['timestamp'] ?? 0,
                logs: $data['logs'] ?? []
            );

            $this->validateRequest($logRequest);

            $autoJsDevice = $this->getAutoJsDeviceByCode($logRequest->getDeviceCode());
            $certificate = $autoJsDevice->getCertificate();

            $signatureData = sprintf(
                '%s:%d:%d:%s',
                $logRequest->getDeviceCode(),
                $logRequest->getTimestamp(),
                count($logRequest->getLogs()),
                $certificate
            );
            if (null === $certificate) {
                throw new UnauthorizedHttpException('Invalid certificate', '设备证书不存在');
            }
            $expectedSignature = hash_hmac('sha256', $signatureData, $certificate);

            if (!hash_equals($expectedSignature, $logRequest->getSignature())) {
                throw new UnauthorizedHttpException('Invalid signature', '签名验证失败');
            }

            $savedCount = 0;
            foreach ($logRequest->getLogs() as $logEntry) {
                $deviceLog = new DeviceLog();
                $deviceLog->setAutoJsDevice($autoJsDevice);
                $deviceLog->setLevel($logEntry->getLevel());
                $deviceLog->setLogType($logEntry->getType());
                $deviceLog->setMessage($logEntry->getMessage());
                $deviceLog->setLogTime($logEntry->getLogTime());
                $deviceLog->setContext($logEntry->getContext());
                $deviceLog->setStackTrace($logEntry->getStackTrace());

                $this->entityManager->persist($deviceLog);
                ++$savedCount;
            }

            $this->entityManager->flush();

            return $this->successResponse([
                'status' => 'ok',
                'message' => sprintf('已保存 %d 条日志', $savedCount),
                'savedCount' => $savedCount,
                'serverTime' => (new \DateTimeImmutable())->format('c'),
            ]);
        } catch (UnauthorizedHttpException $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_UNAUTHORIZED);
        } catch (\Exception $e) {
            $this->logger->error('上报设备日志失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->errorResponse('上报设备日志失败: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function getAutoJsDeviceByCode(string $deviceCode): AutoJsDevice
    {
        $baseDevice = $this->deviceRepository->findOneBy(['code' => $deviceCode]);
        if (null === $baseDevice) {
            throw new NotFoundHttpException('设备不存在');
        }

        $autoJsDevice = $this->autoJsDeviceRepository->findOneBy(['baseDevice' => $baseDevice]);
        if (null === $autoJsDevice) {
            throw new NotFoundHttpException('Auto.js设备不存在');
        }

        return $autoJsDevice;
    }
}
