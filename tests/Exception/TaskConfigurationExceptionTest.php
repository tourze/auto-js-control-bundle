<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\AutoJsControlBundle\Exception\TaskConfigurationException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(TaskConfigurationException::class)]
final class TaskConfigurationExceptionTest extends AbstractExceptionTestCase
{
    public function testExtendsInvalidArgumentException(): void
    {
        $exception = new TaskConfigurationException();

        $this->assertInstanceOf(\InvalidArgumentException::class, $exception);
        $this->assertInstanceOf(TaskConfigurationException::class, $exception);
    }

    public function testScriptParameterRequired(): void
    {
        $exception = TaskConfigurationException::scriptParameterRequired();

        $this->assertInstanceOf(TaskConfigurationException::class, $exception);
        $this->assertSame('必须指定 --script-id 或 --script-code', $exception->getMessage());
        $this->assertSame(0, $exception->getCode());
    }

    public function testScriptParametersExclusive(): void
    {
        $exception = TaskConfigurationException::scriptParametersExclusive();

        $this->assertInstanceOf(TaskConfigurationException::class, $exception);
        $this->assertSame('--script-id 和 --script-code 只能选择一个', $exception->getMessage());
        $this->assertSame(0, $exception->getCode());
    }

    public function testConstructorWithCustomValues(): void
    {
        $message = 'Custom configuration error';
        $code = 422;
        $previous = new \Exception('Previous exception');

        $exception = new TaskConfigurationException($message, $code, $previous);

        $this->assertSame($message, $exception->getMessage());
        $this->assertSame($code, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testConstructorWithDefaults(): void
    {
        $exception = new TaskConfigurationException();

        $this->assertSame('', $exception->getMessage());
        $this->assertSame(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }
}
