<?php

namespace App\Service\Astikoto;

use App\Entity\ReleveEquipement;
use App\Entity\ReleveJournalier;
use App\Enum\CategorieEquipement;
use App\ViewModel\Astikoto\ReleveEquipementRow;
use App\ViewModel\Astikoto\ReleveEquipements;

final readonly class ReleveEquipementsViewBuilder
{
    public function build(ReleveJournalier $releve): ReleveEquipements
    {
        $portiques = [];
        $bornes = [];
        $autres = [];
        $formIndexes = [];
        $relevesParEquipement = [];
        $equipmentLines = $releve->getRelevesEquipements()->toArray();
        usort($equipmentLines, self::compareEquipmentLines(...));

        foreach ($equipmentLines as $index => $ligne) {
            $equipement = $ligne->getEquipement();
            if ($equipement !== null) {
                $key = spl_object_id($equipement);
                $formIndexes[$key] = (int) $index;
                $relevesParEquipement[$key] = $ligne;
            }
        }

        foreach ($equipmentLines as $index => $ligne) {
            $equipement = $ligne->getEquipement();
            $categorie = $equipement?->getCategorie();
            $portique = $equipement?->getPortiqueAssocie();
            $portiqueKey = $portique !== null ? spl_object_id($portique) : null;
            $row = new ReleveEquipementRow(
                (int) $index,
                $ligne,
                $portiqueKey !== null ? ($formIndexes[$portiqueKey] ?? null) : null,
                $portiqueKey !== null ? ($relevesParEquipement[$portiqueKey] ?? null) : null,
            );

            match ($categorie) {
                CategorieEquipement::PORTIQUE => $portiques[] = $row,
                CategorieEquipement::BORNE => $bornes[] = $row,
                default => $autres[] = $row,
            };
        }

        $sort = static fn (ReleveEquipementRow $a, ReleveEquipementRow $b): int => strnatcasecmp(
            $a->getLibelle(),
            $b->getLibelle(),
        );
        usort($portiques, $sort);
        usort($bornes, $sort);
        usort($autres, $sort);

        return new ReleveEquipements($portiques, $bornes, $autres);
    }

    private static function compareEquipmentLines(ReleveEquipement $left, ReleveEquipement $right): int
    {
        return ($left->getEquipement()?->getId() ?? PHP_INT_MAX)
            <=> ($right->getEquipement()?->getId() ?? PHP_INT_MAX);
    }
}
