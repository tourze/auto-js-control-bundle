<?php

namespace Tourze\AutoJsControlBundle\Repository;

use DeviceBundle\Enum\DeviceStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\AutoJsControlBundle\Entity\AutoJsDevice;
use Tourze\AutoJsControlBundle\Entity\DeviceGroup;
use Tourze\PHPUnitSymfonyKernelTest\Attribute\AsRepository;

/**
 * @extends ServiceEntityRepository<AutoJsDevice>
 */
#[AsRepository(entityClass: AutoJsDevice::class)]
class AutoJsDeviceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AutoJsDevice::class);
    }

    /**
     * 查找在线设备.
     *
     * @return array<int, AutoJsDevice>
     */
    public function findOnlineDevices(): array
    {
        return $this->createQueryBuilder('d')
            ->join('d.baseDevice', 'bd')
            ->where('bd.status = :status')
            ->andWhere('bd.valid = :valid')
            ->setParameter('status', DeviceStatus::ONLINE->value)
            ->setParameter('valid', true)
            ->orderBy('bd.lastOnlineTime', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * 查找离线设备（超过指定时间未在线）.
     *
     * @return array<int, AutoJsDevice>
     */
    public function findOfflineDevices(\DateTimeInterface $threshold): array
    {
        return $this->createQueryBuilder('d')
            ->join('d.baseDevice', 'bd')
            ->where('bd.status = :status')
            ->andWhere('bd.lastOnlineTime < :threshold')
            ->andWhere('bd.valid = :valid')
            ->setParameter('status', DeviceStatus::OFFLINE->value)
            ->setParameter('threshold', $threshold)
            ->setParameter('valid', true)
            ->orderBy('bd.lastOnlineTime', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * 根据设备分组查找设备.
     *
     * @return array<int, AutoJsDevice>
     */
    public function findByDeviceGroup(DeviceGroup $group): array
    {
        return $this->createQueryBuilder('d')
            ->join('d.baseDevice', 'bd')
            ->where('d.deviceGroup = :group')
            ->andWhere('bd.valid = :valid')
            ->setParameter('group', $group)
            ->setParameter('valid', true)
            ->orderBy('bd.name', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * 根据设备编码查找设备.
     */
    public function findByDeviceCode(string $deviceCode): ?AutoJsDevice
    {
        return $this->createQueryBuilder('d')
            ->join('d.baseDevice', 'bd')
            ->where('bd.code = :deviceCode')
            ->setParameter('deviceCode', $deviceCode)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    /**
     * 根据基础设备查找Auto.js设备.
     */
    public function findByBaseDevice(string $baseDeviceId): ?AutoJsDevice
    {
        return $this->createQueryBuilder('d')
            ->where('d.baseDevice = :baseDeviceId')
            ->setParameter('baseDeviceId', $baseDeviceId)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    /**
     * 统计设备状态
     *
     * @return array<string, int>
     */
    public function countByStatus(): array
    {
        /** @var array<int, array{status: string|DeviceStatus, count: string|int}> $results */
        $results = $this->createQueryBuilder('d')
            ->join('d.baseDevice', 'bd')
            ->select('bd.status as status, COUNT(d.id) as count')
            ->where('bd.valid = :valid')
            ->setParameter('valid', true)
            ->groupBy('bd.status')
            ->getQuery()
            ->getResult()
        ;

        $counts = [];
        foreach ($results as $row) {
            $status = $row['status'];
            // 处理枚举类型
            if ($status instanceof DeviceStatus) {
                $status = $status->value;
            }
            $counts[strtoupper($status)] = (int) $row['count'];
        }

        return $counts;
    }

    /**
     * 查找需要心跳检测的设备.
     *
     * @return array<int, AutoJsDevice>
     */
    public function findDevicesForHeartbeatCheck(\DateTimeInterface $threshold): array
    {
        return $this->createQueryBuilder('d')
            ->join('d.baseDevice', 'bd')
            ->where('bd.status = :status')
            ->andWhere('bd.lastOnlineTime < :threshold')
            ->andWhere('bd.valid = :valid')
            ->setParameter('status', 'online')
            ->setParameter('threshold', $threshold)
            ->setParameter('valid', true)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * 根据设备编码查找设备（别名方法）.
     */
    public function findOneByDeviceCode(string $deviceCode): ?AutoJsDevice
    {
        return $this->findByDeviceCode($deviceCode);
    }

    /**
     * 根据设备分组查找设备（别名方法）.
     *
     * @return array<int, AutoJsDevice>
     */
    public function findByGroup(DeviceGroup $group): array
    {
        return $this->findByDeviceGroup($group);
    }

    /**
     * 查找活跃设备（在线且有效）.
     *
     * @return array<int, AutoJsDevice>
     */
    public function findActiveDevices(): array
    {
        return $this->createQueryBuilder('d')
            ->join('d.baseDevice', 'bd')
            ->where('bd.status = :status')
            ->andWhere('bd.valid = :valid')
            ->setParameter('status', 'online')
            ->setParameter('valid', true)
            ->orderBy('bd.lastOnlineTime', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * 查找所有在线设备.
     *
     * @return array<int, AutoJsDevice>
     */
    public function findAllOnlineDevices(): array
    {
        return $this->findOnlineDevices();
    }

    /**
     * 根据 WebSocket 连接 ID 查找设备.
     */
    public function findByWsConnectionId(string $wsConnectionId): ?AutoJsDevice
    {
        return $this->createQueryBuilder('d')
            ->where('d.wsConnectionId = :wsConnectionId')
            ->setParameter('wsConnectionId', $wsConnectionId)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    /**
     * 获取设备总数.
     */
    public function getDeviceCount(): int
    {
        $result = $this->createQueryBuilder('d')
            ->join('d.baseDevice', 'bd')
            ->select('COUNT(d.id)')
            ->where('bd.valid = :valid')
            ->setParameter('valid', true)
            ->getQuery()
            ->getSingleScalarResult()
        ;

        return (int) $result;
    }

    /**
     * 根据多个设备编码查找设备.
     *
     * @param array<int, string> $deviceCodes
     *
     * @return array<int, AutoJsDevice>
     */
    public function findByDeviceCodes(array $deviceCodes): array
    {
        if ([] === $deviceCodes) {
            return [];
        }

        return $this->createQueryBuilder('d')
            ->join('d.baseDevice', 'bd')
            ->where('bd.code IN (:deviceCodes)')
            ->setParameter('deviceCodes', $deviceCodes)
            ->getQuery()
            ->getResult()
        ;
    }

    public function save(AutoJsDevice $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(AutoJsDevice $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * 查找所有设备（预加载 baseDevice 关联以避免懒加载）
     *
     * @return array<int, AutoJsDevice>
     */
    public function findAllWithBaseDevice(): array
    {
        return $this->createQueryBuilder('d')
            ->leftJoin('d.baseDevice', 'bd')
            ->addSelect('bd')
            ->getQuery()
            ->getResult()
        ;
    }
}
