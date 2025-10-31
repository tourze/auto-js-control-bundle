<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Repository;

use DeviceBundle\Entity\Device as BaseDevice;
use DeviceBundle\Enum\DeviceType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use Tourze\AutoJsControlBundle\Entity\AutoJsDevice;
use Tourze\AutoJsControlBundle\Entity\Script;
use Tourze\AutoJsControlBundle\Entity\Task;
use Tourze\AutoJsControlBundle\Enum\ScriptType;
use Tourze\AutoJsControlBundle\Enum\TaskStatus;
use Tourze\AutoJsControlBundle\Enum\TaskType;
use Tourze\AutoJsControlBundle\Repository\TaskRepository;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;

/**
 * @internal
 */
#[CoversClass(TaskRepository::class)]
#[RunTestsInSeparateProcesses]
final class TaskRepositoryTest extends AbstractRepositoryTestCase
{
    private TaskRepository $repository;

    protected function onSetUp(): void
    {
        $repository = self::getEntityManager()->getRepository(Task::class);
        $this->assertInstanceOf(TaskRepository::class, $repository);
        $this->repository = $repository;
    }

    #[Test]
    public function testFindPendingTasks(): void
    {
        // Arrange
        $script = $this->createScript('TASK_SCRIPT_001');
        $em = self::getEntityManager();
        $em->persist($script);

        $now = new \DateTime();

        // Immediate pending task
        $immediateTask = $this->createTask($script, 'immediate', TaskStatus::PENDING, true, 20);

        // Scheduled task (past time - should be included)
        $scheduledTaskPast = $this->createTask($script, 'scheduled', TaskStatus::PENDING, true, 15);
        $scheduledTaskPast->setScheduledTime(new \DateTimeImmutable('-1 hour'));

        // Scheduled task (future time - should not be included)
        $scheduledTaskFuture = $this->createTask($script, 'scheduled', TaskStatus::PENDING, true, 25);
        $scheduledTaskFuture->setScheduledTime(new \DateTimeImmutable('+1 hour'));

        // Running task (should not be included)
        $runningTask = $this->createTask($script, 'immediate', TaskStatus::RUNNING, true, 30);

        // Invalid task (should not be included)
        $invalidTask = $this->createTask($script, 'immediate', TaskStatus::PENDING, false, 35);

        $em->persist($immediateTask);
        $em->persist($scheduledTaskPast);
        $em->persist($scheduledTaskFuture);
        $em->persist($runningTask);
        $em->persist($invalidTask);
        $em->flush();

        // Act
        $tasks = $this->repository->findPendingTasks($now);

        // Assert - Filter to only our test tasks
        $testTaskIds = [
            $immediateTask->getId(),
            $scheduledTaskPast->getId(),
        ];
        $matchingTasks = array_filter($tasks, fn ($t) => in_array($t->getId(), $testTaskIds, true));
        $matchingTasks = array_values($matchingTasks);

        $this->assertGreaterThanOrEqual(2, count($tasks)); // Total may include other pending tasks
        $this->assertCount(2, $matchingTasks); // But our test tasks should be exactly 2

        $taskIds = array_map(fn ($t) => $t->getId(), $matchingTasks);
        $this->assertContains($immediateTask->getId(), $taskIds);
        $this->assertContains($scheduledTaskPast->getId(), $taskIds);

        // Check order (priority DESC, createTime ASC) - among our test tasks
        $this->assertEquals($immediateTask->getId(), $matchingTasks[0]->getId()); // Priority 20
        $this->assertEquals($scheduledTaskPast->getId(), $matchingTasks[1]->getId()); // Priority 15
    }

