<?php

namespace Tourze\AutoJsControlBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\AutoJsControlBundle\Entity\AutoJsDevice;
use Tourze\AutoJsControlBundle\Entity\Task;
use Tourze\AutoJsControlBundle\Enum\TaskStatus;
use Tourze\AutoJsControlBundle\Enum\TaskTargetType;
use Tourze\PHPUnitSymfonyKernelTest\Attribute\AsRepository;

/**
 * @extends ServiceEntityRepository<Task>
 */
#[AsRepository(entityClass: Task::class)]
class TaskRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Task::class);
    }

    /**
     * 查找待执行的任务
     *
     * @return array<int, Task>
     */
    public function findPendingTasks(\DateTimeInterface $now): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.status = :status')
            ->andWhere('t.valid = :valid')
            ->andWhere('t.taskType = :immediate OR (t.taskType = :scheduled AND t.scheduledTime <= :now)')
            ->setParameter('status', TaskStatus::PENDING)
            ->setParameter('valid', true)
            ->setParameter('immediate', 'immediate')
            ->setParameter('scheduled', 'scheduled')
            ->setParameter('now', $now)
            ->orderBy('t.priority', 'DESC')
            ->addOrderBy('t.createTime', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * 查找正在执行的任务
     *
     * @return array<int, Task>
     */
    public function findRunningTasks(): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.status = :status')
            ->setParameter('status', TaskStatus::RUNNING)
            ->orderBy('t.startTime', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * 根据状态统计任务数量.
     *
     * @return array<int, array{status: TaskStatus|string, count: int}>
     */
    public function countByStatus(): array
    {
        return $this->createQueryBuilder('t')
            ->select('t.status, COUNT(t.id) as count')
            ->where('t.valid = :valid')
            ->setParameter('valid', true)
            ->groupBy('t.status')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * 查找需要重试的任务
     *
     * @return array<int, Task>
     */
    public function findTasksForRetry(int $maxRetries): array
    {
        return $this->createQueryBuilder('t')
            ->join('t.script', 's')
            ->where('t.status = :status')
            ->andWhere('t.retryCount < :maxRetries')
            ->andWhere('t.retryCount < s.maxRetries')
            ->andWhere('t.valid = :valid')
            ->setParameter('status', TaskStatus::FAILED)
            ->setParameter('maxRetries', $maxRetries)
            ->setParameter('valid', true)
            ->orderBy('t.priority', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * 查找设备的待执行任务
     *
     * @return array<int, Task>
     */
    public function findPendingTasksForDevice(AutoJsDevice $device): array
    {
        $deviceId = $device->getId();
        if (null === $deviceId) {
            return [];
        }

        return $this->createQueryBuilder('t')
            ->where('t.status = :status')
            ->andWhere('t.valid = :valid')
            ->andWhere('JSON_CONTAINS(t.targetDevices, :deviceId) = 1 OR t.targetType = :all')
            ->setParameter('status', TaskStatus::PENDING)
            ->setParameter('valid', true)
            ->setParameter('deviceId', false !== json_encode($deviceId) ? json_encode($deviceId) : (string) $deviceId)
            ->setParameter('all', TaskTargetType::ALL)
            ->orderBy('t.priority', 'DESC')
            ->addOrderBy('t.createTime', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * 查找设备的运行中任务
     *
     * @return array<int, Task>
     */
    public function findRunningTasksForDevice(AutoJsDevice $device): array
    {
        $deviceId = $device->getId();
        if (null === $deviceId) {
            return [];
        }

        return $this->createQueryBuilder('t')
            ->where('t.status = :status')
            ->andWhere('JSON_CONTAINS(t.targetDevices, :deviceId) = 1 OR t.targetType = :all')
            ->setParameter('status', TaskStatus::RUNNING)
            ->setParameter('deviceId', false !== json_encode($deviceId) ? json_encode($deviceId) : (string) $deviceId)
            ->setParameter('all', TaskTargetType::ALL)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * 查找需要执行的计划任务
     *
     * @return array<int, Task>
     */
    public function findScheduledTasksToExecute(\DateTimeInterface $now): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.status = :status')
            ->andWhere('t.taskType = :type')
            ->andWhere('t.scheduledTime <= :now')
            ->andWhere('t.valid = :valid')
            ->setParameter('status', TaskStatus::PENDING)
            ->setParameter('type', 'scheduled')
            ->setParameter('now', $now)
            ->setParameter('valid', true)
            ->orderBy('t.priority', 'DESC')
            ->addOrderBy('t.scheduledTime', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * 查找活跃的循环任务
     *
     * @return array<int, Task>
     */
    public function findActiveRecurringTasks(): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.taskType = :type')
            ->andWhere('t.status IN (:statuses)')
            ->andWhere('t.valid = :valid')
            ->setParameter('type', 'recurring')
            ->setParameter('statuses', [TaskStatus::PENDING, TaskStatus::RUNNING])
            ->setParameter('valid', true)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * 按类型统计任务数量.
     *
     * @return array<int, array{taskType: string, count: int}>
     */
    public function countByType(): array
    {
        return $this->createQueryBuilder('t')
            ->select('t.taskType, COUNT(t.id) as count')
            ->where('t.valid = :valid')
            ->setParameter('valid', true)
            ->groupBy('t.taskType')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * 统计今日任务数.
     */
    public function countTodayTasks(): int
    {
        $today = new \DateTime('today');
        $tomorrow = new \DateTime('tomorrow');

        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.createTime >= :today')
            ->andWhere('t.createTime < :tomorrow')
            ->andWhere('t.valid = :valid')
            ->setParameter('today', $today)
            ->setParameter('tomorrow', $tomorrow)
            ->setParameter('valid', true)
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    public function save(Task $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Task $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
