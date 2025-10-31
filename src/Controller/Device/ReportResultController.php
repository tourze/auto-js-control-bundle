<?php

namespace Tourze\AutoJsControlBundle\Controller\Device;

use DeviceBundle\Repository\DeviceRepository;
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
use Tourze\AutoJsControlBundle\Dto\Request\ReportExecutionResultRequest;
use Tourze\AutoJsControlBundle\Entity\AutoJsDevice;
use Tourze\AutoJsControlBundle\Enum\ExecutionStatus;
use Tourze\AutoJsControlBundle\Repository\AutoJsDeviceRepository;
use Tourze\AutoJsControlBundle\Service\CacheStorageService;
use Tourze\AutoJsControlBundle\Service\DeviceReportService;
use Tourze\LockServiceBundle\Service\LockService;

#[Autoconfigure(public: true)]
final class ReportResultController extends AbstractApiController
{
    use ValidatorAwareTrait;

    public function __construct(
        private readonly AutoJsDeviceRepository $autoJsDeviceRepository,
        private readonly DeviceRepository $deviceRepository,
        private readonly DeviceReportService $reportService,
        private readonly LoggerInterface $logger,
        private readonly CacheStorageService $cacheStorage,
        private readonly LockService $lockService,
        ValidatorInterface $validator,
    ) {
        $this->setValidator($validator);
    }

    #[Route(path: '/api/autojs/v1/device/report-result', name: 'auto_js_device_report_result', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $data = $this->getJsonData($request);
            $reportRequest = $this->createReportExecutionResultRequest($data);
            $this->validateRequest($reportRequest);

            $deviceCode = $reportRequest->getDeviceCode();
            $instructionId = $reportRequest->getInstructionId();

            // 使用指令锁防止并发上报
            $lockKey = sprintf('instruction_report:%s:%s', $deviceCode, $instructionId);

            $result = $this->lockService->blockingRun($lockKey, function () use ($reportRequest, $deviceCode, $instructionId): JsonResponse {
                $autoJsDevice = $this->getAutoJsDeviceByCode($deviceCode);
                $certificate = $autoJsDevice->getCertificate();
                if (null === $certificate) {
                    throw new UnauthorizedHttpException('Invalid certificate', '设备证书不存在');
                }
                $this->verifyReportSignature($reportRequest, $certificate);

                $this->reportService->processExecutionReport($reportRequest, $autoJsDevice);
                $this->updateInstructionStatus($instructionId, $reportRequest->getStatus()->value);

                return $this->successResponse([
                    'status' => 'ok',
                    'message' => '执行结果已记录',
                    'serverTime' => (new \DateTimeImmutable())->format('c'),
                ]);
            });

            if (!$result instanceof JsonResponse) {
                throw new \RuntimeException('Unexpected return type from lock service');
            }

            return $result;
        } catch (UnauthorizedHttpException $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_UNAUTHORIZED);
        } catch (\Exception $e) {
            $this->logger->error('上报执行结果失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->errorResponse('上报执行结果失败: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createReportExecutionResultRequest(array $data): ReportExecutionResultRequest
    {
        return new ReportExecutionResultRequest(
            deviceCode: $this->extractStringParam($data, 'deviceCode'),
            signature: $this->extractStringParam($data, 'signature'),
            timestamp: $this->extractIntParam($data, 'timestamp'),
            instructionId: $this->extractStringParam($data, 'instructionId'),
            status: $this->extractExecutionStatus($data),
            startTime: $this->extractDateTimeParam($data, 'startTime'),
            endTime: $this->extractDateTimeParam($data, 'endTime'),
            output: $this->extractOptionalStringParam($data, 'output'),
            errorMessage: $this->extractOptionalStringParam($data, 'errorMessage'),
            executionMetrics: $this->extractArrayParam($data, 'executionMetrics'),
            screenshots: $this->extractArrayParam($data, 'screenshots')
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function extractStringParam(array $data, string $key, string $default = ''): string
    {
        if (!isset($data[$key])) {
            return $default;
        }

        return is_string($data[$key]) ? $data[$key] : $default;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function extractOptionalStringParam(array $data, string $key): ?string
    {
        if (!isset($data[$key])) {
            return null;
        }

        return is_string($data[$key]) ? $data[$key] : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function extractIntParam(array $data, string $key, int $default = 0): int
    {
        if (!isset($data[$key])) {
            return $default;
        }

        return is_int($data[$key]) ? $data[$key] : $default;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<mixed>
     */
    private function extractArrayParam(array $data, string $key): array
    {
        if (!isset($data[$key])) {
            return [];
        }

        return is_array($data[$key]) ? $data[$key] : [];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function extractExecutionStatus(array $data): ExecutionStatus
    {
        if (!isset($data['status'])) {
            return ExecutionStatus::PENDING;
        }

        return ExecutionStatus::from($data['status']);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function extractDateTimeParam(array $data, string $key): \DateTimeImmutable
    {
        if (!isset($data[$key])) {
            return new \DateTimeImmutable();
        }

        return new \DateTimeImmutable($data[$key]);
    }

    private function verifyReportSignature(ReportExecutionResultRequest $reportRequest, string $certificate): void
    {
        $signatureData = sprintf(
            '%s:%s:%d:%s',
            $reportRequest->getDeviceCode(),
            $reportRequest->getInstructionId(),
            $reportRequest->getTimestamp(),
            $certificate
        );
        $expectedSignature = hash_hmac('sha256', $signatureData, $certificate);

        if (!hash_equals($expectedSignature, $reportRequest->getSignature())) {
            throw new UnauthorizedHttpException('Invalid signature', '签名验证失败');
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

    private function updateInstructionStatus(string $instructionId, string $status): void
    {
        $this->cacheStorage->updateInstructionStatus($instructionId, [
            'status' => $status,
            'updateTime' => (new \DateTimeImmutable())->format('c'),
        ]);
    }
}
