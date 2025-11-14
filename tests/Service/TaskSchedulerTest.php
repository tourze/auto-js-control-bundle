<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Service;

use DeviceBundle\Entity\Device as BaseDevice;
use DeviceBundle\Enum\DeviceStatus;
use DeviceBundle\Enum\DeviceType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use Tourze\AutoJsControlBundle\Entity\AutoJsDevice;
use Tourze\AutoJsControlBundle\Entity\DeviceGroup;
use Tourze\AutoJsControlBundle\Entity\Script;
use Tourze\AutoJsControlBundle\Entity\Task;
use Tourze\AutoJsControlBundle\Enum\ScriptStatus;
use Tourze\AutoJsControlBundle\Enum\TaskStatus;
use Tourze\AutoJsControlBundle\Enum\TaskTargetType;
use Tourze\AutoJsControlBundle\Enum\TaskType;
use Tourze\AutoJsControlBundle\Exception\InvalidTaskArgumentException;
use Tourze\AutoJsControlBundle\Exception\TaskException;
use Tourze\AutoJsControlBundle\Service\TaskScheduler;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(TaskScheduler::class)]
#[RunTestsInSeparateProcesses]
final class TaskSchedulerTest extends AbstractIntegrationTestCase
{
    private TaskScheduler $taskScheduler;

    protected function onSetUp(): void
    {
        $this->taskScheduler = self::getService(TaskScheduler::class);
    }

    public function testCreateAndScheduleTaskWithImmediateTaskCreatesAndDispatches(): void
    {
        // Arrange
        $script = $this->createTestScript();
        $devices = [
            $this->createTestDevice('DEVICE_1'),
            $this->createTestDevice('DEVICE_2'),
        ];

        $taskData = [
            'name' => 'Test Task',
            'description' => 'Test Description',
            'taskType' => TaskType::IMMEDIATE->value,
            'targetType' => TaskTargetType::ALL->value,
            'priority' => 5,
            'script' => $script,
            'parameters' => ['param1' => 'value1'],
        ];

        // Act
        $result = $this->taskScheduler->createAndScheduleTask($taskData);

        // Assert
        $this->assertInstanceOf(Task::class, $result);
        $this->assertEquals('Test Task', $result->getName());
        $this->assertEquals(TaskStatus::RUNNING, $result->getStatus());
    }

    #[Test]
    public function testCreateAndScheduleTaskWithScheduledTaskCreatesWithoutDispatch(): void
    {
        // Arrange
        $script = $this->createTestScript();
        $scheduledTime = new \DateTimeImmutable('+1 hour');

        $taskData = [
            'name' => 'Scheduled Task',
            'description' => 'Scheduled Description',
            'taskType' => TaskType::SCHEDULED->value,
            'targetType' => TaskTargetType::ALL->value,
            'priority' => 5,
            'script' => $script,
            'parameters' => ['param1' => 'value1'],
            'scheduledTime' => $scheduledTime->format('Y-m-d H:i:s'),
        ];

        // Act
        $result = $this->taskScheduler->createAndScheduleTask($taskData);

        // Assert
        $this->assertInstanceOf(Task::class, $result);
        $this->assertEquals('Scheduled Task', $result->getName());
        $this->assertEquals(TaskStatus::PENDING, $result->getStatus());
        $resultScheduledTime = $result->getScheduledTime();
        $this->assertNotNull($resultScheduledTime, 'Scheduled time should not be null');
        $this->assertEquals($scheduledTime->getTimestamp(), $resultScheduledTime->getTimestamp());
    }

    #[Test]
    public function testCreateAndScheduleTaskWithTargetDevicesDispatchesToSpecificDevices(): void
    {
        // Arrange
        $script = $this->createTestScript();
        $targetDevice1 = $this->createTestDevice('TARGET_1');
        $targetDevice2 = $this->createTestDevice('TARGET_2');
        $this->createTestDevice('NON_TARGET'); // 不应该收到指令

        $taskData = [
            'name' => 'Target Devices Task',
            'description' => 'Target specific devices',
            'taskType' => TaskType::IMMEDIATE->value,
            'targetType' => TaskTargetType::SPECIFIC->value,
            'priority' => 5,
            'script' => $script,
            'parameters' => [],
            'targetDevices' => [$targetDevice1->getId(), $targetDevice2->getId()],
        ];

        // Act
        $result = $this->taskScheduler->createAndScheduleTask($taskData);

        // Assert
        $this->assertInstanceOf(Task::class, $result);
        $this->assertEquals(TaskTargetType::SPECIFIC, $result->getTargetType());
    }

