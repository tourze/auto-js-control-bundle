<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Repository;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use Tourze\AutoJsControlBundle\Entity\Task;
use Tourze\AutoJsControlBundle\Enum\TaskStatus;
use Tourze\AutoJsControlBundle\Enum\TaskType;
use Tourze\AutoJsControlBundle\Repository\TaskRepository;
use Tourze\AutoJsControlBundle\Tests\Fixtures\FixtureFactory;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;

/**
 * 任务Repository查询测试.
 *
 * Linus重构：专注于查询方法测试，消除重复的persist/flush
 *
 * @internal
 */
#[CoversClass(TaskRepository::class)]
#[RunTestsInSeparateProcesses]
final class TaskQueryTest extends AbstractRepositoryTestCase
{
    private TaskRepository $repository;

    protected function onSetUp(): void
    {
        // 开始事务 - Linus式的简单方案
        self::getEntityManager()->getConnection()->beginTransaction();

        $repository = self::getEntityManager()->getRepository(Task::class);
        $this->assertInstanceOf(TaskRepository::class, $repository);
        $this->repository = $repository;
    }

    protected function onTearDown(): void
    {
        // 回滚事务 - 自动清理所有测试数据
        $connection = self::getEntityManager()->getConnection();
        if ($connection->isTransactionActive()) {
            $connection->rollBack();
        }
    }

    /**
     * 批量持久化实体并flush一次
     * 这是Linus式的"好品味" - 一次性操作，消除特殊情况.
     *
     * @param array<object> $entities
     */
    protected function persistEntities(array $entities): void
    {
        $em = self::getEntityManager();

        foreach ($entities as $entity) {
            $em->persist($entity);
        }

        // 只flush一次，不是每个实体都flush
        $em->flush();
    }

    /**
     * 创建测试数据的工厂方法
     * 批量创建避免重复代码
     *
     * @return array<string, array<object>>
     */
    protected function createTestDataSet(): array
    {
        return [
            'devices' => [
                FixtureFactory::createAutoJsDevice(['code' => 'OPT_DEV_001']),
                FixtureFactory::createAutoJsDevice(['code' => 'OPT_DEV_002']),
            ],
            'scripts' => [
                FixtureFactory::createScript(['code' => 'OPT_SCRIPT_001']),
                FixtureFactory::createScript(['code' => 'OPT_SCRIPT_002']),
            ],
        ];
    }

    protected function createNewEntity(): object
    {
        return FixtureFactory::createTask(['name' => 'Test Task ' . uniqid()]);
    }

    protected function getRepository(): TaskRepository
    {
        return $this->repository;
    }

    #[Test]
    public function findPendingTasksReturnsCorrectTasks(): void
    {
        // Arrange - 清空表以确保测试隔离
        $em = self::getEntityManager();
        $em->createQuery('DELETE FROM ' . Task::class)->execute();

        $now = new \DateTime();

        // 立即待执行任务 (优先级20)
        $immediateTask = FixtureFactory::createTask([
            'name' => 'Immediate Task',
            'status' => TaskStatus::PENDING,
            'valid' => true,
            'priority' => 20,
        ]);
        $immediateTask->setTaskType(TaskType::IMMEDIATE);
        $immediateTask->setScheduledTime(null);

        // 计划任务(过去时间，应该包含)
        $scheduledPastTask = FixtureFactory::createTask([
            'name' => 'Scheduled Past Task',
            'status' => TaskStatus::PENDING,
            'valid' => true,
            'priority' => 15,
            'scheduledTime' => new \DateTimeImmutable('-1 hour'),
        ]);
        $scheduledPastTask->setTaskType(TaskType::SCHEDULED);

        // 计划任务(未来时间，不应包含)
        $scheduledFutureTask = FixtureFactory::createTask([
            'name' => 'Scheduled Future Task',
            'status' => TaskStatus::PENDING,
            'valid' => true,
            'priority' => 25,
            'scheduledTime' => new \DateTimeImmutable('+1 hour'),
        ]);
        $scheduledFutureTask->setTaskType(TaskType::SCHEDULED);

        // 运行中任务(不应包含)
        $runningTask = FixtureFactory::createTask([
            'name' => 'Running Task',
            'status' => TaskStatus::RUNNING,
            'valid' => true,
            'priority' => 30,
        ]);

        // 无效任务(不应包含)
        $invalidTask = FixtureFactory::createTask([
            'name' => 'Invalid Task',
            'status' => TaskStatus::PENDING,
            'valid' => false,
            'priority' => 35,
        ]);

        $tasks = [$immediateTask, $scheduledPastTask, $scheduledFutureTask, $runningTask, $invalidTask];

        $allEntities = [];
        foreach ($tasks as $task) {
            $script = $task->getScript();
            $this->assertNotNull($script, 'Task should have a script');
            $allEntities[] = $script;
            $allEntities[] = $task;
        }

        $this->persistEntities($allEntities);

        // Act
        $pendingTasks = $this->repository->findPendingTasks($now);

        // Assert
        $this->assertCount(2, $pendingTasks, '应该只返回2个待执行任务');

        // 验证按优先级排序 (高优先级在前)
        $this->assertEquals($tasks[0]->getId(), $pendingTasks[0]->getId());
        $this->assertEquals($tasks[1]->getId(), $pendingTasks[1]->getId());
    }

