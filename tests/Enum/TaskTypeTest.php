<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Enum;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tourze\AutoJsControlBundle\Enum\TaskType;
use Tourze\PHPUnitEnum\AbstractEnumTestCase;

/**
 * @internal
 */
#[CoversClass(TaskType::class)]
final class TaskTypeTest extends AbstractEnumTestCase
{
    #[Test]
    public function casesReturnsAllTypes(): void
    {
        // Act
        $cases = TaskType::cases();

        // Assert
        $this->assertCount(3, $cases);
        $this->assertContains(TaskType::IMMEDIATE, $cases);
        $this->assertContains(TaskType::SCHEDULED, $cases);
        $this->assertContains(TaskType::RECURRING, $cases);
    }

    #[Test]
    public function valuesAreCorrect(): void
    {
        // Assert
        $this->assertEquals('immediate', TaskType::IMMEDIATE->value);
        $this->assertEquals('scheduled', TaskType::SCHEDULED->value);
        $this->assertEquals('recurring', TaskType::RECURRING->value);
    }

    #[Test]
    public function fromCreatesCorrectEnum(): void
    {
        // Assert
        $this->assertEquals(TaskType::IMMEDIATE, TaskType::from('immediate'));
        $this->assertEquals(TaskType::SCHEDULED, TaskType::from('scheduled'));
        $this->assertEquals(TaskType::RECURRING, TaskType::from('recurring'));
    }

    #[Test]
    public function tryFromReturnsCorrectEnum(): void
    {
        // Valid values
        $this->assertEquals(TaskType::IMMEDIATE, TaskType::tryFrom('immediate'));
        $this->assertEquals(TaskType::SCHEDULED, TaskType::tryFrom('scheduled'));

        // Invalid value
        $this->assertNull(TaskType::tryFrom('invalid'));
    }

    #[Test]
    public function getLabelReturnsCorrectLabel(): void
    {
        // Assert
        $this->assertEquals('立即执行', TaskType::IMMEDIATE->getLabel());
        $this->assertEquals('定时执行', TaskType::SCHEDULED->getLabel());
        $this->assertEquals('循环执行', TaskType::RECURRING->getLabel());
    }

    #[Test]
    public function getDescriptionReturnsCorrectDescription(): void
    {
        // Assert
        $this->assertEquals('创建后立即执行', TaskType::IMMEDIATE->getDescription());
        $this->assertEquals('在指定时间执行一次', TaskType::SCHEDULED->getDescription());
        $this->assertEquals('按照设定的规则重复执行', TaskType::RECURRING->getDescription());
    }

    #[Test]
    public function getIconReturnsCorrectIcon(): void
    {
        // Assert
        $this->assertEquals('bolt', TaskType::IMMEDIATE->getIcon());
        $this->assertEquals('clock', TaskType::SCHEDULED->getIcon());
        $this->assertEquals('sync', TaskType::RECURRING->getIcon());
    }

    #[Test]
    public function getChoicesReturnsCorrectArray(): void
    {
        // Act
        $choices = TaskType::getChoices();

        // Assert
        $expected = [
            '立即执行' => 'immediate',
            '定时执行' => 'scheduled',
            '循环执行' => 'recurring',
        ];
        $this->assertEquals($expected, $choices);
    }

    #[Test]
    public function testRequiresCron(): void
    {
        // Only RECURRING requires cron
        $this->assertTrue(TaskType::RECURRING->requiresCron());

        // Others do not require cron
        $this->assertFalse(TaskType::IMMEDIATE->requiresCron());
        $this->assertFalse(TaskType::SCHEDULED->requiresCron());
    }

    #[Test]
    public function testRequiresSchedule(): void
    {
        // SCHEDULED and RECURRING require schedule
        $this->assertTrue(TaskType::SCHEDULED->requiresSchedule());
        $this->assertTrue(TaskType::RECURRING->requiresSchedule());

        // IMMEDIATE does not require schedule
        $this->assertFalse(TaskType::IMMEDIATE->requiresSchedule());
    }

    #[Test]
    public function testToArray(): void
    {
        // Test IMMEDIATE enum
        $immediateArray = TaskType::IMMEDIATE->toArray();
        $this->assertEquals([
            'value' => 'immediate',
            'label' => '立即执行',
        ], $immediateArray);

        // Test SCHEDULED enum
        $scheduledArray = TaskType::SCHEDULED->toArray();
        $this->assertEquals([
            'value' => 'scheduled',
            'label' => '定时执行',
        ], $scheduledArray);
    }
}
