<?php

namespace Tourze\AutoJsControlBundle\Service;

use DeviceBundle\Entity\Device as BaseDevice;
use DeviceBundle\Enum\DeviceStatus;
use DeviceBundle\Enum\DeviceType;
use DeviceBundle\Repository\DeviceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Tourze\AutoJsControlBundle\Entity\AutoJsDevice;
use Tourze\AutoJsControlBundle\Event\DeviceRegisteredEvent;
use Tourze\AutoJsControlBundle\Exception\BusinessLogicException;
use Tourze\AutoJsControlBundle\Repository\AutoJsDeviceRepository;

/**
 * 设备注册处理器.
 *
 * 专门负责设备的注册和更新逻辑
 */
readonly class DeviceRegistrationHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DeviceRepository $deviceRepository,
        private AutoJsDeviceRepository $autoJsDeviceRepository,
        private DeviceAuthService $authService,
        private EventDispatcherInterface $eventDispatcher,
        private LoggerInterface $logger,
        private CacheStorageService $cacheStorage,
    ) {
    }

    /**
     * 注册或更新设备.
     *
     * @param array<string, mixed> $deviceInfo
     */
    public function registerOrUpdate(
        string $deviceCode,
        string $deviceName,
        string $certificateRequest,
        array $deviceInfo,
        string $clientIp,
    ): AutoJsDevice {
        $this->entityManager->beginTransaction();

        try {
            $baseDevice = $this->findOrCreateBaseDevice($deviceCode, $deviceName, $deviceInfo, $clientIp);
            $autoJsDevice = $this->findOrCreateAutoJsDevice($baseDevice, $deviceInfo);
            $isNewDevice = null === $autoJsDevice->getId();

            $certificate = $this->authService->generateDeviceCertificate($deviceCode, $certificateRequest);
            $autoJsDevice->setCertificate($certificate);

            $this->entityManager->persist($baseDevice);
            $this->entityManager->persist($autoJsDevice);
            $this->entityManager->flush();
            $this->entityManager->commit();

            $this->cacheStorage->setDeviceOnline($deviceCode, true);

            if ($isNewDevice) {
                $event = new DeviceRegisteredEvent($autoJsDevice, $clientIp, $deviceInfo);
                $this->eventDispatcher->dispatch($event);
            }

            $this->logger->info('设备注册/更新成功', [
                'deviceCode' => $deviceCode,
                'deviceId' => $autoJsDevice->getId(),
                'isNew' => $isNewDevice,
            ]);

            return $autoJsDevice;
        } catch (\Exception $e) {
            $this->entityManager->rollback();

            $this->logger->error('设备注册/更新失败', [
                'deviceCode' => $deviceCode,
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);

            throw BusinessLogicException::configurationError('设备注册失败: ' . $e->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $deviceInfo
     */
    private function findOrCreateBaseDevice(
        string $deviceCode,
        string $deviceName,
        array $deviceInfo,
        string $clientIp,
    ): BaseDevice {
        $baseDevice = $this->deviceRepository->findOneBy(['code' => $deviceCode]);

        if (null === $baseDevice) {
            $baseDevice = new BaseDevice();
            $baseDevice->setCode($deviceCode);
            $baseDevice->setDeviceType(DeviceType::PHONE);
        }

        $baseDevice->setName($deviceName);
        $baseDevice->setStatus(DeviceStatus::ONLINE);
        $baseDevice->setLastOnlineTime(new \DateTimeImmutable());
        $baseDevice->setLastIp($clientIp);

        $this->updateDeviceHardwareInfo($baseDevice, $deviceInfo);

        return $baseDevice;
    }

    /**
     * @param array<string, mixed> $deviceInfo
     */
    private function updateDeviceHardwareInfo(BaseDevice $baseDevice, array $deviceInfo): void
    {
        $this->updateDeviceModel($baseDevice, $deviceInfo);
        $this->updateDeviceBrand($baseDevice, $deviceInfo);
        $this->updateDeviceOsVersion($baseDevice, $deviceInfo);
        $this->updateDeviceFingerprint($baseDevice, $deviceInfo);
        $this->updateDeviceHardwareSpecs($baseDevice, $deviceInfo);
    }

    /**
     * @param array<string, mixed> $deviceInfo
     */
    private function updateDeviceModel(BaseDevice $baseDevice, array $deviceInfo): void
    {
        if (!array_key_exists('model', $deviceInfo)) {
            return;
        }

        $model = $deviceInfo['model'];
        if (null === $model || is_string($model)) {
            $baseDevice->setModel($model);
        }
    }

    /**
     * @param array<string, mixed> $deviceInfo
     */
    private function updateDeviceBrand(BaseDevice $baseDevice, array $deviceInfo): void
    {
        if (!array_key_exists('brand', $deviceInfo)) {
            return;
        }

        $brand = $deviceInfo['brand'];
        if (null === $brand || is_string($brand)) {
            $baseDevice->setBrand($brand);
        }
    }

    /**
     * @param array<string, mixed> $deviceInfo
     */
    private function updateDeviceOsVersion(BaseDevice $baseDevice, array $deviceInfo): void
    {
        if (!array_key_exists('osVersion', $deviceInfo)) {
            return;
        }

        $osVersion = $deviceInfo['osVersion'];
        if (null === $osVersion || is_string($osVersion)) {
            $baseDevice->setOsVersion($osVersion);
        }
    }

    /**
     * @param array<string, mixed> $deviceInfo
     */
    private function updateDeviceFingerprint(BaseDevice $baseDevice, array $deviceInfo): void
    {
        if (!array_key_exists('fingerprint', $deviceInfo)) {
            return;
        }

        $fingerprint = $deviceInfo['fingerprint'];
        if (null === $fingerprint || is_string($fingerprint)) {
            $baseDevice->setFingerprint($fingerprint);
        }
    }

    /**
     * @param array<string, mixed> $deviceInfo
     */
    private function updateDeviceHardwareSpecs(BaseDevice $baseDevice, array $deviceInfo): void
    {
        if (!isset($deviceInfo['hardwareInfo']) || !is_array($deviceInfo['hardwareInfo'])) {
            return;
        }

        /** @var array<string, mixed> $hardwareInfo */
        $hardwareInfo = $deviceInfo['hardwareInfo'];
        $this->updateDeviceSpecs($baseDevice, $hardwareInfo);
    }

    /**
     * @param array<string, mixed> $hardwareInfo
     */
    private function updateDeviceSpecs(BaseDevice $baseDevice, array $hardwareInfo): void
    {
        if (isset($hardwareInfo['cpuCores']) && (is_int($hardwareInfo['cpuCores']) || is_numeric($hardwareInfo['cpuCores']))) {
            $baseDevice->setCpuCores((int) $hardwareInfo['cpuCores']);
        }
        if (isset($hardwareInfo['memorySize'])) {
            $memorySize = $hardwareInfo['memorySize'];
            if (is_string($memorySize) || is_numeric($memorySize)) {
                $baseDevice->setMemorySize((string) $memorySize);
            }
        }
        if (isset($hardwareInfo['storageSize'])) {
            $storageSize = $hardwareInfo['storageSize'];
            if (is_string($storageSize) || is_numeric($storageSize)) {
                $baseDevice->setStorageSize((string) $storageSize);
            }
        }
    }

    /**
     * @param array<string, mixed> $deviceInfo
     */
    private function findOrCreateAutoJsDevice(BaseDevice $baseDevice, array $deviceInfo): AutoJsDevice
    {
        $autoJsDevice = $this->autoJsDeviceRepository->findOneBy(['baseDevice' => $baseDevice]);

        if (null === $autoJsDevice) {
            $autoJsDevice = new AutoJsDevice();
            $autoJsDevice->setBaseDevice($baseDevice);
        }

        if (array_key_exists('autoJsVersion', $deviceInfo)) {
            $autoJsVersion = $deviceInfo['autoJsVersion'];
            if (null === $autoJsVersion || is_string($autoJsVersion)) {
                $autoJsDevice->setAutoJsVersion($autoJsVersion);
            }
        }

        return $autoJsDevice;
    }
}
