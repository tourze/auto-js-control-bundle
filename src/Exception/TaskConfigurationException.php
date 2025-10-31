<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Exception;

/**
 * 任务配置异常类.
 *
 * 用于处理任务参数配置错误，如脚本选择冲突等
 */
class TaskConfigurationException extends \InvalidArgumentException
{
    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * 创建缺少脚本参数异常.
     */
    public static function scriptParameterRequired(): self
    {
        return new self('必须指定 --script-id 或 --script-code');
    }

    /**
     * 创建脚本参数互斥异常.
     */
    public static function scriptParametersExclusive(): self
    {
        return new self('--script-id 和 --script-code 只能选择一个');
    }
}
