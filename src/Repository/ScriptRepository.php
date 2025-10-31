<?php

namespace Tourze\AutoJsControlBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\AutoJsControlBundle\Entity\Script;
use Tourze\PHPUnitSymfonyKernelTest\Attribute\AsRepository;

/**
 * @extends ServiceEntityRepository<Script>
 */
#[AsRepository(entityClass: Script::class)]
class ScriptRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Script::class);
    }

    /**
     * 查找有效的脚本.
     *
     * @return array<int, Script>
     */
    public function findActiveScripts(): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.valid = :valid')
            ->setParameter('valid', true)
            ->orderBy('s.priority', 'DESC')
            ->addOrderBy('s.name', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * 根据脚本编码查找.
     */
    public function findByCode(string $code): ?Script
    {
        return $this->createQueryBuilder('s')
            ->where('s.code = :code')
            ->setParameter('code', $code)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    /**
     * 根据脚本类型查找.
     *
     * @return array<int, Script>
     */
    public function findByScriptType(string $scriptType): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.scriptType = :scriptType')
            ->andWhere('s.valid = :valid')
            ->setParameter('scriptType', $scriptType)
            ->setParameter('valid', true)
            ->orderBy('s.priority', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    public function save(Script $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Script $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
