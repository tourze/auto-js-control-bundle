<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Enum;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tourze\AutoJsControlBundle\Enum\ScriptStatus;
use Tourze\PHPUnitEnum\AbstractEnumTestCase;

/**
 * @internal
 */
#[CoversClass(ScriptStatus::class)]
final class ScriptStatusTest extends AbstractEnumTestCase
{
    #[Test]
    public function casesReturnsAllStatuses(): void
    {
        // Act
        $cases = ScriptStatus::cases();

        // Assert
        $this->assertCount(5, $cases);
        $this->assertContains(ScriptStatus::DRAFT, $cases);
        $this->assertContains(ScriptStatus::TESTING, $cases);
        $this->assertContains(ScriptStatus::ACTIVE, $cases);
        $this->assertContains(ScriptStatus::INACTIVE, $cases);
        $this->assertContains(ScriptStatus::DEPRECATED, $cases);
    }

    #[Test]
    public function valuesAreCorrect(): void
    {
        // Assert
        $this->assertEquals('draft', ScriptStatus::DRAFT->value);
        $this->assertEquals('testing', ScriptStatus::TESTING->value);
        $this->assertEquals('active', ScriptStatus::ACTIVE->value);
        $this->assertEquals('inactive', ScriptStatus::INACTIVE->value);
        $this->assertEquals('deprecated', ScriptStatus::DEPRECATED->value);
    }

    #[Test]
    public function fromCreatesCorrectEnum(): void
    {
        // Assert
        $this->assertEquals(ScriptStatus::DRAFT, ScriptStatus::from('draft'));
        $this->assertEquals(ScriptStatus::TESTING, ScriptStatus::from('testing'));
        $this->assertEquals(ScriptStatus::ACTIVE, ScriptStatus::from('active'));
        $this->assertEquals(ScriptStatus::INACTIVE, ScriptStatus::from('inactive'));
        $this->assertEquals(ScriptStatus::DEPRECATED, ScriptStatus::from('deprecated'));
    }

    #[Test]
    public function tryFromReturnsCorrectEnum(): void
    {
        // Valid values
        $this->assertEquals(ScriptStatus::DRAFT, ScriptStatus::tryFrom('draft'));
        $this->assertEquals(ScriptStatus::TESTING, ScriptStatus::tryFrom('testing'));
        $this->assertEquals(ScriptStatus::ACTIVE, ScriptStatus::tryFrom('active'));
        $this->assertEquals(ScriptStatus::INACTIVE, ScriptStatus::tryFrom('inactive'));
        $this->assertEquals(ScriptStatus::DEPRECATED, ScriptStatus::tryFrom('deprecated'));

        // Invalid value
        $this->assertNull(ScriptStatus::tryFrom('invalid'));
    }

    #[Test]
    public function getLabelReturnsCorrectLabel(): void
    {
        // Assert
        $this->assertEquals('草稿', ScriptStatus::DRAFT->getLabel());
        $this->assertEquals('测试中', ScriptStatus::TESTING->getLabel());
        $this->assertEquals('激活', ScriptStatus::ACTIVE->getLabel());
        $this->assertEquals('未激活', ScriptStatus::INACTIVE->getLabel());
        $this->assertEquals('已废弃', ScriptStatus::DEPRECATED->getLabel());
    }

    #[Test]
    public function getColorReturnsCorrectColor(): void
    {
        // Assert
        $this->assertEquals('gray', ScriptStatus::DRAFT->getColor());
        $this->assertEquals('yellow', ScriptStatus::TESTING->getColor());
        $this->assertEquals('green', ScriptStatus::ACTIVE->getColor());
        $this->assertEquals('orange', ScriptStatus::INACTIVE->getColor());
        $this->assertEquals('red', ScriptStatus::DEPRECATED->getColor());
    }

    #[Test]
    public function isExecutableReturnsCorrectValue(): void
    {
        // Executable statuses
        $this->assertTrue(ScriptStatus::TESTING->isExecutable());
        $this->assertTrue(ScriptStatus::ACTIVE->isExecutable());

        // Non-executable statuses
        $this->assertFalse(ScriptStatus::DRAFT->isExecutable());
        $this->assertFalse(ScriptStatus::INACTIVE->isExecutable());
        $this->assertFalse(ScriptStatus::DEPRECATED->isExecutable());
    }

    #[Test]
    public function genOptionsReturnsCorrectArray(): void
    {
        // Act
        $options = ScriptStatus::genOptions();

        // Assert
        $this->assertCount(5, $options);

        // Check first option
        $this->assertEquals([
            'label' => '草稿',
            'text' => '草稿',
            'value' => 'draft',
            'name' => '草稿',
        ], $options[0]);

        // Check all values are present
        $values = array_column($options, 'value');
        $expectedValues = ['draft', 'testing', 'active', 'inactive', 'deprecated'];
        $this->assertEquals($expectedValues, $values);
    }

    #[Test]
    public function testToArray(): void
    {
        // Test DRAFT enum
        $draftArray = ScriptStatus::DRAFT->toArray();
        $this->assertEquals([
            'value' => 'draft',
            'label' => '草稿',
        ], $draftArray);

        // Test ACTIVE enum
        $activeArray = ScriptStatus::ACTIVE->toArray();
        $this->assertEquals([
            'value' => 'active',
            'label' => '激活',
        ], $activeArray);
    }

    #[Test]
    public function getDescriptionReturnsCorrectDescription(): void
    {
        // Assert
        $this->assertEquals('脚本正在编写中，尚未完成', ScriptStatus::DRAFT->getDescription());
        $this->assertEquals('脚本正在测试阶段', ScriptStatus::TESTING->getDescription());
        $this->assertEquals('脚本已激活，可以正常使用', ScriptStatus::ACTIVE->getDescription());
        $this->assertEquals('脚本暂时停用', ScriptStatus::INACTIVE->getDescription());
        $this->assertEquals('脚本已废弃，不建议使用', ScriptStatus::DEPRECATED->getDescription());
    }
}
