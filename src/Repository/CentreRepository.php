<?php

namespace App\Repository;

use App\Entity\Centre;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
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
    public function findOrderedBySocieteVilleAgrSearch(?string $q, ?array $centreIds = null): array
    {
        if ($centreIds !== null && $centreIds === []) {
            return [];
        }

        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.societe', 'so')
            ->addSelect('so')
            ->leftJoin('c.reseau', 'r')
            ->addSelect('r')
            ->orderBy('so.nom', 'ASC')
            ->addOrderBy('c.ville', 'ASC')
            ->addOrderBy('c.agrCentre', 'ASC');

        $this->applyCentreScopeFilter($qb, $centreIds);
        $this->applySearchFilter($qb, $q);

        return $qb->getQuery()->getResult();
    }

    private function applyCentreScopeFilter(QueryBuilder $qb, ?array $centreIds): void
    {
        if ($centreIds === null) {
            return;
        }

        $qb
            ->andWhere('c.id IN (:centreIds)')
            ->setParameter('centreIds', $centreIds);
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

    /**
     * @throws Exception
     */
    public function getFirstResultsPerCenter(): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = "WITH ranked AS (
          SELECT
            s.agr_centre,
            s.agr_centre_cl,
            s.annee,
            s.mois,
            ROW_NUMBER() OVER (
              PARTITION BY s.agr_centre
              ORDER BY (s.annee * 100 + s.mois) ASC
            ) AS rn
          FROM synthese_controles s
        )

        SELECT
          c.reseau_nom,
          c.ville AS centre_ville,
          c.agr_centre,
          COALESCE(r.agr_centre_cl, c.agr_cl_centre) AS agr_centre_cl,
          r.annee,
          r.mois,
          c.date_reprise,
          CASE
            WHEN r.annee IS NULL OR r.mois IS NULL THEN NULL
            ELSE DATE_FORMAT(
              STR_TO_DATE(CONCAT(r.annee, '-', LPAD(r.mois, 2, '0'), '-01'), '%Y-%m-%d'),
              '%m-%Y'
            )
          END AS premiere_entree
        FROM centre c
        LEFT JOIN ranked r
          ON r.agr_centre = c.agr_centre
         AND r.rn = 1
        ORDER BY
          (r.annee IS NULL) ASC,
          r.annee ASC,
          r.mois ASC,
          c.reseau_nom ASC,
          c.ville ASC;";

        $stmt = $conn->executeQuery($sql);

        return $stmt->fetchAllAssociative();
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
