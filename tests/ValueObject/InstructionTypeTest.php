<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\ValueObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tourze\AutoJsControlBundle\ValueObject\InstructionType;
use Tourze\EnumExtra\Itemable;
use Tourze\EnumExtra\Labelable;
use Tourze\EnumExtra\Selectable;
use Tourze\PHPUnitEnum\AbstractEnumTestCase;

/**
 * @internal
 */
#[CoversClass(InstructionType::class)]
final class InstructionTypeTest extends AbstractEnumTestCase
{
    #[Test]
    public function allCasesHaveCorrectValues(): void
    {
        // Assert
        $this->assertEquals('execute_script', InstructionType::EXECUTE_SCRIPT->value);
        $this->assertEquals('stop_script', InstructionType::STOP_SCRIPT->value);
        $this->assertEquals('update_status', InstructionType::UPDATE_STATUS->value);
        $this->assertEquals('collect_log', InstructionType::COLLECT_LOG->value);
        $this->assertEquals('restart_app', InstructionType::RESTART_APP->value);
        $this->assertEquals('update_app', InstructionType::UPDATE_APP->value);
        $this->assertEquals('ping', InstructionType::PING->value);
    }

    #[Test]
    public function getLabelReturnsCorrectLabels(): void
    {
        // Assert
        $this->assertEquals('执行脚本', InstructionType::EXECUTE_SCRIPT->getLabel());
        $this->assertEquals('停止脚本', InstructionType::STOP_SCRIPT->getLabel());
        $this->assertEquals('更新状态', InstructionType::UPDATE_STATUS->getLabel());
        $this->assertEquals('收集日志', InstructionType::COLLECT_LOG->getLabel());
        $this->assertEquals('重启应用', InstructionType::RESTART_APP->getLabel());
        $this->assertEquals('更新应用', InstructionType::UPDATE_APP->getLabel());
        $this->assertEquals('心跳检测', InstructionType::PING->getLabel());
    }

    #[Test]
    public function isUrgentReturnsCorrectValues(): void
    {
        // Assert - urgent types
        $this->assertTrue(InstructionType::STOP_SCRIPT->isUrgent());
        $this->assertTrue(InstructionType::RESTART_APP->isUrgent());
        $this->assertTrue(InstructionType::PING->isUrgent());

        // Assert - non-urgent types
        $this->assertFalse(InstructionType::EXECUTE_SCRIPT->isUrgent());
        $this->assertFalse(InstructionType::UPDATE_STATUS->isUrgent());
        $this->assertFalse(InstructionType::COLLECT_LOG->isUrgent());
        $this->assertFalse(InstructionType::UPDATE_APP->isUrgent());
    }

    #[Test]
    public function fromCreatesEnumFromValue(): void
    {
        // Act & Assert
        $this->assertEquals(InstructionType::EXECUTE_SCRIPT, InstructionType::from('execute_script'));
        $this->assertEquals(InstructionType::STOP_SCRIPT, InstructionType::from('stop_script'));
        $this->assertEquals(InstructionType::UPDATE_STATUS, InstructionType::from('update_status'));
        $this->assertEquals(InstructionType::COLLECT_LOG, InstructionType::from('collect_log'));
        $this->assertEquals(InstructionType::RESTART_APP, InstructionType::from('restart_app'));
        $this->assertEquals(InstructionType::UPDATE_APP, InstructionType::from('update_app'));
        $this->assertEquals(InstructionType::PING, InstructionType::from('ping'));
    }

    #[Test]
    public function tryFromReturnsNullForInvalidValue(): void
    {
        // Act & Assert
        $this->assertNull(InstructionType::tryFrom('invalid_type'));
    }

    #[Test]
    public function casesReturnsAllEnumCases(): void
    {
        // Act
        $cases = InstructionType::cases();

        // Assert
        $this->assertCount(7, $cases);
        $this->assertContains(InstructionType::EXECUTE_SCRIPT, $cases);
        $this->assertContains(InstructionType::STOP_SCRIPT, $cases);
        $this->assertContains(InstructionType::UPDATE_STATUS, $cases);
        $this->assertContains(InstructionType::COLLECT_LOG, $cases);
        $this->assertContains(InstructionType::RESTART_APP, $cases);
        $this->assertContains(InstructionType::UPDATE_APP, $cases);
        $this->assertContains(InstructionType::PING, $cases);
    }

    #[Test]
    public function getItemsReturnsAllItemsWithLabels(): void
    {
        // Act
        $items = InstructionType::getItems();

        // Assert
        $this->assertCount(7, $items);
        $this->assertEquals('执行脚本', $items['execute_script']);
        $this->assertEquals('停止脚本', $items['stop_script']);
        $this->assertEquals('更新状态', $items['update_status']);
        $this->assertEquals('收集日志', $items['collect_log']);
        $this->assertEquals('重启应用', $items['restart_app']);
        $this->assertEquals('更新应用', $items['update_app']);
        $this->assertEquals('心跳检测', $items['ping']);
    }

    #[Test]
    public function getSelectItemsReturnsSelectableFormat(): void
    {
        // Act
        $selectItems = InstructionType::getSelectItems();

        // Assert
        $this->assertCount(7, $selectItems);

        // Verify structure
        foreach ($selectItems as $item) {
            $this->assertArrayHasKey('value', $item);
            $this->assertArrayHasKey('text', $item);
        }

        // Verify specific items
        $executeScript = array_filter($selectItems, fn ($item) => 'execute_script' === $item['value']);
        $this->assertCount(1, $executeScript);
        $this->assertEquals('执行脚本', array_values($executeScript)[0]['text']);
    }

    #[Test]
    public function enumImplementsExpectedInterfaces(): void
    {
        // Arrange
        $enum = InstructionType::EXECUTE_SCRIPT;

        // Assert
        $this->assertInstanceOf(\BackedEnum::class, $enum);
        $this->assertInstanceOf(Labelable::class, $enum);
        $this->assertInstanceOf(Itemable::class, $enum);
        $this->assertInstanceOf(Selectable::class, $enum);
    }
}
