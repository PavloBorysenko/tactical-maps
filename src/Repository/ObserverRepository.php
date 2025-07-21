<?php

namespace App\Repository;

use App\Entity\Observer;
use App\Entity\Map;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Observer>
 */
class ObserverRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Observer::class);
    }

    /**
     * Find observer by access token
     */
    public function findByAccessToken(string $accessToken): ?Observer
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.accessToken = :token')
            ->setParameter('token', $accessToken)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find all observers for a specific map
     */
    public function findByMap(Map $map): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.map = :map')
            ->setParameter('map', $map)
            ->orderBy('o.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all observers with optional map filter
     */
    public function findAllWithMapFilter(?Map $map = null): array
    {
        $qb = $this->createQueryBuilder('o');
        
        if ($map) {
            $qb->andWhere('o.map = :map')
               ->setParameter('map', $map);
        }
        
        return $qb->orderBy('o.createdAt', 'DESC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Find observers by name (case-insensitive search)
     */
    public function findByNameLike(string $name): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('LOWER(o.name) LIKE LOWER(:name)')
            ->setParameter('name', '%' . $name . '%')
            ->orderBy('o.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count observers for a specific map
     */
    public function countByMap(Map $map): int
    {
        return $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->andWhere('o.map = :map')
            ->setParameter('map', $map)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Save observer entity
     */
    public function save(Observer $observer, bool $flush = false): void
    {
        $this->getEntityManager()->persist($observer);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Remove observer entity
     */
    public function remove(Observer $observer, bool $flush = false): void
    {
        $this->getEntityManager()->remove($observer);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
} 