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
 * 脚本执行记录Repository统计测试.
 *
 * Linus重构：专注于统计方法测试
 *
 * @internal
 */
#[CoversClass(ScriptExecutionRecordRepository::class)]
#[RunTestsInSeparateProcesses]
final class ScriptExecutionRecordStatsTest extends AbstractRepositoryTestCase
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
     *
     * @param array<object> $entities
     */
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
    public function testCountByStatus(): void
    {
        // Arrange - 创建不同状态的记录
        $dataSet = FixtureFactory::createCompleteDataSet(['recordCount' => 6]);

        // 手动设置状态分布：3个成功，2个失败，1个运行中
        $dataSet['records'][0]->setStatus(ExecutionStatus::SUCCESS);
        $dataSet['records'][1]->setStatus(ExecutionStatus::SUCCESS);
        $dataSet['records'][2]->setStatus(ExecutionStatus::SUCCESS);
        $dataSet['records'][3]->setStatus(ExecutionStatus::FAILED);
        $dataSet['records'][4]->setStatus(ExecutionStatus::FAILED);
        $dataSet['records'][5]->setStatus(ExecutionStatus::RUNNING);

        $this->persistEntities($dataSet['all']);

        // Act
        $counts = $this->repository->countByStatus();

        // Assert
        $this->assertIsArray($counts);
        $statusCounts = [];
        foreach ($counts as $count) {
            $status = $count['status'];
            $key = $status instanceof ExecutionStatus ? $status->value : $status;
            $statusCounts[$key] = (int) $count['count'];
        }

        $this->assertGreaterThanOrEqual(3, $statusCounts[ExecutionStatus::SUCCESS->value] ?? 0);
        $this->assertGreaterThanOrEqual(2, $statusCounts[ExecutionStatus::FAILED->value] ?? 0);
        $this->assertGreaterThanOrEqual(1, $statusCounts[ExecutionStatus::RUNNING->value] ?? 0);
    }

    #[Test]
    public function countByStatusWithDateRangeReturnsCorrectCounts(): void
    {
        // Arrange
        $record1 = FixtureFactory::createMinimalScriptExecutionRecord(['status' => ExecutionStatus::SUCCESS]);
        $record1->setCreateTime(new \DateTimeImmutable('-12 hours'));

        $record2 = FixtureFactory::createMinimalScriptExecutionRecord(['status' => ExecutionStatus::SUCCESS]);
        $record2->setCreateTime(new \DateTimeImmutable('-2 days'));

        $autoJsDevice1 = $record1->getAutoJsDevice();
        $this->assertNotNull($autoJsDevice1);
        $baseDevice1 = $autoJsDevice1->getBaseDevice();
        $this->assertNotNull($baseDevice1);
        $script1 = $record1->getScript();
        $this->assertNotNull($script1);
        $task1 = $record1->getTask();
        $this->assertNotNull($task1);

        $autoJsDevice2 = $record2->getAutoJsDevice();
        $this->assertNotNull($autoJsDevice2);
        $baseDevice2 = $autoJsDevice2->getBaseDevice();
        $this->assertNotNull($baseDevice2);
        $script2 = $record2->getScript();
        $this->assertNotNull($script2);
        $task2 = $record2->getTask();
        $this->assertNotNull($task2);

        $this->persistEntities([
            $baseDevice1,
            $autoJsDevice1,
            $script1,
            $task1,
            $record1,
            $baseDevice2,
            $autoJsDevice2,
            $script2,
            $task2,
            $record2,
        ]);

        // Act
        $startDate = new \DateTime('-1 day');
        $endDate = new \DateTime();
        $counts = $this->repository->countByStatus($startDate, $endDate);

        // Assert
        $totalCount = 0;
        foreach ($counts as $count) {
            $totalCount += (int) $count['count'];
        }

        $this->assertGreaterThanOrEqual(1, $totalCount, '应该只统计日期范围内的记录');
    }

    #[Test]
    public function getTaskExecutionStatsReturnsCorrectStats(): void
    {
        // Arrange
        $dataSet = FixtureFactory::createCompleteDataSet(['taskCount' => 1, 'recordCount' => 10]);
        $task = $dataSet['tasks'][0];

        // 设置状态分布：5个成功，3个失败，2个运行中
        for ($i = 0; $i < 5; ++$i) {
            $dataSet['records'][$i]->setStatus(ExecutionStatus::SUCCESS);
        }
        for ($i = 5; $i < 8; ++$i) {
            $dataSet['records'][$i]->setStatus(ExecutionStatus::FAILED);
        }
        for ($i = 8; $i < 10; ++$i) {
            $dataSet['records'][$i]->setStatus(ExecutionStatus::RUNNING);
        }

        $this->persistEntities($dataSet['all']);

        // Act
        $stats = $this->repository->getTaskExecutionStats($task);

        // Assert
        $this->assertIsArray($stats);
        $this->assertEquals(10, $stats['total']);
        $this->assertEquals(5, $stats['successful']);
        $this->assertEquals(3, $stats['failed']);
        $this->assertEquals(2, $stats['running']);
    }

    #[Test]
    public function testCountByNullFieldShouldReturnCorrectCount(): void
    {
        // Arrange
        $record1 = FixtureFactory::createMinimalScriptExecutionRecord(['status' => ExecutionStatus::FAILED]);
        $record1->setErrorMessage('Some error');

        $record2 = FixtureFactory::createMinimalScriptExecutionRecord(['status' => ExecutionStatus::SUCCESS]);
        $record2->setErrorMessage(null);

        $record3 = FixtureFactory::createMinimalScriptExecutionRecord(['status' => ExecutionStatus::SUCCESS]);
        $record3->setErrorMessage(null);

        $autoJsDevice1 = $record1->getAutoJsDevice();
        $this->assertNotNull($autoJsDevice1);
        $baseDevice1 = $autoJsDevice1->getBaseDevice();
        $this->assertNotNull($baseDevice1);
        $script1 = $record1->getScript();
        $this->assertNotNull($script1);
        $task1 = $record1->getTask();
        $this->assertNotNull($task1);

        $autoJsDevice2 = $record2->getAutoJsDevice();
        $this->assertNotNull($autoJsDevice2);
        $baseDevice2 = $autoJsDevice2->getBaseDevice();
        $this->assertNotNull($baseDevice2);
        $script2 = $record2->getScript();
        $this->assertNotNull($script2);
        $task2 = $record2->getTask();
        $this->assertNotNull($task2);

        $autoJsDevice3 = $record3->getAutoJsDevice();
        $this->assertNotNull($autoJsDevice3);
        $baseDevice3 = $autoJsDevice3->getBaseDevice();
        $this->assertNotNull($baseDevice3);
        $script3 = $record3->getScript();
        $this->assertNotNull($script3);
        $task3 = $record3->getTask();
        $this->assertNotNull($task3);

        $this->persistEntities([
            $baseDevice1,
            $autoJsDevice1,
            $script1,
            $task1,
            $record1,
            $baseDevice2,
            $autoJsDevice2,
            $script2,
            $task2,
            $record2,
            $baseDevice3,
            $autoJsDevice3,
            $script3,
            $task3,
            $record3,
        ]);

        // Act
        $countWithoutError = $this->repository->count(['errorMessage' => null]);

        // Assert
        $this->assertGreaterThanOrEqual(2, $countWithoutError);
    }

    #[Test]
    public function testCancelTaskExecutions(): void
    {
        // Arrange
        $script = FixtureFactory::createScript(['code' => 'STATS_CANCEL_SCRIPT']);
        $device = FixtureFactory::createAutoJsDevice(['code' => 'STATS_CANCEL_DEVICE']);
        $task = FixtureFactory::createTask(['name' => 'Stats Cancel Test Task']);

        // 创建正在运行的执行记录
        $runningRecord = FixtureFactory::createMinimalScriptExecutionRecord([
            'status' => ExecutionStatus::RUNNING,
        ]);
        $runningRecord->setAutoJsDevice($device);
        $runningRecord->setScript($script);
        $runningRecord->setTask($task);

        $this->persistEntities([
            $script,
            $device,
            $task,
            $runningRecord,
        ]);

        // Act
        $this->repository->cancelTaskExecutions($task);

        // Assert
        self::getEntityManager()->clear();
        $cancelledRecord = $this->repository->find($runningRecord->getId());
        $this->assertNotNull($cancelledRecord);
        $this->assertEquals(ExecutionStatus::CANCELLED, $cancelledRecord->getStatus());
        $this->assertNotNull($cancelledRecord->getEndTime());
    }

    #[Test]
    public function testDeleteOldRecords(): void
    {
        // Arrange
        $oldRecord = FixtureFactory::createMinimalScriptExecutionRecord([
            'status' => ExecutionStatus::SUCCESS,
        ]);
        $oldRecord->setCreateTime(new \DateTimeImmutable('-2 days'));

        $newRecord = FixtureFactory::createMinimalScriptExecutionRecord([
            'status' => ExecutionStatus::SUCCESS,
        ]);
        $newRecord->setCreateTime(new \DateTimeImmutable('-12 hours'));

        $autoJsDevice1 = $oldRecord->getAutoJsDevice();
        $this->assertNotNull($autoJsDevice1);
        $baseDevice1 = $autoJsDevice1->getBaseDevice();
        $this->assertNotNull($baseDevice1);
        $script1 = $oldRecord->getScript();
        $this->assertNotNull($script1);
        $task1 = $oldRecord->getTask();
        $this->assertNotNull($task1);

        $autoJsDevice2 = $newRecord->getAutoJsDevice();
        $this->assertNotNull($autoJsDevice2);
        $baseDevice2 = $autoJsDevice2->getBaseDevice();
        $this->assertNotNull($baseDevice2);
        $script2 = $newRecord->getScript();
        $this->assertNotNull($script2);
        $task2 = $newRecord->getTask();
        $this->assertNotNull($task2);

        $this->persistEntities([
            $baseDevice1,
            $autoJsDevice1,
            $script1,
            $task1,
            $oldRecord,
            $baseDevice2,
            $autoJsDevice2,
            $script2,
            $task2,
            $newRecord,
        ]);

        // Act
        $threshold = new \DateTime('-1 day');
        $deletedCount = $this->repository->deleteOldRecords($threshold);

        // Assert
        $this->assertEquals(1, $deletedCount);
    }

    #[Test]
    public function testFindByAutoJsDevice(): void
    {
        // Arrange
        $dataSet = FixtureFactory::createCompleteDataSet([
            'deviceCount' => 1,
            'recordCount' => 3,
        ]);

        $this->persistEntities($dataSet['all']);
        $device = $dataSet['devices'][0];

        // Act
        $deviceId = $device->getId();
        $this->assertNotNull($deviceId);
        $records = $this->repository->findByAutoJsDevice((string) $deviceId, 10);

        // Assert
        $this->assertIsArray($records);
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
        $dataSet = FixtureFactory::createCompleteDataSet([
            'taskCount' => 1,
            'recordCount' => 2,
        ]);

        $this->persistEntities($dataSet['all']);
        $task = $dataSet['tasks'][0];

        // Act
        $taskId = $task->getId();
        $this->assertNotNull($taskId);
        $records = $this->repository->findByTask((string) $taskId);

        // Assert
        $this->assertIsArray($records);
        foreach ($records as $record) {
            $recordTask = $record->getTask();
            $this->assertNotNull($recordTask);
            $this->assertEquals($task->getId(), $recordTask->getId());
        }
    }
}
