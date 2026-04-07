<?php

namespace App\Repository;

use App\Entity\Salarie;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
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
     * Counts employees matching the search query.
     */
    public function countSearch(?string $q): int
    {
        $qb = $this->createQueryBuilder('s')
            ->select('COUNT(s.id)');

        $this->applySearchFilter($qb, $q);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Returns a paginated list of employees ordered by company then identity, filtered by search query.
     *
     * @return array<int, Salarie>
     */
    public function findPaginatedOrderedBySocieteSearch(int $limit, int $offset, ?string $q): array
    {
        $qb = $this->createQueryBuilder('s')
            ->leftJoin('s.societe', 'so')
            ->addSelect('so')
            ->orderBy('so.nom', 'DESC')
            ->addOrderBy('s.nom', 'ASC')
            ->addOrderBy('s.prenom', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        $this->applySearchFilter($qb, $q);

        return $qb->getQuery()->getResult();
    }

    /**
     * Returns employees ordered by identity, filtered by search query.
     *
     * @return array<int, Salarie>
     */
    public function findOrderedByNomPrenomSearch(?string $q): array
    {
        $qb = $this->createQueryBuilder('s')
            ->leftJoin('s.societe', 'so')
            ->addSelect('so')
            ->orderBy('s.nom', 'ASC')
            ->addOrderBy('s.prenom', 'ASC');

        $this->applySearchFilter($qb, $q);

        return $qb->getQuery()->getResult();
    }

    private function applySearchFilter(QueryBuilder $qb, ?string $q): void
    {
        $q = trim((string) $q);
        if ($q === '') {
            return;
        }

        // Tokenized search: each token must match at least one field.
        $tokens = preg_split('/\s+/u', $q) ?: [];
        $tokens = array_values(array_filter(array_map('trim', $tokens), static fn (string $t): bool => $t !== ''));
        if ($tokens === []) {
            return;
        }

        foreach ($tokens as $i => $token) {
            $param = 'tok_' . $i;
            $like = '%' . mb_strtolower($token) . '%';

            $qb->andWhere(
                $qb->expr()->orX(
                    'LOWER(s.nom) LIKE :' . $param,
                    'LOWER(s.prenom) LIKE :' . $param,
                    'LOWER(s.agrControleur) LIKE :' . $param,
                    'LOWER(s.agrClControleur) LIKE :' . $param,
                )
            )->setParameter($param, $like);
        }
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
