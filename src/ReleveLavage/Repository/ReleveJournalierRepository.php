<?php

namespace App\ReleveLavage\Repository;

use App\Organisation\Entity\Centre;
use App\ReleveLavage\Entity\ReleveJournalier;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ReleveJournalier>
 */
class ReleveJournalierRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReleveJournalier::class);
    }

    public function findOneByCentreAndDate(Centre $centre, \DateTimeImmutable $date): ?ReleveJournalier
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.centre = :centre')
            ->andWhere('r.dateReleve = :date')
            ->setParameter('centre', $centre)
            ->setParameter('date', $date)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return list<ReleveJournalier> */
    public function findForPeriod(Centre $centre, \DateTimeImmutable $from, \DateTimeImmutable $until): array
    {
        $periodQuery = $this->createQueryBuilder('r')
            ->andWhere('r.centre = :centre')
            ->andWhere('r.dateReleve >= :from AND r.dateReleve < :until')
            ->setParameter('centre', $centre)
            ->setParameter('from', $from)
            ->setParameter('until', $until)
            ->orderBy('r.dateReleve', 'ASC');

        // Load each collection separately to avoid both per-day queries and a Cartesian product.
        $releves = [];
        foreach (['relevesEquipements', 'relevesProduits', 'relevesPrestations'] as $association) {
            $query = clone $periodQuery;
            $query->leftJoin('r.'.$association, 'line')->addSelect('line');
            if ($association === 'relevesEquipements') {
                $query->leftJoin('line.equipement', 'equipment')->addSelect('equipment');
            }
            $releves = $query->getQuery()->getResult();
            if ($releves === []) break;
        }

        return $releves;
    }
}
