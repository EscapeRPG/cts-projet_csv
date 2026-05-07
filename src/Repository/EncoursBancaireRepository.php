<?php

namespace App\Repository;

use App\Entity\EncoursBancaire;
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

        return $qb->getQuery()->getResult();
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