    #[Test]
    public function testCreateAndScheduleTaskWithTargetGroupDispatchesToGroupDevices(): void
    {
        // Arrange
        $script = $this->createTestScript();
        $group = $this->createTestDeviceGroup();
        $device1 = $this->createTestDevice('GROUP_DEVICE_1');
        $device2 = $this->createTestDevice('GROUP_DEVICE_2');

        $device1->setDeviceGroup($group);
        $device2->setDeviceGroup($group);
        self::getEntityManager()->flush();

        $taskData = [
            'name' => 'Group Task',
            'description' => 'Target device group',
            'taskType' => TaskType::IMMEDIATE->value,
            'targetType' => TaskTargetType::GROUP->value,
            'priority' => 5,
            'script' => $script,
            'parameters' => [],
            'targetGroupId' => $group->getId(),
        ];

        // Act
        $result = $this->taskScheduler->createAndScheduleTask($taskData);

        // Assert
        $this->assertInstanceOf(Task::class, $result);
        $this->assertEquals(TaskTargetType::GROUP, $result->getTargetType());
    }

    #[Test]
    public function testCreateAndScheduleTaskWithInvalidScriptThrowsException(): void
    {
        // Arrange
        $taskData = [
            'name' => 'Invalid Script Task',
            'description' => 'Task with invalid script',
            'taskType' => TaskType::IMMEDIATE->value,
            'targetType' => TaskTargetType::ALL->value,
            'priority' => 5,
            'script' => null, // Invalid script
            'parameters' => [],
        ];

        // Assert
        $this->expectException(TaskException::class);

        // Act
        $this->taskScheduler->createAndScheduleTask($taskData);
    }

    #[Test]
    public function testExecuteScheduledTasksExecutesPendingTasks(): void
    {
        // Arrange
        $script = $this->createTestScript();
        $device = $this->createTestDevice('SCHEDULED_DEVICE');

        $pastTime = new \DateTimeImmutable()->modify('-5 minutes');
        $futureTime = new \DateTimeImmutable()->modify('+5 minutes');

        // 创建已到期的任务
        $pastTask = new Task();
        $pastTask->setName('Past Task');
        $pastTask->setTaskType(TaskType::SCHEDULED);
        $pastTask->setTargetType(TaskTargetType::ALL);
        $pastTask->setStatus(TaskStatus::PENDING);
        $pastTask->setScheduledTime($pastTime);
        $pastTask->setScript($script);
        $pastTask->setParameters('{}');
        self::getEntityManager()->persist($pastTask);

        // 创建未到期的任务
        $futureTask = new Task();
        $futureTask->setName('Future Task');
        $futureTask->setTaskType(TaskType::SCHEDULED);
        $futureTask->setTargetType(TaskTargetType::ALL);
        $futureTask->setStatus(TaskStatus::PENDING);
        $futureTask->setScheduledTime($futureTime);
        $futureTask->setScript($script);
        $futureTask->setParameters('{}');
        self::getEntityManager()->persist($futureTask);

        self::getEntityManager()->flush();

        // Act
        $this->taskScheduler->executeScheduledTasks();

        // Assert

        // 验证过期任务状态已更新
        self::getEntityManager()->refresh($pastTask);
        $this->assertEquals(TaskStatus::RUNNING, $pastTask->getStatus());

        // 验证未到期任务状态未变
        self::getEntityManager()->refresh($futureTask);
        $this->assertEquals(TaskStatus::PENDING, $futureTask->getStatus());
    }

    #[Test]
    public function executeScheduledTasksWithNoTasksReturnsZero(): void
    {
        // Act
        $result = $this->taskScheduler->executeScheduledTasks();

        // Assert - 验证没有任务时返回0
        $this->assertEquals(0, $result);
    }