    #[Test]
    public function findRunningTasksReturnsOnlyRunningTasks(): void
    {
        // Arrange
        $tasks = [
            FixtureFactory::createTask([
                'name' => 'Running Task 1',
                'status' => TaskStatus::RUNNING,
                'valid' => true,
            ]),
            FixtureFactory::createTask([
                'name' => 'Running Task 2',
                'status' => TaskStatus::RUNNING,
                'valid' => true,
            ]),
            FixtureFactory::createTask([
                'name' => 'Pending Task',
                'status' => TaskStatus::PENDING,
                'valid' => true,
            ]),
            FixtureFactory::createTask([
                'name' => 'Completed Task',
                'status' => TaskStatus::COMPLETED,
                'valid' => true,
            ]),
        ];

        $allEntities = [];
        foreach ($tasks as $task) {
            $script = $task->getScript();
            $this->assertNotNull($script, 'Task should have a script');
            $allEntities[] = $script;
            $allEntities[] = $task;
        }

        $this->persistEntities($allEntities);

        // Act
        $runningTasks = $this->repository->findRunningTasks();

        // Assert
        $this->assertCount(2, $runningTasks, '应该只返回2个运行中的任务');

        foreach ($runningTasks as $task) {
            $this->assertEquals(TaskStatus::RUNNING, $task->getStatus());
        }
    }

    #[Test]
    public function findTasksByStatusReturnsCorrectTasks(): void
    {
        // Arrange - 清空表以确保测试隔离
        $em = self::getEntityManager();
        $em->createQuery('DELETE FROM ' . Task::class)->execute();

        $tasks = [
            FixtureFactory::createTask(['name' => 'Completed Task 1', 'status' => TaskStatus::COMPLETED]),
            FixtureFactory::createTask(['name' => 'Completed Task 2', 'status' => TaskStatus::COMPLETED]),
            FixtureFactory::createTask(['name' => 'Pending Task 1', 'status' => TaskStatus::PENDING]),
        ];

        $allEntities = [];
        foreach ($tasks as $task) {
            $script = $task->getScript();
            $this->assertNotNull($script, 'Task should have a script');
            $allEntities[] = $script;
            $allEntities[] = $task;
        }

        $this->persistEntities($allEntities);

        // Act
        $completedTasks = $this->repository->findBy(['status' => TaskStatus::COMPLETED]);

        // Assert
        $this->assertCount(2, $completedTasks);
        foreach ($completedTasks as $task) {
            $this->assertEquals(TaskStatus::COMPLETED, $task->getStatus());
        }
    }

    #[Test]
    public function testFindPendingTasks(): void
    {
        // Arrange
        $script = FixtureFactory::createScript(['code' => 'QUERY_PENDING_SCRIPT']);
        $now = new \DateTime();

        $pendingTask = FixtureFactory::createTask(['name' => 'Query Pending Task', 'status' => TaskStatus::PENDING]);
        $pendingTask->setScript($script);

        $runningTask = FixtureFactory::createTask(['name' => 'Query Running Task', 'status' => TaskStatus::RUNNING]);
        $runningTask->setScript($script);

        $this->persistEntities([$script, $pendingTask, $runningTask]);

        // Act
        $tasks = $this->repository->findPendingTasks($now);

        // Assert
        $this->assertIsArray($tasks);
        $taskNames = array_map(fn ($t) => $t->getName(), $tasks);
        $this->assertContains('Query Pending Task', $taskNames);
        $this->assertNotContains('Query Running Task', $taskNames);
    }

    #[Test]
    public function testFindRunningTasks(): void
    {
        // Arrange
        $script = FixtureFactory::createScript(['code' => 'QUERY_RUNNING_SCRIPT']);

        $runningTask1 = FixtureFactory::createTask(['name' => 'Query Running Task 1', 'status' => TaskStatus::RUNNING]);
        $runningTask1->setScript($script);

        $runningTask2 = FixtureFactory::createTask(['name' => 'Query Running Task 2', 'status' => TaskStatus::RUNNING]);
        $runningTask2->setScript($script);

        $pendingTask = FixtureFactory::createTask(['name' => 'Query Pending Task', 'status' => TaskStatus::PENDING]);
        $pendingTask->setScript($script);

        $this->persistEntities([$script, $runningTask1, $runningTask2, $pendingTask]);

        // Act
        $tasks = $this->repository->findRunningTasks();

        // Assert
        $this->assertIsArray($tasks);
        $taskNames = array_map(fn ($t) => $t->getName(), $tasks);
        $this->assertContains('Query Running Task 1', $taskNames);
        $this->assertContains('Query Running Task 2', $taskNames);
        $this->assertNotContains('Query Pending Task', $taskNames);
    }

