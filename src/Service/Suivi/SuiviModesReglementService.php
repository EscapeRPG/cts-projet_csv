<?php

namespace App\Service\Suivi;

final class SuiviModesReglementService
{
    private const array TYPES = [
        'vtp' => ['label' => 'VTP', 'nb' => 'nb_vtp', 'montant' => 'montant_regle_vtp'],
        'clvtp' => ['label' => 'CLVTP', 'nb' => 'nb_clvtp', 'montant' => 'montant_regle_clvtp'],
        'cv' => ['label' => 'CV', 'nb' => 'nb_cv', 'montant' => 'montant_regle_cv'],
        'clcv' => ['label' => 'CLCV', 'nb' => 'nb_clcv', 'montant' => 'montant_regle_clcv'],
        'vtc' => ['label' => 'VTC', 'nb' => 'nb_vtc', 'montant' => 'montant_regle_vtc'],
        'vol' => ['label' => 'VOL', 'nb' => 'nb_vol', 'montant' => 'montant_regle_vol'],
        'clvol' => ['label' => 'CLVOL', 'nb' => 'nb_clvol', 'montant' => 'montant_regle_clvol'],
    ];

    /**
     * Builds normalized payment-mode table rows for the selected display mode.
     *
     * @param array<int, array<string, mixed>> $rows Aggregated rows from `synthese_reglements`.
     * @param string $selectedTable Selected table mode: `centres` or `salaries`.
     *
     * @return array{
     *     0: array<int, string>,
     *     1: array<string, array{label: string, rows: array<int, array<string, mixed>>}>
     * } Tuple containing payment mode columns and rows grouped by control type.
     */
    public function buildModesReglementRows(array $rows, string $selectedTable): array
    {
        return $selectedTable === 'salaries'
            ? $this->buildForSalaries($rows)
            : $this->buildForCentres($rows);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array{0: array<int, string>, 1: array<string, array{label: string, rows: array<int, array<string, mixed>>}>}
     */
    private function buildForCentres(array $rows): array
    {
        $modesReglt = [];
        $types = [];

        foreach ($rows as $row) {
            $mode = $this->normalizeMode($row);
            $centre = $this->formatCentre($row);
            $societe = strtoupper((string)($row['societe_nom'] ?? 'Société inconnue'));
            $centreKey = $societe . '|' . $centre;

            foreach (self::TYPES as $type => $config) {
                $nb = (int)($row[$config['nb']] ?? 0);
                $montant = (float)($row[$config['montant']] ?? 0);

                if ($nb === 0 && $montant === 0.0) {
                    continue;
                }

                $modesReglt[$mode] = $mode;
                $this->ensureType($types, $type);

                if (!isset($types[$type]['groups'][$societe])) {
                    $types[$type]['groups'][$societe] = [
                        'rows' => [],
                        'summary' => $this->makeDisplayRow('summary', $societe, ''),
                    ];
                }

                if (!isset($types[$type]['groups'][$societe]['rows'][$centreKey])) {
                    $types[$type]['groups'][$societe]['rows'][$centreKey] = $this->makeDisplayRow(
                        'data',
                        trim(($row['reseau_nom'] ?? '') . ' ' . ($row['centre_ville'] ?? '')),
                        $centre
                    );
                }

                $this->addCellValue($types[$type]['groups'][$societe]['rows'][$centreKey], $mode, $nb, $montant);
                $this->addCellValue($types[$type]['groups'][$societe]['summary'], $mode, $nb, $montant);
            }
        }

        return [
            $this->sortModesReglt($modesReglt),
            $this->flattenGroupedTypes($types),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array{0: array<int, string>, 1: array<string, array{label: string, rows: array<int, array<string, mixed>>}>}
     */
    private function buildForSalaries(array $rows): array
    {
        $modesReglt = [];
        $types = [];

        foreach ($rows as $row) {
            $mode = $this->normalizeMode($row);
            $centre = $this->formatCentre($row);
            $societe = strtoupper((string)($row['societe_nom'] ?? 'Société inconnue'));
            $centreKey = $societe . '|' . $centre;
            $salarieId = (int)($row['salarie_id'] ?? 0);
            $salarieAgr = (string)($row['salarie_agr'] ?? '');
            $salarieKey = $centreKey . '|' . $salarieId . '|' . $salarieAgr;

            foreach (self::TYPES as $type => $config) {
                $nb = (int)($row[$config['nb']] ?? 0);
                $montant = (float)($row[$config['montant']] ?? 0);

                if ($nb === 0 && $montant === 0.0) {
                    continue;
                }

                $modesReglt[$mode] = $mode;
                $this->ensureType($types, $type);

                if (!isset($types[$type]['groups'][$centreKey])) {
                    $types[$type]['groups'][$centreKey] = [
                        'rows' => [],
                        'summary' => $this->makeDisplayRow(
                            'summary',
                            trim(($row['reseau_nom'] ?? '') . ' ' . ($row['centre_ville'] ?? '')),
                            $centre
                        ),
                    ];
                }

                if (!isset($types[$type]['groups'][$centreKey]['rows'][$salarieKey])) {
                    $types[$type]['groups'][$centreKey]['rows'][$salarieKey] = $this->makeDisplayRow(
                        'data',
                        trim(strtoupper((string)($row['salarie_nom'] ?? 'Contrôleur inconnu')) . ' ' . ucfirst(strtolower((string)($row['salarie_prenom'] ?? '')))),
                        $this->formatSalarieAgreement($row)
                    );
                }

                $this->addCellValue($types[$type]['groups'][$centreKey]['rows'][$salarieKey], $mode, $nb, $montant);
                $this->addCellValue($types[$type]['groups'][$centreKey]['summary'], $mode, $nb, $montant);
            }
        }

        return [
            $this->sortModesReglt($modesReglt),
            $this->flattenGroupedTypes($types),
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $types
     */
    private function ensureType(array &$types, string $type): void
    {
        if (isset($types[$type])) {
            return;
        }

        $types[$type] = [
            'label' => self::TYPES[$type]['label'],
            'groups' => [],
        ];
    }

    /**
     * @return array{kind: string, label: string, detail: string, modes: array<string, array{nb: int, montant: float}>, total: array{nb: int, montant: float}}
     */
    private function makeDisplayRow(string $kind, string $label, string $detail): array
    {
        return [
            'kind' => $kind,
            'label' => $label,
            'detail' => $detail,
            'modes' => [],
            'total' => ['nb' => 0, 'montant' => 0.0],
        ];
    }

    /**
     * @param array{modes: array<string, array{nb: int, montant: float}>, total: array{nb: int, montant: float}} $displayRow
     */
    private function addCellValue(array &$displayRow, string $mode, int $nb, float $montant): void
    {
        if (!isset($displayRow['modes'][$mode])) {
            $displayRow['modes'][$mode] = ['nb' => 0, 'montant' => 0.0];
        }

        $displayRow['modes'][$mode]['nb'] += $nb;
        $displayRow['modes'][$mode]['montant'] += $montant;
        $displayRow['total']['nb'] += $nb;
        $displayRow['total']['montant'] += $montant;
    }

    /**
     * @param array<string, array{label: string, groups: array<string, array{rows: array<string, array<string, mixed>>, summary: array<string, mixed>}>}> $types
     * @return array<string, array{label: string, rows: array<int, array<string, mixed>>}>
     */
    private function flattenGroupedTypes(array $types): array
    {
        $tableByType = [];

        foreach ($this->sortTypes($types) as $type => $typeData) {
            $rows = [];

            foreach ($typeData['groups'] as $group) {
                foreach ($group['rows'] as $row) {
                    $rows[] = $row;
                }

                $rows[] = $group['summary'];
            }

            $tableByType[$type] = [
                'label' => $typeData['label'],
                'rows' => $rows,
            ];
        }

        return $tableByType;
    }

    /**
     * @param array<string, string> $modesReglt
     * @return array<int, string>
     */
    private function sortModesReglt(array $modesReglt): array
    {
        $modeOrder = ['Carte', 'Espèces', 'Chèque', 'Internet', 'Autre'];

        return array_values(array_filter(
            $modeOrder,
            static fn(string $mode): bool => isset($modesReglt[$mode])
        ));
    }

    private function normalizeMode(array $row): string
    {
        $mode = trim((string)($row['mode_reglt'] ?? 'Inconnu'));

        return $mode !== '' ? $mode : 'Inconnu';
    }

    private function formatCentre(array $row): string
    {
        $centre = strtoupper((string)($row['agr_centre'] ?? 'Centre inconnu'));
        if (!empty($row['agr_centre_cl'])) {
            $centre .= ' - ' . strtoupper((string)$row['agr_centre_cl']);
        }

        return $centre;
    }

    private function formatSalarieAgreement(array $row): string
    {
        $agreement = (string)($row['salarie_agr'] ?? '');
        if (!empty($row['salarie_agr_cl'])) {
            $agreement .= ' - ' . (string)$row['salarie_agr_cl'];
        }

        return $agreement;
    }

    /**
     * @param array<string, array<string, mixed>> $types
     * @return array<string, array<string, mixed>>
     */
    private function sortTypes(array $types): array
    {
        return array_filter(
            array_replace(array_fill_keys(array_keys(self::TYPES), null), $types),
            static fn($typeData): bool => $typeData !== null
        );
    }
}
