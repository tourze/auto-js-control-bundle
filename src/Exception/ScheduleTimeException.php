<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Exception;

/**
 * 计划时间异常类.
 *
 * 用于处理任务计划时间格式错误
 */
class ScheduleTimeException extends \InvalidArgumentException
{
    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * 创建无效时间格式异常.
     */
    public static function invalidFormat(): self
    {
        return new self('无效的计划时间格式，请使用ISO8601格式');
    }
}
