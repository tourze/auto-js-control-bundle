<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Enum;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tourze\AutoJsControlBundle\Enum\LogType;
use Tourze\PHPUnitEnum\AbstractEnumTestCase;

/**
 * @internal
 */
#[CoversClass(LogType::class)]
final class LogTypeTest extends AbstractEnumTestCase
{
    #[Test]
    public function casesReturnsAllTypes(): void
    {
        // Act
        $cases = LogType::cases();

        // Assert
        $this->assertCount(5, $cases);
        $this->assertContains(LogType::SYSTEM, $cases);
        $this->assertContains(LogType::SCRIPT, $cases);
        $this->assertContains(LogType::CONNECTION, $cases);
        $this->assertContains(LogType::COMMAND, $cases);
        $this->assertContains(LogType::TASK, $cases);
    }

    #[Test]
    public function valuesAreCorrect(): void
    {
        // Assert
        $this->assertEquals('system', LogType::SYSTEM->value);
        $this->assertEquals('script', LogType::SCRIPT->value);
        $this->assertEquals('connection', LogType::CONNECTION->value);
        $this->assertEquals('command', LogType::COMMAND->value);
        $this->assertEquals('task', LogType::TASK->value);
    }

    #[Test]
    public function fromCreatesCorrectEnum(): void
    {
        // Assert
        $this->assertEquals(LogType::SYSTEM, LogType::from('system'));
        $this->assertEquals(LogType::SCRIPT, LogType::from('script'));
        $this->assertEquals(LogType::CONNECTION, LogType::from('connection'));
        $this->assertEquals(LogType::COMMAND, LogType::from('command'));
        $this->assertEquals(LogType::TASK, LogType::from('task'));
    }

    #[Test]
    public function tryFromReturnsCorrectEnum(): void
    {
        // Valid values
        $this->assertEquals(LogType::SYSTEM, LogType::tryFrom('system'));
        $this->assertEquals(LogType::SCRIPT, LogType::tryFrom('script'));

        // Invalid value
        $this->assertNull(LogType::tryFrom('invalid_type'));
    }

    #[Test]
    public function getLabelReturnsCorrectLabel(): void
    {
        // Assert
        $this->assertEquals('系统日志', LogType::SYSTEM->getLabel());
        $this->assertEquals('脚本执行', LogType::SCRIPT->getLabel());
        $this->assertEquals('连接日志', LogType::CONNECTION->getLabel());
        $this->assertEquals('命令执行', LogType::COMMAND->getLabel());
        $this->assertEquals('任务日志', LogType::TASK->getLabel());
    }

    #[Test]
    public function getIconReturnsCorrectIcon(): void
    {
        // Assert
        $this->assertEquals('settings', LogType::SYSTEM->getIcon());
        $this->assertEquals('code', LogType::SCRIPT->getIcon());
        $this->assertEquals('sync_alt', LogType::CONNECTION->getIcon());
        $this->assertEquals('terminal', LogType::COMMAND->getIcon());
        $this->assertEquals('assignment', LogType::TASK->getIcon());
    }

    #[Test]
    public function isSystemTypeReturnsCorrectValue(): void
    {
        // System types
        $this->assertTrue(LogType::SYSTEM->isSystemType());

        // Non-system types
        $this->assertFalse(LogType::SCRIPT->isSystemType());
        $this->assertFalse(LogType::CONNECTION->isSystemType());
        $this->assertFalse(LogType::COMMAND->isSystemType());
        $this->assertFalse(LogType::TASK->isSystemType());
    }

    #[Test]
    public function getChoicesReturnsCorrectArray(): void
    {
        // Act
        $choices = LogType::getChoices();

        // Assert
        $expected = [
            '系统日志' => 'system',
            '脚本执行' => 'script',
            '连接日志' => 'connection',
            '命令执行' => 'command',
            '任务日志' => 'task',
        ];
        $this->assertEquals($expected, $choices);
    }

    #[Test]
    public function getColorReturnsCorrectColor(): void
    {
        // Assert
        $this->assertEquals('primary', LogType::SYSTEM->getColor());
        $this->assertEquals('success', LogType::SCRIPT->getColor());
        $this->assertEquals('info', LogType::CONNECTION->getColor());
        $this->assertEquals('secondary', LogType::COMMAND->getColor());
        $this->assertEquals('warning', LogType::TASK->getColor());
    }

    #[Test]
    public function testToArray(): void
    {
        // Test CONNECTION enum
        $connectionArray = LogType::CONNECTION->toArray();
        $this->assertEquals([
            'value' => 'connection',
            'label' => '连接日志',
        ], $connectionArray);

        // Test TASK enum
        $taskArray = LogType::TASK->toArray();
        $this->assertEquals([
            'value' => 'task',
            'label' => '任务日志',
        ], $taskArray);
    }

    #[Test]
    public function genOptionsReturnsCorrectArray(): void
    {
        // Act
        $options = LogType::genOptions();

        // Assert
        $this->assertCount(5, $options);

        // Check first option
        $this->assertEquals([
            'label' => '系统日志',
            'text' => '系统日志',
            'value' => 'system',
            'name' => '系统日志',
        ], $options[0]);

        // Check all values are present
        $values = array_column($options, 'value');
        $expectedValues = ['system', 'script', 'connection', 'command', 'task'];
        $this->assertEquals($expectedValues, $values);

        // Check all labels are present
        $labels = array_column($options, 'label');
        $expectedLabels = ['系统日志', '脚本执行', '连接日志', '命令执行', '任务日志'];
        $this->assertEquals($expectedLabels, $labels);
    }
}