    #[Test]
    public function testFindRunningTasks(): void
    {
        // Arrange
        $script = $this->createScript('TASK_SCRIPT_002');
        $em = self::getEntityManager();
        $em->persist($script);

        $runningTask1 = $this->createTask($script, 'immediate', TaskStatus::RUNNING, true);
        $runningTask1->setStartTime(new \DateTimeImmutable('-5 minutes'));

        $runningTask2 = $this->createTask($script, 'scheduled', TaskStatus::RUNNING, true);
        $runningTask2->setStartTime(new \DateTimeImmutable('-2 minutes'));

        $pendingTask = $this->createTask($script, 'immediate', TaskStatus::PENDING, true);
        $completedTask = $this->createTask($script, 'immediate', TaskStatus::COMPLETED, true);

        $em->persist($runningTask1);
        $em->persist($runningTask2);
        $em->persist($pendingTask);
        $em->persist($completedTask);
        $em->flush();

        // Act
        $tasks = $this->repository->findRunningTasks();

        // Assert
        $this->assertCount(2, $tasks);
        $taskIds = array_map(fn ($t) => $t->getId(), $tasks);
        $this->assertContains($runningTask1->getId(), $taskIds);
        $this->assertContains($runningTask2->getId(), $taskIds);

        // Check order (startTime DESC)
        $this->assertEquals($runningTask2->getId(), $tasks[0]->getId()); // Started more recently
        $this->assertEquals($runningTask1->getId(), $tasks[1]->getId());
    }

    #[Test]
    public function testCountByStatus(): void
    {
        // Arrange
        $script = $this->createScript('TASK_SCRIPT_003');
        $em = self::getEntityManager();
        $em->persist($script);

        // Create tasks with different statuses
        for ($i = 0; $i < 3; ++$i) {
            $task = $this->createTask($script, 'immediate', TaskStatus::PENDING, true);
            $em->persist($task);
        }

        for ($i = 0; $i < 2; ++$i) {
            $task = $this->createTask($script, 'immediate', TaskStatus::RUNNING, true);
            $em->persist($task);
        }

        $completedTask = $this->createTask($script, 'immediate', TaskStatus::COMPLETED, true);
        $failedTask = $this->createTask($script, 'immediate', TaskStatus::FAILED, true);

        // Invalid task should not be counted
        $invalidTask = $this->createTask($script, 'immediate', TaskStatus::PENDING, false);

        $em->persist($completedTask);
        $em->persist($failedTask);
        $em->persist($invalidTask);
        $em->flush();

        // Act
        $counts = $this->repository->countByStatus();

        // Assert
        $statusCounts = [];
        foreach ($counts as $count) {
            $status = $count['status'];
            $key = $status instanceof TaskStatus ? $status->value : $status;
            $statusCounts[$key] = (int) $count['count'];
        }

        $this->assertGreaterThanOrEqual(3, $statusCounts[TaskStatus::PENDING->value] ?? 0);
        $this->assertGreaterThanOrEqual(2, $statusCounts[TaskStatus::RUNNING->value] ?? 0);
        $this->assertGreaterThanOrEqual(1, $statusCounts[TaskStatus::COMPLETED->value] ?? 0);
        $this->assertGreaterThanOrEqual(1, $statusCounts[TaskStatus::FAILED->value] ?? 0);
    }

    #[Test]
    public function testFindTasksForRetry(): void
    {
        // Arrange
        $script1 = $this->createScript('RETRY_SCRIPT_001');
        $script1->setMaxRetries(3);

        $script2 = $this->createScript('RETRY_SCRIPT_002');
        $script2->setMaxRetries(1);

        $em = self::getEntityManager();
        $em->persist($script1);
        $em->persist($script2);

        // Failed task with retries remaining
        $retryableTask = $this->createTask($script1, 'immediate', TaskStatus::FAILED, true);
        $retryableTask->setRetryCount(1); // Less than script max (3) and global max

        // Failed task at script retry limit
        $atScriptLimitTask = $this->createTask($script2, 'immediate', TaskStatus::FAILED, true);
        $atScriptLimitTask->setRetryCount(1); // Equal to script max (1)

        // Failed task at global retry limit
        $atGlobalLimitTask = $this->createTask($script1, 'immediate', TaskStatus::FAILED, true);
        $atGlobalLimitTask->setRetryCount(5); // Equal to global max

        // Non-failed task
        $runningTask = $this->createTask($script1, 'immediate', TaskStatus::RUNNING, true);
        $runningTask->setRetryCount(0);

        $em->persist($retryableTask);
        $em->persist($atScriptLimitTask);
        $em->persist($atGlobalLimitTask);
        $em->persist($runningTask);
        $em->flush();

        // Act
        $tasks = $this->repository->findTasksForRetry(5);

        // Assert
        $this->assertCount(1, $tasks);
        $this->assertEquals($retryableTask->getId(), $tasks[0]->getId());
    }