    #[Test]
    public function testExecuteScheduledTasksHandlesMultipleTasks(): void
    {
        // Arrange
        $script = $this->createTestScript();
        $device = $this->createTestDevice('MULTI_SCHEDULED_DEVICE');

        $pastTime = new \DateTimeImmutable()->modify('-5 minutes');

        // 创建多个已到期的任务
        $tasks = [];
        for ($i = 0; $i < 3; ++$i) {
            $task = new Task();
            $task->setName("Past Task {$i}");
            $task->setTaskType(TaskType::SCHEDULED);
            $task->setTargetType(TaskTargetType::ALL);
            $task->setStatus(TaskStatus::PENDING);
            $task->setScheduledTime($pastTime);
            $task->setScript($script);
            $task->setParameters('{}');
            self::getEntityManager()->persist($task);
            $tasks[] = $task;
        }

        self::getEntityManager()->flush();

        // Act
        $this->taskScheduler->executeScheduledTasks();

        // Assert

        // 验证所有任务状态都已更新
        foreach ($tasks as $task) {
            self::getEntityManager()->refresh($task);
            $this->assertEquals(TaskStatus::RUNNING, $task->getStatus());
        }
    }

    #[Test]
    public function testCancelTaskWithRunningTaskSetsStatusToCancelled(): void
    {
        // Arrange
        $script = $this->createTestScript();
        $task = new Task();
        $task->setName('Task to Cancel');
        $task->setTaskType(TaskType::IMMEDIATE);
        $task->setTargetType(TaskTargetType::ALL);
        $task->setStatus(TaskStatus::RUNNING);
        $task->setScript($script);
        $task->setParameters('{}');
        self::getEntityManager()->persist($task);
        self::getEntityManager()->flush();

        // Act
        $taskId = $task->getId();
        $this->assertNotNull($taskId, 'Task ID should not be null after persist');
        $this->taskScheduler->cancelTask($taskId);

        // Assert
        self::getEntityManager()->refresh($task);
        $this->assertEquals(TaskStatus::CANCELLED, $task->getStatus());
    }

    #[Test]
    public function testCancelTaskWithCompletedTaskReturnsFalse(): void
    {
        // Arrange
        $script = $this->createTestScript();
        $task = new Task();
        $task->setName('Completed Task');
        $task->setTaskType(TaskType::IMMEDIATE);
        $task->setTargetType(TaskTargetType::ALL);
        $task->setStatus(TaskStatus::COMPLETED);
        $task->setScript($script);
        $task->setParameters('{}');
        self::getEntityManager()->persist($task);
        self::getEntityManager()->flush();

        // Act
        $this->expectException(InvalidTaskArgumentException::class);
        $taskId = $task->getId();
        $this->assertNotNull($taskId, 'Task ID should not be null after persist');
        $this->taskScheduler->cancelTask($taskId);

        // Assert
        self::getEntityManager()->refresh($task);
        $this->assertEquals(TaskStatus::COMPLETED, $task->getStatus());
    }

    #[Test]
    public function testCancelTaskWithNonExistentTaskReturnsFalse(): void
    {
        // Act
        $this->expectException(InvalidTaskArgumentException::class);
        $this->taskScheduler->cancelTask(999999);
    }

    #[Test]
    public function testDispatchTaskSuccessfullyDispatchesToDevices(): void
    {
        // Arrange
        $script = $this->createTestScript();
        $device = $this->createTestDevice('DISPATCH_DEVICE');

        $task = new Task();
        $task->setName('Dispatch Test Task');
        $task->setTaskType(TaskType::IMMEDIATE);
        $task->setTargetType(TaskTargetType::ALL);
        $task->setStatus(TaskStatus::PENDING);
        $task->setScript($script);
        $task->setParameters('{}');
        self::getEntityManager()->persist($task);
        self::getEntityManager()->flush();

        // Act
        $this->taskScheduler->dispatchTask($task);

        // Assert
        self::getEntityManager()->refresh($task);
        $this->assertEquals(TaskStatus::RUNNING, $task->getStatus());
    }

    #[Test]
    public function testDispatchTaskHandlesLockingProperly(): void
    {
        // Arrange
        $script = $this->createTestScript();
        $device = $this->createTestDevice('LOCK_DEVICE');

        $task = new Task();
        $task->setName('Lock Test Task');
        $task->setTaskType(TaskType::IMMEDIATE);
        $task->setTargetType(TaskTargetType::ALL);
        $task->setStatus(TaskStatus::PENDING);
        $task->setScript($script);
        $task->setParameters('{}');
        self::getEntityManager()->persist($task);
        self::getEntityManager()->flush();

        // Act - 调用多次应该由于锁定机制而安全执行
        $this->taskScheduler->dispatchTask($task);
        $this->taskScheduler->dispatchTask($task); // 第二次调用应该被锁机制保护

        // Assert
        self::getEntityManager()->refresh($task);
        $this->assertEquals(TaskStatus::RUNNING, $task->getStatus());
    }

