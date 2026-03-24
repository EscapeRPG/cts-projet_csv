<?php

namespace App\Repository;

use App\Entity\Salarie;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Salarie>
 */
class SalarieRepository extends ServiceEntityRepository
{
    /**
     * @param ManagerRegistry $registry Doctrine manager registry.
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Salarie::class);
    }

    /**
     * Returns a paginated list of employees ordered by company then identity.
     *
     * @param int $limit Maximum number of rows to return.
     * @param int $offset Row offset for pagination.
     *
     * @return array<int, Salarie> Ordered employees page.
     */
    public function findPaginatedOrderedBySociete(int $limit, int $offset): array
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.societe', 'so')
            ->addSelect('so')
            ->orderBy('so.nom', 'DESC')
            ->addOrderBy('s.nom', 'ASC')
            ->addOrderBy('s.prenom', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Returns active employees with a defined birth date.
     *
     * @return array<int, Salarie>
     */
    public function findActiveWithBirthday(): array
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.societe', 'so')
            ->addSelect('so')
            ->andWhere('s.isActive = :isActive')
            ->andWhere('s.dateNaissance IS NOT NULL')
            ->setParameter('isActive', true)
            ->orderBy('s.nom', 'ASC')
            ->addOrderBy('s.prenom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    //    /**
    //     * @return Salarie[] Returns an array of Salarie objects
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

    //    public function findOneBySomeField($value): ?Salarie
    //    {
    //        return $this->createQueryBuilder('s')
    //            ->andWhere('s.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