    #[Test]
    public function testFindPendingTasksForDevice(): void
    {
        // Arrange
        $device1 = $this->createAutoJsDevice('TASK_DEVICE_001');
        $device2 = $this->createAutoJsDevice('TASK_DEVICE_002');
        $script = $this->createScript('TASK_SCRIPT_004');

        $em = self::getEntityManager();
        $em->persist($device1);
        $em->persist($device2);
        $em->persist($script);

        // Task for device1
        $task1 = $this->createTask($script, 'immediate', TaskStatus::PENDING, true);
        $task1->addTargetDevice($device1);

        // Task for both devices
        $task2 = $this->createTask($script, 'immediate', TaskStatus::PENDING, true);
        $task2->addTargetDevice($device1);
        $task2->addTargetDevice($device2);

        // Task for device2 only
        $task3 = $this->createTask($script, 'immediate', TaskStatus::PENDING, true);
        $task3->addTargetDevice($device2);

        // Running task for device1 (should not be included)
        $task4 = $this->createTask($script, 'immediate', TaskStatus::RUNNING, true);
        $task4->addTargetDevice($device1);

        $em->persist($task1);
        $em->persist($task2);
        $em->persist($task3);
        $em->persist($task4);
        $em->flush();

        // Act
        $tasks = $this->repository->findPendingTasksForDevice($device1);

        // Assert
        $this->assertCount(2, $tasks);
        $taskIds = array_map(fn ($t) => $t->getId(), $tasks);
        $this->assertContains($task1->getId(), $taskIds);
        $this->assertContains($task2->getId(), $taskIds);
        $this->assertNotContains($task3->getId(), $taskIds);
        $this->assertNotContains($task4->getId(), $taskIds);
    }

    #[Test]
    public function testFindRunningTasksForDevice(): void
    {
        // Arrange
        $device = $this->createAutoJsDevice('TASK_DEVICE_003');
        $script = $this->createScript('TASK_SCRIPT_005');

        $em = self::getEntityManager();
        $em->persist($device);
        $em->persist($script);

        // Running task for device
        $runningTask = $this->createTask($script, 'immediate', TaskStatus::RUNNING, true);
        $runningTask->addTargetDevice($device);

        // Pending task for device (should not be included)
        $pendingTask = $this->createTask($script, 'immediate', TaskStatus::PENDING, true);
        $pendingTask->addTargetDevice($device);

        $em->persist($runningTask);
        $em->persist($pendingTask);
        $em->flush();

        // Act
        $tasks = $this->repository->findRunningTasksForDevice($device);

        // Assert
        $this->assertCount(1, $tasks);
        $this->assertEquals($runningTask->getId(), $tasks[0]->getId());
    }

    #[Test]
    public function testFindScheduledTasksToExecute(): void
    {
        // Arrange
        $script = $this->createScript('TASK_SCRIPT_006');
        $em = self::getEntityManager();
        $em->persist($script);

        $now = new \DateTime();

        // Scheduled task ready to execute
        $readyTask = $this->createTask($script, 'scheduled', TaskStatus::PENDING, true, 20);
        $readyTask->setScheduledTime(new \DateTimeImmutable('-30 minutes'));

        // Scheduled task not yet ready
        $futureTask = $this->createTask($script, 'scheduled', TaskStatus::PENDING, true, 25);
        $futureTask->setScheduledTime(new \DateTimeImmutable('+30 minutes'));

        // Immediate task (should not be included)
        $immediateTask = $this->createTask($script, 'immediate', TaskStatus::PENDING, true);

        // Invalid scheduled task (should not be included)
        $invalidTask = $this->createTask($script, 'scheduled', TaskStatus::PENDING, false);
        $invalidTask->setScheduledTime(new \DateTimeImmutable('-10 minutes'));

        $em->persist($readyTask);
        $em->persist($futureTask);
        $em->persist($immediateTask);
        $em->persist($invalidTask);
        $em->flush();

        // Act
        $tasks = $this->repository->findScheduledTasksToExecute($now);

        // Assert
        $this->assertCount(1, $tasks);
        $this->assertEquals($readyTask->getId(), $tasks[0]->getId());
    }

