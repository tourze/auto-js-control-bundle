<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\AutoJsControlBundle\Exception\ScheduleTimeException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(ScheduleTimeException::class)]
final class ScheduleTimeExceptionTest extends AbstractExceptionTestCase
{
    public function testExtendsInvalidArgumentException(): void
    {
        $exception = new ScheduleTimeException();

        $this->assertInstanceOf(\InvalidArgumentException::class, $exception);
        $this->assertInstanceOf(ScheduleTimeException::class, $exception);
    }

    public function testInvalidFormat(): void
    {
        $exception = ScheduleTimeException::invalidFormat();

        $this->assertInstanceOf(ScheduleTimeException::class, $exception);
        $this->assertSame('无效的计划时间格式，请使用ISO8601格式', $exception->getMessage());
        $this->assertSame(0, $exception->getCode());
    }

    public function testConstructorWithCustomValues(): void
    {
        $message = 'Custom schedule time error';
        $code = 422;
        $previous = new \Exception('Previous exception');

        $exception = new ScheduleTimeException($message, $code, $previous);

        $this->assertSame($message, $exception->getMessage());
        $this->assertSame($code, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testConstructorWithDefaults(): void
    {
        $exception = new ScheduleTimeException();

        $this->assertSame('', $exception->getMessage());
        $this->assertSame(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }
}
