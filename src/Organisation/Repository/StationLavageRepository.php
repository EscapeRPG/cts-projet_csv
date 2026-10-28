<?php

namespace App\Organisation\Repository;

use App\Organisation\Entity\Centre;
use App\Organisation\Entity\EquipementStation;
use App\ReleveLavage\Entity\PortiqueImporte;
use App\ReleveLavage\Entity\ReleveEquipement;
use App\ReleveLavage\Entity\ReleveJournalier;
use App\ReleveLavage\Entity\RelevePrestation;
use App\ReleveLavage\Entity\ReleveProduit;
use App\Organisation\Enum\CategorieEquipement;
use App\Organisation\Enum\TypeCentre;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Centre>
 */
class StationLavageRepository extends ServiceEntityRepository
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
    public function findStationsLavage(?string $q, ?array $centreIds = null): array
    {
        if ($centreIds !== null && $centreIds === []) {
            return [];
        }

        $qb = $this->createQueryBuilder('c')
            ->andWhere('c.type = :centreType')
            ->setParameter('centreType', TypeCentre::STATION_LAVAGE)
            ->leftJoin('c.societe', 'so')
            ->addSelect('so')
            ->leftJoin('c.reseau', 'r')
            ->addSelect('r')
            ->orderBy('so.nom', 'ASC')
            ->addOrderBy('c.ville', 'ASC');

        $this->applyCentreScopeFilter($qb, $centreIds);
        $this->applySearchFilter($qb, $q);

        return $qb->getQuery()->getResult();
    }

    private function applySearchFilter(QueryBuilder $qb, ?string $q): void
    {
        $q = trim((string)$q);
        if ($q === '') {
            return;
        }

        $like = '%' . mb_strtolower($q) . '%';
        $qb->andWhere(
            $qb->expr()->orX(
                'LOWER(c.ville) LIKE :q',
                'LOWER(c.reseauNom) LIKE :q',
                'LOWER(r.nom) LIKE :q',
            )
        )->setParameter('q', $like);
    }

    public function findOneStationInScope(
        int    $id,
        ?array $centreIds,
    ): ?Centre
    {
        if ($centreIds === []) {
            return null;
        }

        $qb = $this->createQueryBuilder('c')
            ->andWhere('c.id = :id')
            ->andWhere('c.type = :type')
            ->setParameter('id', $id)
            ->setParameter('type', TypeCentre::STATION_LAVAGE);

        $this->applyCentreScopeFilter($qb, $centreIds);

        return $qb->getQuery()->getOneOrNullResult();
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
}
