<?php

namespace App\Repository;

use App\Entity\Side;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Side>
 *
 * @method Side|null find($id, $lockMode = null, $lockVersion = null)
 * @method Side|null findOneBy(array $criteria, array $orderBy = null)
 * @method Side[]    findAll()
 * @method Side[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SideRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Side::class);
    }

    /**
     * Save a side
     */
    public function save(Side $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Remove a side
     */
    public function remove(Side $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find sides by partial name match
     *
     * @param string $term The search term
     * @return Side[] Returns an array of matching Side objects
     */
    public function findByNameLike(string $term): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.name LIKE :term')
            ->setParameter('term', '%' . $term . '%')
            ->orderBy('s.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get an array of side IDs for all sides
     * Useful for visibility settings
     *
     * @return int[] Array of side IDs
     */
    public function getAllSideIds(): array
    {
        $sides = $this->createQueryBuilder('s')
            ->select('s.id')
            ->getQuery()
            ->getResult();
            
        return array_map(fn($item) => $item['id'], $sides);
    }
} 