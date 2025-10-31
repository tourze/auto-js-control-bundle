<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\AutoJsControlBundle\Exception\InvalidTaskArgumentException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(InvalidTaskArgumentException::class)]
final class InvalidTaskArgumentExceptionTest extends AbstractExceptionTestCase
{
    public function testExtendsInvalidArgumentException(): void
    {
        $exception = new InvalidTaskArgumentException();

        $this->assertInstanceOf(\InvalidArgumentException::class, $exception);
        $this->assertInstanceOf(InvalidTaskArgumentException::class, $exception);
    }

    public function testConstructorWithCustomValues(): void
    {
        $message = 'Task argument is invalid';
        $code = 400;
        $previous = new \Exception('Previous exception');

        $exception = new InvalidTaskArgumentException($message, $code, $previous);

        $this->assertSame($message, $exception->getMessage());
        $this->assertSame($code, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testConstructorWithDefaults(): void
    {
        $exception = new InvalidTaskArgumentException();

        $this->assertSame('', $exception->getMessage());
        $this->assertSame(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }
}
