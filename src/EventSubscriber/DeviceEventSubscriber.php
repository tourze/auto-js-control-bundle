<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\EventSubscriber;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Tourze\AutoJsControlBundle\Contract\DeviceMonitorDataRepositoryInterface;
use Tourze\AutoJsControlBundle\Event\DeviceRegisteredEvent;
use Tourze\AutoJsControlBundle\Event\DeviceStatusChangedEvent;
use Tourze\AutoJsControlBundle\Service\DeviceManagerInterface;

/**
 * 设备相关事件订阅者.
 *
 * 处理设备注册、状态变更等事件
 */
readonly class DeviceEventSubscriber
{
    public function __construct(
        private DeviceManagerInterface $deviceManager,
        private DeviceMonitorDataRepositoryInterface $monitorDataRepository,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * 处理设备注册事件.
     */
    #[AsEventListener(event: DeviceRegisteredEvent::class, priority: 10)]
    public function onDeviceRegistered(DeviceRegisteredEvent $event): void
    {
        $device = $event->getDevice();
        $this->logger->info('New device registered', [
            'device_id' => $device->getDeviceId(),
            'name' => $device->getName(),
            'ip_address' => $event->getIpAddress(),
            'info' => $event->getDeviceInfo(),
        ]);

        // 初始化设备监控数据
        try {
            $this->monitorDataRepository->createInitialData($device);
        } catch (\Exception $e) {
            $this->logger->error('Failed to create monitor data for device', [
                'device_id' => $device->getDeviceId(),
                'error' => $e->getMessage(),
            ]);
        }

        // 发送欢迎指令或初始化配置
        try {
            $this->deviceManager->sendWelcomeInstruction($device);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send welcome instruction', [
                'device_id' => $device->getDeviceId(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 处理设备状态变更事件.
     */
    #[AsEventListener(event: DeviceStatusChangedEvent::class, priority: 5)]
    public function onDeviceStatusChanged(DeviceStatusChangedEvent $event): void
    {
        $device = $event->getDevice();

        if ($event->isOnline()) {
            $this->logger->info('Device came online', [
                'device_id' => $device->getDeviceId(),
                'name' => $device->getName(),
            ]);

            // 设备上线后，检查是否有待执行的任务
            $this->deviceManager->checkPendingTasks($device);
        } elseif ($event->isOffline()) {
            $this->logger->warning('Device went offline', [
                'device_id' => $device->getDeviceId(),
                'name' => $device->getName(),
                'last_heartbeat' => $device->getLastOnlineTime()?->format('Y-m-d H:i:s'),
            ]);

            // 设备离线后，取消该设备的运行中任务
            $this->deviceManager->cancelRunningTasks($device);
        }

        // 更新监控数据
        try {
            $statusChangedTime = $event->getStatusChangedTime();
            // 将 DateTimeInterface 转换为 DateTime
            $dateTime = null !== $statusChangedTime
                ? ($statusChangedTime instanceof \DateTime
                    ? $statusChangedTime
                    : \DateTime::createFromInterface($statusChangedTime))
                : new \DateTime();

            $this->monitorDataRepository->updateStatusChangedTime($device, $dateTime);
        } catch (\Exception $e) {
            $this->logger->error('Failed to update monitor data', [
                'device_id' => $device->getDeviceId(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
