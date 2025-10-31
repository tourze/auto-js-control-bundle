<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Exception;

/**
 * 设备目标相关异常类.
 *
 * 用于处理设备选择、设备组不存在等相关错误
 */
class DeviceTargetException extends \InvalidArgumentException
{
    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * 创建目标设备列表缺失异常.
     */
    public static function targetDevicesRequired(): self
    {
        return new self('必须指定目标设备列表');
    }

    /**
     * 创建目标设备组缺失异常.
     */
    public static function targetGroupRequired(): self
    {
        return new self('必须指定目标设备组');
    }

    /**
     * 创建设备组不存在异常.
     */
    public static function groupNotFound(): self
    {
        return new self('设备组不存在');
    }

    /**
     * 创建必须指定目标设备异常.
     */
    public static function targetDeviceRequired(): self
    {
        return new self('必须指定目标设备：--device-ids, --group-id 或 --all-devices');
    }

    /**
     * 创建目标设备选项互斥异常.
     */
    public static function targetDeviceOptionsExclusive(): self
    {
        return new self('目标设备选项只能选择一个');
    }
}
