<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Enum;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tourze\AutoJsControlBundle\Enum\TaskTargetType;
use Tourze\PHPUnitEnum\AbstractEnumTestCase;

/**
 * @internal
 */
#[CoversClass(TaskTargetType::class)]
final class TaskTargetTypeTest extends AbstractEnumTestCase
{
    #[Test]
    public function casesReturnsAllTypes(): void
    {
        // Act
        $cases = TaskTargetType::cases();

        // Assert
        $this->assertCount(3, $cases);
        $this->assertContains(TaskTargetType::ALL, $cases);
        $this->assertContains(TaskTargetType::GROUP, $cases);
        $this->assertContains(TaskTargetType::SPECIFIC, $cases);
    }

    #[Test]
    public function valuesAreCorrect(): void
    {
        // Assert
        $this->assertEquals('all', TaskTargetType::ALL->value);
        $this->assertEquals('group', TaskTargetType::GROUP->value);
        $this->assertEquals('specific', TaskTargetType::SPECIFIC->value);
    }

    #[Test]
    public function fromCreatesCorrectEnum(): void
    {
        // Assert
        $this->assertEquals(TaskTargetType::ALL, TaskTargetType::from('all'));
        $this->assertEquals(TaskTargetType::GROUP, TaskTargetType::from('group'));
        $this->assertEquals(TaskTargetType::SPECIFIC, TaskTargetType::from('specific'));
    }

    #[Test]
    public function tryFromReturnsCorrectEnum(): void
    {
        // Valid values
        $this->assertEquals(TaskTargetType::ALL, TaskTargetType::tryFrom('all'));
        $this->assertEquals(TaskTargetType::GROUP, TaskTargetType::tryFrom('group'));

        // Invalid value
        $this->assertNull(TaskTargetType::tryFrom('invalid'));
    }

    #[Test]
    public function getLabelReturnsCorrectLabel(): void
    {
        // Assert
        $this->assertEquals('所有设备', TaskTargetType::ALL->getLabel());
        $this->assertEquals('设备分组', TaskTargetType::GROUP->getLabel());
        $this->assertEquals('指定设备', TaskTargetType::SPECIFIC->getLabel());
    }

    #[Test]
    public function getDescriptionReturnsCorrectDescription(): void
    {
        // Assert
        $this->assertEquals('在所有可用设备上执行', TaskTargetType::ALL->getDescription());
        $this->assertEquals('在指定设备组上执行', TaskTargetType::GROUP->getDescription());
        $this->assertEquals('在特定设备上执行', TaskTargetType::SPECIFIC->getDescription());
    }

    #[Test]
    public function testRequiresTarget(): void
    {
        // Does not require target
        $this->assertFalse(TaskTargetType::ALL->requiresTarget());

        // Requires target
        $this->assertTrue(TaskTargetType::GROUP->requiresTarget());
        $this->assertTrue(TaskTargetType::SPECIFIC->requiresTarget());
    }

    #[Test]
    public function getIconReturnsCorrectIcon(): void
    {
        // Assert
        $this->assertEquals('globe', TaskTargetType::ALL->getIcon());
        $this->assertEquals('object-group', TaskTargetType::GROUP->getIcon());
        $this->assertEquals('crosshairs', TaskTargetType::SPECIFIC->getIcon());
    }

    #[Test]
    public function getChoicesReturnsCorrectArray(): void
    {
        // Act
        $choices = TaskTargetType::getChoices();

        // Assert
        $expected = [
            '所有设备' => 'all',
            '设备组' => 'group',
            '指定设备' => 'specific',
        ];
        $this->assertEquals($expected, $choices);
    }

    #[Test]
    public function testRequiresDeviceList(): void
    {
        // Requires device list
        $this->assertTrue(TaskTargetType::SPECIFIC->requiresDeviceList());

        // Does not require device list
        $this->assertFalse(TaskTargetType::ALL->requiresDeviceList());
        $this->assertFalse(TaskTargetType::GROUP->requiresDeviceList());
    }

    #[Test]
    public function testRequiresGroup(): void
    {
        // Requires group
        $this->assertTrue(TaskTargetType::GROUP->requiresGroup());

        // Does not require group
        $this->assertFalse(TaskTargetType::ALL->requiresGroup());
        $this->assertFalse(TaskTargetType::SPECIFIC->requiresGroup());
    }

    #[Test]
    public function testToArray(): void
    {
        // Test ALL enum
        $allArray = TaskTargetType::ALL->toArray();
        $this->assertEquals([
            'value' => 'all',
            'label' => '所有设备',
        ], $allArray);

        // Test GROUP enum
        $groupArray = TaskTargetType::GROUP->toArray();
        $this->assertEquals([
            'value' => 'group',
            'label' => '设备分组',
        ], $groupArray);

        // Test SPECIFIC enum
        $specificArray = TaskTargetType::SPECIFIC->toArray();
        $this->assertEquals([
            'value' => 'specific',
            'label' => '指定设备',
        ], $specificArray);
    }
}
