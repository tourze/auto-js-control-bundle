<?php

namespace Tourze\AutoJsControlBundle\Service;

use DeviceBundle\Entity\Device as BaseDevice;
use DeviceBundle\Enum\DeviceStatus;
use DeviceBundle\Enum\DeviceType;
use DeviceBundle\Repository\DeviceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Tourze\AutoJsControlBundle\Dto\Request\DeviceRegisterRequest;
use Tourze\AutoJsControlBundle\Entity\AutoJsDevice;
use Tourze\AutoJsControlBundle\Repository\AutoJsDeviceRepository;

/**
 * 设备注册服务
 *
 * 负责处理设备注册的业务逻辑
 */
#[Autoconfigure(public: true)]
readonly class DeviceRegistrationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DeviceRepository $deviceRepository,
        private AutoJsDeviceRepository $autoJsDeviceRepository,
        private DeviceAuthService $authService,
    ) {
    }

    /**
     * 注册或更新设备.
     *
     * @return array{autoJsDevice: AutoJsDevice, certificate: string}
     */
    public function registerDevice(DeviceRegisterRequest $request, string $clientIp): array
    {
        $this->entityManager->beginTransaction();

        try {
            $baseDevice = $this->createOrUpdateBaseDevice($request, $clientIp);
            $autoJsDevice = $this->createOrUpdateAutoJsDevice($baseDevice, $request);

            $this->entityManager->flush();
            $this->entityManager->commit();

            $certificate = $autoJsDevice->getCertificate();
            if (null === $certificate) {
                throw new \RuntimeException('Device certificate is required but not set');
            }

            return [
                'autoJsDevice' => $autoJsDevice,
                'certificate' => $certificate,
            ];
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }

    private function createOrUpdateBaseDevice(DeviceRegisterRequest $request, string $clientIp): BaseDevice
    {
        $baseDevice = $this->deviceRepository
            ->findOneBy(['code' => $request->getDeviceCode()])
        ;

        if (null === $baseDevice) {
            $baseDevice = new BaseDevice();
            $baseDevice->setCode($request->getDeviceCode());
            $baseDevice->setDeviceType(DeviceType::PHONE);
        }

        $this->updateBaseDeviceInfo($baseDevice, $request, $clientIp);
        $this->updateHardwareInfo($baseDevice, $request->getHardwareInfo());

        $this->entityManager->persist($baseDevice);

        return $baseDevice;
    }

    private function updateBaseDeviceInfo(BaseDevice $baseDevice, DeviceRegisterRequest $request, string $clientIp): void
    {
        $baseDevice->setName($request->getDeviceName());
        $baseDevice->setModel($request->getModel() ?? 'Unknown');
        $baseDevice->setBrand($request->getBrand());
        $baseDevice->setOsVersion($request->getOsVersion());
        $baseDevice->setFingerprint($request->getFingerprint());
        $baseDevice->setStatus(DeviceStatus::ONLINE);
        $baseDevice->setLastOnlineTime(new \DateTimeImmutable());
        $baseDevice->setLastIp($clientIp);
    }

    /**
     * @param array<string, mixed> $hardwareInfo
     */
    private function updateHardwareInfo(BaseDevice $baseDevice, array $hardwareInfo): void
    {
        if (isset($hardwareInfo['cpuCores'])) {
            $baseDevice->setCpuCores((int) $hardwareInfo['cpuCores']);
        }
        if (isset($hardwareInfo['memorySize'])) {
            $baseDevice->setMemorySize((string) $hardwareInfo['memorySize']);
        }
        if (isset($hardwareInfo['storageSize'])) {
            $baseDevice->setStorageSize((string) $hardwareInfo['storageSize']);
        }
    }

    private function createOrUpdateAutoJsDevice(BaseDevice $baseDevice, DeviceRegisterRequest $request): AutoJsDevice
    {
        $autoJsDevice = $this->autoJsDeviceRepository
            ->findOneBy(['baseDevice' => $baseDevice])
        ;

        if (null === $autoJsDevice) {
            $autoJsDevice = new AutoJsDevice();
            $autoJsDevice->setBaseDevice($baseDevice);
        }

        $certificate = $this->authService->generateDeviceCertificate(
            $request->getDeviceCode(),
            $request->getCertificateRequest()
        );

        $autoJsDevice->setCertificate($certificate);
        $autoJsDevice->setAutoJsVersion($request->getAutoJsVersion());

        $this->entityManager->persist($autoJsDevice);

        return $autoJsDevice;
    }
}