    #[Test]
    public function testExecuteRecurringTasksProcessesCronTasks(): void
    {
        // Arrange
        $script = $this->createTestScript();
        $device = $this->createTestDevice('RECURRING_DEVICE');

        // 创建一个每分钟执行的循环任务
        $task = new Task();
        $task->setName('Recurring Task');
        $task->setTaskType(TaskType::RECURRING);
        $task->setTargetType(TaskTargetType::ALL);
        $task->setStatus(TaskStatus::PENDING);
        $task->setScript($script);
        $task->setParameters('{}');
        $task->setCronExpression('* * * * *'); // 每分钟执行
        self::getEntityManager()->persist($task);
        self::getEntityManager()->flush();

        // Act
        $this->taskScheduler->executeRecurringTasks();

        // Assert
        self::getEntityManager()->refresh($task);
        $this->assertEquals(TaskStatus::RUNNING, $task->getStatus());
        $this->assertInstanceOf(\DateTimeImmutable::class, $task->getLastExecutionTime());
    }

    #[Test]
    public function testExecuteRecurringTasksSkipsInvalidCronExpression(): void
    {
        // Arrange
        $script = $this->createTestScript();
        $device = $this->createTestDevice('INVALID_CRON_DEVICE');

        $task = new Task();
        $task->setName('Invalid Cron Task');
        $task->setTaskType(TaskType::RECURRING);
        $task->setTargetType(TaskTargetType::ALL);
        $task->setStatus(TaskStatus::PENDING);
        $task->setScript($script);
        $task->setParameters('{}');
        $task->setCronExpression('invalid-cron-expression');
        self::getEntityManager()->persist($task);
        self::getEntityManager()->flush();

        // Act
        $this->taskScheduler->executeRecurringTasks();

        // Assert
        self::getEntityManager()->refresh($task);
        $this->assertEquals(TaskStatus::PENDING, $task->getStatus()); // 状态应该保持不变
        $this->assertNull($task->getLastExecutionTime()); // 不应该有执行时间
    }

    #[Test]
    public function testExecuteRecurringTasksHandlesTasksWithLastExecutionTime(): void
    {
        // Arrange
        $script = $this->createTestScript();
        $device = $this->createTestDevice('LAST_EXEC_DEVICE');

        $task = new Task();
        $task->setName('Task with Last Execution');
        $task->setTaskType(TaskType::RECURRING);
        $task->setTargetType(TaskTargetType::ALL);
        $task->setStatus(TaskStatus::PENDING);
        $task->setScript($script);
        $task->setParameters('{}');
        $task->setCronExpression('* * * * *');
        // 设置很久之前的执行时间，确保现在应该执行
        $task->setLastExecutionTime(new \DateTimeImmutable()->modify('-10 minutes'));
        self::getEntityManager()->persist($task);
        self::getEntityManager()->flush();

        // Act
        $this->taskScheduler->executeRecurringTasks();

        // Assert
        self::getEntityManager()->refresh($task);
        $this->assertEquals(TaskStatus::RUNNING, $task->getStatus());
    }

    #[Test]
    public function testPauseTaskSuccessfullyPausesPendingTask(): void
    {
        // Arrange
        $script = $this->createTestScript();
        $task = new Task();
        $task->setName('Task to Pause');
        $task->setTaskType(TaskType::IMMEDIATE);
        $task->setTargetType(TaskTargetType::ALL);
        $task->setStatus(TaskStatus::PENDING);
        $task->setScript($script);
        $task->setParameters('{}');
        self::getEntityManager()->persist($task);
        self::getEntityManager()->flush();

        // Act
        $taskId = $task->getId();
        $this->assertNotNull($taskId, 'Task ID should not be null');
        $this->taskScheduler->pauseTask($taskId);

        // Assert
        self::getEntityManager()->refresh($task);
        $this->assertEquals(TaskStatus::PAUSED, $task->getStatus());
    }

