<?php

namespace App\ViewModel\Astikoto;

final readonly class MonthlyRevenue
{
    public function __construct(
        public int $equipementsCents,
        public int $produitsCents,
        public int $prestationsCents,
    ) {
    }

    public function getTotalCents(): int
    {
        return $this->equipementsCents
            + $this->produitsCents
            + $this->prestationsCents;
    }
}
