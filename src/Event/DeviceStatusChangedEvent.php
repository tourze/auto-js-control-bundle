<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Event;

use DeviceBundle\Enum\DeviceStatus;
use Symfony\Contracts\EventDispatcher\Event;
use Tourze\AutoJsControlBundle\Entity\AutoJsDevice;

/**
 * 设备状态变更事件.
 *
 * 当设备的在线状态发生变化时触发此事件
 */
class DeviceStatusChangedEvent extends Event
{
    public function __construct(
        private readonly AutoJsDevice $device,
        private readonly DeviceStatus|bool $previousStatus,
        private readonly DeviceStatus|bool $currentStatus,
        private readonly ?\DateTimeInterface $statusChangedTime = null,
    ) {
    }

    /**
     * 获取状态变更的设备.
     */
    public function getDevice(): AutoJsDevice
    {
        return $this->device;
    }

    /**
     * 获取之前的状态
     */
    public function getPreviousStatus(): DeviceStatus|bool
    {
        return $this->previousStatus;
    }

    /**
     * 获取当前状态
     */
    public function getCurrentStatus(): DeviceStatus|bool
    {
        return $this->currentStatus;
    }

    /**
     * 获取旧状态（getPreviousStatus 的别名）.
     */
    public function getOldStatus(): DeviceStatus|bool
    {
        return $this->previousStatus;
    }

    /**
     * 获取新状态（getCurrentStatus 的别名）.
     */
    public function getNewStatus(): DeviceStatus|bool
    {
        return $this->currentStatus;
    }

    /**
     * 获取状态变更时间.
     */
    public function getStatusChangedTime(): ?\DateTimeInterface
    {
        return $this->statusChangedTime;
    }

    /**
     * 获取状态变更时间（废弃方法）.
     *
     * @deprecated 使用 getStatusChangedTime() 代替
     */
    public function getStatusChangedAt(): ?\DateTimeInterface
    {
        return $this->statusChangedTime;
    }

    /**
     * 判断是否发生了在线状态变化.
     */
    public function isOnlineChange(): bool
    {
        $previousOnline = $this->isStatusOnline($this->previousStatus);
        $currentOnline = $this->isStatusOnline($this->currentStatus);

        return $previousOnline !== $currentOnline;
    }

    /**
     * 判断是否变为在线状态
     */
    public function isBecameOnline(): bool
    {
        $previousOnline = $this->isStatusOnline($this->previousStatus);
        $currentOnline = $this->isStatusOnline($this->currentStatus);

        return !$previousOnline && $currentOnline;
    }

    /**
     * 判断是否变为离线状态
     */
    public function isBecameOffline(): bool
    {
        $previousOnline = $this->isStatusOnline($this->previousStatus);
        $currentOnline = $this->isStatusOnline($this->currentStatus);

        return $previousOnline && !$currentOnline;
    }

    /**
     * 判断是否从离线变为在线
     */
    public function isOnline(): bool
    {
        return $this->isBecameOnline();
    }

    /**
     * 判断是否从在线变为离线
     */
    public function isOffline(): bool
    {
        return $this->isBecameOffline();
    }

    /**
     * 获取状态变更描述.
     */
    public function getStatusChangeDescription(): string
    {
        $deviceId = $this->device->getId() ?? 'N/A';
        $oldStatus = $this->getStatusValue($this->previousStatus);
        $newStatus = $this->getStatusValue($this->currentStatus);

        return sprintf('设备 #%s 状态从 %s 变更为 %s', $deviceId, strtoupper($oldStatus), strtoupper($newStatus));
    }

    /**
     * 转为数组表示.
     *
     * @return array{deviceId: int|null, oldStatus: string, newStatus: string, timestamp: string}
     */
    public function toArray(): array
    {
        return [
            'deviceId' => $this->device->getId(),
            'oldStatus' => $this->getStatusValue($this->previousStatus),
            'newStatus' => $this->getStatusValue($this->currentStatus),
            'timestamp' => $this->statusChangedTime?->format('Y-m-d H:i:s') ?? new \DateTime()->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * 判断状态是否为在线
     */
    private function isStatusOnline(DeviceStatus|bool $status): bool
    {
        if (is_bool($status)) {
            return $status;
        }

        return DeviceStatus::ONLINE === $status;
    }

    /**
     * 获取状态值
     */
    private function getStatusValue(DeviceStatus|bool $status): string
    {
        if (is_bool($status)) {
            return $status ? 'online' : 'offline';
        }

        return $status->value;
    }

    /**
     * 创建设备上线事件的静态工厂方法.
     */
    public static function online(AutoJsDevice $device, ?\DateTimeInterface $statusChangedTime = null): self
    {
        return new self($device, false, true, $statusChangedTime);
    }

    /**
     * 创建设备下线事件的静态工厂方法.
     */
    public static function offline(AutoJsDevice $device, ?\DateTimeInterface $statusChangedTime = null): self
    {
        return new self($device, true, false, $statusChangedTime);
    }
}
