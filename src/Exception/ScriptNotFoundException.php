<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Exception;

/**
 * 脚本不存在异常类.
 *
 * 用于处理脚本ID或脚本代码不存在的情况
 */
class ScriptNotFoundException extends \RuntimeException
{
    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * 创建脚本ID不存在异常.
     */
    public static function byId(int $scriptId): self
    {
        return new self(sprintf('脚本 #%d 不存在', $scriptId));
    }

    /**
     * 创建脚本代码不存在异常.
     */
    public static function byCode(string $scriptCode): self
    {
        return new self(sprintf('脚本代码 "%s" 不存在', $scriptCode));
    }

    /**
     * 创建脚本ID不存在异常（byId 的别名）.
     */
    public static function createForId(int $scriptId): self
    {
        return new self(sprintf('脚本 #%d 不存在', $scriptId), 404);
    }

    /**
     * 创建脚本名称不存在异常.
     */
    public static function createForName(string $scriptName): self
    {
        return new self(sprintf('脚本 "%s" 不存在', $scriptName), 404);
    }
}
