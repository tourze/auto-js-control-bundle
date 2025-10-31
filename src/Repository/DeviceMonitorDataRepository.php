<?php

namespace Tourze\AutoJsControlBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\AutoJsControlBundle\Entity\AutoJsDevice;
use Tourze\AutoJsControlBundle\Entity\DeviceMonitorData;
use Tourze\PHPUnitSymfonyKernelTest\Attribute\AsRepository;

/**
 * @extends ServiceEntityRepository<DeviceMonitorData>
 */
#[AsRepository(entityClass: DeviceMonitorData::class)]
class DeviceMonitorDataRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DeviceMonitorData::class);
    }

    /**
     * 获取设备最新的监控数据.
     */
    public function findLatestByAutoJsDevice(string $autoJsDeviceId): ?DeviceMonitorData
    {
        return $this->createQueryBuilder('m')
            ->where('m.autoJsDevice = :autoJsDeviceId')
            ->setParameter('autoJsDeviceId', $autoJsDeviceId)
            ->orderBy('m.createTime', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    /**
     * 获取设备的历史监控数据.
     *
     * @return DeviceMonitorData[]
     */
    public function findByAutoJsDeviceAndTimeRange(
        string $autoJsDeviceId,
        \DateTimeInterface $startTime,
        \DateTimeInterface $endTime,
        int $limit = 1000,
    ): array {
        return $this->createQueryBuilder('m')
            ->where('m.autoJsDevice = :autoJsDeviceId')
            ->andWhere('m.createTime BETWEEN :startTime AND :endTime')
            ->setParameter('autoJsDeviceId', $autoJsDeviceId)
            ->setParameter('startTime', $startTime)
            ->setParameter('endTime', $endTime)
            ->orderBy('m.createTime', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * 删除旧的监控数据.
     */
    public function deleteOldData(\DateTimeInterface $threshold): int
    {
        return $this->createQueryBuilder('m')
            ->delete()
            ->where('m.createTime < :threshold')
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->execute()
        ;
    }

    /**
     * 获取设备的平均监控数据.
     *
     * @return array<string, float|null>
     */
    public function getAverageStats(
        string $autoJsDeviceId,
        \DateTimeInterface $startTime,
        \DateTimeInterface $endTime,
    ): array {
        return $this->createQueryBuilder('m')
            ->select('
                AVG(m.cpuUsage) as avgCpuUsage,
                AVG(m.memoryUsed) as avgMemoryUsed,
                AVG(m.networkLatency) as avgNetworkLatency,
                AVG(m.temperature) as avgTemperature
            ')
            ->where('m.autoJsDevice = :autoJsDeviceId')
            ->andWhere('m.createTime BETWEEN :startTime AND :endTime')
            ->setParameter('autoJsDeviceId', $autoJsDeviceId)
            ->setParameter('startTime', $startTime)
            ->setParameter('endTime', $endTime)
            ->getQuery()
            ->getSingleResult()
        ;
    }

    /**
     * 创建设备初始监控数据.
     */
    public function createInitialData(AutoJsDevice $device): DeviceMonitorData
    {
        $monitorData = new DeviceMonitorData();
        $monitorData->setAutoJsDevice($device);
        $monitorData->setCpuUsage(0.0);
        $monitorData->setMemoryUsed('0');
        $monitorData->setMemoryTotal('0');
        $monitorData->setStorageUsed('0');
        $monitorData->setStorageTotal('0');
        $monitorData->setBatteryLevel(100);
        $monitorData->setTemperature(0.0);
        $monitorData->setNetworkType('UNKNOWN');
        $monitorData->setNetworkLatency(0);
        $monitorData->setCreateTime(new \DateTimeImmutable());

        $this->getEntityManager()->persist($monitorData);
        $this->getEntityManager()->flush();

        return $monitorData;
    }

    /**
     * 更新设备状态变更时间.
     */
    public function updateStatusChangedTime(AutoJsDevice $device, \DateTimeInterface $changedAt): void
    {
        $monitorData = $this->findLatestByAutoJsDevice((string) $device->getId());

        if (null === $monitorData) {
            $monitorData = $this->createInitialData($device);
        }

        // 这里可以记录状态变更的历史，或者更新相关字段
        // 目前只是更新最后修改时间
        $monitorData->setCreateTime(\DateTimeImmutable::createFromInterface($changedAt));

        $this->getEntityManager()->flush();
    }

    public function save(DeviceMonitorData $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(DeviceMonitorData $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