    #[Test]
    public function testFindActiveRecurringTasks(): void
    {
        // Arrange
        $script = $this->createScript('TASK_SCRIPT_007');
        $em = self::getEntityManager();
        $em->persist($script);

        // Active recurring tasks
        $pendingRecurring = $this->createTask($script, 'recurring', TaskStatus::PENDING, true);
        $runningRecurring = $this->createTask($script, 'recurring', TaskStatus::RUNNING, true);

        // Completed recurring task (should not be included)
        $completedRecurring = $this->createTask($script, 'recurring', TaskStatus::COMPLETED, true);

        // Invalid recurring task (should not be included)
        $invalidRecurring = $this->createTask($script, 'recurring', TaskStatus::PENDING, false);

        // Non-recurring task (should not be included)
        $immediateTask = $this->createTask($script, 'immediate', TaskStatus::PENDING, true);

        $em->persist($pendingRecurring);
        $em->persist($runningRecurring);
        $em->persist($completedRecurring);
        $em->persist($invalidRecurring);
        $em->persist($immediateTask);
        $em->flush();

        // Act
        $tasks = $this->repository->findActiveRecurringTasks();

        // Assert
        $this->assertCount(2, $tasks);
        $taskIds = array_map(fn ($t) => $t->getId(), $tasks);
        $this->assertContains($pendingRecurring->getId(), $taskIds);
        $this->assertContains($runningRecurring->getId(), $taskIds);
    }

    #[Test]
    public function testCountByType(): void
    {
        // Arrange
        $script = $this->createScript('TASK_SCRIPT_008');
        $em = self::getEntityManager();
        $em->persist($script);

        // Create tasks with different types
        for ($i = 0; $i < 3; ++$i) {
            $task = $this->createTask($script, 'immediate', TaskStatus::PENDING, true);
            $em->persist($task);
        }

        for ($i = 0; $i < 2; ++$i) {
            $task = $this->createTask($script, 'scheduled', TaskStatus::PENDING, true);
            $em->persist($task);
        }

        $recurringTask = $this->createTask($script, 'recurring', TaskStatus::PENDING, true);

        // Invalid task should not be counted
        $invalidTask = $this->createTask($script, 'immediate', TaskStatus::PENDING, false);

        $em->persist($recurringTask);
        $em->persist($invalidTask);
        $em->flush();

        // Act
        $counts = $this->repository->countByType();

        // Assert
        $typeCounts = [];
        foreach ($counts as $count) {
            $taskType = $count['taskType'];
            $key = $taskType instanceof TaskType ? $taskType->value : $taskType;
            $typeCounts[$key] = (int) $count['count'];
        }

        $this->assertGreaterThanOrEqual(3, $typeCounts['immediate'] ?? 0);
        $this->assertGreaterThanOrEqual(2, $typeCounts['scheduled'] ?? 0);
        $this->assertGreaterThanOrEqual(1, $typeCounts['recurring'] ?? 0);
    }

