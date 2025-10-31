<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Enum;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tourze\AutoJsControlBundle\Enum\LogLevel;
use Tourze\PHPUnitEnum\AbstractEnumTestCase;

/**
 * @internal
 */
#[CoversClass(LogLevel::class)]
final class LogLevelTest extends AbstractEnumTestCase
{
    #[Test]
    public function casesReturnsAllLevels(): void
    {
        // Act
        $cases = LogLevel::cases();

        // Assert
        $this->assertCount(5, $cases);
        $this->assertContains(LogLevel::DEBUG, $cases);
        $this->assertContains(LogLevel::INFO, $cases);
        $this->assertContains(LogLevel::WARNING, $cases);
        $this->assertContains(LogLevel::ERROR, $cases);
        $this->assertContains(LogLevel::CRITICAL, $cases);
    }

    #[Test]
    public function valuesAreCorrect(): void
    {
        // Assert
        $this->assertEquals('debug', LogLevel::DEBUG->value);
        $this->assertEquals('info', LogLevel::INFO->value);
        $this->assertEquals('warning', LogLevel::WARNING->value);
        $this->assertEquals('error', LogLevel::ERROR->value);
        $this->assertEquals('critical', LogLevel::CRITICAL->value);
    }

    #[Test]
    public function fromCreatesCorrectEnum(): void
    {
        // Assert
        $this->assertEquals(LogLevel::DEBUG, LogLevel::from('debug'));
        $this->assertEquals(LogLevel::INFO, LogLevel::from('info'));
        $this->assertEquals(LogLevel::WARNING, LogLevel::from('warning'));
        $this->assertEquals(LogLevel::ERROR, LogLevel::from('error'));
        $this->assertEquals(LogLevel::CRITICAL, LogLevel::from('critical'));
    }

    #[Test]
    public function tryFromReturnsCorrectEnum(): void
    {
        // Valid values
        $this->assertEquals(LogLevel::DEBUG, LogLevel::tryFrom('debug'));
        $this->assertEquals(LogLevel::ERROR, LogLevel::tryFrom('error'));

        // Invalid value
        $this->assertNull(LogLevel::tryFrom('invalid'));
    }

    #[Test]
    public function getLabelReturnsCorrectLabel(): void
    {
        // Assert
        $this->assertEquals('调试', LogLevel::DEBUG->getLabel());
        $this->assertEquals('信息', LogLevel::INFO->getLabel());
        $this->assertEquals('警告', LogLevel::WARNING->getLabel());
        $this->assertEquals('错误', LogLevel::ERROR->getLabel());
        $this->assertEquals('严重', LogLevel::CRITICAL->getLabel());
    }

    #[Test]
    public function getColorReturnsCorrectColor(): void
    {
        // Assert
        $this->assertEquals('secondary', LogLevel::DEBUG->getColor());
        $this->assertEquals('info', LogLevel::INFO->getColor());
        $this->assertEquals('warning', LogLevel::WARNING->getColor());
        $this->assertEquals('danger', LogLevel::ERROR->getColor());
        $this->assertEquals('dark', LogLevel::CRITICAL->getColor());
    }

    #[Test]
    public function getPriorityReturnsCorrectPriority(): void
    {
        // Assert
        $this->assertEquals(100, LogLevel::DEBUG->getPriority());
        $this->assertEquals(200, LogLevel::INFO->getPriority());
        $this->assertEquals(300, LogLevel::WARNING->getPriority());
        $this->assertEquals(400, LogLevel::ERROR->getPriority());
        $this->assertEquals(500, LogLevel::CRITICAL->getPriority());
    }

    #[Test]
    public function isErrorLevelReturnsCorrectValue(): void
    {
        // Non-error levels
        $this->assertFalse(LogLevel::DEBUG->isErrorLevel());
        $this->assertFalse(LogLevel::INFO->isErrorLevel());
        $this->assertFalse(LogLevel::WARNING->isErrorLevel());

        // Error levels
        $this->assertTrue(LogLevel::ERROR->isErrorLevel());
        $this->assertTrue(LogLevel::CRITICAL->isErrorLevel());
    }

