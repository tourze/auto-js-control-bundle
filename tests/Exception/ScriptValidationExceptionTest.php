<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\AutoJsControlBundle\Exception\ScriptValidationException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(ScriptValidationException::class)]
final class ScriptValidationExceptionTest extends AbstractExceptionTestCase
{
    public function testExtendsInvalidArgumentException(): void
    {
        $exception = new ScriptValidationException();

        $this->assertInstanceOf(\InvalidArgumentException::class, $exception);
        $this->assertInstanceOf(ScriptValidationException::class, $exception);
    }

    public function testScriptNotFound(): void
    {
        $scriptId = 'script-123';
        $exception = ScriptValidationException::scriptNotFound($scriptId);

        $this->assertInstanceOf(ScriptValidationException::class, $exception);
        $this->assertSame('脚本 "script-123" 不存在', $exception->getMessage());
        $this->assertSame(0, $exception->getCode());
    }

    public function testFileNotFound(): void
    {
        $file = '/path/to/script.js';
        $exception = ScriptValidationException::fileNotFound($file);

        $this->assertInstanceOf(ScriptValidationException::class, $exception);
        $this->assertSame('文件不存在: /path/to/script.js', $exception->getMessage());
        $this->assertSame(0, $exception->getCode());
    }

    public function testValidationFailed(): void
    {
        $reason = '语法错误';
        $exception = ScriptValidationException::validationFailed($reason);

        $this->assertInstanceOf(ScriptValidationException::class, $exception);
        $this->assertSame('脚本验证失败: 语法错误', $exception->getMessage());
        $this->assertSame(0, $exception->getCode());
    }

    public function testUnsupportedType(): void
    {
        $type = 'python';
        $exception = ScriptValidationException::unsupportedType($type);

        $this->assertInstanceOf(ScriptValidationException::class, $exception);
        $this->assertSame('不支持的脚本类型: python', $exception->getMessage());
        $this->assertSame(0, $exception->getCode());
    }

    public function testConstructorWithCustomValues(): void
    {
        $message = 'Custom validation error';
        $code = 400;
        $previous = new \Exception('Previous exception');

        $exception = new ScriptValidationException($message, $code, $previous);

        $this->assertSame($message, $exception->getMessage());
        $this->assertSame($code, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testConstructorWithDefaults(): void
    {
        $exception = new ScriptValidationException();

        $this->assertSame('', $exception->getMessage());
        $this->assertSame(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }
}
