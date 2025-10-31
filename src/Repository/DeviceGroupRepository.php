<?php

namespace Tourze\AutoJsControlBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\AutoJsControlBundle\Entity\DeviceGroup;
use Tourze\PHPUnitSymfonyKernelTest\Attribute\AsRepository;

/**
 * @extends ServiceEntityRepository<DeviceGroup>
 */
#[AsRepository(entityClass: DeviceGroup::class)]
class DeviceGroupRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DeviceGroup::class);
    }

    /**
     * 查找所有有效的分组.
     *
     * @return array<int, DeviceGroup>
     */
    public function findActiveGroups(): array
    {
        return $this->createQueryBuilder('g')
            ->where('g.valid = :valid')
            ->setParameter('valid', true)
            ->orderBy('g.sortOrder', 'ASC')
            ->addOrderBy('g.name', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    public function save(DeviceGroup $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(DeviceGroup $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
