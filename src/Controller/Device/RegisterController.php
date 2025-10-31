<?php

namespace Tourze\AutoJsControlBundle\Controller\Device;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Tourze\AutoJsControlBundle\Controller\AbstractApiController;
use Tourze\AutoJsControlBundle\Controller\ValidatorAwareTrait;
use Tourze\AutoJsControlBundle\Dto\Request\DeviceRegisterRequest;
use Tourze\AutoJsControlBundle\Entity\AutoJsDevice;
use Tourze\AutoJsControlBundle\Service\CacheStorageService;
use Tourze\AutoJsControlBundle\Service\DeviceRegistrationService;

#[Autoconfigure(public: true)]
final class RegisterController extends AbstractApiController
{
    use ValidatorAwareTrait;

    public function __construct(
        private readonly DeviceRegistrationService $registrationService,
        private readonly LoggerInterface $logger,
        private readonly CacheStorageService $cacheStorage,
        ValidatorInterface $validator,
    ) {
        $this->setValidator($validator);
    }

    #[Route(path: '/api/autojs/v1/device/register', name: 'auto_js_device_register', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $data = $this->getJsonData($request);
            $registerRequest = $this->createDeviceRegisterRequest($data);
            $this->validateRequest($registerRequest);

            $result = $this->registrationService->registerDevice(
                $registerRequest,
                $this->getRequestIp($request)
            );

            $this->updateDeviceOnlineStatus($registerRequest->getDeviceCode());

            return $this->buildRegisterSuccessResponse($result['autoJsDevice'], $result['certificate']);
        } catch (BadRequestHttpException $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            $this->logger->error('设备注册失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->errorResponse('设备注册失败: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createDeviceRegisterRequest(array $data): DeviceRegisterRequest
    {
        return new DeviceRegisterRequest(
            deviceCode: $this->extractStringParam($data, 'deviceCode'),
            deviceName: $this->extractStringParam($data, 'deviceName'),
            certificateRequest: $this->extractStringParam($data, 'certificateRequest'),
            model: $this->extractOptionalStringParam($data, 'model'),
            brand: $this->extractOptionalStringParam($data, 'brand'),
            osVersion: $this->extractOptionalStringParam($data, 'osVersion'),
            autoJsVersion: $this->extractOptionalStringParam($data, 'autoJsVersion'),
            fingerprint: $this->extractOptionalStringParam($data, 'fingerprint'),
            hardwareInfo: $this->extractArrayParam($data, 'hardwareInfo')
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
     * @return array<mixed>
     */
    private function extractArrayParam(array $data, string $key): array
    {
        if (!isset($data[$key])) {
            return [];
        }

        return is_array($data[$key]) ? $data[$key] : [];
    }

    private function buildRegisterSuccessResponse(AutoJsDevice $autoJsDevice, string $certificate): JsonResponse
    {
        return $this->successResponse([
            'status' => 'ok',
            'deviceId' => (string) $autoJsDevice->getId(),
            'certificate' => $certificate,
            'message' => '设备注册成功',
            'serverTime' => (new \DateTimeImmutable())->format('c'),
            'config' => [
                'heartbeatInterval' => 30,
                'logUploadInterval' => 300,
            ],
        ]);
    }

    private function updateDeviceOnlineStatus(string $deviceCode): void
    {
        $this->cacheStorage->setDeviceOnline($deviceCode, true);
    }
}