    #[Test]
    public function testCountTodayTasks(): void
    {
        // Arrange
        $script = $this->createScript('TASK_SCRIPT_009');
        $em = self::getEntityManager();
        $em->persist($script);

        // Today's tasks
        $todayTask1 = $this->createTask($script, 'immediate', TaskStatus::PENDING, true);
        $todayTask1->setCreateTime(new \DateTimeImmutable());

        $todayTask2 = $this->createTask($script, 'scheduled', TaskStatus::RUNNING, true);
        $todayTask2->setCreateTime(new \DateTimeImmutable('-2 hours'));

        // Yesterday's task
        $yesterdayTask = $this->createTask($script, 'immediate', TaskStatus::PENDING, true);
        $yesterdayTask->setCreateTime(new \DateTimeImmutable('-25 hours'));

        // Invalid today's task (should not be counted)
        $invalidTask = $this->createTask($script, 'immediate', TaskStatus::PENDING, false);
        $invalidTask->setCreateTime(new \DateTimeImmutable());

        $em->persist($todayTask1);
        $em->persist($todayTask2);
        $em->persist($yesterdayTask);
        $em->persist($invalidTask);
        $em->flush();

        // Act
        $count = $this->repository->countTodayTasks();

        // Assert
        $this->assertGreaterThanOrEqual(2, $count);
    }

    #[Test]
    public function findReturnsEntityById(): void
    {
        // Arrange
        $script = $this->createScript('FIND_TASK_SCRIPT');
        $task = $this->createTask($script, 'immediate', TaskStatus::PENDING, true);

        $em = self::getEntityManager();
        $em->persist($script);
        $em->persist($task);
        $em->flush();

        $taskId = $task->getId();
        $this->assertNotNull($taskId);

        // Act
        $found = $this->repository->find($taskId);

        // Assert
        $this->assertNotNull($found);
        $this->assertEquals($taskId, $found->getId());
        $this->assertEquals($task->getName(), $found->getName());
    }

    #[Test]
    public function findReturnsNullForNonExistentId(): void
    {
        // Act
        $found = $this->repository->find(99999);

        // Assert
        $this->assertNull($found);
    }

    #[Test]
    public function findAllReturnsAllEntities(): void
    {
        // Arrange
        $script = $this->createScript('ALL_TASK_SCRIPT');
        $task1 = $this->createTask($script, 'immediate', TaskStatus::PENDING, true);
        $task2 = $this->createTask($script, 'scheduled', TaskStatus::RUNNING, true);
        $task3 = $this->createTask($script, 'recurring', TaskStatus::COMPLETED, false);

        $em = self::getEntityManager();
        $em->persist($script);
        $em->persist($task1);
        $em->persist($task2);
        $em->persist($task3);
        $em->flush();

        // Act
        $tasks = $this->repository->findAll();

        // Assert
        $this->assertGreaterThanOrEqual(3, count($tasks));
        $taskIds = array_map(fn ($t) => $t->getId(), $tasks);
        $this->assertContains($task1->getId(), $taskIds);
        $this->assertContains($task2->getId(), $taskIds);
        $this->assertContains($task3->getId(), $taskIds);
    }

    #[Test]
    public function findByReturnsMatchingEntities(): void
    {
        // Arrange
        $script = $this->createScript('BY_TASK_SCRIPT');
        $task1 = $this->createTask($script, 'immediate', TaskStatus::PENDING, true, 10);
        $task2 = $this->createTask($script, 'immediate', TaskStatus::RUNNING, true, 20);
        $task3 = $this->createTask($script, 'scheduled', TaskStatus::PENDING, false, 10);

        $em = self::getEntityManager();
        $em->persist($script);
        $em->persist($task1);
        $em->persist($task2);
        $em->persist($task3);
        $em->flush();

        // Act
        $pendingTasks = $this->repository->findBy(['status' => TaskStatus::PENDING]);
        $validTasks = $this->repository->findBy(['valid' => true]);
        $priorityTasks = $this->repository->findBy(['priority' => 10]);

        // Assert
        $this->assertGreaterThanOrEqual(2, count($pendingTasks));
        foreach ($pendingTasks as $task) {
            $this->assertEquals(TaskStatus::PENDING, $task->getStatus());
        }

        $this->assertGreaterThanOrEqual(2, count($validTasks));
        foreach ($validTasks as $task) {
            $this->assertTrue($task->isValid());
        }

        $this->assertGreaterThanOrEqual(2, count($priorityTasks));
        foreach ($priorityTasks as $task) {
            $this->assertEquals(10, $task->getPriority());
        }
    }

