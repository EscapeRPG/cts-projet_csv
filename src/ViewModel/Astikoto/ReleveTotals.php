<?php

namespace App\ViewModel\Astikoto;

final readonly class ReleveTotals
{
    public function __construct(
        public array $portiques,
        public array $bornes,
        public array $equipements,
        public array $produits,
        public array $prestations,
        public array $equipmentLines,
        public array $productLines,
        public array $prestationLines,
        public int $caJourCents,
    ) {
    }
}
