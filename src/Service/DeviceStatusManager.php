<?php

namespace Tourze\AutoJsControlBundle\Service;

use DeviceBundle\Enum\DeviceStatus;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Tourze\AutoJsControlBundle\Entity\AutoJsDevice;
use Tourze\AutoJsControlBundle\Event\DeviceStatusChangedEvent;
use Tourze\AutoJsControlBundle\Exception\BusinessLogicException;

/**
 * 设备状态管理器.
 *
 * 专门负责设备状态的更新和管理
 */
readonly class DeviceStatusManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DeviceHeartbeatService $heartbeatService,
        private EventDispatcherInterface $eventDispatcher,
        private LoggerInterface $logger,
        private CacheStorageService $cacheStorage,
    ) {
    }

    /**
     * 更新设备状态
     */
    public function updateStatus(AutoJsDevice $device, DeviceStatus $status): void
    {
        $baseDevice = $device->getBaseDevice();
        if (null === $baseDevice) {
            throw BusinessLogicException::configurationError('设备基础信息丢失');
        }

        $oldStatus = $baseDevice->getStatus();
        $baseDevice->setStatus($status);

        if (DeviceStatus::OFFLINE === $status) {
            $this->heartbeatService->markDeviceOffline($baseDevice->getCode());
        }

        $this->entityManager->flush();

        if ($oldStatus !== $status) {
            $this->dispatchStatusChangedEvent($device, $oldStatus, $status);
        }

        $this->logger->info('设备状态已更新', [
            'deviceCode' => $baseDevice->getCode(),
            'oldStatus' => $oldStatus->value,
            'newStatus' => $status->value,
        ]);
    }

    /**
     * 标记设备为已删除.
     */
    public function markAsDeleted(AutoJsDevice $device): void
    {
        $baseDevice = $device->getBaseDevice();
        if (null === $baseDevice) {
            throw BusinessLogicException::configurationError('设备基础信息丢失');
        }

        $baseDevice->setStatus(DeviceStatus::DISABLED);
        $this->cacheStorage->clearDeviceData($baseDevice->getCode());
        $this->entityManager->flush();
    }

    private function dispatchStatusChangedEvent(
        AutoJsDevice $device,
        DeviceStatus $oldStatus,
        DeviceStatus $newStatus,
    ): void {
        $previousOnline = DeviceStatus::ONLINE === $oldStatus;
        $currentOnline = DeviceStatus::ONLINE === $newStatus;

        if ($previousOnline !== $currentOnline) {
            $event = new DeviceStatusChangedEvent(
                $device,
                $previousOnline,
                $currentOnline,
                new \DateTime()
            );
            $this->eventDispatcher->dispatch($event);
        }
    }
}
