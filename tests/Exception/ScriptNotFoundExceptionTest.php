<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tourze\AutoJsControlBundle\Exception\BusinessLogicException;
use Tourze\AutoJsControlBundle\Exception\ScriptNotFoundException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(ScriptNotFoundException::class)]
final class ScriptNotFoundExceptionTest extends AbstractExceptionTestCase
{
    #[Test]
    public function createForIdCreatesExceptionWithCorrectMessage(): void
    {
        // Act
        $exception = ScriptNotFoundException::createForId(123);

        // Assert
        $this->assertEquals('脚本 #123 不存在', $exception->getMessage());
        $this->assertEquals(404, $exception->getCode());
    }

    #[Test]
    public function createForNameCreatesExceptionWithCorrectMessage(): void
    {
        // Act
        $exception = ScriptNotFoundException::createForName('Auto Click Script');

        // Assert
        $this->assertEquals('脚本 "Auto Click Script" 不存在', $exception->getMessage());
        $this->assertEquals(404, $exception->getCode());
    }

    #[Test]
    public function exceptionIsThrowable(): void
    {
        // Assert
        $this->expectException(ScriptNotFoundException::class);
        $this->expectExceptionMessage('脚本 #456 不存在');
        $this->expectExceptionCode(404);

        // Act
        throw ScriptNotFoundException::createForId(456);
    }

    #[Test]
    public function exceptionExtendsBusinessLogicException(): void
    {
        // Arrange
        $exception = new ScriptNotFoundException('Test');

        // Assert
        $this->assertInstanceOf(BusinessLogicException::class, $exception);
    }
}
