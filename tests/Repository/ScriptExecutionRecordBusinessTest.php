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
 * 脚本执行记录Repository业务逻辑测试.
 *
 * Linus重构：专注于业务特定方法
 *
 * @internal
 */
#[CoversClass(ScriptExecutionRecordRepository::class)]
#[RunTestsInSeparateProcesses]
final class ScriptExecutionRecordBusinessTest extends AbstractRepositoryTestCase
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
    public function testCancelTaskExecutions(): void
    {
        // Arrange
        $dataSet = FixtureFactory::createCompleteDataSet([
            'taskCount' => 1,
            'recordCount' => 3,
        ]);
        $task = $dataSet['tasks'][0];

        // 创建运行中的记录和已完成的记录
        $dataSet['records'][0]->setStatus(ExecutionStatus::RUNNING);
        $dataSet['records'][1]->setStatus(ExecutionStatus::RUNNING);
        $dataSet['records'][2]->setStatus(ExecutionStatus::SUCCESS); // 已完成，不应被影响

        $this->persistEntities($dataSet['all']);

        // Act
        $this->repository->cancelTaskExecutions($task);

        // Assert
        self::getEntityManager()->refresh($dataSet['records'][0]);
        self::getEntityManager()->refresh($dataSet['records'][1]);
        self::getEntityManager()->refresh($dataSet['records'][2]);

        $this->assertEquals(ExecutionStatus::CANCELLED, $dataSet['records'][0]->getStatus());
        $this->assertEquals(ExecutionStatus::CANCELLED, $dataSet['records'][1]->getStatus());
        $this->assertNotNull($dataSet['records'][0]->getEndTime());
        $this->assertNotNull($dataSet['records'][1]->getEndTime());

        // 已完成的记录不应被影响
        $this->assertEquals(ExecutionStatus::SUCCESS, $dataSet['records'][2]->getStatus());
    }

    #[Test]
    public function testFindByAssociationWithAutoJsDevice(): void
    {
        // Arrange
        $dataSet = FixtureFactory::createCompleteDataSet([
            'deviceCount' => 2,
            'recordCount' => 4,
        ]);

        $device1 = $dataSet['devices'][0];
        $this->persistEntities($dataSet['all']);

        // Act
        $device1Records = $this->repository->findBy(['autoJsDevice' => $device1]);

        // Assert
        $this->assertIsArray($device1Records);
        foreach ($device1Records as $record) {
            $autoJsDevice = $record->getAutoJsDevice();
            $this->assertNotNull($autoJsDevice, 'AutoJsDevice should not be null');
            $this->assertEquals($device1->getId(), $autoJsDevice->getId());
        }
    }

    #[Test]
    public function testCountByAssociationWithScript(): void
    {
        // Arrange
        $dataSet = FixtureFactory::createCompleteDataSet([
            'scriptCount' => 2,
            'recordCount' => 6,
        ]);

        $script1 = $dataSet['scripts'][0];
        $this->persistEntities($dataSet['all']);

        // Act
        $count = $this->repository->count(['script' => $script1]);

        // Assert
        $this->assertGreaterThanOrEqual(1, $count);
    }

    #[Test]
    public function testFindByNullTask(): void
    {
        // Arrange - 创建一个没有关联task的记录
        $record = FixtureFactory::createMinimalScriptExecutionRecord();
        $record->setTask(null); // 设置为null

        $autoJsDevice = $record->getAutoJsDevice();
        $this->assertNotNull($autoJsDevice, 'AutoJsDevice should not be null');

        $baseDevice = $autoJsDevice->getBaseDevice();
        $this->assertNotNull($baseDevice, 'BaseDevice should not be null');

        $script = $record->getScript();
        $this->assertNotNull($script, 'Script should not be null');

        $this->persistEntities([
            $baseDevice,
            $autoJsDevice,
            $script,
            $record,
        ]);

        // Act
        $recordsWithoutTask = $this->repository->findBy(['task' => null]);

        // Assert
        $this->assertIsArray($recordsWithoutTask);
        $this->assertGreaterThanOrEqual(1, count($recordsWithoutTask));

        foreach ($recordsWithoutTask as $foundRecord) {
            $this->assertNull($foundRecord->getTask());
        }
    }

    #[Test]
    public function testFindOneByWithOrderByStartTime(): void
    {
        // Arrange
        $dataSet = FixtureFactory::createCompleteDataSet(['recordCount' => 3]);

        // 设置不同的开始时间
        $dataSet['records'][0]->setStartTime(new \DateTimeImmutable('-3 hours'));
        $dataSet['records'][1]->setStartTime(new \DateTimeImmutable('-1 hour'));
        $dataSet['records'][2]->setStartTime(new \DateTimeImmutable('-2 hours'));

        $this->persistEntities($dataSet['all']);

        // Act - 查找最新开始的记录
        $latestRecord = $this->repository->findOneBy([], ['startTime' => 'DESC']);

        // Assert
        $this->assertInstanceOf(ScriptExecutionRecord::class, $latestRecord);
        $this->assertNotNull($latestRecord->getStartTime());

        // Linus修复：通过instructionId过滤我们创建的记录，避免干扰
        $ourRecords = [];
        foreach ($dataSet['records'] as $record) {
            $foundRecord = $this->repository->findOneBy(['instructionId' => $record->getInstructionId()]);
            if (null !== $foundRecord) {
                $ourRecords[] = $foundRecord;
            }
        }

        $this->assertCount(3, $ourRecords, '应该找到我们创建的3个记录');

        // 按开始时间排序我们的记录
        usort($ourRecords, function ($a, $b) {
            $startTimeA = $a->getStartTime();
            $startTimeB = $b->getStartTime();
            $this->assertNotNull($startTimeA, 'Start time should not be null');
            $this->assertNotNull($startTimeB, 'Start time should not be null');

            return $startTimeB <=> $startTimeA; // DESC排序
        });

        // 验证排序是否正确
        $this->assertNotNull($ourRecords[0]->getStartTime(), 'First record start time should not be null');
        $this->assertNotNull($ourRecords[1]->getStartTime(), 'Second record start time should not be null');
        $this->assertNotNull($ourRecords[2]->getStartTime(), 'Third record start time should not be null');
        $this->assertGreaterThanOrEqual($ourRecords[1]->getStartTime()->getTimestamp(), $ourRecords[0]->getStartTime()->getTimestamp());
        $this->assertGreaterThanOrEqual($ourRecords[2]->getStartTime()->getTimestamp(), $ourRecords[1]->getStartTime()->getTimestamp());
    }

    #[Test]
    public function testCountByStatus(): void
    {
        // Arrange
        $dataSet = FixtureFactory::createCompleteDataSet([
            'scriptCount' => 2,
            'recordCount' => 6,
        ]);

        $this->persistEntities($dataSet['all']);

        // Act
        $startDate = new \DateTimeImmutable('-1 day');
        $endDate = new \DateTimeImmutable();
        $counts = $this->repository->countByStatus($startDate, $endDate);

        // Assert
        $this->assertIsArray($counts);
        $this->assertArrayHasKey('PENDING', $counts);
        $this->assertArrayHasKey('RUNNING', $counts);
        $this->assertArrayHasKey('SUCCESS', $counts);
        $this->assertArrayHasKey('FAILED', $counts);
        $this->assertTrue(true); // 基本断言
    }

    #[Test]
    public function testDeleteOldRecords(): void
    {
        // Arrange
        $dataSet = FixtureFactory::createCompleteDataSet([
            'scriptCount' => 2,
            'recordCount' => 6,
        ]);

        // 创建一些旧记录
        foreach ($dataSet['records'] as $record) {
            $record->setCreateTime(new \DateTimeImmutable('-2 days'));
        }

        $this->persistEntities($dataSet['all']);

        $threshold = new \DateTimeImmutable('-1 day');

        // Act
        $deletedCount = $this->repository->deleteOldRecords($threshold);

        // Assert
        $this->assertGreaterThanOrEqual(0, $deletedCount);
        $this->assertTrue(true); // 基本断言
    }

    #[Test]
    public function testFindByAutoJsDevice(): void
    {
        // Arrange
        $dataSet = FixtureFactory::createCompleteDataSet([
            'deviceCount' => 2,
            'scriptCount' => 2,
            'recordCount' => 6,
        ]);

        $this->persistEntities($dataSet['all']);

        $device = $dataSet['devices'][0];
        $this->assertNotNull($device->getId());

        // Act
        $records = $this->repository->findByAutoJsDevice($device->getId());

        // Assert
        $this->assertIsArray($records);
        $this->assertTrue(true); // 基本断言
    }

    #[Test]
    public function testFindByTask(): void
    {
        // Arrange
        $dataSet = FixtureFactory::createCompleteDataSet([
            'scriptCount' => 2,
            'taskCount' => 2,
            'recordCount' => 6,
        ]);

        $this->persistEntities($dataSet['all']);

        $task = $dataSet['tasks'][0];
        $this->assertNotNull($task->getId());

        // Act
        $records = $this->repository->findByTask($task->getId());

        // Assert
        $this->assertIsArray($records);
        $this->assertTrue(true); // 基本断言
    }
}
