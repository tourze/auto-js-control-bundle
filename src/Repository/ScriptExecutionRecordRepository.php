<?php

namespace Tourze\AutoJsControlBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\AutoJsControlBundle\Entity\ScriptExecutionRecord;
use Tourze\AutoJsControlBundle\Entity\Task;
use Tourze\AutoJsControlBundle\Enum\ExecutionStatus;
use Tourze\PHPUnitSymfonyKernelTest\Attribute\AsRepository;

/**
 * @extends ServiceEntityRepository<ScriptExecutionRecord>
 */
#[AsRepository(entityClass: ScriptExecutionRecord::class)]
class ScriptExecutionRecordRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ScriptExecutionRecord::class);
    }

    /**
     * 查找设备的执行记录.
     *
     * @return array<int, ScriptExecutionRecord>
     */
    public function findByAutoJsDevice(string $autoJsDeviceId, int $limit = 100): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.autoJsDevice = :autoJsDeviceId')
            ->setParameter('autoJsDeviceId', $autoJsDeviceId)
            ->orderBy('r.createTime', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * 查找任务的执行记录.
     *
     * @return array<int, ScriptExecutionRecord>
     */
    public function findByTask(string $taskId): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.task = :taskId')
            ->setParameter('taskId', $taskId)
            ->orderBy('r.createTime', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * 统计执行状态
     *
     * @return array<int, array{status: ExecutionStatus|string, count: int}>
     */
    public function countByStatus(?\DateTimeInterface $startDate = null, ?\DateTimeInterface $endDate = null): array
    {
        $qb = $this->createQueryBuilder('r')
            ->select('r.status, COUNT(r.id) as count')
        ;

        if (null !== $startDate && null !== $endDate) {
            $qb->where('r.createTime BETWEEN :startDate AND :endDate')
                ->setParameter('startDate', $startDate)
                ->setParameter('endDate', $endDate)
            ;
        }

        return $qb->groupBy('r.status')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * 删除旧的执行记录.
     */
    public function deleteOldRecords(\DateTimeInterface $threshold): int
    {
        return $this->createQueryBuilder('r')
            ->delete()
            ->where('r.createTime < :threshold')
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->execute()
        ;
    }

    /**
     * 获取任务执行统计
     *
     * @return array<string, int>
     */
    public function getTaskExecutionStats(Task $task): array
    {
        /** @var array{total: string|int, successful: string|int, failed: string|int, running: string|int} $result */
        $result = $this->createQueryBuilder('r')
            ->select('
                COUNT(r.id) as total,
                SUM(CASE WHEN r.status = :completed THEN 1 ELSE 0 END) as successful,
                SUM(CASE WHEN r.status = :failed THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN r.status = :running THEN 1 ELSE 0 END) as running
            ')
            ->where('r.task = :task')
            ->setParameter('task', $task)
            ->setParameter('completed', ExecutionStatus::SUCCESS)
            ->setParameter('failed', ExecutionStatus::FAILED)
            ->setParameter('running', ExecutionStatus::RUNNING)
            ->getQuery()
            ->getSingleResult()
        ;

        return [
            'total' => (int) ($result['total'] ?? 0),
            'successful' => (int) ($result['successful'] ?? 0),
            'failed' => (int) ($result['failed'] ?? 0),
            'running' => (int) ($result['running'] ?? 0),
        ];
    }

    /**
     * 取消任务的所有执行记录.
     */
    public function cancelTaskExecutions(Task $task): void
    {
        $this->createQueryBuilder('r')
            ->update()
            ->set('r.status', ':status')
            ->set('r.endTime', ':completedAt')
            ->where('r.task = :task')
            ->andWhere('r.status = :runningStatus')
            ->setParameter('task', $task)
            ->setParameter('status', ExecutionStatus::CANCELLED)
            ->setParameter('completedAt', new \DateTime())
            ->setParameter('runningStatus', ExecutionStatus::RUNNING)
            ->getQuery()
            ->execute()
        ;
    }

    public function save(ScriptExecutionRecord $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ScriptExecutionRecord $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
