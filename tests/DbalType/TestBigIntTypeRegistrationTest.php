<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\DbalType;

use Doctrine\DBAL\Types\Type;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use Tourze\AutoJsControlBundle\DbalType\TestBigIntType;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * 验证 TestBigIntType 是否正确注册到 Doctrine DBAL
 *
 * @internal
 */
#[CoversClass(TestBigIntType::class)]
#[RunTestsInSeparateProcesses]
final class TestBigIntTypeRegistrationTest extends AbstractIntegrationTestCase
{
    protected function onSetUp(): void
    {
        // No specific setup needed for this test
    }

    #[Test]
    public function testBigIntTypeIsRegisteredInTestEnvironment(): void
    {
        // Act - 检查 bigint 类型是否已注册
        $hasType = Type::hasType('bigint');

        // Assert
        $this->assertTrue($hasType, 'bigint type should be registered');

        // Act - 获取 bigint 类型实例
        $type = Type::getType('bigint');

        // Assert - 验证类型是 TestBigIntType 的实例
        $this->assertInstanceOf(
            TestBigIntType::class,
            $type,
            'bigint type should be TestBigIntType in test environment'
        );
    }

    #[Test]
    public function testConvertToPHPValueNullHandling(): void
    {
        // Arrange
        $type = Type::getType('bigint');
        $platform = self::getEntityManager()->getConnection()->getDatabasePlatform();

        // Act
        $result = $type->convertToPHPValue(null, $platform);

        // Assert
        $this->assertNull($result);
    }

    #[Test]
    public function testConvertToPHPValueConvertsIntegerToString(): void
    {
        // Arrange
        $type = Type::getType('bigint');
        $platform = self::getEntityManager()->getConnection()->getDatabasePlatform();

        // Act
        $result = $type->convertToPHPValue(123456789, $platform);

        // Assert
        $this->assertIsString($result);
        $this->assertEquals('123456789', $result);
    }

    #[Test]
    public function testConvertToPHPValueKeepsStringAsString(): void
    {
        // Arrange
        $type = Type::getType('bigint');
        $platform = self::getEntityManager()->getConnection()->getDatabasePlatform();

        // Act
        $result = $type->convertToPHPValue('987654321', $platform);

        // Assert
        $this->assertIsString($result);
        $this->assertEquals('987654321', $result);
    }
}
