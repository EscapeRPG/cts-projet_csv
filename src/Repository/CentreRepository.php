<?php

namespace App\Repository;

use App\Entity\Centre;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Centre>
 */
class CentreRepository extends ServiceEntityRepository
{
    /**
     * @param ManagerRegistry $registry Doctrine manager registry.
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Centre::class);
    }

    /**
     * @return array<int, Centre>
     */
    public function findOrderedBySocieteVilleAgrSearch(?string $q): array
    {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.societe', 'so')
            ->addSelect('so')
            ->leftJoin('c.reseau', 'r')
            ->addSelect('r')
            ->orderBy('so.nom', 'ASC')
            ->addOrderBy('c.ville', 'ASC')
            ->addOrderBy('c.agrCentre', 'ASC');

        $this->applySearchFilter($qb, $q);

        return $qb->getQuery()->getResult();
    }

    private function applySearchFilter(QueryBuilder $qb, ?string $q): void
    {
        $q = trim((string) $q);
        if ($q === '') {
            return;
        }

        $like = '%' . mb_strtolower($q) . '%';
        $qb->andWhere(
            $qb->expr()->orX(
                'LOWER(c.agrCentre) LIKE :q',
                'LOWER(c.agrClCentre) LIKE :q',
                'LOWER(c.ville) LIKE :q',
                'LOWER(c.reseauNom) LIKE :q',
                'LOWER(r.nom) LIKE :q',
            )
        )->setParameter('q', $like);
    }

    //    /**
    //     * @return Centre[] Returns an array of Centre objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('c.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Centre
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
