<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Repository;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use Tourze\AutoJsControlBundle\Entity\ScriptExecutionRecord;
use Tourze\AutoJsControlBundle\Enum\ExecutionStatus;
use Tourze\AutoJsControlBundle\Repository\ScriptExecutionRecordRepository;
use Tourze\AutoJsControlBundle\Tests\Fixtures\FixtureFactory;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;

/**
 * 脚本执行记录Repository CRUD测试.
 *
 * Linus重构：专注于基础CRUD操作
 *
 * @internal
 */
#[CoversClass(ScriptExecutionRecordRepository::class)]
#[RunTestsInSeparateProcesses]
final class ScriptExecutionRecordCrudTest extends AbstractRepositoryTestCase
{
    private ScriptExecutionRecordRepository $repository;

    protected function onSetUp(): void
    {
        // 开始事务 - Linus式的简单方案
        self::getEntityManager()->getConnection()->beginTransaction();

        $repository = self::getEntityManager()->getRepository(ScriptExecutionRecord::class);
        $this->assertInstanceOf(ScriptExecutionRecordRepository::class, $repository);
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
     * 这是Linus式的"好品味" - 一次性操作，消除特殊情况
     * Linus修复：处理复杂的关联对象持久化.
     */
    /** @param array<object> $entities */
    protected function persistEntities(array $entities): void
    {
        $em = self::getEntityManager();

        foreach ($entities as $entity) {
            if ($entity instanceof ScriptExecutionRecord) {
                $this->persistScriptExecutionRecordDependencies($em, $entity);
            }
            $em->persist($entity);
        }

        $em->flush();
    }

    /**
     * 持久化 ScriptExecutionRecord 的依赖实体
     * Linus式重构：提取方法降低复杂度.
     */
    private function persistScriptExecutionRecordDependencies(EntityManagerInterface $em, ScriptExecutionRecord $entity): void
    {
        $device = $entity->getAutoJsDevice();
        if (null !== $device) {
            $baseDevice = $device->getBaseDevice();
            if (null !== $baseDevice) {
                $em->persist($baseDevice);
            }
            $em->persist($device);
        }

        $script = $entity->getScript();
        if (null !== $script) {
            $em->persist($script);
        }

        $task = $entity->getTask();
        if (null !== $task) {
            $em->persist($task);
        }
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
        // Linus修复：必须创建完整的关联对象，因为数据库字段 nullable: false
        // 但要确保每次调用都是独立的，使用最小的 FixtureFactory 方法
        $entity = FixtureFactory::createMinimalScriptExecutionRecord();

        // 提前持久化依赖对象，避免在 AbstractRepositoryTestCase 中的级联错误
        $this->persistScriptExecutionRecordDependencies(self::getEntityManager(), $entity);

        return $entity;
    }

    protected function getRepository(): ScriptExecutionRecordRepository
    {
        return $this->repository;
    }

    #[Test]
    public function testSaveShouldPersistEntity(): void
    {
        // Arrange
        $record = FixtureFactory::createMinimalScriptExecutionRecord([
            'status' => ExecutionStatus::SUCCESS,
        ]);
        $record->setDuration(777);

        // 先持久化依赖实体
        $autoJsDevice = $record->getAutoJsDevice();
        $this->assertNotNull($autoJsDevice, 'AutoJsDevice should not be null');
        $baseDevice = $autoJsDevice->getBaseDevice();
        $this->assertNotNull($baseDevice, 'BaseDevice should not be null');
        $script = $record->getScript();
        $this->assertNotNull($script, 'Script should not be null');
        $task = $record->getTask();
        $this->assertNotNull($task, 'Task should not be null');

        $this->persistEntities([
            $baseDevice,
            $autoJsDevice,
            $script,
            $task,
        ]);

        // Act
        $this->repository->save($record);

        // Assert
        $this->assertNotNull($record->getId());

        // 验证确实保存了
        $found = $this->repository->find($record->getId());
        $this->assertNotNull($found);
        $this->assertEquals(777, $found->getDuration());
        $this->assertEquals(ExecutionStatus::SUCCESS, $found->getStatus());
    }

    #[Test]
    public function testRemoveShouldDeleteEntity(): void
    {
        // Arrange
        $record = FixtureFactory::createMinimalScriptExecutionRecord([
            'status' => ExecutionStatus::SUCCESS,
        ]);

        $autoJsDevice = $record->getAutoJsDevice();
        $this->assertNotNull($autoJsDevice, 'AutoJsDevice should not be null');
        $baseDevice = $autoJsDevice->getBaseDevice();
        $this->assertNotNull($baseDevice, 'BaseDevice should not be null');
        $script = $record->getScript();
        $this->assertNotNull($script, 'Script should not be null');
        $task = $record->getTask();
        $this->assertNotNull($task, 'Task should not be null');

        $this->persistEntities([
            $baseDevice,
            $autoJsDevice,
            $script,
            $task,
            $record,
        ]);

        $recordId = $record->getId();
        $this->assertNotNull($recordId);

        // Act
        $this->repository->remove($record);

        // Assert
        $found = $this->repository->find($recordId);
        $this->assertNull($found, '实体应该被删除');
    }

    #[Test]
    public function testSaveMethodExists(): void
    {
        // 验证 save 方法可以被调用（不会抛出异常）
        $record = FixtureFactory::createMinimalScriptExecutionRecord();
        $autoJsDevice = $record->getAutoJsDevice();
        $this->assertNotNull($autoJsDevice, 'AutoJsDevice should not be null');
        $baseDevice = $autoJsDevice->getBaseDevice();
        $this->assertNotNull($baseDevice, 'BaseDevice should not be null');
        $script = $record->getScript();
        $this->assertNotNull($script, 'Script should not be null');
        $task = $record->getTask();
        $this->assertNotNull($task, 'Task should not be null');

        $this->persistEntities([
            $baseDevice,
            $autoJsDevice,
            $script,
            $task,
        ]);

        // 这应该不会抛出异常
        $this->repository->save($record);
        $this->assertNotNull($record->getId(), 'save 方法应该为实体分配 ID');

        // 验证 remove 方法也能正常工作
        $recordId = $record->getId();
        $this->repository->remove($record);

        // 确认记录已被删除
        self::getEntityManager()->clear();
        $removedRecord = $this->repository->find($recordId);
        $this->assertNull($removedRecord, 'remove 方法应该删除实体');
    }

    #[Test]
    public function testDeleteOldRecords(): void
    {
        // Arrange
        $oldRecord1 = FixtureFactory::createMinimalScriptExecutionRecord(['status' => ExecutionStatus::SUCCESS]);
        $oldRecord1->setCreateTime(new \DateTimeImmutable('-2 days'));

        $oldRecord2 = FixtureFactory::createMinimalScriptExecutionRecord(['status' => ExecutionStatus::FAILED]);
        $oldRecord2->setCreateTime(new \DateTimeImmutable('-3 days'));

        $newRecord = FixtureFactory::createMinimalScriptExecutionRecord(['status' => ExecutionStatus::SUCCESS]);
        $newRecord->setCreateTime(new \DateTimeImmutable('-12 hours'));

        $autoJsDevice1 = $oldRecord1->getAutoJsDevice();
        $this->assertNotNull($autoJsDevice1);
        $baseDevice1 = $autoJsDevice1->getBaseDevice();
        $this->assertNotNull($baseDevice1);
        $script1 = $oldRecord1->getScript();
        $this->assertNotNull($script1);
        $task1 = $oldRecord1->getTask();
        $this->assertNotNull($task1);

        $autoJsDevice2 = $oldRecord2->getAutoJsDevice();
        $this->assertNotNull($autoJsDevice2);
        $baseDevice2 = $autoJsDevice2->getBaseDevice();
        $this->assertNotNull($baseDevice2);
        $script2 = $oldRecord2->getScript();
        $this->assertNotNull($script2);
        $task2 = $oldRecord2->getTask();
        $this->assertNotNull($task2);

        $autoJsDevice3 = $newRecord->getAutoJsDevice();
        $this->assertNotNull($autoJsDevice3);
        $baseDevice3 = $autoJsDevice3->getBaseDevice();
        $this->assertNotNull($baseDevice3);
        $script3 = $newRecord->getScript();
        $this->assertNotNull($script3);
        $task3 = $newRecord->getTask();
        $this->assertNotNull($task3);

        $this->persistEntities([
            $baseDevice1,
            $autoJsDevice1,
            $script1,
            $task1,
            $oldRecord1,
            $baseDevice2,
            $autoJsDevice2,
            $script2,
            $task2,
            $oldRecord2,
            $baseDevice3,
            $autoJsDevice3,
            $script3,
            $task3,
            $newRecord,
        ]);

        // Act
        $threshold = new \DateTime('-1 day');
        $deletedCount = $this->repository->deleteOldRecords($threshold);

        // Assert
        $this->assertEquals(2, $deletedCount, '应该删除2个旧记录');

        // 验证新记录仍然存在
        self::getEntityManager()->clear();
        $task = $newRecord->getTask();
        $this->assertNotNull($task);
        $taskId = $task->getId();
        $this->assertNotNull($taskId);
        $remainingRecords = $this->repository->findByTask((string) $taskId);
        $this->assertCount(1, $remainingRecords);
    }

    #[Test]
    public function testCancelTaskExecutions(): void
    {
        // Arrange
        $script = FixtureFactory::createScript(['code' => 'CANCEL_SCRIPT']);
        $device = FixtureFactory::createAutoJsDevice(['code' => 'CANCEL_DEVICE']);
        $task = FixtureFactory::createTask(['name' => 'Cancel Test Task']);

        // 创建正在运行的执行记录
        $runningRecord1 = FixtureFactory::createMinimalScriptExecutionRecord([
            'status' => ExecutionStatus::RUNNING,
        ]);
        $runningRecord1->setAutoJsDevice($device);
        $runningRecord1->setScript($script);
        $runningRecord1->setTask($task);

        $runningRecord2 = FixtureFactory::createMinimalScriptExecutionRecord([
            'status' => ExecutionStatus::RUNNING,
        ]);
        $runningRecord2->setAutoJsDevice($device);
        $runningRecord2->setScript($script);
        $runningRecord2->setTask($task);

        // 创建已完成的执行记录（不应该被取消）
        $completedRecord = FixtureFactory::createMinimalScriptExecutionRecord([
            'status' => ExecutionStatus::SUCCESS,
        ]);
        $completedRecord->setAutoJsDevice($device);
        $completedRecord->setScript($script);
        $completedRecord->setTask($task);

        // 创建失败记录（不应该被取消）
        $failedRecord = FixtureFactory::createMinimalScriptExecutionRecord([
            'status' => ExecutionStatus::FAILED,
        ]);
        $failedRecord->setAutoJsDevice($device);
        $failedRecord->setScript($script);
        $failedRecord->setTask($task);

        $this->persistEntities([
            $script,
            $device,
            $task,
            $runningRecord1,
            $runningRecord2,
            $completedRecord,
            $failedRecord,
        ]);

        // Act
        $this->repository->cancelTaskExecutions($task);

        // Assert
        self::getEntityManager()->clear();

        // 验证正在运行的记录已被取消
        $cancelledRunning1 = $this->repository->find($runningRecord1->getId());
        $this->assertNotNull($cancelledRunning1);
        $this->assertEquals(ExecutionStatus::CANCELLED, $cancelledRunning1->getStatus());
        $this->assertNotNull($cancelledRunning1->getEndTime());

        $cancelledRunning2 = $this->repository->find($runningRecord2->getId());
        $this->assertNotNull($cancelledRunning2);
        $this->assertEquals(ExecutionStatus::CANCELLED, $cancelledRunning2->getStatus());
        $this->assertNotNull($cancelledRunning2->getEndTime());

        // 验证已完成的记录状态未改变
        $unchangedCompleted = $this->repository->find($completedRecord->getId());
        $this->assertNotNull($unchangedCompleted);
        $this->assertEquals(ExecutionStatus::SUCCESS, $unchangedCompleted->getStatus());

        $unchangedFailed = $this->repository->find($failedRecord->getId());
        $this->assertNotNull($unchangedFailed);
        $this->assertEquals(ExecutionStatus::FAILED, $unchangedFailed->getStatus());
    }

    #[Test]
    public function testCountByStatus(): void
    {
        // Arrange
        $script = FixtureFactory::createScript(['code' => 'COUNT_STATUS_SCRIPT']);
        $device = FixtureFactory::createAutoJsDevice(['code' => 'COUNT_STATUS_DEVICE']);
        $task = FixtureFactory::createTask(['name' => 'Count Status Task']);

        $startDate = new \DateTimeImmutable('-2 days');
        $endDate = new \DateTimeImmutable();

        // 在日期范围内创建不同状态的记录
        $successRecord = FixtureFactory::createMinimalScriptExecutionRecord([
            'status' => ExecutionStatus::SUCCESS,
        ]);
        $successRecord->setAutoJsDevice($device);
        $successRecord->setScript($script);
        $successRecord->setTask($task);
        $successRecord->setCreateTime($startDate->modify('+1 day'));

        $failedRecord = FixtureFactory::createMinimalScriptExecutionRecord([
            'status' => ExecutionStatus::FAILED,
        ]);
        $failedRecord->setAutoJsDevice($device);
        $failedRecord->setScript($script);
        $failedRecord->setTask($task);
        $failedRecord->setCreateTime($startDate->modify('+2 hours'));

        $runningRecord = FixtureFactory::createMinimalScriptExecutionRecord([
            'status' => ExecutionStatus::RUNNING,
        ]);
        $runningRecord->setAutoJsDevice($device);
        $runningRecord->setScript($script);
        $runningRecord->setTask($task);
        $runningRecord->setCreateTime($endDate->modify('-1 hour'));

        // 在日期范围外创建记录（不应该被计数）
        $outOfRangeRecord = FixtureFactory::createMinimalScriptExecutionRecord([
            'status' => ExecutionStatus::SUCCESS,
        ]);
        $outOfRangeRecord->setAutoJsDevice($device);
        $outOfRangeRecord->setScript($script);
        $outOfRangeRecord->setTask($task);
        $outOfRangeRecord->setCreateTime(new \DateTimeImmutable('-5 days'));

        $this->persistEntities([
            $script,
            $device,
            $task,
            $successRecord,
            $failedRecord,
            $runningRecord,
            $outOfRangeRecord,
        ]);

        // Act
        $counts = $this->repository->countByStatus($startDate, $endDate);

        // Assert
        $this->assertIsArray($counts);
        $statusCounts = [];
        foreach ($counts as $count) {
            $status = $count['status'];
            $statusKey = $status instanceof ExecutionStatus ? $status->value : (string) $status;
            $statusCounts[$statusKey] = (int) $count['count'];
        }

        $this->assertEquals(1, $statusCounts[ExecutionStatus::SUCCESS->value] ?? 0);
        $this->assertEquals(1, $statusCounts[ExecutionStatus::FAILED->value] ?? 0);
        $this->assertEquals(1, $statusCounts[ExecutionStatus::RUNNING->value] ?? 0);
    }

    #[Test]
    public function testCountByStatusWithoutDateRange(): void
    {
        // Arrange
        $script = FixtureFactory::createScript(['code' => 'COUNT_ALL_SCRIPT']);
        $device = FixtureFactory::createAutoJsDevice(['code' => 'COUNT_ALL_DEVICE']);
        $task = FixtureFactory::createTask(['name' => 'Count All Task']);

        $successRecord = FixtureFactory::createMinimalScriptExecutionRecord([
            'status' => ExecutionStatus::SUCCESS,
        ]);
        $successRecord->setAutoJsDevice($device);
        $successRecord->setScript($script);
        $successRecord->setTask($task);

        $failedRecord = FixtureFactory::createMinimalScriptExecutionRecord([
            'status' => ExecutionStatus::FAILED,
        ]);
        $failedRecord->setAutoJsDevice($device);
        $failedRecord->setScript($script);
        $failedRecord->setTask($task);

        $this->persistEntities([
            $script,
            $device,
            $task,
            $successRecord,
            $failedRecord,
        ]);

        // Act
        $counts = $this->repository->countByStatus();

        // Assert
        $this->assertIsArray($counts);
        $statusCounts = [];
        foreach ($counts as $count) {
            $status = $count['status'];
            $statusKey = $status instanceof ExecutionStatus ? $status->value : (string) $status;
            $statusCounts[$statusKey] = (int) $count['count'];
        }

        $this->assertGreaterThanOrEqual(1, $statusCounts[ExecutionStatus::SUCCESS->value] ?? 0);
        $this->assertGreaterThanOrEqual(1, $statusCounts[ExecutionStatus::FAILED->value] ?? 0);
    }

    #[Test]
    public function testFindByAutoJsDevice(): void
    {
        // Arrange
        $script = FixtureFactory::createScript(['code' => 'CRUD_FIND_SCRIPT']);
        $device = FixtureFactory::createAutoJsDevice(['code' => 'CRUD_FIND_DEVICE']);
        $task = FixtureFactory::createTask(['name' => 'Crud Find Task']);

        $record1 = FixtureFactory::createMinimalScriptExecutionRecord([
            'status' => ExecutionStatus::SUCCESS,
        ]);
        $record1->setAutoJsDevice($device);
        $record1->setScript($script);
        $record1->setTask($task);

        $record2 = FixtureFactory::createMinimalScriptExecutionRecord([
            'status' => ExecutionStatus::FAILED,
        ]);
        $record2->setAutoJsDevice($device);
        $record2->setScript($script);
        $record2->setTask($task);

        $this->persistEntities([
            $script,
            $device,
            $task,
            $record1,
            $record2,
        ]);

        // Act
        $deviceId = $device->getId();
        $this->assertNotNull($deviceId);
        $records = $this->repository->findByAutoJsDevice((string) $deviceId, 10);

        // Assert
        $this->assertIsArray($records);
        $this->assertGreaterThanOrEqual(2, count($records));
        foreach ($records as $record) {
            $autoJsDevice = $record->getAutoJsDevice();
            $this->assertNotNull($autoJsDevice);
            $this->assertEquals($device->getId(), $autoJsDevice->getId());
        }
    }

    #[Test]
    public function testFindByTask(): void
    {
        // Arrange
        $script = FixtureFactory::createScript(['code' => 'CRUD_FIND_TASK_SCRIPT']);
        $device = FixtureFactory::createAutoJsDevice(['code' => 'CRUD_FIND_TASK_DEVICE']);
        $task = FixtureFactory::createTask(['name' => 'Crud Find Task Task']);

        $record1 = FixtureFactory::createMinimalScriptExecutionRecord([
            'status' => ExecutionStatus::SUCCESS,
        ]);
        $record1->setAutoJsDevice($device);
        $record1->setScript($script);
        $record1->setTask($task);

        $record2 = FixtureFactory::createMinimalScriptExecutionRecord([
            'status' => ExecutionStatus::RUNNING,
        ]);
        $record2->setAutoJsDevice($device);
        $record2->setScript($script);
        $record2->setTask($task);

        $this->persistEntities([
            $script,
            $device,
            $task,
            $record1,
            $record2,
        ]);

        // Act
        $taskId = $task->getId();
        $this->assertNotNull($taskId);
        $records = $this->repository->findByTask((string) $taskId);

        // Assert
        $this->assertIsArray($records);
        $this->assertGreaterThanOrEqual(2, count($records));
        foreach ($records as $record) {
            $recordTask = $record->getTask();
            $this->assertNotNull($recordTask);
            $this->assertEquals($task->getId(), $recordTask->getId());
        }
    }
}
