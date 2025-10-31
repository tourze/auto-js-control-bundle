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
use Tourze\AutoJsControlBundle\Controller\AbstractApiController;
use Tourze\AutoJsControlBundle\Dto\Response\ScriptDownloadResponse;
use Tourze\AutoJsControlBundle\Entity\AutoJsDevice;
use Tourze\AutoJsControlBundle\Entity\Script;
use Tourze\AutoJsControlBundle\Repository\AutoJsDeviceRepository;
use Tourze\AutoJsControlBundle\Repository\ScriptRepository;

#[Autoconfigure(public: true)]
final class GetScriptController extends AbstractApiController
{
    public function __construct(
        private readonly AutoJsDeviceRepository $autoJsDeviceRepository,
        private readonly DeviceRepository $deviceRepository,
        private readonly ScriptRepository $scriptRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route(path: '/api/autojs/v1/device/script/{scriptId}', name: 'auto_js_device_get_script', requirements: ['scriptId' => '\d+'], methods: ['GET'])]
    public function __invoke(Request $request, int $scriptId): JsonResponse
    {
        try {
            $this->validateScriptRequest($request);

            $deviceCode = $request->query->get('deviceCode');
            if (!is_string($deviceCode) || '' === $deviceCode) {
                throw new UnauthorizedHttpException('Missing parameters', '缺少设备代码参数');
            }
            $autoJsDevice = $this->getAutoJsDeviceByCode($deviceCode);

            $signature = $request->query->get('signature');
            $timestamp = $request->query->get('timestamp');
            $certificate = $autoJsDevice->getCertificate();

            if (!is_string($signature) || '' === $signature) {
                throw new UnauthorizedHttpException('Missing parameters', '缺少签名参数');
            }
            if (!is_numeric($timestamp) || 0 === (int) $timestamp) {
                throw new UnauthorizedHttpException('Missing parameters', '缺少或无效的时间戳参数');
            }
            if (null === $certificate) {
                throw new UnauthorizedHttpException('Invalid certificate', '设备证书不存在');
            }

            $this->verifyDeviceSignature(
                $deviceCode,
                $signature,
                (int) $timestamp,
                $certificate
            );

            $script = $this->getScriptById($scriptId);
            $response = $this->buildScriptDownloadResponse($script);

            return $this->successResponse($response->jsonSerialize());
        } catch (UnauthorizedHttpException $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_UNAUTHORIZED);
        } catch (NotFoundHttpException $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            $this->logger->error('获取脚本失败', [
                'scriptId' => $scriptId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->errorResponse('获取脚本失败: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function validateScriptRequest(Request $request): void
    {
        $deviceCode = $request->query->get('deviceCode', '');
        $signature = $request->query->get('signature', '');
        $timestamp = (int) $request->query->get('timestamp', 0);

        if ('' === $deviceCode || '' === $signature || 0 === $timestamp) {
            throw new UnauthorizedHttpException('Missing parameters', '缺少必要的认证参数');
        }
    }

    private function getScriptById(int $scriptId): Script
    {
        $script = $this->scriptRepository->find($scriptId);
        if (null === $script) {
            throw new NotFoundHttpException('脚本不存在');
        }

        return $script;
    }

    private function buildScriptDownloadResponse(Script $script): ScriptDownloadResponse
    {
        return new ScriptDownloadResponse(
            status: 'ok',
            scriptId: $script->getId(),
            scriptCode: $script->getCode(),
            scriptName: $script->getName(),
            scriptType: $script->getScriptType()->value,
            content: $script->getContent(),
            version: (string) $script->getVersion(),
            parameters: $this->parseScriptParameters($script->getParameters()),
            timeout: $script->getTimeout(),
            checksum: hash('sha256', $script->getContent() ?? ''),
            serverTime: new \DateTimeImmutable()
        );
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

    /**
     * @return array<string, mixed>
     */
    private function parseScriptParameters(?string $parameters): array
    {
        if (null === $parameters || '' === $parameters) {
            return [];
        }

        $decoded = json_decode($parameters, true);
        if (!is_array($decoded)) {
            $this->logger->warning('Invalid JSON in script parameters', ['parameters' => $parameters]);

            return [];
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
