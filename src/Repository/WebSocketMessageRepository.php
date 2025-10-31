<?php

namespace Tourze\AutoJsControlBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\AutoJsControlBundle\Entity\WebSocketMessage;
use Tourze\PHPUnitSymfonyKernelTest\Attribute\AsRepository;

/**
 * @extends ServiceEntityRepository<WebSocketMessage>
 */
#[AsRepository(entityClass: WebSocketMessage::class)]
class WebSocketMessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WebSocketMessage::class);
    }

    /**
     * 查找未处理的消息.
     *
     * @return array<int, WebSocketMessage>
     */
    public function findUnprocessedMessages(int $limit = 100): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.isProcessed = :isProcessed')
            ->andWhere('m.direction = :direction')
            ->setParameter('isProcessed', false)
            ->setParameter('direction', 'in')
            ->orderBy('m.createTime', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * 查找设备的消息记录.
     *
     * @param array<string, mixed> $filters
     *
     * @return array<int, WebSocketMessage>
     */
    public function findByAutoJsDevice(string $autoJsDeviceId, array $filters = [], int $limit = 100): array
    {
        $qb = $this->createQueryBuilder('m')
            ->where('m.autoJsDevice = :autoJsDeviceId')
            ->setParameter('autoJsDeviceId', $autoJsDeviceId)
        ;

        if (isset($filters['messageType']) && '' !== $filters['messageType']) {
            $qb->andWhere('m.messageType = :messageType')
                ->setParameter('messageType', $filters['messageType'])
            ;
        }

        if (isset($filters['direction']) && '' !== $filters['direction']) {
            $qb->andWhere('m.direction = :direction')
                ->setParameter('direction', $filters['direction'])
            ;
        }

        return $qb->orderBy('m.createTime', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * 删除旧消息记录.
     */
    public function deleteOldMessages(\DateTimeInterface $threshold): int
    {
        return $this->createQueryBuilder('m')
            ->delete()
            ->where('m.createTime < :threshold')
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->execute()
        ;
    }

    public function save(WebSocketMessage $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(WebSocketMessage $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