    #[Test]
    public function testCountByStatus(): void
    {
        // Arrange
        $script = FixtureFactory::createScript(['code' => 'QUERY_COUNT_STATUS_SCRIPT']);

        $pendingTask = FixtureFactory::createTask(['name' => 'Query Count Pending', 'status' => TaskStatus::PENDING]);
        $pendingTask->setScript($script);

        $runningTask = FixtureFactory::createTask(['name' => 'Query Count Running', 'status' => TaskStatus::RUNNING]);
        $runningTask->setScript($script);

        $completedTask = FixtureFactory::createTask(['name' => 'Query Count Completed', 'status' => TaskStatus::COMPLETED]);
        $completedTask->setScript($script);

        $this->persistEntities([$script, $pendingTask, $runningTask, $completedTask]);

        // Act
        $counts = $this->repository->countByStatus();

        // Assert
        $this->assertIsArray($counts);
        $this->assertNotEmpty($counts);
    }

    #[Test]
    public function testFindTasksForRetry(): void
    {
        // Arrange
        $script = FixtureFactory::createScript(['code' => 'QUERY_RETRY_SCRIPT']);
        $script->setMaxRetries(3);

        $failedTaskWithRetries = FixtureFactory::createTask(['name' => 'Query Failed Task', 'status' => TaskStatus::FAILED]);
        $failedTaskWithRetries->setScript($script);
        $failedTaskWithRetries->setRetryCount(1);

        $failedTaskNoRetries = FixtureFactory::createTask(['name' => 'Query Failed No Retries', 'status' => TaskStatus::FAILED]);
        $failedTaskNoRetries->setScript($script);
        $failedTaskNoRetries->setRetryCount(3);

        $this->persistEntities([$script, $failedTaskWithRetries, $failedTaskNoRetries]);

        // Act
        $tasks = $this->repository->findTasksForRetry(5);

        // Assert
        $this->assertIsArray($tasks);
        $taskNames = array_map(fn ($t) => $t->getName(), $tasks);
        $this->assertContains('Query Failed Task', $taskNames);
        $this->assertNotContains('Query Failed No Retries', $taskNames);
    }

    #[Test]
    public function testFindPendingTasksForDevice(): void
    {
        // Arrange
        $script = FixtureFactory::createScript(['code' => 'QUERY_DEVICE_SCRIPT']);
        $device = FixtureFactory::createAutoJsDevice(['code' => 'QUERY_DEVICE']);

        $pendingTask = FixtureFactory::createTask(['name' => 'Query Device Pending', 'status' => TaskStatus::PENDING]);
        $pendingTask->setScript($script);
        $pendingTask->addTargetDevice($device);

        $runningTask = FixtureFactory::createTask(['name' => 'Query Device Running', 'status' => TaskStatus::RUNNING]);
        $runningTask->setScript($script);
        $runningTask->addTargetDevice($device);

        $this->persistEntities([$script, $device, $pendingTask, $runningTask]);

        // Act
        $tasks = $this->repository->findPendingTasksForDevice($device);

        // Assert
        $this->assertIsArray($tasks);
        $taskNames = array_map(fn ($t) => $t->getName(), $tasks);
        $this->assertContains('Query Device Pending', $taskNames);
        $this->assertNotContains('Query Device Running', $taskNames);
    }

    #[Test]
    public function testFindRunningTasksForDevice(): void
    {
        // Arrange
        $script = FixtureFactory::createScript(['code' => 'QUERY_DEVICE_RUNNING_SCRIPT']);
        $device = FixtureFactory::createAutoJsDevice(['code' => 'QUERY_DEVICE_RUNNING']);

        $runningTask = FixtureFactory::createTask(['name' => 'Query Device Running', 'status' => TaskStatus::RUNNING]);
        $runningTask->setScript($script);
        $runningTask->addTargetDevice($device);

        $pendingTask = FixtureFactory::createTask(['name' => 'Query Device Pending', 'status' => TaskStatus::PENDING]);
        $pendingTask->setScript($script);
        $pendingTask->addTargetDevice($device);

        $this->persistEntities([$script, $device, $runningTask, $pendingTask]);

        // Act
        $tasks = $this->repository->findRunningTasksForDevice($device);

        // Assert
        $this->assertIsArray($tasks);
        $taskNames = array_map(fn ($t) => $t->getName(), $tasks);
        $this->assertContains('Query Device Running', $taskNames);
        $this->assertNotContains('Query Device Pending', $taskNames);
    }

