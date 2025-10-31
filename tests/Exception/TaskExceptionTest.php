<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\AutoJsControlBundle\Exception\TaskException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(TaskException::class)]
final class TaskExceptionTest extends AbstractExceptionTestCase
{
    public function testExtendsRuntimeException(): void
    {
        $exception = new TaskException();

        $this->assertInstanceOf(\RuntimeException::class, $exception);
        $this->assertInstanceOf(TaskException::class, $exception);
    }

    public function testConstructorWithCustomValues(): void
    {
        $message = 'Task execution failed';
        $code = 500;
        $previous = new \Exception('Previous exception');

        $exception = new TaskException($message, $code, $previous);

        $this->assertSame($message, $exception->getMessage());
        $this->assertSame($code, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testConstructorWithDefaults(): void
    {
        $exception = new TaskException();

        $this->assertSame('', $exception->getMessage());
        $this->assertSame(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }
}