    #[Test]
    public function findByWithOrderByWorks(): void
    {
        // Arrange
        $script = $this->createScript('ORDER_TASK_SCRIPT');
        $task1 = $this->createTask($script, 'immediate', TaskStatus::PENDING, true, 30);
        $task2 = $this->createTask($script, 'immediate', TaskStatus::PENDING, true, 10);
        $task3 = $this->createTask($script, 'immediate', TaskStatus::PENDING, true, 20);

        $em = self::getEntityManager();
        $em->persist($script);
        $em->persist($task1);
        $em->persist($task2);
        $em->persist($task3);
        $em->flush();

        // Act
        $tasks = $this->repository->findBy(['status' => TaskStatus::PENDING], ['priority' => 'DESC']);

        // Assert
        $this->assertGreaterThanOrEqual(3, count($tasks));
        // Find our test tasks in the results
        $testTasks = array_filter($tasks, function ($t) {
            $script = $t->getScript();
            $this->assertNotNull($script, 'Script should not be null');
            $scriptCode = $script->getCode();

            return 'ORDER_TASK_SCRIPT' === $scriptCode;
        });
        $testTasks = array_values($testTasks);

        $this->assertCount(3, $testTasks);
        $this->assertEquals(30, $testTasks[0]->getPriority()); // Highest priority first
        $this->assertEquals(20, $testTasks[1]->getPriority());
        $this->assertEquals(10, $testTasks[2]->getPriority()); // Lowest priority last
    }

    #[Test]
    public function findByWithLimitWorks(): void
    {
        // Arrange
        $script = $this->createScript('LIMIT_TASK_SCRIPT');
        $task1 = $this->createTask($script, 'immediate', TaskStatus::PENDING, true);
        $task2 = $this->createTask($script, 'immediate', TaskStatus::PENDING, true);
        $task3 = $this->createTask($script, 'immediate', TaskStatus::PENDING, true);

        $em = self::getEntityManager();
        $em->persist($script);
        $em->persist($task1);
        $em->persist($task2);
        $em->persist($task3);
        $em->flush();

        // Act
        $tasks = $this->repository->findBy(['status' => TaskStatus::PENDING], null, 2);

        // Assert
        $this->assertGreaterThanOrEqual(2, count($tasks));
        $this->assertLessThanOrEqual(2, count($tasks));
    }

    #[Test]
    public function findOneByReturnsFirstMatch(): void
    {
        // Arrange
        $script = $this->createScript('ONE_TASK_SCRIPT');
        $task1 = $this->createTask($script, 'immediate', TaskStatus::PENDING, true, 15);
        $task2 = $this->createTask($script, 'immediate', TaskStatus::PENDING, true, 15);

        $em = self::getEntityManager();
        $em->persist($script);
        $em->persist($task1);
        $em->persist($task2);
        $em->flush();

        // Act
        $found = $this->repository->findOneBy(['priority' => 15]);

        // Assert
        $this->assertNotNull($found);
        $this->assertEquals(15, $found->getPriority());
    }

    #[Test]
    public function findOneByReturnsNullWhenNoMatch(): void
    {
        // Arrange
        $script = $this->createScript('ONE_TASK_SCRIPT_NULL');
        $task = $this->createTask($script, 'immediate', TaskStatus::PENDING, true, 10);

        $em = self::getEntityManager();
        $em->persist($script);
        $em->persist($task);
        $em->flush();

        // Act
        $found = $this->repository->findOneBy(['priority' => 999]);

        // Assert
        $this->assertNull($found);
    }

