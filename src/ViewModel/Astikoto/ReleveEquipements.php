<?php

namespace App\ViewModel\Astikoto;

final readonly class ReleveEquipements
{
    /**
     * @param list<ReleveEquipementRow> $portiques
     * @param list<ReleveEquipementRow> $bornes
     * @param list<ReleveEquipementRow> $autres
     */
    public function __construct(
        public array $portiques,
        public array $bornes,
        public array $autres,
    ) {
    }
}
