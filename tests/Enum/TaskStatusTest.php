<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Enum;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tourze\AutoJsControlBundle\Enum\TaskStatus;
use Tourze\PHPUnitEnum\AbstractEnumTestCase;

/**
 * @internal
 */
#[CoversClass(TaskStatus::class)]
final class TaskStatusTest extends AbstractEnumTestCase
{
    #[Test]
    public function casesReturnsAllStatuses(): void
    {
        // Act
        $cases = TaskStatus::cases();

        // Assert
        $this->assertCount(8, $cases);
        $this->assertContains(TaskStatus::PENDING, $cases);
        $this->assertContains(TaskStatus::SCHEDULED, $cases);
        $this->assertContains(TaskStatus::RUNNING, $cases);
        $this->assertContains(TaskStatus::PAUSED, $cases);
        $this->assertContains(TaskStatus::COMPLETED, $cases);
        $this->assertContains(TaskStatus::PARTIALLY_COMPLETED, $cases);
        $this->assertContains(TaskStatus::FAILED, $cases);
        $this->assertContains(TaskStatus::CANCELLED, $cases);
    }

    #[Test]
    public function valuesAreCorrect(): void
    {
        // Assert
        $this->assertEquals('pending', TaskStatus::PENDING->value);
        $this->assertEquals('scheduled', TaskStatus::SCHEDULED->value);
        $this->assertEquals('running', TaskStatus::RUNNING->value);
        $this->assertEquals('paused', TaskStatus::PAUSED->value);
        $this->assertEquals('completed', TaskStatus::COMPLETED->value);
        $this->assertEquals('partially_completed', TaskStatus::PARTIALLY_COMPLETED->value);
        $this->assertEquals('failed', TaskStatus::FAILED->value);
        $this->assertEquals('cancelled', TaskStatus::CANCELLED->value);
    }

    #[Test]
    public function fromCreatesCorrectEnum(): void
    {
        // Assert
        $this->assertEquals(TaskStatus::PENDING, TaskStatus::from('pending'));
        $this->assertEquals(TaskStatus::RUNNING, TaskStatus::from('running'));
        $this->assertEquals(TaskStatus::PAUSED, TaskStatus::from('paused'));
        $this->assertEquals(TaskStatus::COMPLETED, TaskStatus::from('completed'));
        $this->assertEquals(TaskStatus::PARTIALLY_COMPLETED, TaskStatus::from('partially_completed'));
        $this->assertEquals(TaskStatus::FAILED, TaskStatus::from('failed'));
        $this->assertEquals(TaskStatus::CANCELLED, TaskStatus::from('cancelled'));
    }

    #[Test]
    public function tryFromReturnsCorrectEnum(): void
    {
        // Valid values
        $this->assertEquals(TaskStatus::PENDING, TaskStatus::tryFrom('pending'));
        $this->assertEquals(TaskStatus::COMPLETED, TaskStatus::tryFrom('completed'));

        // Invalid value
        $this->assertNull(TaskStatus::tryFrom('invalid'));
    }

    #[Test]
    public function getLabelReturnsCorrectLabel(): void
    {
        // Assert
        $this->assertEquals('待执行', TaskStatus::PENDING->getLabel());
        $this->assertEquals('执行中', TaskStatus::RUNNING->getLabel());
        $this->assertEquals('已暂停', TaskStatus::PAUSED->getLabel());
        $this->assertEquals('已完成', TaskStatus::COMPLETED->getLabel());
        $this->assertEquals('部分完成', TaskStatus::PARTIALLY_COMPLETED->getLabel());
        $this->assertEquals('执行失败', TaskStatus::FAILED->getLabel());
        $this->assertEquals('已取消', TaskStatus::CANCELLED->getLabel());
    }

    #[Test]
    public function getColorReturnsCorrectColor(): void
    {
        // Assert
        $this->assertEquals('secondary', TaskStatus::PENDING->getColor());
        $this->assertEquals('primary', TaskStatus::RUNNING->getColor());
        $this->assertEquals('info', TaskStatus::PAUSED->getColor());
        $this->assertEquals('success', TaskStatus::COMPLETED->getColor());
        $this->assertEquals('warning', TaskStatus::PARTIALLY_COMPLETED->getColor());
        $this->assertEquals('danger', TaskStatus::FAILED->getColor());
        $this->assertEquals('warning', TaskStatus::CANCELLED->getColor());
    }

