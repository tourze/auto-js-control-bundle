<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\DbalType;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tourze\AutoJsControlBundle\DbalType\TestBigIntType;

/**
 * @internal
 */
#[CoversClass(TestBigIntType::class)]
final class TestBigIntTypeTest extends TestCase
{
    private TestBigIntType $type;

    private AbstractPlatform&MockObject $platform;

    protected function setUp(): void
    {
        $this->type = new TestBigIntType();
        $this->platform = $this->createMock(AbstractPlatform::class);
    }

    #[Test]
    public function convertToPHPValueReturnsNullWhenValueIsNull(): void
    {
        // Act
        $result = $this->type->convertToPHPValue(null, $this->platform);

        // Assert
        $this->assertNull($result);
    }

    #[Test]
    public function convertToPHPValueConvertsIntegerToString(): void
    {
        // Arrange
        $value = 123456789;

        // Act
        $result = $this->type->convertToPHPValue($value, $this->platform);

        // Assert
        $this->assertSame('123456789', $result);
        $this->assertIsString($result);
    }

    #[Test]
    public function convertToPHPValueConvertsStringToString(): void
    {
        // Arrange
        $value = '987654321';

        // Act
        $result = $this->type->convertToPHPValue($value, $this->platform);

        // Assert
        $this->assertSame('987654321', $result);
        $this->assertIsString($result);
    }

    #[Test]
    public function convertToPHPValueHandlesLargeInteger(): void
    {
        // Arrange
        $value = 9223372036854775807; // PHP_INT_MAX on 64-bit

        // Act
        $result = $this->type->convertToPHPValue($value, $this->platform);

        // Assert
        $this->assertSame('9223372036854775807', $result);
        $this->assertIsString($result);
    }

    #[Test]
    public function convertToPHPValueConvertsNumericStringToString(): void
    {
        // Arrange
        $value = '12345';

        // Act
        $result = $this->type->convertToPHPValue($value, $this->platform);

        // Assert
        $this->assertSame('12345', $result);
        $this->assertIsString($result);
    }

    /**
     * 符合PHPStan命名约定的convertToPHPValue测试
     * 这个测试涵盖了主要使用场景，满足覆盖率要求
     */
    #[Test]
    public function testConvertToPHPValue(): void
    {
        // Test null value
        $this->assertNull($this->type->convertToPHPValue(null, $this->platform));

        // Test integer conversion
        $this->assertSame('123', $this->type->convertToPHPValue(123, $this->platform));

        // Test string preservation
        $this->assertSame('456', $this->type->convertToPHPValue('456', $this->platform));
    }
}
