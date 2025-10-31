<?php

namespace Tourze\AutoJsControlBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\AutoJsControlBundle\Entity\DeviceLog;
use Tourze\PHPUnitSymfonyKernelTest\Attribute\AsRepository;

/**
 * @extends ServiceEntityRepository<DeviceLog>
 */
#[AsRepository(entityClass: DeviceLog::class)]
class DeviceLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DeviceLog::class);
    }

    /**
     * 查找设备的日志.
     *
     * @param array<string, mixed> $filters
     *
     * @return array<int, DeviceLog>
     */
    public function findByAutoJsDevice(string $autoJsDeviceId, array $filters = [], int $limit = 100): array
    {
        $qb = $this->createQueryBuilder('l')
            ->where('l.autoJsDevice = :autoJsDeviceId')
            ->setParameter('autoJsDeviceId', $autoJsDeviceId)
        ;

        if (isset($filters['logLevel']) && '' !== $filters['logLevel']) {
            $qb->andWhere('l.logLevel = :logLevel')
                ->setParameter('logLevel', $filters['logLevel'])
            ;
        }

        if (isset($filters['logType']) && '' !== $filters['logType']) {
            $qb->andWhere('l.logType = :logType')
                ->setParameter('logType', $filters['logType'])
            ;
        }

        if (isset($filters['startTime']) && '' !== $filters['startTime'] && isset($filters['endTime']) && '' !== $filters['endTime']) {
            $qb->andWhere('l.createTime BETWEEN :startTime AND :endTime')
                ->setParameter('startTime', $filters['startTime'])
                ->setParameter('endTime', $filters['endTime'])
            ;
        }

        return $qb->orderBy('l.createTime', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * 删除旧日志.
     */
    public function deleteOldLogs(\DateTimeInterface $threshold): int
    {
        return $this->createQueryBuilder('l')
            ->delete()
            ->where('l.createTime < :threshold')
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->execute()
        ;
    }

    public function save(DeviceLog $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(DeviceLog $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
