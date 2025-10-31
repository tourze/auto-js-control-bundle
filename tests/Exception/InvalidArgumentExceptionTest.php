<?php

namespace Tourze\AutoJsControlBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\AutoJsControlBundle\Exception\InvalidArgumentException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(InvalidArgumentException::class)]
final class InvalidArgumentExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionClass(): void
    {
        $exception = new InvalidArgumentException('Test message');

        $this->assertInstanceOf(\InvalidArgumentException::class, $exception);
        $this->assertInstanceOf(InvalidArgumentException::class, $exception);
        $this->assertSame('Test message', $exception->getMessage());
    }

    public function testExceptionWithCode(): void
    {
        $exception = new InvalidArgumentException('Test message', 123);

        $this->assertSame('Test message', $exception->getMessage());
        $this->assertSame(123, $exception->getCode());
    }

    public function testExceptionWithPrevious(): void
    {
        $previous = new \Exception('Previous exception');
        $exception = new InvalidArgumentException('Test message', 0, $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }
}