    #[Test]
    public function countReturnsCorrectCount(): void
    {
        // Arrange
        $initialCount = $this->repository->count([]);

        $script = $this->createScript('COUNT_TASK_SCRIPT');
        $task1 = $this->createTask($script, 'immediate', TaskStatus::PENDING, true);
        $task2 = $this->createTask($script, 'immediate', TaskStatus::RUNNING, false);

        $em = self::getEntityManager();
        $em->persist($script);
        $em->persist($task1);
        $em->persist($task2);
        $em->flush();

        // Act
        $totalCount = $this->repository->count([]);
        $pendingCount = $this->repository->count(['status' => TaskStatus::PENDING]);
        $validCount = $this->repository->count(['valid' => true]);
        $invalidCount = $this->repository->count(['valid' => false]);

        // Assert
        $this->assertEquals($initialCount + 2, $totalCount);
        $this->assertGreaterThanOrEqual(1, $pendingCount);
        $this->assertGreaterThanOrEqual(1, $validCount);
        $this->assertGreaterThanOrEqual(1, $invalidCount);
    }

    #[Test]
    public function saveWorksCorrectly(): void
    {
        // Arrange
        $script = $this->createScript('SAVE_TASK_SCRIPT');
        $task = $this->createTask($script, 'immediate', TaskStatus::PENDING, true);

        $em = self::getEntityManager();
        $em->persist($script);

        // Act
        $this->repository->save($task);

        // Assert - Verify task is persisted with correct data
        $found = $this->repository->find($task->getId());
        $this->assertNotNull($found);
        $this->assertEquals($task->getName(), $found->getName());
    }

    #[Test]
    public function saveWithoutFlushDoesNotPersist(): void
    {
        // Arrange
        $script = $this->createScript('SAVE_TASK_SCRIPT_NO_FLUSH');
        $task = $this->createTask($script, 'immediate', TaskStatus::PENDING, true);

        $em = self::getEntityManager();
        $em->persist($script);

        // Act
        $this->repository->save($task, false);

        // Clear entity manager to force database query
        $em->clear();

        // Assert
        $found = $this->repository->find($task->getId());
        $this->assertNull($found);
    }

    #[Test]
    public function removeWorksCorrectly(): void
    {
        // Arrange
        $script = $this->createScript('REMOVE_TASK_SCRIPT');
        $task = $this->createTask($script, 'immediate', TaskStatus::PENDING, true);

        $em = self::getEntityManager();
        $em->persist($script);
        $em->persist($task);
        $em->flush();

        $taskId = $task->getId();
        $this->assertNotNull($taskId);

        // Act
        $this->repository->remove($task);

        // Assert
        $found = $this->repository->find($taskId);
        $this->assertNull($found);
    }

    #[Test]
    public function removeWithoutFlushDoesNotRemove(): void
    {
        // Arrange
        $script = $this->createScript('REMOVE_TASK_SCRIPT_NO_FLUSH');
        $task = $this->createTask($script, 'immediate', TaskStatus::PENDING, true);

        $em = self::getEntityManager();
        $em->persist($script);
        $em->persist($task);
        $em->flush();

        $taskId = $task->getId();
        $this->assertNotNull($taskId);

        // Act
        $this->repository->remove($task, false);

        // Clear entity manager to force database query
        $em->clear();

        // Assert
        $found = $this->repository->find($taskId);
        $this->assertNotNull($found);
    }

    #[Test]
    public function findByWithNullValuesWorks(): void
    {
        // Arrange
        $script = $this->createScript('NULL_TASK_SCRIPT');
        $task = $this->createTask($script, 'immediate', TaskStatus::PENDING, true);
        $task->setStartTime(null);

        $em = self::getEntityManager();
        $em->persist($script);
        $em->persist($task);
        $em->flush();

        // Act
        $tasks = $this->repository->findBy(['startTime' => null]);

        // Assert
        $this->assertGreaterThanOrEqual(1, count($tasks));
        $taskIds = array_map(fn ($t) => $t->getId(), $tasks);
        $this->assertContains($task->getId(), $taskIds);
    }

