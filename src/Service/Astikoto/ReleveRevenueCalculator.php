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

