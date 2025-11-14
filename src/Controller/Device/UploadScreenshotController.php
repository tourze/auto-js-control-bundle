<?php

namespace Tourze\AutoJsControlBundle\Controller\Device;

use DeviceBundle\Repository\DeviceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Tourze\AutoJsControlBundle\Controller\AbstractApiController;
use Tourze\AutoJsControlBundle\Entity\AutoJsDevice;
use Tourze\AutoJsControlBundle\Repository\AutoJsDeviceRepository;
use Tourze\AutoJsControlBundle\Repository\ScriptExecutionRecordRepository;

#[Autoconfigure(public: true)]
final class UploadScreenshotController extends AbstractApiController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AutoJsDeviceRepository $autoJsDeviceRepository,
        private readonly DeviceRepository $deviceRepository,
        private readonly ScriptExecutionRecordRepository $executionRecordRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route(path: '/api/autojs/v1/device/screenshot', name: 'auto_js_device_upload_screenshot', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $deviceCode = $request->request->get('deviceCode');
            $signature = $request->request->get('signature');
            $timestamp = $request->request->get('timestamp');
            $instructionId = $request->request->get('instructionId', '');

            if (!is_string($deviceCode) || '' === $deviceCode) {
                throw new UnauthorizedHttpException('Missing parameters', '缺少设备代码参数');
            }
            if (!is_string($signature) || '' === $signature) {
                throw new UnauthorizedHttpException('Missing parameters', '缺少签名参数');
            }
            if (!is_numeric($timestamp) || 0 === (int) $timestamp) {
                throw new UnauthorizedHttpException('Missing parameters', '缺少或无效的时间戳参数');
            }

            $autoJsDevice = $this->getAutoJsDeviceByCode($deviceCode);
            $certificate = $autoJsDevice->getCertificate();

            if (null === $certificate) {
                throw new UnauthorizedHttpException('Invalid certificate', '设备证书不存在');
            }

            $this->verifyDeviceSignature($deviceCode, $signature, (int) $timestamp, $certificate);

            $filename = $this->handleFileUpload($request, 'screenshot');
            if (null === $filename) {
                throw new BadRequestHttpException('未找到截图文件');
            }

            if ('' !== $instructionId) {
                $executionRecord = $this->executionRecordRepository->findOneBy([
                    'instructionId' => $instructionId,
                    'autoJsDevice' => $autoJsDevice,
                ]);

                if (null !== $executionRecord) {
                    $screenshots = $executionRecord->getScreenshots() ?? [];
                    $screenshots[] = $filename;
                    $executionRecord->setScreenshots($screenshots);

                    $this->entityManager->persist($executionRecord);
                    $this->entityManager->flush();
                }
            }

            return $this->successResponse([
                'status' => 'ok',
                'message' => '截图上传成功',
                'filename' => $filename,
                'serverTime' => new \DateTimeImmutable()->format('c'),
            ]);
        } catch (UnauthorizedHttpException $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_UNAUTHORIZED);
        } catch (\Exception $e) {
            $this->logger->error('上传截图失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->errorResponse('上传截图失败: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
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
