<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Contract;

/**
 * Device Monitor Data Repository Interface
 *
 * 用于测试时的设备监控数据仓库接口
 */
interface DeviceMonitorDataRepositoryInterface
{
    /**
     * 创建初始监控数据
     */
    public function createInitialData(object $device): void;

    /**
     * 更新状态变更时间
     */
    public function updateStatusChangedTime(object $device, ?\DateTime $time = null): void;
}