    #[Test]
    public function getChoicesReturnsCorrectArray(): void
    {
        // Act
        $choices = LogLevel::getChoices();

        // Assert
        $expected = [
            '调试' => 'debug',
            '信息' => 'info',
            '警告' => 'warning',
            '错误' => 'error',
            '严重' => 'critical',
        ];
        $this->assertEquals($expected, $choices);
    }

    #[Test]
    public function testCompareTo(): void
    {
        // Same level
        $this->assertEquals(0, LogLevel::INFO->compareTo(LogLevel::INFO));

        // Lower priority (DEBUG < INFO)
        $this->assertLessThan(0, LogLevel::DEBUG->compareTo(LogLevel::INFO));

        // Higher priority (ERROR > WARNING)
        $this->assertGreaterThan(0, LogLevel::ERROR->compareTo(LogLevel::WARNING));
    }

    #[Test]
    public function getIconReturnsCorrectIcon(): void
    {
        // Assert
        $this->assertEquals('bug_report', LogLevel::DEBUG->getIcon());
        $this->assertEquals('info', LogLevel::INFO->getIcon());
        $this->assertEquals('warning', LogLevel::WARNING->getIcon());
        $this->assertEquals('error', LogLevel::ERROR->getIcon());
        $this->assertEquals('report', LogLevel::CRITICAL->getIcon());
    }

    #[Test]
    public function getWeightReturnsCorrectWeight(): void
    {
        // Assert
        $this->assertEquals(100, LogLevel::DEBUG->getWeight());
        $this->assertEquals(200, LogLevel::INFO->getWeight());
        $this->assertEquals(300, LogLevel::WARNING->getWeight());
        $this->assertEquals(400, LogLevel::ERROR->getWeight());
        $this->assertEquals(500, LogLevel::CRITICAL->getWeight());
    }

    #[Test]
    public function isErrorReturnsCorrectValue(): void
    {
        // Non-error levels
        $this->assertFalse(LogLevel::DEBUG->isError());
        $this->assertFalse(LogLevel::INFO->isError());
        $this->assertFalse(LogLevel::WARNING->isError());

        // Error levels
        $this->assertTrue(LogLevel::ERROR->isError());
        $this->assertTrue(LogLevel::CRITICAL->isError());
    }

    #[Test]
    public function toSelectReturnsCorrectArray(): void
    {
        // Act
        $choices = LogLevel::toSelect();

        // Assert
        $expected = [
            '调试' => 'debug',
            '信息' => 'info',
            '警告' => 'warning',
            '错误' => 'error',
            '严重' => 'critical',
        ];
        $this->assertEquals($expected, $choices);
    }

    #[Test]
    public function testToArray(): void
    {
        // Test INFO enum
        $infoArray = LogLevel::INFO->toArray();
        $this->assertEquals([
            'value' => 'info',
            'label' => '信息',
        ], $infoArray);

        // Test CRITICAL enum
        $criticalArray = LogLevel::CRITICAL->toArray();
        $this->assertEquals([
            'value' => 'critical',
            'label' => '严重',
        ], $criticalArray);
    }

    #[Test]
    public function genOptionsReturnsCorrectArray(): void
    {
        // Act
        $options = LogLevel::genOptions();

        // Assert
        $this->assertCount(5, $options);

        // Check first option
        $this->assertEquals([
            'label' => '调试',
            'text' => '调试',
            'value' => 'debug',
            'name' => '调试',
        ], $options[0]);

        // Check all values are present
        $values = array_column($options, 'value');
        $expectedValues = ['debug', 'info', 'warning', 'error', 'critical'];
        $this->assertEquals($expectedValues, $values);

        // Check all labels are present
        $labels = array_column($options, 'label');
        $expectedLabels = ['调试', '信息', '警告', '错误', '严重'];
        $this->assertEquals($expectedLabels, $labels);
    }
}
