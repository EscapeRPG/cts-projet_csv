<?php

namespace App\ReleveLavage\Service;

use App\ReleveLavage\Entity\ReleveJournalier;
use App\Shared\Money\MoneyToCents;

final class RelevePeriodSummary
{
    /** @param iterable<ReleveJournalier> $releves */
    public function build(iterable $releves): array
    {
        $sections = [
            'equipements' => ['title' => 'Station', 'fields' => ['cb' => 'CB', 'especes' => 'Espèces', 'cheque' => 'Chèques', 'jetons' => 'Jetons', 'bl' => 'BL']],
            'produits' => ['title' => 'Produits', 'fields' => ['cb' => 'CB', 'especes' => 'Espèces', 'cheque' => 'Chèques', 'bl' => 'BL']],
            'prestations' => ['title' => 'Prestations', 'fields' => ['cb' => 'CB', 'especes' => 'Espèces', 'cheque' => 'Chèques', 'enCompte' => 'En compte', 'contrat' => 'Contrat']],
        ];
        foreach ($sections as &$section) {
            $section['rows'] = [];
            $section['totals'] = array_fill_keys([...array_keys($section['fields']), 'total'], 0);
        }
        unset($section);
        $count = $validated = 0;
        $comments = [];
        foreach ($releves as $releve) {
            ++$count;
            $validated += (int) $releve->isValidated();
            if (trim($releve->getCommentaire() ?? '') !== '') {
                $comments[] = ['date' => $releve->getDateReleve(), 'text' => $releve->getCommentaire()];
            }
            foreach ($sections as $name => &$section) {
                $getter = 'getReleves'.ucfirst($name);
                foreach ($releve->$getter() as $line) {
                    if ($name === 'equipements') {
                        $equipment = $line->getEquipement();
                        $key = (string) ($equipment?->getId() ?? spl_object_id($equipment ?? $line));
                        $label = $equipment?->getLibelle() ?? 'Équipement';
                        $category = $equipment?->getCategorie()?->value ?? '';
                    } else {
                        $label = $name === 'produits' ? $line->getDesignation() : $line->getNom();
                        $key = trim($label ?? '');
                        $category = '';
                    }
                    $section['rows'][$key] ??= ['label' => $label, 'category' => $category, 'values' => array_fill_keys(array_keys($section['totals']), 0)];
                    $total = 0;
                    foreach ($section['fields'] as $field => $_) {
                        $fieldGetter = 'get'.ucfirst($field);
                        $value = $field === 'jetons' ? $line->$fieldGetter() : MoneyToCents::moneyToCents($line->$fieldGetter());
                        $section['rows'][$key]['values'][$field] += $value;
                        $section['totals'][$field] += $value;
                        if ($field !== 'jetons') $total += $value;
                    }
                    $section['rows'][$key]['values']['total'] += $total;
                    $section['totals']['total'] += $total;
                }
            }
            unset($section);
        }
        foreach ($sections as &$section) {
            uasort($section['rows'], static fn (array $a, array $b): int =>
                strcmp($a['category'], $b['category']) ?: strnatcasecmp($a['label'] ?? '', $b['label'] ?? ''));
        }
        unset($section);

        return ['sections' => $sections, 'count' => $count, 'validatedCount' => $validated,
            'totalCents' => array_sum(array_column(array_column($sections, 'totals'), 'total')), 'comments' => $comments];
    }
}