    #[Test]
    public function isFinalReturnsCorrectValue(): void
    {
        // Non-final states
        $this->assertFalse(TaskStatus::PENDING->isFinal());
        $this->assertFalse(TaskStatus::RUNNING->isFinal());
        $this->assertFalse(TaskStatus::PAUSED->isFinal());

        // Final states
        $this->assertTrue(TaskStatus::COMPLETED->isFinal());
        $this->assertTrue(TaskStatus::PARTIALLY_COMPLETED->isFinal());
        $this->assertTrue(TaskStatus::FAILED->isFinal());
        $this->assertTrue(TaskStatus::CANCELLED->isFinal());
    }

    #[Test]
    public function testCanCancelReturnsCorrectValue(): void
    {
        // Can cancel states
        $this->assertTrue(TaskStatus::PENDING->canCancel());
        $this->assertTrue(TaskStatus::RUNNING->canCancel());
        $this->assertTrue(TaskStatus::PAUSED->canCancel());

        // Cannot cancel states
        $this->assertFalse(TaskStatus::COMPLETED->canCancel());
        $this->assertFalse(TaskStatus::PARTIALLY_COMPLETED->canCancel());
        $this->assertFalse(TaskStatus::FAILED->canCancel());
        $this->assertFalse(TaskStatus::CANCELLED->canCancel());
    }

    #[Test]
    public function testCanRetryReturnsCorrectValue(): void
    {
        // Can retry states
        $this->assertTrue(TaskStatus::FAILED->canRetry());
        $this->assertTrue(TaskStatus::CANCELLED->canRetry());

        // Cannot retry states
        $this->assertFalse(TaskStatus::PENDING->canRetry());
        $this->assertFalse(TaskStatus::RUNNING->canRetry());
        $this->assertFalse(TaskStatus::PAUSED->canRetry());
        $this->assertFalse(TaskStatus::COMPLETED->canRetry());
        $this->assertFalse(TaskStatus::PARTIALLY_COMPLETED->canRetry());
    }

    #[Test]
    public function getBadgeClassReturnsCorrectClass(): void
    {
        // Assert
        $this->assertEquals('badge-secondary', TaskStatus::PENDING->getBadgeClass());
        $this->assertEquals('badge-primary', TaskStatus::RUNNING->getBadgeClass());
        $this->assertEquals('badge-info', TaskStatus::PAUSED->getBadgeClass());
        $this->assertEquals('badge-success', TaskStatus::COMPLETED->getBadgeClass());
        $this->assertEquals('badge-warning', TaskStatus::PARTIALLY_COMPLETED->getBadgeClass());
        $this->assertEquals('badge-danger', TaskStatus::FAILED->getBadgeClass());
        $this->assertEquals('badge-warning', TaskStatus::CANCELLED->getBadgeClass());
    }

    #[Test]
    public function testToArrayReturnsCorrectStructure(): void
    {
        // Test PENDING status
        $pendingArray = TaskStatus::PENDING->toArray();
        $this->assertIsArray($pendingArray);
        $this->assertArrayHasKey('value', $pendingArray);
        $this->assertArrayHasKey('label', $pendingArray);
        $this->assertEquals('pending', $pendingArray['value']);
        $this->assertEquals('待执行', $pendingArray['label']);

        // Test RUNNING status
        $runningArray = TaskStatus::RUNNING->toArray();
        $this->assertEquals('running', $runningArray['value']);
        $this->assertEquals('执行中', $runningArray['label']);

        // Test COMPLETED status
        $completedArray = TaskStatus::COMPLETED->toArray();
        $this->assertEquals('completed', $completedArray['value']);
        $this->assertEquals('已完成', $completedArray['label']);

        // Test FAILED status
        $failedArray = TaskStatus::FAILED->toArray();
        $this->assertEquals('failed', $failedArray['value']);
        $this->assertEquals('执行失败', $failedArray['label']);

        // Test CANCELLED status
        $cancelledArray = TaskStatus::CANCELLED->toArray();
        $this->assertEquals('cancelled', $cancelledArray['value']);
        $this->assertEquals('已取消', $cancelledArray['label']);

        // Test SCHEDULED status
        $scheduledArray = TaskStatus::SCHEDULED->toArray();
        $this->assertEquals('scheduled', $scheduledArray['value']);
        $this->assertEquals('已计划', $scheduledArray['label']);

        // Test PAUSED status
        $pausedArray = TaskStatus::PAUSED->toArray();
        $this->assertEquals('paused', $pausedArray['value']);
        $this->assertEquals('已暂停', $pausedArray['label']);

        // Test PARTIALLY_COMPLETED status
        $partiallyCompletedArray = TaskStatus::PARTIALLY_COMPLETED->toArray();
        $this->assertEquals('partially_completed', $partiallyCompletedArray['value']);
        $this->assertEquals('部分完成', $partiallyCompletedArray['label']);
    }

