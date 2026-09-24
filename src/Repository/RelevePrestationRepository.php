<?php

namespace App\Repository;

use App\Entity\Centre;
use App\Entity\RelevePrestation;
use App\Utils\MoneyToCents;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RelevePrestation>
 */
class RelevePrestationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RelevePrestation::class);
    }

    /**
     * Retourne le chiffre d'affaires des prestations, en centimes, pour une
     * période semi-ouverte : date >= $from et date < $until.
     */
    public function sumRevenueForPeriod(
        Centre $station,
        \DateTimeImmutable $from,
        \DateTimeImmutable $until,
    ): int
    {
        if ($until <= $from) {
            throw new \InvalidArgumentException('La fin de période doit être postérieure au début.');
        }

        /** @var array{cb: string|int, especes: string|int, cheque: string|int, bl: string|int} $totals */
        $totals = $this->createQueryBuilder('ligne')
            ->select('COALESCE(SUM(ligne.cb), 0) AS cb')
            ->addSelect('COALESCE(SUM(ligne.especes), 0) AS especes')
            ->addSelect('COALESCE(SUM(ligne.cheque), 0) AS cheque')
            ->addSelect('COALESCE(SUM(ligne.enCompte), 0) AS compte')
            ->addSelect('COALESCE(SUM(ligne.contrat), 0) AS contrat')
            ->innerJoin('ligne.releveJournalier', 'releve')
            ->andWhere('releve.centre = :station')
            ->andWhere('releve.dateReleve >= :from')
            ->andWhere('releve.dateReleve < :until')
            ->setParameter('station', $station)
            ->setParameter('from', $from)
            ->setParameter('until', $until)
            ->getQuery()
            ->getSingleResult();

        return MoneyToCents::moneyToCents((string) $totals['cb'])
            + MoneyToCents::moneyToCents((string) $totals['especes'])
            + MoneyToCents::moneyToCents((string) $totals['cheque'])
            + MoneyToCents::moneyToCents((string) $totals['compte'])
            + MoneyToCents::moneyToCents((string) $totals['contrat']);
    }
    /** @return list<array{date: \DateTimeInterface, revenueCents: int}> */
    public function sumRevenueByDate(
        Centre $station,
        \DateTimeImmutable $from,
        \DateTimeImmutable $until,
    ): array
    {
        if ($until <= $from) {
            throw new \InvalidArgumentException('La fin de période doit être postérieure au début.');
        }

        $rows = $this->createQueryBuilder('ligne')
            ->select('COALESCE(SUM(ligne.cb), 0) AS cb')
            ->addSelect('COALESCE(SUM(ligne.especes), 0) AS especes')
            ->addSelect('COALESCE(SUM(ligne.cheque), 0) AS cheque')
            ->addSelect('COALESCE(SUM(ligne.enCompte), 0) AS compte')
            ->addSelect('COALESCE(SUM(ligne.contrat), 0) AS contrat')
            ->innerJoin('ligne.releveJournalier', 'releve')
            ->andWhere('releve.centre = :station')
            ->andWhere('releve.dateReleve >= :from')
            ->andWhere('releve.dateReleve < :until')
            ->setParameter('station', $station)
            ->setParameter('from', $from)
            ->setParameter('until', $until)
            ->addSelect('releve.dateReleve AS date')
            ->groupBy('releve.dateReleve')
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($rows as $totals) {
            $result[] = ['date' => $totals['date'], 'revenueCents' => MoneyToCents::moneyToCents((string) $totals['cb'])
            + MoneyToCents::moneyToCents((string) $totals['especes'])
            + MoneyToCents::moneyToCents((string) $totals['cheque'])
            + MoneyToCents::moneyToCents((string) $totals['compte'])
            + MoneyToCents::moneyToCents((string) $totals['contrat'])];
        }

        return $result;
    }
}