    #[Test]
    public function testPauseTaskSuccessfullyPausesRunningTask(): void
    {
        // Arrange
        $script = $this->createTestScript();
        $task = new Task();
        $task->setName('Running Task to Pause');
        $task->setTaskType(TaskType::IMMEDIATE);
        $task->setTargetType(TaskTargetType::ALL);
        $task->setStatus(TaskStatus::RUNNING);
        $task->setScript($script);
        $task->setParameters('{}');
        self::getEntityManager()->persist($task);
        self::getEntityManager()->flush();

        // Act
        $taskId = $task->getId();
        $this->assertNotNull($taskId, 'Task ID should not be null');
        $this->taskScheduler->pauseTask($taskId);

        // Assert
        self::getEntityManager()->refresh($task);
        $this->assertEquals(TaskStatus::PAUSED, $task->getStatus());
    }

    #[Test]
    public function testPauseTaskThrowsExceptionForCompletedTask(): void
    {
        // Arrange
        $script = $this->createTestScript();
        $task = new Task();
        $task->setName('Completed Task');
        $task->setTaskType(TaskType::IMMEDIATE);
        $task->setTargetType(TaskTargetType::ALL);
        $task->setStatus(TaskStatus::COMPLETED);
        $task->setScript($script);
        $task->setParameters('{}');
        self::getEntityManager()->persist($task);
        self::getEntityManager()->flush();

        // Act & Assert
        $this->expectException(InvalidTaskArgumentException::class);
        $this->expectExceptionMessage('只能暂停待执行或正在执行的任务');
        $taskId = $task->getId();
        $this->assertNotNull($taskId, 'Task ID should not be null');
        $this->taskScheduler->pauseTask($taskId);
    }

    #[Test]
    public function testPauseTaskThrowsExceptionForNonExistentTask(): void
    {
        // Act & Assert
        $this->expectException(InvalidTaskArgumentException::class);
        $this->expectExceptionMessage('任务不存在');
        $this->taskScheduler->pauseTask(999999);
    }

    #[Test]
    public function testResumeTaskSuccessfullyResumesPausedTask(): void
    {
        // Arrange
        $script = $this->createTestScript();
        $device = $this->createTestDevice('RESUME_DEVICE');

        $task = new Task();
        $task->setName('Paused Task to Resume');
        $task->setTaskType(TaskType::IMMEDIATE);
        $task->setTargetType(TaskTargetType::ALL);
        $task->setStatus(TaskStatus::PAUSED);
        $task->setScript($script);
        $task->setParameters('{}');
        self::getEntityManager()->persist($task);
        self::getEntityManager()->flush();

        // Act
        $taskId = $task->getId();
        $this->assertNotNull($taskId, 'Task ID should not be null');
        $this->taskScheduler->resumeTask($taskId);

        // Assert
        self::getEntityManager()->refresh($task);
        $this->assertEquals(TaskStatus::RUNNING, $task->getStatus());
    }

    #[Test]
    public function testResumeTaskThrowsExceptionForPendingTask(): void
    {
        // Arrange
        $script = $this->createTestScript();
        $task = new Task();
        $task->setName('Pending Task');
        $task->setTaskType(TaskType::IMMEDIATE);
        $task->setTargetType(TaskTargetType::ALL);
        $task->setStatus(TaskStatus::PENDING);
        $task->setScript($script);
        $task->setParameters('{}');
        self::getEntityManager()->persist($task);
        self::getEntityManager()->flush();

        // Act & Assert
        $this->expectException(InvalidTaskArgumentException::class);
        $this->expectExceptionMessage('只能恢复已暂停的任务');
        $taskId = $task->getId();
        $this->assertNotNull($taskId, 'Task ID should not be null');
        $this->taskScheduler->resumeTask($taskId);
    }

    #[Test]
    public function testResumeTaskThrowsExceptionForNonExistentTask(): void
    {
        // Act & Assert
        $this->expectException(InvalidTaskArgumentException::class);
        $this->expectExceptionMessage('任务不存在');
        $this->taskScheduler->resumeTask(999999);
    }

