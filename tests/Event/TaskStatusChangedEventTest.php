<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use Tourze\AutoJsControlBundle\Entity\Script;
use Tourze\AutoJsControlBundle\Entity\Task;
use Tourze\AutoJsControlBundle\Enum\TaskStatus;
use Tourze\AutoJsControlBundle\Enum\TaskType;
use Tourze\AutoJsControlBundle\Event\TaskStatusChangedEvent;
use Tourze\PHPUnitSymfonyUnitTest\AbstractEventTestCase;

/**
 * @internal
 */
#[CoversClass(TaskStatusChangedEvent::class)]
final class TaskStatusChangedEventTest extends AbstractEventTestCase
{
    #[Test]
    public function constructorSetsProperties(): void
    {
        // Arrange
        $task = new Task();
        $task->setName('Test Task');
        $oldStatus = TaskStatus::PENDING;
        $newStatus = TaskStatus::RUNNING;

        // Act
        $event = new TaskStatusChangedEvent($task, $oldStatus, $newStatus);

        // Assert
        $this->assertSame($task, $event->getTask());
        $this->assertEquals($oldStatus, $event->getOldStatus());
        $this->assertEquals($newStatus, $event->getNewStatus());
    }

    #[Test]
    public function getTaskIdReturnsCorrectId(): void
    {
        // Arrange
        $task = new Task();
        $reflection = new \ReflectionClass($task);
        $property = $reflection->getProperty('id');
        $property->setAccessible(true);
        $property->setValue($task, 123);

        $event = new TaskStatusChangedEvent($task, TaskStatus::PENDING, TaskStatus::SCHEDULED);

        // Act & Assert
        $this->assertEquals(123, $event->getTaskId());
    }

    #[Test]
    public function getTaskNameReturnsCorrectName(): void
    {
        // Arrange
        $task = new Task();
        $task->setName('Daily Script Run');

        $event = new TaskStatusChangedEvent($task, TaskStatus::SCHEDULED, TaskStatus::RUNNING);

        // Act & Assert
        $this->assertEquals('Daily Script Run', $event->getTaskName());
    }

    #[Test]
    public function isStartedReturnsCorrectValue(): void
    {
        // Started - from pending/scheduled to running
        $task = new Task();
        $startedEvent1 = new TaskStatusChangedEvent($task, TaskStatus::PENDING, TaskStatus::RUNNING);
        $this->assertTrue($startedEvent1->isStarted());

        $startedEvent2 = new TaskStatusChangedEvent($task, TaskStatus::SCHEDULED, TaskStatus::RUNNING);
        $this->assertTrue($startedEvent2->isStarted());

        // Not started
        $notStartedEvent = new TaskStatusChangedEvent($task, TaskStatus::RUNNING, TaskStatus::COMPLETED);
        $this->assertFalse($notStartedEvent->isStarted());
    }

    #[Test]
    public function isCompletedReturnsCorrectValue(): void
    {
        // Completed
        $task = new Task();
        $completedEvent = new TaskStatusChangedEvent($task, TaskStatus::RUNNING, TaskStatus::COMPLETED);
        $this->assertTrue($completedEvent->isCompleted());

        // Not completed - other terminal states
        $failedEvent = new TaskStatusChangedEvent($task, TaskStatus::RUNNING, TaskStatus::FAILED);
        $this->assertFalse($failedEvent->isCompleted());

        $cancelledEvent = new TaskStatusChangedEvent($task, TaskStatus::RUNNING, TaskStatus::CANCELLED);
        $this->assertFalse($cancelledEvent->isCompleted());
    }

    #[Test]
    public function isFailedReturnsCorrectValue(): void
    {
        // Failed
        $task = new Task();
        $failedEvent = new TaskStatusChangedEvent($task, TaskStatus::RUNNING, TaskStatus::FAILED);
        $this->assertTrue($failedEvent->isFailed());

        // Not failed
        $completedEvent = new TaskStatusChangedEvent($task, TaskStatus::RUNNING, TaskStatus::COMPLETED);
        $this->assertFalse($completedEvent->isFailed());
    }

    #[Test]
    public function testWasCancelled(): void
    {
        // Cancelled
        $task = new Task();
        $cancelledEvent = new TaskStatusChangedEvent($task, TaskStatus::RUNNING, TaskStatus::CANCELLED);
        $this->assertTrue($cancelledEvent->wasCancelled());

        // Not cancelled
        $completedEvent = new TaskStatusChangedEvent($task, TaskStatus::RUNNING, TaskStatus::COMPLETED);
        $this->assertFalse($completedEvent->wasCancelled());
    }

