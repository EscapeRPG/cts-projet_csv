<?php

namespace App\Repository;

use App\Entity\EncoursBancaire;
use App\Entity\Societe;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EncoursBancaire>
 */
class EncoursBancaireRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EncoursBancaire::class);
    }

    /**
     * @throws Exception
     */
    public function getResults(?int $societe = null, ?string $type = null, ?array $societeScopeIds = null): array
    {
        $qb = $this->createQueryBuilder('e')
            ->leftJoin('e.societe', 's')
            ->addSelect('s')
            ->leftJoin('e.montants', 'm')
            ->addSelect('m');

        if ($societe !== null) {
            $qb->andWhere('e.societe = :societe')
                ->setParameter('societe', $societe);
        }

        if (is_array($societeScopeIds)) {
            if ($societeScopeIds === []) {
                // No access.
                $qb->andWhere('1 = 0');
            } else {
                $qb->andWhere('s.id IN (:societeScopeIds)')
                    ->setParameter('societeScopeIds', $societeScopeIds);
            }
        }

        if ($type !== null) {
            $qb->andWhere('e.type = :type')
                ->setParameter('type', $type);
        }

        // Order for display: societes (manual order) then centres within societes (manual order),
        // then keep a stable tie-breaker.
        $qb->orderBy('s.orderViewEncours', 'ASC')
            ->addOrderBy('s.nom', 'ASC')
            ->addOrderBy('e.centreOrderView', 'ASC')
            ->addOrderBy('e.centre', 'ASC')
            ->addOrderBy('m.annee', 'ASC');

        return $qb->getQuery()->getResult();
    }

    /**
     * Used for auto-assigning a centre order when new encours are created.
     *
     * @return list<array{centre:string|null, order:int|null}>
     */
    public function getCentreOrdersForSociete(Societe $societe): array
    {
        /** @var array<int, array{centre: string|null, ord: int|string|null}> $rows */
        $rows = $this->createQueryBuilder('e')
            ->select('e.centre AS centre', 'e.centreOrderView AS ord')
            ->andWhere('e.societe = :societe')
            ->setParameter('societe', $societe)
            ->getQuery()
            ->getArrayResult();

        $out = [];
        foreach ($rows as $row) {
            $centre = isset($row['centre']) ? (string) $row['centre'] : null;
            $ord = $row['ord'] ?? null;
            $out[] = [
                'centre' => $centre,
                'order' => is_numeric($ord) ? (int) $ord : null,
            ];
        }

        return $out;
    }

    public function getNextCentreOrderForSociete(Societe $societe): int
    {
        $raw = $this->createQueryBuilder('e')
            ->select('MAX(e.centreOrderView)')
            ->andWhere('e.societe = :societe')
            ->setParameter('societe', $societe)
            ->getQuery()
            ->getSingleScalarResult();

        $max = is_numeric($raw) ? (int) $raw : 0;
        return max(0, $max) + 1;
    }

    //    /**
    //     * @return EncoursBancaire[] Returns an array of EncoursBancaire objects
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

    //    public function findOneBySomeField($value): ?EncoursBancaire
    //    {
    //        return $this->createQueryBuilder('e')
    //            ->andWhere('e.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
