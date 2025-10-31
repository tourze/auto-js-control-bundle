<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\EventSubscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tourze\AutoJsControlBundle\Entity\AutoJsDevice;
use Tourze\AutoJsControlBundle\Entity\Script;
use Tourze\AutoJsControlBundle\Entity\ScriptExecutionRecord;
use Tourze\AutoJsControlBundle\Entity\Task;
use Tourze\AutoJsControlBundle\Enum\ExecutionStatus;
use Tourze\AutoJsControlBundle\Enum\TaskStatus;
use Tourze\AutoJsControlBundle\Enum\TaskType;
use Tourze\AutoJsControlBundle\Event\ScriptExecutedEvent;
use Tourze\AutoJsControlBundle\Event\TaskCreatedEvent;
use Tourze\AutoJsControlBundle\Event\TaskStatusChangedEvent;
use Tourze\AutoJsControlBundle\EventSubscriber\TaskEventSubscriber;
use Tourze\AutoJsControlBundle\Repository\ScriptExecutionRecordRepository;
use Tourze\AutoJsControlBundle\Service\TaskScheduler;
use Tourze\PHPUnitSymfonyKernelTest\AbstractEventSubscriberTestCase;

/**
 * @internal
 */
#[CoversClass(TaskEventSubscriber::class)]
#[RunTestsInSeparateProcesses]
final class TaskEventSubscriberTest extends AbstractEventSubscriberTestCase
{
    private TaskEventSubscriber $subscriber;

    private TaskScheduler&MockObject $taskScheduler;

    private ScriptExecutionRecordRepository&MockObject $executionRecordRepository;

    private LoggerInterface&MockObject $logger;

