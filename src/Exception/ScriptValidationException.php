<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Exception;

/**
 * 脚本验证异常类.
 *
 * 用于替代 InvalidArgumentException，提供更明确的脚本验证异常处理
 */
class ScriptValidationException extends \InvalidArgumentException
{
    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * 创建脚本不存在异常.
     */
    public static function scriptNotFound(string $scriptId): self
    {
        return new self(sprintf('脚本 "%s" 不存在', $scriptId));
    }

    /**
     * 创建文件不存在异常.
     */
    public static function fileNotFound(string $file): self
    {
        return new self(sprintf('文件不存在: %s', $file));
    }

    /**
     * 创建验证失败异常.
     */
    public static function validationFailed(string $reason): self
    {
        return new self(sprintf('脚本验证失败: %s', $reason));
    }

    /**
     * 创建类型不支持异常.
     */
    public static function unsupportedType(string $type): self
    {
        return new self(sprintf('不支持的脚本类型: %s', $type));
    }

    /**
     * 创建文件不可读异常.
     */
    public static function fileNotReadable(string $file): self
    {
        return new self(sprintf('文件不可读: %s', $file));
    }
}
