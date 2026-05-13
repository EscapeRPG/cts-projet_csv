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

    public function getYears(?string $type = null, ?array $societeScopeIds = null): array
    {
        $qb = $this->createQueryBuilder('e')
            ->select('DISTINCT e.annee AS annee');

        $qb->innerJoin('e.encours', 'b');

        if ($type !== null) {
            $qb->andWhere('b.type = :type')
                ->setParameter('type', $type);
        }

        if (is_array($societeScopeIds)) {
            if ($societeScopeIds === []) {
                $qb->andWhere('1 = 0');
            } else {
                $qb->innerJoin('b.societe', 's')
                    ->andWhere('s.id IN (:societeScopeIds)')
                    ->setParameter('societeScopeIds', $societeScopeIds);
            }
        }

        $qb->orderBy('e.annee', 'ASC');

        /** @var array<int, array{annee: int|string|null}> $rows */
        $rows = $qb->getQuery()->getScalarResult();

        return array_values(array_filter(array_map(static function (array $row): ?int {
            $v = $row['annee'] ?? null;
            if ($v === null) return null;
            if (is_int($v)) return $v;
            $s = trim((string) $v);
            return ctype_digit($s) ? (int) $s : null;
        }, $rows)));
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