    protected function onSetUp(): void
    {
        /*
         * Mock具体类说明：
         * 1. 为什么必须使用具体类而不是接口：TaskScheduler 是业务逻辑服务类，没有定义接口
         * 2. 这种使用是否合理和必要：是的，用于隔离测试，避免真实的任务调度逻辑
         * 3. 是否有更好的替代方案：可以考虑为服务层创建接口，但需要重构整个服务层架构
         */
        // 创建 TaskScheduler 的 mock
        $this->taskScheduler = $this->createMock(TaskScheduler::class);
        /*
         * Mock具体类说明：
         * 1. 为什么必须使用具体类而不是接口：ScriptExecutionRecordRepository 是 Doctrine Repository 的具体实现类，没有独立接口
         * 2. 这种使用是否合理和必要：是的，这是测试 Repository 依赖的标准做法
         * 3. 是否有更好的替代方案：可以考虑创建 Repository 接口，但会增加不必要的复杂性
         */
        $this->executionRecordRepository = $this->createMock(ScriptExecutionRecordRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        // 直接实例化 subscriber 并注入测试用的依赖，避免在RunTestsInSeparateProcesses模式下替换容器服务
        // 参数顺序: TaskScheduler, ScriptExecutionRecordRepository, EntityManagerInterface, LoggerInterface
        // @phpstan-ignore-next-line integrationTest.noDirectInstantiationOfCoveredClass - 使用 PHPUnit Mock 对象，无法通过容器注入
        $this->subscriber = new TaskEventSubscriber(
            $this->taskScheduler,
            $this->executionRecordRepository,
            self::getEntityManager(),
            $this->logger
        );
    }

    #[Test]
    public function testGetSubscribedEventsReturnsCorrectEvents(): void
    {
        // Act
        $events = TaskEventSubscriber::getSubscribedEvents();

        // Assert
        $this->assertArrayHasKey(TaskCreatedEvent::class, $events);
        $this->assertArrayHasKey(TaskStatusChangedEvent::class, $events);
        $this->assertArrayHasKey(ScriptExecutedEvent::class, $events);

        $this->assertEquals('onTaskCreated', $events[TaskCreatedEvent::class]);
        $this->assertEquals('onTaskStatusChanged', $events[TaskStatusChangedEvent::class]);
        $this->assertEquals('onScriptExecuted', $events[ScriptExecutedEvent::class]);
    }

    #[Test]
    public function testOnTaskCreatedSchedulesImmediateTask(): void
    {
        // Arrange
        $task = new Task();
        $task->setType(TaskType::IMMEDIATE);
        $task->setStatus(TaskStatus::PENDING);

        $event = new TaskCreatedEvent($task);

        $this->taskScheduler->expects($this->once())
            ->method('scheduleTask')
            ->with($task)
        ;

        $callCount = 0;
        $this->logger->expects($this->exactly(2))
            ->method('info')
            ->willReturnCallback(function ($message, $context) use (&$callCount): void {
                ++$callCount;
                if (1 === $callCount) {
                    $this->assertEquals('Task created', $message);
                } elseif (2 === $callCount) {
                    $this->assertEquals('Task scheduled for immediate execution', $message);
                }
            })
        ;

        // Act
        $this->subscriber->onTaskCreated($event);
    }

    #[Test]
    public function testOnTaskCreatedDoesNotScheduleScheduledTask(): void
    {
        // Arrange
        $scheduledTime = new \DateTimeImmutable('+1 hour');
        $task = new Task();
        $task->setType(TaskType::SCHEDULED);
        $task->setStatus(TaskStatus::PENDING);
        $task->setScheduledTime($scheduledTime);

        $event = new TaskCreatedEvent($task);

        // 预定的任务不会立即调度
        $this->taskScheduler->expects($this->never())
            ->method('scheduleTask')
        ;

        $this->logger->expects($this->once())
            ->method('info')
            ->with('Task created', self::callback(function ($context) {
                return 'scheduled' === $context['type'];
            }))
        ;

        // Act
        $this->subscriber->onTaskCreated($event);
    }

    #[Test]
    public function testOnTaskCreatedSchedulesRecurringTask(): void
    {
        // Arrange
        $task = new Task();
        $task->setType(TaskType::RECURRING);
        $task->setStatus(TaskStatus::PENDING);
        $task->setCronExpression('0 0 * * *');
        // 不设置 scheduledTime，让它成为 immediate 任务

        $event = new TaskCreatedEvent($task);

        $this->taskScheduler->expects($this->once())
            ->method('scheduleTask')
            ->with($task)
        ;

        $callCount = 0;
        $this->logger->expects($this->exactly(2))
            ->method('info')
            ->willReturnCallback(function ($message, $context) use (&$callCount): void {
                ++$callCount;
                if (1 === $callCount) {
                    $this->assertEquals('Task created', $message);
                    $this->assertEquals('recurring', $context['type']);
                } elseif (2 === $callCount) {
                    $this->assertEquals('Task scheduled for immediate execution', $message);
                }
            })
        ;

        // Act
        $this->subscriber->onTaskCreated($event);
    }

    #[Test]
    public function testOnTaskCreatedDoesNotScheduleNonPendingTask(): void
    {
        // Arrange
        $task = new Task();
        $task->setType(TaskType::IMMEDIATE);
        $task->setStatus(TaskStatus::RUNNING); // Not pending
        $task->setScheduledTime(new \DateTimeImmutable('+1 hour')); // 设置未来时间，确保不会被认为是 immediate

        $event = new TaskCreatedEvent($task);

        $this->taskScheduler->expects($this->never())
            ->method('scheduleTask')
        ;

        // Act
        $this->subscriber->onTaskCreated($event);
    }

    #[Test]
    public function testOnTaskStatusChangedToRunningLogsStart(): void
    {
        // Arrange
        $task = new Task();
        $task->setName('Test Task');
        $reflection = new \ReflectionClass($task);
        $property = $reflection->getProperty('id');
        $property->setAccessible(true);
        $property->setValue($task, 123);

        $event = new TaskStatusChangedEvent($task, TaskStatus::PENDING, TaskStatus::RUNNING);

        // PHPUnit 11 不再支持 withConsecutive，使用 willReturnCallback 替代
        $callCount = 0;
        $this->logger->expects($this->exactly(2))
            ->method('info')
            ->willReturnCallback(function ($message, $context) use (&$callCount): void {
                ++$callCount;
                if (1 === $callCount) {
                    $this->assertEquals('Task status changed', $message);
                } elseif (2 === $callCount) {
                    $this->assertEquals('Task execution started', $message);
                    $this->assertEquals(123, $context['task_id']);
                }
            })
        ;

        // Act
        $this->subscriber->onTaskStatusChanged($event);
    }

    #[Test]
    public function testOnTaskStatusChangedToCompletedLogsCompletion(): void
    {
        // Arrange
        $task = new Task();
        $task->setName('Completed Task');
        $startTime = new \DateTimeImmutable('-5 minutes');
        $endTime = new \DateTimeImmutable();

        // 使用反射设置私有属性
        $reflection = new \ReflectionClass($task);

        $startProperty = $reflection->getProperty('startTime');
        $startProperty->setAccessible(true);
        $startProperty->setValue($task, $startTime);

        $endProperty = $reflection->getProperty('endTime');
        $endProperty->setAccessible(true);
        $endProperty->setValue($task, $endTime);

        $event = new TaskStatusChangedEvent($task, TaskStatus::RUNNING, TaskStatus::COMPLETED);

        // Mock getTaskExecutionStats 方法
        $this->executionRecordRepository->expects($this->once())
            ->method('getTaskExecutionStats')
            ->with($task)
            ->willReturn([
                'total' => 5,
                'successful' => 4,
                'failed' => 1,
            ])
        ;

        // Task status changed + Task execution completed
        $callCount = 0;
        $this->logger->expects($this->exactly(2))
            ->method('info')
            ->willReturnCallback(function ($message, $context) use (&$callCount): void {
                ++$callCount;
                if (1 === $callCount) {
                    $this->assertEquals('Task status changed', $message);
                } elseif (2 === $callCount) {
                    $this->assertEquals('Task execution completed', $message);
                    $this->assertEquals(300, $context['duration']);
                }
            })
        ;

        // Act
        $this->subscriber->onTaskStatusChanged($event);
    }

    #[Test]
    public function testOnTaskStatusChangedToFailedLogsError(): void
    {
        // Arrange
        $task = new Task();
        $task->setName('Failed Task');
        $task->setRetryCount(0);
        $task->setMaxRetries(3); // 允许重试
        // 使用反射设置 ID 以便在日志中识别
        $reflection = new \ReflectionClass($task);
        $property = $reflection->getProperty('id');
        $property->setAccessible(true);
        $property->setValue($task, 456);

        $event = new TaskStatusChangedEvent($task, TaskStatus::RUNNING, TaskStatus::FAILED);

        // Mock scheduleTask to throw exception for retry
        $this->taskScheduler->expects($this->once())
            ->method('scheduleTask')
            ->with($task)
            ->willThrowException(new \Exception('Retry scheduling failed'))
        ;

        // Task status changed + Task execution failed
        $this->logger->expects($this->once())
            ->method('info')
            ->with('Task status changed', self::anything())
        ;

        $errorCallCount = 0;
        $this->logger->expects($this->exactly(2))
            ->method('error')
            ->willReturnCallback(function ($message, $context) use (&$errorCallCount): void {
                ++$errorCallCount;
                if (1 === $errorCallCount) {
                    $this->assertEquals('Task execution failed', $message);
                    $this->assertEquals(456, $context['task_id']);
                } elseif (2 === $errorCallCount) {
                    $this->assertEquals('Failed to schedule task retry', $message);
                }
            })
        ;

        // Act
        $this->subscriber->onTaskStatusChanged($event);
    }

    #[Test]
    public function testOnTaskStatusChangedToCancelledLogsWarning(): void
    {
        // Arrange
        $task = new Task();
        $task->setName('Cancelled Task');
        // 使用反射设置 ID 以便在日志中识别
        $reflection = new \ReflectionClass($task);
        $property = $reflection->getProperty('id');
        $property->setAccessible(true);
        $property->setValue($task, 789);

        $event = new TaskStatusChangedEvent($task, TaskStatus::RUNNING, TaskStatus::CANCELLED);

        // Task status changed + Task cancelled
        $this->logger->expects($this->once())
            ->method('info')
            ->with('Task status changed', self::anything())
        ;

        $this->logger->expects($this->once())
            ->method('warning')
            ->with('Task cancelled', self::callback(function ($context) {
                return 789 === $context['task_id'];
            }))
        ;

        // Act
        $this->subscriber->onTaskStatusChanged($event);
    }

    #[Test]
    public function testOnScriptExecutedUpdatesTaskProgress(): void
    {
        // Arrange
        $task = new Task();
        $reflection = new \ReflectionClass($task);
        $property = $reflection->getProperty('id');
        $property->setAccessible(true);
        $property->setValue($task, 456);

        $executionRecord = new ScriptExecutionRecord();
        $executionRecord->setTask($task);
        $executionRecord->setStatus(ExecutionStatus::SUCCESS);

        // 创建必需的 Script 和 AutoJsDevice 实体
        /*
         * Mock具体类说明：
         * 1. 为什么必须使用具体类而不是接口：Script 是 Doctrine Entity 实体类，没有定义接口
         * 2. 这种使用是否合理和必要：是的，测试 ScriptExecutedEvent 需要 Script 实体作为构造参数
         * 3. 是否有更好的替代方案：可以使用真实的实体对象，但 mock 更轻量且满足测试需求
         */
        $script = $this->createMock(Script::class);
        /*
         * Mock具体类说明：
         * 1. 为什么必须使用具体类而不是接口：AutoJsDevice 是 Doctrine Entity 实体类，没有定义接口
         * 2. 这种使用是否合理和必要：是的，测试 ScriptExecutedEvent 需要 AutoJsDevice 实体作为构造参数
         * 3. 是否有更好的替代方案：可以使用真实的实体对象，但 mock 更轻量且满足测试需求
         */
        $device = $this->createMock(AutoJsDevice::class);

        // 设置 executionRecord 的关联
        $executionRecord->setScript($script);
        $executionRecord->setAutoJsDevice($device);

        $event = new ScriptExecutedEvent($script, $device, $task, $executionRecord, false, ['success' => true]);

        // 设置 mock 期望以返回 device ID
        $device->expects($this->once())
            ->method('getDeviceId')
            ->willReturn(123)
        ;

        // 设置 mock 期望以返回 script 信息
        $script->expects($this->once())
            ->method('getId')
            ->willReturn(789)
        ;
        $script->expects($this->once())
            ->method('getName')
            ->willReturn('Test Script')
        ;

        // ScriptExecutedEvent with isStarted=false 会记录完成日志
        $this->logger->expects($this->once())
            ->method('info')
            ->with('Script execution completed', self::callback(function ($context) {
                return 789 === $context['script_id']
                    && 'Test Script' === $context['script_name']
                    && 123 === $context['device_id']
                    && true === $context['success'];
            }))
        ;

        // Act
        $this->subscriber->onScriptExecuted($event);
    }

    #[Test]
    public function testOnScriptExecutedWithoutTaskDoesNothing(): void
    {
        // Arrange
        $executionRecord = new ScriptExecutionRecord();
        $executionRecord->setTask(null); // No associated task

        // 创建必需的 Script 和 AutoJsDevice 实体
        /*
         * Mock具体类说明：
         * 1. 为什么必须使用具体类而不是接口：Script 是 Doctrine Entity 实体类，没有定义接口
         * 2. 这种使用是否合理和必要：是的，测试 ScriptExecutedEvent 需要 Script 实体作为构造参数
         * 3. 是否有更好的替代方案：可以使用真实的实体对象，但 mock 更轻量且满足测试需求
         */
        $script = $this->createMock(Script::class);
        /*
         * Mock具体类说明：
         * 1. 为什么必须使用具体类而不是接口：AutoJsDevice 是 Doctrine Entity 实体类，没有定义接口
         * 2. 这种使用是否合理和必要：是的，测试 ScriptExecutedEvent 需要 AutoJsDevice 实体作为构造参数
         * 3. 是否有更好的替代方案：可以使用真实的实体对象，但 mock 更轻量且满足测试需求
         */
        $device = $this->createMock(AutoJsDevice::class);

        // 设置 executionRecord 的关联
        $executionRecord->setScript($script);
        $executionRecord->setAutoJsDevice($device);

        $event = new ScriptExecutedEvent($script, $device, null, $executionRecord, false);

        // 设置 mock 期望以返回 device ID
        $device->expects($this->once())
            ->method('getDeviceId')
            ->willReturn(456)
        ;

        // 设置 mock 期望以返回 script 信息
        $script->expects($this->once())
            ->method('getId')
            ->willReturn(999)
        ;
        $script->expects($this->once())
            ->method('getName')
            ->willReturn('Script Without Task')
        ;

        // 即使没有 task，仍然会记录脚本执行完成日志
        $this->logger->expects($this->once())
            ->method('info')
            ->with('Script execution completed', self::callback(function ($context) {
                return 999 === $context['script_id']
                    && 'Script Without Task' === $context['script_name']
                    && 456 === $context['device_id']
                    && null === $context['task_id'];
            }))
        ;

        // Act
        $this->subscriber->onScriptExecuted($event);
    }

    #[Test]
    public function testSchedulingErrorsAreLogged(): void
    {
        // Arrange
        $task = new Task();
        $task->setType(TaskType::IMMEDIATE);
        $task->setStatus(TaskStatus::PENDING);

        $event = new TaskCreatedEvent($task);

        $exception = new \Exception('Scheduler service unavailable');

        $this->taskScheduler->expects($this->once())
            ->method('scheduleTask')
            ->with(self::anything())
            ->willThrowException($exception)
        ;

        $this->logger->expects($this->once())
            ->method('error')
            ->with('Failed to schedule task for immediate execution', self::callback(function ($context) use ($exception) {
                return $context['error'] === $exception->getMessage();
            }))
        ;

        // Act
        $this->subscriber->onTaskCreated($event);
    }
}
