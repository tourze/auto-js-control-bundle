<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Exception;

/**
 * 设备认证异常类.
 *
 * 用于设备认证、签名验证和证书管理相关的异常
 */
class DeviceAuthException extends \RuntimeException
{
    /**
     * 创建证书生成失败异常.
     */
    public static function certificateGenerationFailed(string $deviceCode, string $reason): self
    {
        return new self("设备证书生成失败 [{$deviceCode}]: {$reason}");
    }

    /**
     * 创建签名验证失败异常.
     */
    public static function signatureVerificationFailed(string $deviceCode): self
    {
        return new self("设备签名验证失败: {$deviceCode}");
    }

    /**
     * 创建证书无效异常.
     */
    public static function invalidCertificate(string $deviceCode): self
    {
        return new self("设备证书无效: {$deviceCode}");
    }

    /**
     * 创建时间戳过期异常.
     */
    public static function timestampExpired(): self
    {
        return new self('请求时间戳已过期');
    }
}
