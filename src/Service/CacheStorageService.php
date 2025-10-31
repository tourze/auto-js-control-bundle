<?php

namespace Tourze\AutoJsControlBundle\Service;

use Tourze\AutoJsControlBundle\ValueObject\RedisQueueKeys;

/**
 * 缓存存储服务
 *
 * 封装设备状态、指令状态等存储操作
 */
readonly class CacheStorageService
{
    public function __construct(
        private StorageAdapterInterface $storage,
    ) {
    }

    /**
     * 设置设备在线状态
     */
    public function setDeviceOnline(string $deviceCode, bool $online): void
    {
        $key = RedisQueueKeys::getDeviceOnline($deviceCode);
        if ($online) {
            $this->storage->set($key, time(), RedisQueueKeys::TTL_ONLINE_STATUS);
        } else {
            $this->storage->delete($key);
        }
    }

    /**
     * 获取设备在线状态
     */
    public function getDeviceOnline(string $deviceCode): ?int
    {
        $key = RedisQueueKeys::getDeviceOnline($deviceCode);
        $value = $this->storage->get($key);

        return false !== $value && null !== $value ? (int) $value : null;
    }

    /**
     * 更新指令状态
     *
     * @param array<string, mixed> $status
     */
    public function updateInstructionStatus(string $instructionId, array $status): void
    {
        $key = RedisQueueKeys::getInstructionStatus($instructionId);
        $this->storage->hMSet($key, $status);
        $this->storage->expire($key, RedisQueueKeys::TTL_INSTRUCTION_STATUS);
    }

    /**
     * 获取指令状态
     *
     * @return array<string, mixed>|null
     */
    public function getInstructionStatus(string $instructionId): ?array
    {
        $key = RedisQueueKeys::getInstructionStatus($instructionId);
        $status = $this->storage->hGetAll($key);

        return [] !== $status ? $status : null;
    }

    /**
     * 更新设备性能指标.
     *
     * @param array<string, mixed> $metrics
     */
    public function updateDeviceMetrics(string $deviceCode, array $metrics): void
    {
        $key = RedisQueueKeys::getDeviceMetrics($deviceCode);
        $this->storage->hMSet($key, $metrics);
        $this->storage->expire($key, RedisQueueKeys::TTL_METRICS);
    }

    /**
     * 获取设备性能指标.
     *
     * @return array<string, mixed>
     */
    public function getDeviceMetrics(string $deviceCode): array
    {
        $key = RedisQueueKeys::getDeviceMetrics($deviceCode);

        return $this->storage->hGetAll($key);
    }

    /**
     * 设置设备心跳时间.
     */
    public function setDeviceHeartbeat(string $deviceCode, int $timestamp): void
    {
        $key = RedisQueueKeys::getDeviceHeartbeat($deviceCode);
        $this->storage->set($key, $timestamp, RedisQueueKeys::TTL_HEARTBEAT);
    }

    /**
     * 获取设备心跳时间.
     */
    public function getDeviceHeartbeat(string $deviceCode): ?int
    {
        $key = RedisQueueKeys::getDeviceHeartbeat($deviceCode);
        $value = $this->storage->get($key);

        return false !== $value && null !== $value ? (int) $value : null;
    }

    /**
     * 删除设备相关数据.
     */
    public function clearDeviceData(string $deviceCode): void
    {
        $keys = [
            RedisQueueKeys::getDeviceOnline($deviceCode),
            RedisQueueKeys::getDeviceHeartbeat($deviceCode),
            RedisQueueKeys::getDeviceLastHeartbeat($deviceCode),
            RedisQueueKeys::getDeviceMetrics($deviceCode),
            RedisQueueKeys::getDeviceInstructionQueue($deviceCode),
            RedisQueueKeys::getDevicePollNotify($deviceCode),
        ];

        foreach ($keys as $key) {
            $this->storage->delete($key);
        }
    }
}
