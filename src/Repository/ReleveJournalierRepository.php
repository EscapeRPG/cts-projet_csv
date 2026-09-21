<?php

namespace App\Repository;

use App\Entity\Centre;
use App\Entity\ReleveJournalier;
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
}
