<?php

namespace App\Repository;

use App\Entity\Voiture;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Voiture>
 */
class VoitureRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Voiture::class);
    }

    /**
     * Returns a paginated list of cars ordered by company then license plate.
     *
     * The inline-edit list builds one form per row: pagination is required to keep
     * memory usage reasonable (dev toolbar/profiler stores a lot of form metadata).
     *
     * @param int $limit Maximum number of rows to return.
     * @param int $offset Row offset for pagination.
     *
     * @return array<int, Voiture>
     */
    public function findPaginatedOrderedBySociete(int $limit, int $offset): array
    {
        return $this->createQueryBuilder('v')
            ->leftJoin('v.societe', 'so')
            ->addSelect('so')
            ->orderBy('so.nom', 'ASC')
            ->addOrderBy('v.immatriculation', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    //    /**
    //     * @return Voiture[] Returns an array of Voiture objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('v')
    //            ->andWhere('v.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('v.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Voiture
    //    {
    //        return $this->createQueryBuilder('v')
    //            ->andWhere('v.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
