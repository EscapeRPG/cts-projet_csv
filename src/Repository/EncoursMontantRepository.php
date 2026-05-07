<?php

namespace App\Repository;

use App\Entity\EncoursMontant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EncoursMontant>
 */
class EncoursMontantRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EncoursMontant::class);
    }

    public function getYears(): array
    {
        return $this->createQueryBuilder('e')
            ->select('DISTINCT e.annee')
            ->orderBy('e.annee', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();
    }

    //    /**
    //     * @return EncoursMontant[] Returns an array of EncoursMontant objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('e')
    //            ->andWhere('e.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('e.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?EncoursMontant
    //    {
    //        return $this->createQueryBuilder('e')
    //            ->andWhere('e.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
