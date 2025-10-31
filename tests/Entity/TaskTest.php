<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\AutoJsControlBundle\Entity\DeviceGroup;
use Tourze\AutoJsControlBundle\Entity\Script;
use Tourze\AutoJsControlBundle\Entity\ScriptExecutionRecord;
use Tourze\AutoJsControlBundle\Entity\Task;
use Tourze\AutoJsControlBundle\Enum\TaskStatus;
use Tourze\AutoJsControlBundle\Enum\TaskTargetType;
use Tourze\AutoJsControlBundle\Enum\TaskType;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;

/**
 * @internal
 */
#[CoversClass(Task::class)]
final class TaskTest extends AbstractEntityTestCase
{
    private Task $task;

    protected function createEntity(): object
    {
        return new Task();
    }

    protected function setUp(): void
    {
        $this->task = new Task();
    }

    public function testConstructorSetsDefaultValues(): void
    {
        // Assert
        $this->assertNull($this->task->getId());
        $this->assertNull($this->task->getName());
        $this->assertNull($this->task->getDescription());
        $this->assertEquals(TaskType::IMMEDIATE, $this->task->getTaskType());
        $this->assertEquals(TaskTargetType::ALL, $this->task->getTargetType());
        $this->assertEquals(TaskStatus::PENDING, $this->task->getStatus());
        $this->assertEquals(0, $this->task->getPriority());
        $this->assertNull($this->task->getScript());
        $this->assertNull($this->task->getParameters());
        $this->assertNull($this->task->getScheduledTime());
        $this->assertNull($this->task->getCronExpression());
        $this->assertNull($this->task->getNextRunTime());
        $this->assertNull($this->task->getTargetGroup());
        $this->assertNull($this->task->getTargetDeviceIds());
        $this->assertEquals(0, $this->task->getTotalDevices());
        $this->assertEquals(0, $this->task->getCompletedDevices());
        $this->assertEquals(0, $this->task->getFailedDevices());
        $this->assertNull($this->task->getStartTime());
        $this->assertNull($this->task->getEndTime());
        $this->assertNull($this->task->getErrorMessage());
        $this->assertNotNull($this->task->getCreateTime());
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->task->getCreateTime());
        $this->assertNotNull($this->task->getUpdateTime());
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->task->getUpdateTime());
        $this->assertCount(0, $this->task->getExecutionRecords());
    }

    public function testSetNameSetsAndGetsCorrectly(): void
    {
        // Arrange
        $name = 'Test Task Name';

        // Act
        $this->task->setName($name);

        // Assert
        $this->assertEquals($name, $this->task->getName());
    }

    public function testSetDescriptionSetsAndGetsCorrectly(): void
    {
        // Arrange
        $description = 'This is a test task description';

        // Act
        $this->task->setDescription($description);

        // Assert
        $this->assertEquals($description, $this->task->getDescription());
    }

    public function testSetTaskTypeSetsAndGetsCorrectly(): void
    {
        // Act & Assert for each type
        $this->task->setTaskType(TaskType::SCHEDULED);
        $this->assertEquals(TaskType::SCHEDULED, $this->task->getTaskType());

        $this->task->setTaskType(TaskType::RECURRING);
        $this->assertEquals(TaskType::RECURRING, $this->task->getTaskType());

        $this->task->setTaskType(TaskType::IMMEDIATE);
        $this->assertEquals(TaskType::IMMEDIATE, $this->task->getTaskType());
    }

    public function testSetTargetTypeSetsAndGetsCorrectly(): void
    {
        // Act & Assert for each type
        $this->task->setTargetType(TaskTargetType::SPECIFIC);
        $this->assertEquals(TaskTargetType::SPECIFIC, $this->task->getTargetType());

        $this->task->setTargetType(TaskTargetType::GROUP);
        $this->assertEquals(TaskTargetType::GROUP, $this->task->getTargetType());

        $this->task->setTargetType(TaskTargetType::ALL);
        $this->assertEquals(TaskTargetType::ALL, $this->task->getTargetType());
    }

    public function testSetStatusSetsAndGetsCorrectly(): void
    {
        // Act & Assert for each status
        $this->task->setStatus(TaskStatus::RUNNING);
        $this->assertEquals(TaskStatus::RUNNING, $this->task->getStatus());

        $this->task->setStatus(TaskStatus::COMPLETED);
        $this->assertEquals(TaskStatus::COMPLETED, $this->task->getStatus());

        $this->task->setStatus(TaskStatus::FAILED);
        $this->assertEquals(TaskStatus::FAILED, $this->task->getStatus());

        $this->task->setStatus(TaskStatus::CANCELLED);
        $this->assertEquals(TaskStatus::CANCELLED, $this->task->getStatus());
    }

    public function testSetPrioritySetsAndGetsCorrectly(): void
    {
        // Arrange
        $priority = 5;

        // Act
        $this->task->setPriority($priority);

        // Assert
        $this->assertEquals($priority, $this->task->getPriority());
    }

    public function testSetScriptSetsAndGetsCorrectly(): void
    {
        // Arrange
        $script = new Script();
        $script->setCode('ENTITY-TEST-' . uniqid());
        $script->setName('Test Script');

        // Act
        $this->task->setScript($script);

        // Assert
        $this->assertSame($script, $this->task->getScript());
    }

    public function testSetParametersWithStringSetsAndGetsCorrectly(): void
    {
        // Arrange
        $parameters = '{"key": "value", "number": 42}';

        // Act
        $this->task->setParameters($parameters);

        // Assert
        $this->assertEquals($parameters, $this->task->getParameters());
    }

    public function testSetScheduledTimeSetsAndGetsCorrectly(): void
    {
        // Arrange
        $scheduledTime = new \DateTimeImmutable('2024-12-25 10:00:00');

        // Act
        $this->task->setScheduledTime($scheduledTime);

        // Assert
        $this->assertSame($scheduledTime, $this->task->getScheduledTime());
    }

    public function testSetCronExpressionSetsAndGetsCorrectly(): void
    {
        // Arrange
        $cronExpression = '0 */2 * * *';

        // Act
        $this->task->setCronExpression($cronExpression);

        // Assert
        $this->assertEquals($cronExpression, $this->task->getCronExpression());
    }

    public function testSetNextRunTimeSetsAndGetsCorrectly(): void
    {
        // Arrange
        $nextRunTime = new \DateTimeImmutable('2024-12-26 12:00:00');

        // Act
        $this->task->setNextRunTime($nextRunTime);

        // Assert
        $this->assertSame($nextRunTime, $this->task->getNextRunTime());
    }

    public function testSetTargetGroupSetsAndGetsCorrectly(): void
    {
        // Arrange
        $group = new DeviceGroup();
        $group->setName('Test Group');

        // Act
        $this->task->setTargetGroup($group);

        // Assert
        $this->assertSame($group, $this->task->getTargetGroup());
    }

    public function testSetTargetDeviceIdsSetsAndGetsCorrectly(): void
    {
        // Arrange
        $deviceIds = '[1, 2, 3, 4, 5]';

        // Act
        $this->task->setTargetDeviceIds($deviceIds);

        // Assert
        $this->assertEquals($deviceIds, $this->task->getTargetDeviceIds());
    }

    public function testSetTotalDevicesSetsAndGetsCorrectly(): void
    {
        // Arrange
        $total = 10;

        // Act
        $this->task->setTotalDevices($total);

        // Assert
        $this->assertEquals($total, $this->task->getTotalDevices());
    }

    public function testSetCompletedDevicesSetsAndGetsCorrectly(): void
    {
        // Arrange
        $completed = 7;

        // Act
        $this->task->setCompletedDevices($completed);

        // Assert
        $this->assertEquals($completed, $this->task->getCompletedDevices());
    }

    public function testSetFailedDevicesSetsAndGetsCorrectly(): void
    {
        // Arrange
        $failed = 2;

        // Act
        $this->task->setFailedDevices($failed);

        // Assert
        $this->assertEquals($failed, $this->task->getFailedDevices());
    }

    public function testSetStartTimeSetsAndGetsCorrectly(): void
    {
        // Arrange
        $startTime = new \DateTimeImmutable('2024-01-01 09:00:00');

        // Act
        $this->task->setStartTime($startTime);

        // Assert
        $this->assertSame($startTime, $this->task->getStartTime());
    }

    public function testSetEndTimeSetsAndGetsCorrectly(): void
    {
        // Arrange
        $endTime = new \DateTimeImmutable('2024-01-01 10:30:00');

        // Act
        $this->task->setEndTime($endTime);

        // Assert
        $this->assertSame($endTime, $this->task->getEndTime());
    }

    public function testSetErrorMessageSetsAndGetsCorrectly(): void
    {
        // Arrange
        $errorMessage = 'Task failed due to network error';

        // Act
        $this->task->setErrorMessage($errorMessage);

        // Assert
        $this->assertEquals($errorMessage, $this->task->getErrorMessage());
    }

    public function testAddExecutionRecordAddsToCollection(): void
    {
        // Arrange
        $record1 = new ScriptExecutionRecord();
        $record2 = new ScriptExecutionRecord();

        // Act
        $this->task->addExecutionRecord($record1);
        $this->task->addExecutionRecord($record2);

        // Assert
        $this->assertCount(2, $this->task->getExecutionRecords());
        $this->assertTrue($this->task->getExecutionRecords()->contains($record1));
        $this->assertTrue($this->task->getExecutionRecords()->contains($record2));
        $this->assertSame($this->task, $record1->getTask());
        $this->assertSame($this->task, $record2->getTask());
    }

    public function testRemoveExecutionRecordRemovesFromCollection(): void
    {
        // Arrange
        $record1 = new ScriptExecutionRecord();
        $record2 = new ScriptExecutionRecord();

        $this->task->addExecutionRecord($record1);
        $this->task->addExecutionRecord($record2);

        // Act
        $this->task->removeExecutionRecord($record1);

        // Assert
        $this->assertCount(1, $this->task->getExecutionRecords());
        $this->assertFalse($this->task->getExecutionRecords()->contains($record1));
        $this->assertTrue($this->task->getExecutionRecords()->contains($record2));
        $this->assertNull($record1->getTask());
        $this->assertSame($this->task, $record2->getTask());
    }

    public function testAddExecutionRecordPreventsDuplicates(): void
    {
        // Arrange
        $record = new ScriptExecutionRecord();

        // Act
        $this->task->addExecutionRecord($record);
        $this->task->addExecutionRecord($record); // Add same record again

        // Assert
        $this->assertCount(1, $this->task->getExecutionRecords());
    }

    public function testToStringReturnsTaskName(): void
    {
        // Arrange
        $taskName = 'Important Task';
        $this->task->setName($taskName);

        // Assert
        $this->assertEquals('Important Task (pending)', (string) $this->task);
    }

    public function testToStringReturnsEmptyStringWhenNoName(): void
    {
        // Assert
        $this->assertEquals('未命名任务 (pending)', (string) $this->task);
    }

    public function testGetProgressCalculatesCorrectly(): void
    {
        // Test case 1: No devices
        $this->task->setTotalDevices(0);
        $this->assertEquals(0.0, $this->task->getProgress());

        // Test case 2: Some completed devices
        $this->task->setTotalDevices(10);
        $this->task->setCompletedDevices(7);
        $this->assertEquals(70.0, $this->task->getProgress());

        // Test case 3: All devices completed
        $this->task->setTotalDevices(5);
        $this->task->setCompletedDevices(5);
        $this->assertEquals(100.0, $this->task->getProgress());

        // Test case 4: Failed devices also count as progress
        $this->task->setTotalDevices(10);
        $this->task->setCompletedDevices(6);
        $this->task->setFailedDevices(3);
        $this->assertEquals(90.0, $this->task->getProgress());
    }

    public function testGetDurationCalculatesCorrectly(): void
    {
        // Test case 1: Not started
        $this->assertNull($this->task->getDuration());

        // Test case 2: Started but not ended - should return duration to now
        $startTime = new \DateTimeImmutable('2024-01-01 10:00:00');
        $this->task->setStartTime($startTime);
        $duration = $this->task->getDuration();
        $this->assertIsInt($duration);
        $this->assertGreaterThan(0, $duration); // Should be positive duration

        // Test case 3: Started and ended
        $endTime = new \DateTimeImmutable('2024-01-01 10:30:45');
        $this->task->setEndTime($endTime);

        $duration = $this->task->getDuration();
        $this->assertIsInt($duration);
        $this->assertEquals(1845, $duration); // 30 minutes and 45 seconds = 1845 seconds
    }

    public function testIsExpiredForScheduledTasks(): void
    {
        // Test case 1: Not scheduled task
        $this->task->setTaskType(TaskType::IMMEDIATE);
        $this->assertFalse($this->task->isExpired());

        // Test case 2: Scheduled task without scheduled time
        $this->task->setTaskType(TaskType::SCHEDULED);
        $this->assertFalse($this->task->isExpired());

        // Test case 3: Scheduled task with future time
        $futureTime = new \DateTimeImmutable('+1 hour');
        $this->task->setScheduledTime($futureTime);
        $this->assertFalse($this->task->isExpired());

        // Test case 4: Scheduled task with past time and pending status (expired after 1 hour grace period)
        $pastTime = new \DateTimeImmutable('-2 hours'); // More than 1 hour grace period
        $this->task->setTaskType(TaskType::SCHEDULED);
        $this->task->setScheduledTime($pastTime);
        $this->task->setStatus(TaskStatus::PENDING);
        $this->assertTrue($this->task->isExpired());

        // Test case 5: Scheduled task with past time but already running
        $this->task->setStatus(TaskStatus::RUNNING);
        $this->assertFalse($this->task->isExpired());
    }

    /**
     * 提供属性及其样本值的 Data Provider.
     *
     * @return iterable<string, array{string, mixed}>
     */
    public static function propertiesProvider(): iterable
    {
        yield 'name' => ['name', 'Test Task Name'];

        yield 'description' => ['description', 'This is a test task description'];

        yield 'taskType' => ['taskType', TaskType::SCHEDULED];

        yield 'targetType' => ['targetType', TaskTargetType::SPECIFIC];

        yield 'status' => ['status', TaskStatus::RUNNING];

        yield 'priority' => ['priority', 5];

        yield 'parameters' => ['parameters', '{"key": "value", "number": 42}'];

        yield 'scheduledTime' => ['scheduledTime', new \DateTimeImmutable('2024-12-25 10:00:00')];

        yield 'cronExpression' => ['cronExpression', '0 */2 * * *'];

        yield 'targetDeviceIds' => ['targetDeviceIds', '[1, 2, 3, 4, 5]'];

        yield 'totalDevices' => ['totalDevices', 10];

        yield 'successDevices' => ['successDevices', 7];

        yield 'failedDevices' => ['failedDevices', 2];

        yield 'startTime' => ['startTime', new \DateTimeImmutable('2024-01-01 09:00:00')];

        yield 'endTime' => ['endTime', new \DateTimeImmutable('2024-01-01 10:30:00')];

        yield 'retryCount' => ['retryCount', 2];

        yield 'maxRetries' => ['maxRetries', 5];

        yield 'failureReason' => ['failureReason', 'Task failed due to network error'];

        yield 'valid' => ['valid', false];

        yield 'createTime' => ['createTime', new \DateTimeImmutable('2024-01-01 10:00:00')];

        yield 'updateTime' => ['updateTime', new \DateTimeImmutable('2024-01-01 10:00:00')];
    }
}
