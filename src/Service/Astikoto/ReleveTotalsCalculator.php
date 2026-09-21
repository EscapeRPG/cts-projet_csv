<?php

namespace App\Service\Astikoto;

use App\Enum\CategorieEquipement;
use App\Form\Model\ReleveJournalierDTO;
use App\Utils\MoneyToCents;
use App\ViewModel\Astikoto\ReleveTotals;

final readonly class ReleveTotalsCalculator
{
    public function calculate(ReleveJournalierDTO $releve): ReleveTotals
    {
        $portiques = $this->equipmentTotals();
        $bornes = $this->equipmentTotals();
        $equipements = $this->equipmentTotals();
        $produits = $this->emptyTotals(['cb', 'especes', 'cheque', 'bl']);
        $prestations = $this->emptyTotals(['cb', 'especes', 'cheque', 'enCompte', 'contrat']);
        $equipmentLines = $productLines = $prestationLines = [];

        foreach ($releve->relevesEquipements as $index => $line) {
            $values = [
                'cb' => MoneyToCents::moneyToCents($line->cb),
                'especes' => MoneyToCents::moneyToCents($line->especes),
                'cheque' => MoneyToCents::moneyToCents($line->cheque),
                'jetons' => $line->jetons,
                'bl' => MoneyToCents::moneyToCents($line->bl),
            ];
            $values['total'] = $values['cb'] + $values['especes'] + $values['cheque'] + $values['bl'];
            $equipmentLines[$index] = $values['total'];
            $this->add($equipements, $values);
            if ($line->categorie === CategorieEquipement::PORTIQUE) {
                $this->add($portiques, $values);
            } elseif ($line->categorie === CategorieEquipement::BORNE) {
                $this->add($bornes, $values);
            }
        }

        foreach ($releve->relevesProduits as $index => $line) {
            $values = $this->entityValues($line, ['cb', 'especes', 'cheque', 'bl']);
            $productLines[$index] = $values['total'];
            $this->add($produits, $values);
        }

        foreach ($releve->relevesPrestations as $index => $line) {
            $values = $this->entityValues($line, ['cb', 'especes', 'cheque', 'enCompte', 'contrat']);
            $prestationLines[$index] = $values['total'];
            $this->add($prestations, $values);
        }

        return new ReleveTotals(
            $portiques, $bornes, $equipements, $produits, $prestations,
            $equipmentLines, $productLines, $prestationLines,
            $equipements['total'] + $produits['total'] + $prestations['total'],
        );
    }

    private function equipmentTotals(): array
    {
        return ['cb' => 0, 'especes' => 0, 'cheque' => 0, 'jetons' => 0, 'bl' => 0, 'total' => 0];
    }

    private function emptyTotals(array $fields): array
    {
        return array_fill_keys([...$fields, 'total'], 0);
    }

    private function entityValues(object $line, array $fields): array
    {
        $values = [];
        foreach ($fields as $field) {
            $getter = 'get'.ucfirst($field);
            $values[$field] = MoneyToCents::moneyToCents($line->$getter());
        }
        $values['total'] = array_sum($values);

        return $values;
    }

    private function add(array &$target, array $values): void
    {
        foreach ($values as $field => $value) {
            $target[$field] += $value;
        }
    }
}
