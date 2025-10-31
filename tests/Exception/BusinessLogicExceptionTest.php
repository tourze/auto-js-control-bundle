<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tourze\AutoJsControlBundle\Exception\BusinessLogicException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(BusinessLogicException::class)]
final class BusinessLogicExceptionTest extends AbstractExceptionTestCase
{
    #[Test]
    public function constructorSetsMessage(): void
    {
        // Arrange & Act
        $exception = new BusinessLogicException('业务逻辑错误');

        // Assert
        $this->assertEquals('业务逻辑错误', $exception->getMessage());
    }

    #[Test]
    public function constructorSetsCodeAndPrevious(): void
    {
        // Arrange
        $previous = new \Exception('Previous exception');

        // Act
        $exception = new BusinessLogicException('Error message', 500, $previous);

        // Assert
        $this->assertEquals('Error message', $exception->getMessage());
        $this->assertEquals(500, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }

    #[Test]
    public function exceptionIsThrowable(): void
    {
        // Assert
        $this->expectException(BusinessLogicException::class);
        $this->expectExceptionMessage('Test exception');

        // Act
        throw new BusinessLogicException('Test exception');
    }

    #[Test]
    public function exceptionExtendsRuntimeException(): void
    {
        // Arrange
        $exception = new BusinessLogicException('Test');

        // Assert
        $this->assertInstanceOf(\RuntimeException::class, $exception);
    }
}