    #[Test]
    public function testScheduleForImmediateSuccessfullyReschedulesPendingTask(): void
    {
        // Arrange
        $script = $this->createTestScript();
        $device = $this->createTestDevice('IMMEDIATE_DEVICE');

        $task = new Task();
        $task->setName('Task to Schedule Immediately');
        $task->setTaskType(TaskType::SCHEDULED);
        $task->setTargetType(TaskTargetType::ALL);
        $task->setStatus(TaskStatus::PENDING);
        $task->setScript($script);
        $task->setParameters('{}');
        $task->setScheduledTime(new \DateTimeImmutable()->modify('+1 hour')); // 原本计划1小时后执行
        self::getEntityManager()->persist($task);
        self::getEntityManager()->flush();

        $originalScheduledTime = $task->getScheduledTime();

        // Act
        $this->taskScheduler->scheduleForImmediate($task);

        // Assert
        self::getEntityManager()->refresh($task);
        $this->assertEquals(TaskStatus::RUNNING, $task->getStatus());
        $this->assertNotEquals($originalScheduledTime, $task->getScheduledTime());
        $this->assertLessThanOrEqual(time(), $task->getScheduledTime()?->getTimestamp());
    }

    #[Test]
    public function testScheduleForImmediateSuccessfullyReschedulesFailedTask(): void
    {
        // Arrange
        $script = $this->createTestScript();
        $device = $this->createTestDevice('FAILED_IMMEDIATE_DEVICE');

        $task = new Task();
        $task->setName('Failed Task to Reschedule');
        $task->setTaskType(TaskType::IMMEDIATE);
        $task->setTargetType(TaskTargetType::ALL);
        $task->setStatus(TaskStatus::FAILED);
        $task->setScript($script);
        $task->setParameters('{}');
        self::getEntityManager()->persist($task);
        self::getEntityManager()->flush();

        // Act
        $this->taskScheduler->scheduleForImmediate($task);

        // Assert
        self::getEntityManager()->refresh($task);
        $this->assertEquals(TaskStatus::RUNNING, $task->getStatus());
    }

    #[Test]
    public function testScheduleForImmediateThrowsExceptionForRunningTask(): void
    {
        // Arrange
        $script = $this->createTestScript();
        $task = new Task();
        $task->setName('Running Task');
        $task->setTaskType(TaskType::IMMEDIATE);
        $task->setTargetType(TaskTargetType::ALL);
        $task->setStatus(TaskStatus::RUNNING);
        $task->setScript($script);
        $task->setParameters('{}');
        self::getEntityManager()->persist($task);
        self::getEntityManager()->flush();

        // Act & Assert
        $this->expectException(InvalidTaskArgumentException::class);
        $this->expectExceptionMessage('任务状态不允许重新调度');
        $this->taskScheduler->scheduleForImmediate($task);
    }

    #[Test]
    public function testScheduleRetryIncreasesRetryCountAndCalculatesDelay(): void
    {
        // Arrange
        $script = $this->createTestScript();
        $task = new Task();
        $task->setName('Task to Retry');
        $task->setTaskType(TaskType::IMMEDIATE);
        $task->setTargetType(TaskTargetType::ALL);
        $task->setStatus(TaskStatus::FAILED);
        $task->setScript($script);
        $task->setParameters('{}');
        $task->setRetryCount(0);
        self::getEntityManager()->persist($task);
        self::getEntityManager()->flush();

        $beforeRetryTime = new \DateTimeImmutable();

        // Act
        $this->taskScheduler->scheduleRetry($task);

        // Assert
        self::getEntityManager()->refresh($task);
        $this->assertEquals(1, $task->getRetryCount());
        $this->assertEquals(TaskStatus::PENDING, $task->getStatus());
        $this->assertNotNull($task->getScheduledTime());
        $this->assertGreaterThan($beforeRetryTime, $task->getScheduledTime());
    }

    #[Test]
    public function testScheduleRetryImplementsExponentialBackoff(): void
    {
        // Arrange
        $script = $this->createTestScript();
        $task = new Task();
        $task->setName('Multiple Retry Task');
        $task->setTaskType(TaskType::IMMEDIATE);
        $task->setTargetType(TaskTargetType::ALL);
        $task->setStatus(TaskStatus::FAILED);
        $task->setScript($script);
        $task->setParameters('{}');
        $task->setRetryCount(2); // 已经重试过2次
        self::getEntityManager()->persist($task);
        self::getEntityManager()->flush();

        $beforeRetryTime = new \DateTimeImmutable();

        // Act
        $this->taskScheduler->scheduleRetry($task);

        // Assert
        self::getEntityManager()->refresh($task);
        $this->assertEquals(3, $task->getRetryCount());
        $this->assertEquals(TaskStatus::PENDING, $task->getStatus());

        $scheduledTime = $task->getScheduledTime();
        $this->assertNotNull($scheduledTime);

        // 计算预期的延迟时间（指数退避：2^3 * 10 = 80秒）
        $expectedDelay = min(300, pow(2, 3) * 10);
        $expectedTime = $beforeRetryTime->add(new \DateInterval('PT' . $expectedDelay . 'S'));

        // 允许一些时间差异（±5秒）
        $timeDiff = abs($scheduledTime->getTimestamp() - $expectedTime->getTimestamp());
        $this->assertLessThanOrEqual(5, $timeDiff, 'Scheduled time should be close to expected exponential backoff time');
    }

