<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use Tourze\AutoJsControlBundle\Entity\Task;
use Tourze\AutoJsControlBundle\Event\TaskCreatedEvent;
use Tourze\PHPUnitSymfonyUnitTest\AbstractEventTestCase;

/**
 * @internal
 */
#[CoversClass(TaskCreatedEvent::class)]
final class TaskCreatedEventTest extends AbstractEventTestCase
{
    #[Test]
    public function constructorSetsProperties(): void
    {
        // Arrange
        $task = new Task();
        $task->setName('Test Task');
        $createdBy = 'admin';
        $context = ['source' => 'api', 'priority' => 'high'];

        // Act
        $event = new TaskCreatedEvent($task, $createdBy, $context);

        // Assert
        $this->assertSame($task, $event->getTask());
        $this->assertEquals($createdBy, $event->getCreatedBy());
        $this->assertEquals($context, $event->getContext());
    }

    #[Test]
    public function constructorWithOptionalParameters(): void
    {
        // Arrange
        $task = new Task();

        // Act
        $event = new TaskCreatedEvent($task);

        // Assert
        $this->assertSame($task, $event->getTask());
        $this->assertNull($event->getCreatedBy());
        $this->assertEquals([], $event->getContext());
    }

    #[Test]
    public function getTaskReturnsCorrectTask(): void
    {
        // Arrange
        $task = new Task();
        $task->setName('Test Task');

        $event = new TaskCreatedEvent($task);

        // Act & Assert
        $this->assertSame($task, $event->getTask());
        $this->assertEquals('Test Task', $event->getTask()->getName());
    }

    #[Test]
    public function getCreatedByReturnsCorrectCreator(): void
    {
        // Arrange
        $task = new Task();
        $createdBy = 'system_admin';

        $event = new TaskCreatedEvent($task, $createdBy);

        // Act & Assert
        $this->assertEquals($createdBy, $event->getCreatedBy());
    }

    #[Test]
    public function getContextReturnsCorrectContext(): void
    {
        // Arrange
        $task = new Task();
        $context = [
            'api_version' => '1.0',
            'client_ip' => '192.168.1.1',
            'user_agent' => 'Test Agent',
        ];

        $event = new TaskCreatedEvent($task, null, $context);

        // Act & Assert
        $this->assertEquals($context, $event->getContext());
    }

    #[Test]
    public function isImmediateWithNullScheduledTime(): void
    {
        // Arrange
        $task = new Task();
        // No scheduled time set (null)

        $event = new TaskCreatedEvent($task);

        // Act & Assert
        $this->assertTrue($event->isImmediate());
        $this->assertFalse($event->isScheduled());
    }

    #[Test]
    public function isImmediateWithPastScheduledTime(): void
    {
        // Arrange
        $task = new Task();
        $pastTime = new \DateTimeImmutable('-1 hour');
        $task->setScheduledTime($pastTime);

        $event = new TaskCreatedEvent($task);

        // Act & Assert
        $this->assertTrue($event->isImmediate());
        $this->assertFalse($event->isScheduled());
    }

    #[Test]
    public function isScheduledWithFutureScheduledTime(): void
    {
        // Arrange
        $task = new Task();
        $futureTime = new \DateTimeImmutable('+1 hour');
        $task->setScheduledTime($futureTime);

        $event = new TaskCreatedEvent($task);

        // Act & Assert
        $this->assertFalse($event->isImmediate());
        $this->assertTrue($event->isScheduled());
    }

    #[Test]
    public function isScheduledWithCurrentTime(): void
    {
        // Arrange
        $task = new Task();
        $currentTime = new \DateTimeImmutable();
        $task->setScheduledTime($currentTime);

        $event = new TaskCreatedEvent($task);

        // Act & Assert
        // Current time should be considered immediate
        $this->assertTrue($event->isImmediate());
        $this->assertFalse($event->isScheduled());
    }

    #[Test]
    public function eventWithComplexTaskProperties(): void
    {
        // Arrange
        $task = new Task();
        $task->setName('Complex Automation Task');
        $futureExecution = new \DateTimeImmutable('+2 hours');
        $task->setScheduledTime($futureExecution);

        $context = [
            'automation_level' => 'advanced',
            'retry_count' => 3,
            'timeout' => 300,
        ];

        $event = new TaskCreatedEvent($task, 'automation_system', $context);

        // Act & Assert
        $this->assertEquals('Complex Automation Task', $event->getTask()->getName());
        $this->assertEquals('automation_system', $event->getCreatedBy());
        $this->assertEquals('advanced', $event->getContext()['automation_level']);
        $this->assertTrue($event->isScheduled());
        $this->assertFalse($event->isImmediate());
    }
}
