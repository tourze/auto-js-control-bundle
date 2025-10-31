<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Service;

use Tourze\AutoJsControlBundle\Entity\AutoJsDevice;

/**
 * Device Manager Interface
 *
 * 用于测试时的设备管理器接口
 */
interface DeviceManagerInterface
{
    /**
     * 注册或更新设备.
     *
     * @param array<string, mixed> $deviceInfo
     */
    public function registerOrUpdateDevice(
        string $deviceCode,
        string $deviceName,
        string $certificateRequest,
        array $deviceInfo,
        string $clientIp,
    ): object;

    /**
     * 获取设备信息.
     */
    public function getDevice(string $deviceCode): object;

    /**
     * 获取设备信息（通过ID）.
     */
    public function getDeviceById(int $deviceId): object;

    /**
     * 删除设备（软删除）.
     */
    public function deleteDevice(string $deviceCode): void;

    /**
     * 更新设备状态
     */
    public function updateDeviceStatus(string $deviceCode, string $status): void;

    /**
     * 获取在线设备列表.
     *
     * @return array<string, mixed>
     */
    public function getOnlineDevices(int $page = 1, int $limit = 20): array;

    /**
     * 批量获取设备状态
     *
     * @param array<string> $deviceCodes
     *
     * @return array<string, mixed>
     */
    public function getDevicesStatus(array $deviceCodes): array;

    /**
     * 获取设备统计信息.
     *
     * @return array<string, mixed>
     */
    public function getDeviceStatistics(): array;

    /**
     * 检查设备的待执行任务
     */
    public function checkPendingTasks(object $device): void;

    /**
     * 取消设备的运行中任务
     */
    public function cancelRunningTasks(object $device): void;

    /**
     * 发送欢迎指令到设备.
     */
    public function sendWelcomeInstruction(object $device): void;

    /**
     * 搜索设备.
     *
     * @param array<string, mixed>         $criteria
     * @param array<string, string>        $orderBy
     * @return array<object>
     */
    public function searchDevices(
        array $criteria = [],
        array $orderBy = [],
        ?int $limit = null,
        ?int $offset = null,
    ): array;
}
