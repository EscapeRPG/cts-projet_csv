<?php

namespace App\Service\Astikoto;

use App\Entity\Centre;
use App\Repository\ReleveEquipementRepository;
use App\Repository\RelevePrestationRepository;
use App\Repository\ReleveProduitRepository;
use App\ViewModel\Astikoto\MonthlyRevenue;

final readonly class ReleveRevenueCalculator
{
    public function __construct(
        private ReleveEquipementRepository $equipementRepository,
        private ReleveProduitRepository $produitRepository,
        private RelevePrestationRepository $prestationRepository,
    ) {
    }

    /**
     * @throws \DateMalformedStringException
     */
    public function getMonthlyRevenue(
        Centre $station,
        \DateTimeImmutable $month,
    ): MonthlyRevenue {
        $from = $month->modify('first day of this month')->setTime(0, 0);
        $until = $from->modify('first day of next month');

        return new MonthlyRevenue(
            $this->equipementRepository->sumRevenueForPeriod($station, $from, $until),
            $this->produitRepository->sumRevenueForPeriod($station, $from, $until),
            $this->prestationRepository->sumRevenueForPeriod($station, $from, $until),
        );
    }

    /**
     * Totaux des années civiles N-2 à N, pour tous les relevés enregistrés.
     * @return list<array{year: int, revenueCents: int, evolution: ?float}>
     */
    public function getAnnualRevenues(Centre $station, \DateTimeImmutable $referenceDate): array
    {
        return $this->buildAnnualRevenues($this->getMonthlyRevenues($station, $referenceDate), $referenceDate);
    }

    /** @return list<array{annee: int, mois: int, revenueCents: int, ca: float}> */
    public function getMonthlyRevenues(Centre $station, \DateTimeImmutable $referenceDate): array
    {
        $year = (int) $referenceDate->format('Y');
        $from = $referenceDate->setDate($year - 2, 1, 1)->setTime(0, 0);
        $until = $from->modify('+3 years');
        $months = [];
        foreach ([$this->equipementRepository, $this->produitRepository, $this->prestationRepository] as $repository) {
            foreach ($repository->sumRevenueByDate($station, $from, $until) as $row) {
                $key = $row['date']->format('Y-m');
                $months[$key] ??= [
                    'annee' => (int) $row['date']->format('Y'),
                    'mois' => (int) $row['date']->format('m'),
                    'revenueCents' => 0,
                    'ca' => 0.0,
                ];
                $months[$key]['revenueCents'] += $row['revenueCents'];
            }
        }
        ksort($months);
        foreach ($months as &$month) {
            $month['ca'] = $month['revenueCents'] / 100.0;
        }
        unset($month);

        return array_values($months);
    }

    /**
     * @param list<array{annee: int, mois: int, revenueCents: int, ca: float}> $months
     * @return list<array{year: int, revenueCents: int, evolution: ?float}>
     */
    public function buildAnnualRevenues(array $months, \DateTimeImmutable $referenceDate): array
    {
        $referenceYear = (int) $referenceDate->format('Y');
        $totals = [];
        foreach ($months as $month) {
            $totals[$month['annee']] = ($totals[$month['annee']] ?? 0) + $month['revenueCents'];
        }
        $years = [];
        foreach (range($referenceYear - 2, $referenceYear) as $year) {
            $years[] = [
                'year' => $year,
                'revenueCents' => $totals[$year] ?? 0,
                'evolution' => null,
            ];
        }

        foreach ([0, 1] as $index) {
            $years[$index]['evolution'] = $this->compareRevenues($years[2]['revenueCents'], $years[$index]['revenueCents']);
        }

        return $years;
    }

    /**
     * Calcule l'évolution de $current par rapport à $reference.
     *
     * Retourne null lorsque la référence vaut zéro et que le revenu courant
     * est non nul : dans ce cas, aucun pourcentage fini n'est calculable.
     */
    public function compareRevenues(int $current, int $reference): ?float
    {
        if ($reference === 0) {
            return $current === 0 ? 0.0 : null;
        }

        return (($current - $reference) / abs($reference)) * 100;
    }
}

