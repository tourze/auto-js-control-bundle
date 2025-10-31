<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\ValueObject;

/**
 * Redis队列键定义.
 */
final class RedisQueueKeys
{
    /**
     * 设备指令队列前缀
     * 格式：device_instruction_queue:{deviceCode}.
     */
    public const DEVICE_INSTRUCTION_QUEUE = 'device_instruction_queue:%s';

    /**
     * 设备长轮询通知键前缀（用于Redis Pub/Sub）
     * 格式：device_poll_notify:{deviceCode}.
     */
    public const DEVICE_POLL_NOTIFY = 'device_poll_notify:%s';

    /**
     * 设备在线状态键前缀
     * 格式：device_online:{deviceCode}.
     */
    public const DEVICE_ONLINE = 'device_online:%s';

    /**
     * 指令执行状态键前缀
     * 格式：instruction_status:{instructionId}.
     */
    public const INSTRUCTION_STATUS = 'instruction_status:%s';

    /**
     * 设备最后心跳时间键前缀
     * 格式：device_last_heartbeat:{deviceCode}.
     */
    public const DEVICE_LAST_HEARTBEAT = 'device_last_heartbeat:%s';

    /**
     * 全局待执行任务队列.
     */
    public const GLOBAL_TASK_QUEUE = 'global_task_queue';

    /**
     * 设备组任务队列前缀
     * 格式：group_task_queue:{groupId}.
     */
    public const GROUP_TASK_QUEUE = 'group_task_queue:%s';

    /**
     * 设备锁键前缀（防止并发操作）
     * 格式：device_lock:{deviceCode}.
     */
    public const DEVICE_LOCK = 'device_lock:%s';

    /**
     * 指令重试计数器前缀
     * 格式：instruction_retry:{instructionId}.
     */
    public const INSTRUCTION_RETRY = 'instruction_retry:%s';

    /**
     * 设备性能指标键前缀
     * 格式：device_metrics:{deviceCode}.
     */
    public const DEVICE_METRICS = 'device_metrics:%s';

    /**
     * 默认TTL设置（秒）.
     */
    public const TTL_ONLINE_STATUS = 120; // 在线状态2分钟过期
    public const TTL_INSTRUCTION_STATUS = 3600; // 指令状态1小时过期
    public const TTL_HEARTBEAT = 300; // 心跳记录5分钟过期
    public const TTL_LOCK = 30; // 锁30秒过期
    public const TTL_RETRY_COUNTER = 1800; // 重试计数器30分钟过期
    public const TTL_METRICS = 86400; // 性能指标1天过期

    /**
     * 获取设备指令队列键.
     */
    public static function getDeviceInstructionQueue(string $deviceCode): string
    {
        return sprintf(self::DEVICE_INSTRUCTION_QUEUE, $deviceCode);
    }

    /**
     * 获取设备轮询通知键.
     */
    public static function getDevicePollNotify(string $deviceCode): string
    {
        return sprintf(self::DEVICE_POLL_NOTIFY, $deviceCode);
    }

    /**
     * 获取设备在线状态键.
     */
    public static function getDeviceOnline(string $deviceCode): string
    {
        return sprintf(self::DEVICE_ONLINE, $deviceCode);
    }

    /**
     * 获取指令执行状态键.
     */
    public static function getInstructionStatus(string $instructionId): string
    {
        return sprintf(self::INSTRUCTION_STATUS, $instructionId);
    }

    /**
     * 获取设备最后心跳时间键.
     */
    public static function getDeviceLastHeartbeat(string $deviceCode): string
    {
        return sprintf(self::DEVICE_LAST_HEARTBEAT, $deviceCode);
    }

    /**
     * 获取设备心跳时间键（别名）.
     */
    public static function getDeviceHeartbeat(string $deviceCode): string
    {
        return self::getDeviceLastHeartbeat($deviceCode);
    }

    /**
     * 获取设备组任务队列键.
     */
    public static function getGroupTaskQueue(int $groupId): string
    {
        return sprintf(self::GROUP_TASK_QUEUE, $groupId);
    }

    /**
     * 获取设备锁键.
     */
    public static function getDeviceLock(string $deviceCode): string
    {
        return sprintf(self::DEVICE_LOCK, $deviceCode);
    }

    /**
     * 获取指令重试计数器键.
     */
    public static function getInstructionRetry(string $instructionId): string
    {
        return sprintf(self::INSTRUCTION_RETRY, $instructionId);
    }

    /**
     * 获取设备性能指标键.
     */
    public static function getDeviceMetrics(string $deviceCode): string
    {
        return sprintf(self::DEVICE_METRICS, $deviceCode);
    }
}