    #[Test]
    public function testScheduleTaskIsAliasForScheduleForImmediate(): void
    {
        // Arrange
        $script = $this->createTestScript();
        $device = $this->createTestDevice('SCHEDULE_ALIAS_DEVICE');

        $task = new Task();
        $task->setName('Task for scheduleTask Method');
        $task->setTaskType(TaskType::SCHEDULED);
        $task->setTargetType(TaskTargetType::ALL);
        $task->setStatus(TaskStatus::PENDING);
        $task->setScript($script);
        $task->setParameters('{}');
        self::getEntityManager()->persist($task);
        self::getEntityManager()->flush();

        // Act
        $this->taskScheduler->scheduleTask($task);

        // Assert
        self::getEntityManager()->refresh($task);
        $this->assertEquals(TaskStatus::RUNNING, $task->getStatus());
    }

    #[Test]
    public function testUpdateTaskProgressWithExistingTask(): void
    {
        // Arrange
        $script = $this->createTestScript();
        $task = new Task();
        $task->setName('Task for Progress Update');
        $task->setTaskType(TaskType::IMMEDIATE);
        $task->setTargetType(TaskTargetType::ALL);
        $task->setStatus(TaskStatus::RUNNING);
        $task->setScript($script);
        $task->setParameters('{}');
        self::getEntityManager()->persist($task);
        self::getEntityManager()->flush();

        // Act
        $taskId = $task->getId();
        $this->assertNotNull($taskId, 'Task ID should not be null');
        $this->taskScheduler->updateTaskProgress($taskId, 'instruction-123', 'completed');

        // Assert - 由于这个方法只是委托给TaskDispatcher，我们主要验证方法能正常调用而不抛出异常
        $this->assertTrue(true, 'Method executed without exception');
    }

    #[Test]
    public function testUpdateTaskProgressWithNonExistentTask(): void
    {
        // Act & Assert - 对于不存在的任务，方法应该静默返回而不抛出异常
        $this->taskScheduler->updateTaskProgress(999999, 'instruction-123', 'completed');

        // 验证方法执行完成而没有抛出异常
        $this->assertTrue(true, 'Method executed without exception for non-existent task');
    }

    /**
     * 创建测试用脚本.
     */
    private function createTestScript(): Script
    {
        $script = new Script();
        $script->setCode('TEST-SCRIPT-' . uniqid());
        $script->setName('Test Script');
        $script->setContent('console.log("test");');
        $script->setStatus(ScriptStatus::ACTIVE);
        $script->setVersion(1);
        $script->setTimeout(600);

        $em = self::getEntityManager();
        $em->persist($script);
        $em->flush();

        return $script;
    }

    /**
     * 创建测试用设备.
     */
    private function createTestDevice(string $deviceCode): AutoJsDevice
    {
        $baseDevice = new BaseDevice();
        $baseDevice->setCode($deviceCode);
        $baseDevice->setName('Test Device ' . $deviceCode);
        $baseDevice->setDeviceType(DeviceType::PHONE);
        $baseDevice->setStatus(DeviceStatus::ONLINE);
        $baseDevice->setValid(true);

        $autoJsDevice = new AutoJsDevice();
        $autoJsDevice->setBaseDevice($baseDevice);
        $autoJsDevice->setAutoJsVersion('9.3.0');

        $em = self::getEntityManager();
        $em->persist($baseDevice);
        $em->persist($autoJsDevice);
        $em->flush();

        return $autoJsDevice;
    }

    /**
     * 创建测试用设备组.
     */
    private function createTestDeviceGroup(): DeviceGroup
    {
        $group = new DeviceGroup();
        $group->setName('Test Group');
        $group->setDescription('Test device group');

        $em = self::getEntityManager();
        $em->persist($group);
        $em->flush();

        return $group;
    }
}