    #[Test]
    public function testFindScheduledTasksToExecute(): void
    {
        // Arrange
        $script = FixtureFactory::createScript(['code' => 'QUERY_SCHEDULED_SCRIPT']);
        $now = new \DateTime();

        $readyScheduledTask = FixtureFactory::createTask(['name' => 'Query Ready Scheduled', 'status' => TaskStatus::PENDING]);
        $readyScheduledTask->setScript($script);
        $readyScheduledTask->setTaskType(TaskType::SCHEDULED);
        $readyScheduledTask->setScheduledTime(new \DateTimeImmutable('-30 minutes'));

        $futureScheduledTask = FixtureFactory::createTask(['name' => 'Query Future Scheduled', 'status' => TaskStatus::PENDING]);
        $futureScheduledTask->setScript($script);
        $futureScheduledTask->setTaskType(TaskType::SCHEDULED);
        $futureScheduledTask->setScheduledTime(new \DateTimeImmutable('+30 minutes'));

        $this->persistEntities([$script, $readyScheduledTask, $futureScheduledTask]);

        // Act
        $tasks = $this->repository->findScheduledTasksToExecute($now);

        // Assert
        $this->assertIsArray($tasks);
        $taskNames = array_map(fn ($t) => $t->getName(), $tasks);
        $this->assertContains('Query Ready Scheduled', $taskNames);
        $this->assertNotContains('Query Future Scheduled', $taskNames);
    }

    #[Test]
    public function testFindActiveRecurringTasks(): void
    {
        // Arrange
        $script = FixtureFactory::createScript(['code' => 'QUERY_RECURRING_SCRIPT']);

        $pendingRecurring = FixtureFactory::createTask(['name' => 'Query Pending Recurring', 'status' => TaskStatus::PENDING]);
        $pendingRecurring->setScript($script);
        $pendingRecurring->setTaskType(TaskType::RECURRING);

        $runningRecurring = FixtureFactory::createTask(['name' => 'Query Running Recurring', 'status' => TaskStatus::RUNNING]);
        $runningRecurring->setScript($script);
        $runningRecurring->setTaskType(TaskType::RECURRING);

        $completedRecurring = FixtureFactory::createTask(['name' => 'Query Completed Recurring', 'status' => TaskStatus::COMPLETED]);
        $completedRecurring->setScript($script);
        $completedRecurring->setTaskType(TaskType::RECURRING);

        $this->persistEntities([$script, $pendingRecurring, $runningRecurring, $completedRecurring]);

        // Act
        $tasks = $this->repository->findActiveRecurringTasks();

        // Assert
        $this->assertIsArray($tasks);
        $taskNames = array_map(fn ($t) => $t->getName(), $tasks);
        $this->assertContains('Query Pending Recurring', $taskNames);
        $this->assertContains('Query Running Recurring', $taskNames);
        $this->assertNotContains('Query Completed Recurring', $taskNames);
    }

    #[Test]
    public function testCountByType(): void
    {
        // Arrange
        $script = FixtureFactory::createScript(['code' => 'QUERY_COUNT_TYPE_SCRIPT']);

        $immediateTask = FixtureFactory::createTask(['name' => 'Query Count Immediate', 'status' => TaskStatus::PENDING]);
        $immediateTask->setScript($script);
        $immediateTask->setTaskType(TaskType::IMMEDIATE);

        $scheduledTask = FixtureFactory::createTask(['name' => 'Query Count Scheduled', 'status' => TaskStatus::PENDING]);
        $scheduledTask->setScript($script);
        $scheduledTask->setTaskType(TaskType::SCHEDULED);

        $this->persistEntities([$script, $immediateTask, $scheduledTask]);

        // Act
        $counts = $this->repository->countByType();

        // Assert
        $this->assertIsArray($counts);
        $this->assertNotEmpty($counts);
    }

    #[Test]
    public function testCountTodayTasks(): void
    {
        // Arrange
        $script = FixtureFactory::createScript(['code' => 'QUERY_TODAY_SCRIPT']);

        $todayTask = FixtureFactory::createTask(['name' => 'Query Today Task', 'status' => TaskStatus::PENDING]);
        $todayTask->setScript($script);
        $todayTask->setCreateTime(new \DateTimeImmutable());

        $yesterdayTask = FixtureFactory::createTask(['name' => 'Query Yesterday Task', 'status' => TaskStatus::PENDING]);
        $yesterdayTask->setScript($script);
        $yesterdayTask->setCreateTime(new \DateTimeImmutable('-25 hours'));

        $this->persistEntities([$script, $todayTask, $yesterdayTask]);

        // Act
        $count = $this->repository->countTodayTasks();

        // Assert
        $this->assertGreaterThanOrEqual(1, $count);
    }
}
