<?php

namespace App\Repository;

use App\Entity\Societe;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Societe>
 */
class SocieteRepository extends ServiceEntityRepository
{
    /**
     * @param ManagerRegistry $registry Doctrine manager registry.
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Societe::class);
    }

    /**
     * @return array<int, Societe>
     */
    public function findOrderedByNomSearch(?string $q): array
    {
        $qb = $this->createQueryBuilder('s')
            ->orderBy('s.nom', 'ASC');

        $this->applySearchFilter($qb, $q);

        return $qb->getQuery()->getResult();
    }

    private function applySearchFilter(QueryBuilder $qb, ?string $q): void
    {
        $q = trim((string) $q);
        if ($q === '') {
            return;
        }

        $qb
            ->andWhere('LOWER(s.nom) LIKE :q')
            ->setParameter('q', '%' . mb_strtolower($q) . '%');
    }

    //    /**
    //     * @return Societe[] Returns an array of Societe objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('s')
    //            ->andWhere('s.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('s.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Societe
    //    {
    //        return $this->createQueryBuilder('s')
    //            ->andWhere('s.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
