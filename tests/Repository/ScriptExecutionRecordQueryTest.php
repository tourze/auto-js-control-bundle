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
 * 脚本执行记录Repository查询测试.
 *
 * Linus重构：专注于查询方法测试
 *
 * @internal
 */
#[CoversClass(ScriptExecutionRecordRepository::class)]
#[RunTestsInSeparateProcesses]
final class ScriptExecutionRecordQueryTest extends AbstractRepositoryTestCase
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
    public function testFindByAutoJsDevice(): void
    {
        // Arrange - 使用批量创建数据
        $dataSet = FixtureFactory::createCompleteDataSet([
            'deviceCount' => 2,
            'recordCount' => 3,
        ]);

        $this->persistEntities($dataSet['all']);

        $device1 = $dataSet['devices'][0];
        $device2 = $dataSet['devices'][1];

        // Act
        $deviceId = $device1->getId();
        $this->assertNotNull($deviceId);
        $records = $this->repository->findByAutoJsDevice((string) $deviceId, 10);

        // Assert
        $this->assertIsArray($records);
        foreach ($records as $record) {
            $autoJsDevice = $record->getAutoJsDevice();
            $this->assertNotNull($autoJsDevice);
            $this->assertEquals($device1->getId(), $autoJsDevice->getId());
        }
    }

    #[Test]
    public function findByAutoJsDeviceRespectsLimit(): void
    {
        // Arrange
        $dataSet = FixtureFactory::createCompleteDataSet([
            'deviceCount' => 1,
            'recordCount' => 5,
        ]);

        $this->persistEntities($dataSet['all']);
        $device = $dataSet['devices'][0];

        // Act
        $deviceId = $device->getId();
        $this->assertNotNull($deviceId);
        $records = $this->repository->findByAutoJsDevice((string) $deviceId, 3);

        // Assert
        $this->assertCount(3, $records);
    }

    #[Test]
    public function testFindByTask(): void
    {
        // Arrange
        $dataSet = FixtureFactory::createCompleteDataSet([
            'taskCount' => 2,
            'recordCount' => 4,
        ]);

        $this->persistEntities($dataSet['all']);
        $task1 = $dataSet['tasks'][0];

        // Act
        $taskId = $task1->getId();
        $this->assertNotNull($taskId);
        $records = $this->repository->findByTask((string) $taskId);

        // Assert
        $this->assertIsArray($records);
        foreach ($records as $record) {
            $task = $record->getTask();
            $this->assertNotNull($task);
            $this->assertEquals($task1->getId(), $task->getId());
        }
    }

    #[Test]
    public function testFindByNullFieldShouldReturnEntitiesWithNullValues(): void
    {
        // Arrange
        $record1 = FixtureFactory::createMinimalScriptExecutionRecord([
            'status' => ExecutionStatus::FAILED,
        ]);
        $record1->setErrorMessage('Some error');

        $record2 = FixtureFactory::createMinimalScriptExecutionRecord([
            'status' => ExecutionStatus::SUCCESS,
        ]);
        $record2->setErrorMessage(null);

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
        $recordsWithoutError = $this->repository->findBy(['errorMessage' => null]);

        // Assert
        $this->assertIsArray($recordsWithoutError);
        $recordStatuses = array_map(fn ($r) => $r->getStatus(), $recordsWithoutError);
        $this->assertContains(ExecutionStatus::SUCCESS, $recordStatuses);
    }

    #[Test]
    public function testFindOneByWithOrderByDuration(): void
    {
        // Arrange
        $dataSet = FixtureFactory::createCompleteDataSet(['recordCount' => 3]);

        // 设置不同的duration
        $dataSet['records'][0]->setDuration(100);
        $dataSet['records'][1]->setDuration(300);
        $dataSet['records'][2]->setDuration(200);

        $this->persistEntities($dataSet['all']);

        // Act - 查找duration最长的记录
        $longestRecord = $this->repository->findOneBy([], ['duration' => 'DESC']);

        // Assert
        $this->assertInstanceOf(ScriptExecutionRecord::class, $longestRecord);
        $this->assertEquals(300, $longestRecord->getDuration());
    }

    #[Test]
    public function testGetTaskExecutionStats(): void
    {
        // Arrange
        $dataSet = FixtureFactory::createCompleteDataSet([
            'taskCount' => 1,
            'recordCount' => 5,
        ]);

        // 设置不同的执行状态
        $dataSet['records'][0]->setStatus(ExecutionStatus::SUCCESS);
        $dataSet['records'][1]->setStatus(ExecutionStatus::SUCCESS);
        $dataSet['records'][2]->setStatus(ExecutionStatus::FAILED);
        $dataSet['records'][3]->setStatus(ExecutionStatus::RUNNING);
        $dataSet['records'][4]->setStatus(ExecutionStatus::CANCELLED);

        $this->persistEntities($dataSet['all']);
        $task = $dataSet['tasks'][0];

        // Act
        $stats = $this->repository->getTaskExecutionStats($task);

        // Assert
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total', $stats);
        $this->assertArrayHasKey('successful', $stats);
        $this->assertArrayHasKey('failed', $stats);
        $this->assertArrayHasKey('running', $stats);

        $this->assertEquals(5, $stats['total']);
        $this->assertEquals(2, $stats['successful']);
        $this->assertEquals(1, $stats['failed']);
        $this->assertEquals(1, $stats['running']);
    }

    #[Test]
    public function testGetTaskExecutionStatsForEmptyTask(): void
    {
        // Arrange
        $script = FixtureFactory::createScript(['code' => 'NO_RECORDS_SCRIPT']);
        $task = FixtureFactory::createTask(['name' => 'No Records Task']);

        $this->persistEntities([$script, $task]);

        // Act
        $stats = $this->repository->getTaskExecutionStats($task);

        // Assert
        $this->assertIsArray($stats);
        $this->assertEquals(0, $stats['total']);
        $this->assertEquals(0, $stats['successful']);
        $this->assertEquals(0, $stats['failed']);
        $this->assertEquals(0, $stats['running']);
    }

    #[Test]
    public function testFindOneBy(): void
    {
        // Arrange
        $dataSet = FixtureFactory::createCompleteDataSet(['recordCount' => 3]);

        // 设置相同的duration来测试findOneBy的确定性
        foreach ($dataSet['records'] as $record) {
            $record->setDuration(200);
        }

        $this->persistEntities($dataSet['all']);

        // Act
        $found = $this->repository->findOneBy(['duration' => 200]);

        // Assert
        $this->assertInstanceOf(ScriptExecutionRecord::class, $found);
        $this->assertEquals(200, $found->getDuration());
    }

    #[Test]
    public function testFindOneByReturnsNull(): void
    {
        // Arrange
        $dataSet = FixtureFactory::createCompleteDataSet(['recordCount' => 2]);
        $this->persistEntities($dataSet['all']);

        // Act
        $found = $this->repository->findOneBy(['duration' => 999]);

        // Assert
        $this->assertNull($found);
    }

    #[Test]
    public function testCount(): void
    {
        // Arrange
        $initialCount = $this->repository->count([]);

        $dataSet = FixtureFactory::createCompleteDataSet(['recordCount' => 3]);
        $this->persistEntities($dataSet['all']);

        // Act
        $totalCount = $this->repository->count([]);
        $successCount = $this->repository->count(['status' => ExecutionStatus::SUCCESS]);
        $runningCount = $this->repository->count(['status' => ExecutionStatus::RUNNING]);

        // Assert
        $this->assertEquals($initialCount + 3, $totalCount);
        $this->assertGreaterThanOrEqual(0, $successCount);
        $this->assertGreaterThanOrEqual(0, $runningCount);
    }

    #[Test]
    public function testCancelTaskExecutions(): void
    {
        // Arrange
        $script = FixtureFactory::createScript(['code' => 'QUERY_CANCEL_SCRIPT']);
        $device = FixtureFactory::createAutoJsDevice(['code' => 'QUERY_CANCEL_DEVICE']);
        $task = FixtureFactory::createTask(['name' => 'Query Cancel Task']);

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
    }

    #[Test]
    public function testCountByStatus(): void
    {
        // Arrange
        $script = FixtureFactory::createScript(['code' => 'QUERY_COUNT_SCRIPT']);
        $device = FixtureFactory::createAutoJsDevice(['code' => 'QUERY_COUNT_DEVICE']);
        $task = FixtureFactory::createTask(['name' => 'Query Count Task']);

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
        $this->assertNotEmpty($counts);
    }

    #[Test]
    public function testDeleteOldRecords(): void
    {
        // Arrange
        $oldRecord = FixtureFactory::createMinimalScriptExecutionRecord([
            'status' => ExecutionStatus::SUCCESS,
        ]);
        $oldRecord->setCreateTime(new \DateTimeImmutable('-2 days'));

        $autoJsDevice = $oldRecord->getAutoJsDevice();
        $this->assertNotNull($autoJsDevice);
        $baseDevice = $autoJsDevice->getBaseDevice();
        $this->assertNotNull($baseDevice);

        $script = $oldRecord->getScript();
        $this->assertNotNull($script);
        $task = $oldRecord->getTask();
        $this->assertNotNull($task);

        $this->persistEntities([
            $baseDevice,
            $autoJsDevice,
            $script,
            $task,
            $oldRecord,
        ]);

        // Act
        $threshold = new \DateTime('-1 day');
        $deletedCount = $this->repository->deleteOldRecords($threshold);

        // Assert
        $this->assertGreaterThanOrEqual(1, $deletedCount);
    }
}