    #[Test]
    public function testToSelectItemReturnsCorrectStructure(): void
    {
        // Test PENDING status
        $pendingItem = TaskStatus::PENDING->toSelectItem();
        $this->assertIsArray($pendingItem);
        $this->assertArrayHasKey('label', $pendingItem);
        $this->assertArrayHasKey('text', $pendingItem);
        $this->assertArrayHasKey('value', $pendingItem);
        $this->assertArrayHasKey('name', $pendingItem);
        $this->assertEquals('待执行', $pendingItem['label']);
        $this->assertEquals('待执行', $pendingItem['text']);
        $this->assertEquals('pending', $pendingItem['value']);
        $this->assertEquals('待执行', $pendingItem['name']);

        // Test RUNNING status
        $runningItem = TaskStatus::RUNNING->toSelectItem();
        $this->assertEquals('执行中', $runningItem['label']);
        $this->assertEquals('执行中', $runningItem['text']);
        $this->assertEquals('running', $runningItem['value']);
        $this->assertEquals('执行中', $runningItem['name']);

        // Test COMPLETED status
        $completedItem = TaskStatus::COMPLETED->toSelectItem();
        $this->assertEquals('已完成', $completedItem['label']);
        $this->assertEquals('已完成', $completedItem['text']);
        $this->assertEquals('completed', $completedItem['value']);
        $this->assertEquals('已完成', $completedItem['name']);

        // Test FAILED status
        $failedItem = TaskStatus::FAILED->toSelectItem();
        $this->assertEquals('执行失败', $failedItem['label']);
        $this->assertEquals('执行失败', $failedItem['text']);
        $this->assertEquals('failed', $failedItem['value']);
        $this->assertEquals('执行失败', $failedItem['name']);

        // Test CANCELLED status
        $cancelledItem = TaskStatus::CANCELLED->toSelectItem();
        $this->assertEquals('已取消', $cancelledItem['label']);
        $this->assertEquals('已取消', $cancelledItem['text']);
        $this->assertEquals('cancelled', $cancelledItem['value']);
        $this->assertEquals('已取消', $cancelledItem['name']);

        // Test SCHEDULED status
        $scheduledItem = TaskStatus::SCHEDULED->toSelectItem();
        $this->assertEquals('已计划', $scheduledItem['label']);
        $this->assertEquals('已计划', $scheduledItem['text']);
        $this->assertEquals('scheduled', $scheduledItem['value']);
        $this->assertEquals('已计划', $scheduledItem['name']);

        // Test PAUSED status
        $pausedItem = TaskStatus::PAUSED->toSelectItem();
        $this->assertEquals('已暂停', $pausedItem['label']);
        $this->assertEquals('已暂停', $pausedItem['text']);
        $this->assertEquals('paused', $pausedItem['value']);
        $this->assertEquals('已暂停', $pausedItem['name']);

        // Test PARTIALLY_COMPLETED status
        $partiallyCompletedItem = TaskStatus::PARTIALLY_COMPLETED->toSelectItem();
        $this->assertEquals('部分完成', $partiallyCompletedItem['label']);
        $this->assertEquals('部分完成', $partiallyCompletedItem['text']);
        $this->assertEquals('partially_completed', $partiallyCompletedItem['value']);
        $this->assertEquals('部分完成', $partiallyCompletedItem['name']);
    }
}
