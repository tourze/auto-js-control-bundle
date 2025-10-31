<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;
use Tourze\AutoJsControlBundle\Entity\AutoJsDevice;

/**
 * 设备注册事件.
 *
 * 当新设备首次注册到系统时触发此事件
 */
class DeviceRegisteredEvent extends Event
{
    private readonly \DateTimeImmutable $registeredTime;

    /**
     * @param array<string, mixed> $deviceInfo
     */
    public function __construct(
        private readonly AutoJsDevice $device,
        private readonly string $ipAddress,
        private readonly array $deviceInfo = [],
    ) {
        $this->registeredTime = new \DateTimeImmutable();
    }

    /**
     * 获取注册的设备实体.
     */
    public function getDevice(): AutoJsDevice
    {
        return $this->device;
    }

    /**
     * 获取设备IP地址
     */
    public function getIpAddress(): string
    {
        return $this->ipAddress;
    }

    /**
     * 获取客户端IP地址（getIpAddress的别名）.
     */
    public function getClientIp(): string
    {
        return $this->ipAddress;
    }

    /**
     * 获取设备额外信息.
     *
     * @return array<string, mixed>
     */
    public function getDeviceInfo(): array
    {
        return $this->deviceInfo;
    }

    /**
     * 获取设备注册时间.
     */
    public function getRegisteredTime(): \DateTimeImmutable
    {
        return $this->registeredTime;
    }

    /**
     * 获取设备注册时间（废弃方法）.
     *
     * @deprecated 使用 getRegisteredTime() 代替
     */
    public function getRegisteredAt(): \DateTimeImmutable
    {
        return $this->registeredTime;
    }
}
