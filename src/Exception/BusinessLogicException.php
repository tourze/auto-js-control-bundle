<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Exception;

/**
 * 业务逻辑异常类.
 *
 * 用于替代 RuntimeException，提供更明确的业务异常处理
 */
class BusinessLogicException extends \RuntimeException
{
    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * 创建设备相关异常.
     */
    public static function deviceNotFound(string $deviceCode): self
    {
        return new self("设备不存在: {$deviceCode}");
    }

    /**
     * 创建任务相关异常.
     */
    public static function taskNotFound(int $taskId): self
    {
        return new self("任务不存在: {$taskId}");
    }

    /**
     * 创建脚本相关异常.
     */
    public static function scriptNotFound(int $scriptId): self
    {
        return new self("脚本不存在: {$scriptId}");
    }

    /**
     * 创建验证相关异常.
     */
    public static function validationFailed(string $reason): self
    {
        return new self("验证失败: {$reason}");
    }

    /**
     * 创建配置相关异常.
     */
    public static function configurationError(string $message): self
    {
        return new self("配置错误: {$message}");
    }

    /**
     * 创建数据处理异常.
     */
    public static function dataProcessingError(string $message): self
    {
        return new self("数据处理错误: {$message}");
    }

    /**
     * 创建任务状态错误异常.
     */
    public static function taskStateError(string $message): self
    {
        return new self("任务状态错误: {$message}");
    }

    /**
     * 创建请求参数错误异常.
     */
    public static function invalidRequest(string $message): self
    {
        return new self("请求参数错误: {$message}");
    }

    /**
     * 创建业务规则违反异常.
     */
    public static function businessRuleViolation(string $message): self
    {
        return new self("业务规则违反: {$message}");
    }

    /**
     * 创建资源状态错误异常.
     */
    public static function resourceStateError(string $message): self
    {
        return new self("资源状态错误: {$message}");
    }

    /**
     * 创建认证授权异常.
     */
    public static function authenticationError(string $message): self
    {
        return new self("认证授权错误: {$message}");
    }
}