    #[Test]
    public function testFindOneByAssociationScriptShouldReturnMatchingEntity(): void
    {
        // Arrange
        $script1 = $this->createScript('ASSOC_SCRIPT_1');
        $script2 = $this->createScript('ASSOC_SCRIPT_2');
        $task1 = $this->createTask($script1, 'immediate', TaskStatus::PENDING, true);
        $task2 = $this->createTask($script2, 'immediate', TaskStatus::PENDING, true);

        $em = self::getEntityManager();
        $em->persist($script1);
        $em->persist($script2);
        $em->persist($task1);
        $em->persist($task2);
        $em->flush();

        // Act
        $found = $this->repository->findOneBy(['script' => $script1]);

        // Assert
        $this->assertNotNull($found);
        $script = $found->getScript();
        $this->assertNotNull($script, 'Script should not be null');
        $this->assertEquals($script1->getId(), $script->getId());
    }

    #[Test]
    public function testCountByAssociationScriptShouldReturnCorrectNumber(): void
    {
        // Arrange
        $script = $this->createScript('COUNT_ASSOC_SCRIPT');
        $task1 = $this->createTask($script, 'immediate', TaskStatus::PENDING, true);
        $task2 = $this->createTask($script, 'scheduled', TaskStatus::RUNNING, true);
        $task3 = $this->createTask($script, 'recurring', TaskStatus::COMPLETED, true);

        $otherScript = $this->createScript('OTHER_COUNT_SCRIPT');
        $otherTask = $this->createTask($otherScript, 'immediate', TaskStatus::PENDING, true);

        $em = self::getEntityManager();
        $em->persist($script);
        $em->persist($otherScript);
        $em->persist($task1);
        $em->persist($task2);
        $em->persist($task3);
        $em->persist($otherTask);
        $em->flush();

        // Act
        $count = $this->repository->count(['script' => $script]);

        // Assert
        $this->assertEquals(3, $count);
    }

    private function createAutoJsDevice(string $code): AutoJsDevice
    {
        $baseDevice = new BaseDevice();
        $baseDevice->setCode($code);
        $baseDevice->setName('Device ' . $code);
        $baseDevice->setModel('TestModel-' . $code);
        $baseDevice->setDeviceType(DeviceType::PHONE);

        $autoJsDevice = new AutoJsDevice();
        $autoJsDevice->setBaseDevice($baseDevice);
        $autoJsDevice->setAutoJsVersion('4.1.1');

        self::getEntityManager()->persist($baseDevice);

        return $autoJsDevice;
    }

    private function createScript(string $code): Script
    {
        $script = new Script();
        $script->setCode($code);
        $script->setName('Script ' . $code);
        $script->setDescription('Test script');
        $script->setContent('// Test script content');
        $script->setScriptType(ScriptType::AUTO_JS);
        $script->setValid(true);
        $script->setPriority(10);
        $script->setMaxRetries(3);
        $script->setTimeout(300);

        return $script;
    }

    private function createTask(
        Script $script,
        string $taskType,
        TaskStatus $status,
        bool $valid,
        int $priority = 10,
    ): Task {
        $task = new Task();
        $task->setName('Test Task ' . uniqid());
        $task->setScript($script);
        $task->setTaskType(TaskType::from($taskType));
        $task->setStatus($status);
        $task->setValid($valid);
        $task->setPriority($priority);
        $task->setRetryCount(0);
        $task->setCreateTime(new \DateTimeImmutable());

        return $task;
    }

    protected function createNewEntity(): object
    {
        $script = new Script();
        $script->setCode('TEST-SCRIPT-' . uniqid());
        $script->setName('Test Script');
        $script->setDescription('Test script description');
        $script->setContent('// Test script content');
        $script->setScriptType(ScriptType::AUTO_JS);
        $script->setValid(true);
        $script->setPriority(10);
        $script->setMaxRetries(3);
        $script->setTimeout(300);

        $task = new Task();
        $task->setScript($script);
        $task->setName('Test Task ' . uniqid());
        $task->setTaskType(TaskType::IMMEDIATE);
        $task->setStatus(TaskStatus::PENDING);
        $task->setValid(true);
        $task->setPriority(10);
        $task->setRetryCount(0);
        $task->setCreateTime(new \DateTimeImmutable());

        return $task;
    }

    /**
     * @return ServiceEntityRepository<Task>
     */
    protected function getRepository(): ServiceEntityRepository
    {
        return $this->repository;
    }
}