    #[Test]
    public function isTerminalStatusReturnsCorrectValue(): void
    {
        $task = new Task();

        // Terminal statuses
        $completedEvent = new TaskStatusChangedEvent($task, TaskStatus::RUNNING, TaskStatus::COMPLETED);
        $this->assertTrue($completedEvent->isTerminalStatus());

        $failedEvent = new TaskStatusChangedEvent($task, TaskStatus::RUNNING, TaskStatus::FAILED);
        $this->assertTrue($failedEvent->isTerminalStatus());

        $cancelledEvent = new TaskStatusChangedEvent($task, TaskStatus::PENDING, TaskStatus::CANCELLED);
        $this->assertTrue($cancelledEvent->isTerminalStatus());

        // Non-terminal statuses
        $runningEvent = new TaskStatusChangedEvent($task, TaskStatus::PENDING, TaskStatus::RUNNING);
        $this->assertFalse($runningEvent->isTerminalStatus());

        $scheduledEvent = new TaskStatusChangedEvent($task, TaskStatus::PENDING, TaskStatus::SCHEDULED);
        $this->assertFalse($scheduledEvent->isTerminalStatus());
    }

    #[Test]
    public function getStatusChangeDescriptionReturnsCorrectDescription(): void
    {
        // Arrange
        $task = new Task();
        $task->setName('Test Task');
        $reflection = new \ReflectionClass($task);
        $property = $reflection->getProperty('id');
        $property->setAccessible(true);
        $property->setValue($task, 456);

        $event = new TaskStatusChangedEvent($task, TaskStatus::PENDING, TaskStatus::RUNNING);

        // Act
        $description = $event->getStatusChangeDescription();

        // Assert
        $this->assertEquals('任务 "Test Task" (#456) 状态从 待执行 变更为 执行中', $description);
    }

    #[Test]
    public function testToArray(): void
    {
        // Arrange
        $script = new Script();
        $script->setCode('STATUS-CHANGE-' . uniqid());
        $script->setName('Status Script');
        $scriptReflection = new \ReflectionClass($script);
        $scriptProperty = $scriptReflection->getProperty('id');
        $scriptProperty->setAccessible(true);
        $scriptProperty->setValue($script, 789);

        $task = new Task();
        $taskReflection = new \ReflectionClass($task);
        $taskProperty = $taskReflection->getProperty('id');
        $taskProperty->setAccessible(true);
        $taskProperty->setValue($task, 999);

        $task->setName('Status Change Task');
        $task->setType(TaskType::IMMEDIATE);
        $task->setScript($script);

        $event = new TaskStatusChangedEvent($task, TaskStatus::PENDING, TaskStatus::COMPLETED);

        // Act
        $array = $event->toArray();

        // Assert
        $this->assertEquals([
            'taskId' => 999,
            'taskName' => 'Status Change Task',
            'taskType' => 'immediate',
            'oldStatus' => 'pending',
            'newStatus' => 'completed',
            'scriptId' => 789,
            'timestamp' => $array['timestamp'], // Dynamic value
        ], $array);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $array['timestamp']);
    }

    #[Test]
    public function getDurationReturnsCorrectDuration(): void
    {
        // Arrange
        $task = new Task();
        $startTime = new \DateTimeImmutable('2024-01-01 10:00:00');
        $task->setStartTime($startTime);

        // Completed task with end time
        $endTime = new \DateTimeImmutable('2024-01-01 10:15:30');
        $task->setEndTime($endTime);
        $completedEvent = new TaskStatusChangedEvent($task, TaskStatus::RUNNING, TaskStatus::COMPLETED);
        $this->assertEquals(930, $completedEvent->getDuration()); // 15 minutes 30 seconds

        // Running task without end time
        $task->setEndTime(null);
        $runningEvent = new TaskStatusChangedEvent($task, TaskStatus::PENDING, TaskStatus::RUNNING);
        $this->assertNull($runningEvent->getDuration());

        // Task without start time
        $task->setStartTime(null);
        $pendingEvent = new TaskStatusChangedEvent($task, TaskStatus::PENDING, TaskStatus::SCHEDULED);
        $this->assertNull($pendingEvent->getDuration());
    }
}
