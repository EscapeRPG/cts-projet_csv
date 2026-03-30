<?php

namespace App\Repository\Suivi;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Exception;

/**
 * Provides synthesized controller activity rows for monitoring pages.
 */
final readonly class SuiviSyntheseRepository extends AbstractSuiviQueryRepository
{
    private const array SYNTHESIS_TYPE_METRICS = [
        'vtp' => [
            'family' => 'VTP',
            'vehicle' => 'VL',
        ],
        'clvtp' => [
            'family' => 'VTP',
            'vehicle' => 'CL',
        ],
        'cv' => [
            'family' => 'CV',
            'vehicle' => 'VL',
        ],
        'clcv' => [
            'family' => 'CV',
            'vehicle' => 'CL',
        ],
        'vtc' => [
            'family' => 'VTC',
            'vehicle' => 'VL',
        ],
        'vol' => [
            'family' => 'VOL',
            'vehicle' => 'VL',
        ],
        'clvol' => [
            'family' => 'VOL',
            'vehicle' => 'CL',
        ],
    ];

    /**
     * Returns synthesized rows for selected filters.
     *
     * @param array<string, mixed> $filters Selected filters.
     *
     * @return array<int, array<string, mixed>> Synthesized activity rows.
     *
     * @throws Exception
     */
    public function fetchSyntheseRows(array $filters = []): array
    {
        $selectedTypeFamilies = $this->normalizeTypeFamilies($filters['type'] ?? []);
        $selectedVehicleTypes = $this->normalizeVehicleTypes($filters['vehicule'] ?? []);

        $hasTypeFilter = !empty($filters['type']) && !$this->isAllTypeFamiliesSelected($selectedTypeFamilies);
        $hasVehicleFilter = !$this->isAllVehicleTypesSelected($selectedVehicleTypes);

        $where = [];
        $params = [];
        $types = [];
        $this->applySyntheseDimensionFilters($filters, $where, $params, $types);

        $metricsSelect = ($hasTypeFilter || $hasVehicleFilter)
            ? $this->getSyntheseFilteredMetricsSelect($selectedTypeFamilies, $selectedVehicleTypes)
            : $this->getSyntheseDefaultMetricsSelect();

        $sql = $this->buildSyntheseControlesAggregationSql($metricsSelect, $where);

        return $this->connection->executeQuery($sql, $params, $types)->fetchAllAssociative();
    }

    /**
     * Builds a filtered metrics projection from `synthese_controles`.
     *
     * @param array<int, string> $selectedTypeFamilies Selected type families.
     * @param array<int, string> $selectedVehicleTypes Selected vehicle categories.
     *
     * @return string SQL projection fragment.
     */
    private function getSyntheseFilteredMetricsSelect(array $selectedTypeFamilies, array $selectedVehicleTypes): string
    {
        $selectedSubtypes = [];
        foreach (self::SYNTHESIS_TYPE_METRICS as $subtype => $config) {
            if (
                in_array($config['family'], $selectedTypeFamilies, true)
                && in_array($config['vehicle'], $selectedVehicleTypes, true)
            ) {
                $selectedSubtypes[] = $subtype;
            }
        }

        if ($selectedSubtypes === []) {
            return $this->getSyntheseZeroMetricsSelect();
        }

        $perSubtypeMetrics = [];
        foreach (array_keys(self::SYNTHESIS_TYPE_METRICS) as $subtype) {
            $perSubtypeMetrics[] = in_array($subtype, $selectedSubtypes, true)
                ? "SUM(nb_{$subtype}) AS nb_{$subtype}"
                : "SUM(0) AS nb_{$subtype}";
        }
        foreach (array_keys(self::SYNTHESIS_TYPE_METRICS) as $subtype) {
            $perSubtypeMetrics[] = in_array($subtype, $selectedSubtypes, true)
                ? "SUM(nb_{$subtype}_factures) AS nb_{$subtype}_factures"
                : "SUM(0) AS nb_{$subtype}_factures";
        }
        foreach (array_keys(self::SYNTHESIS_TYPE_METRICS) as $subtype) {
            $perSubtypeMetrics[] = in_array($subtype, $selectedSubtypes, true)
                ? "SUM(total_ht_{$subtype}) AS total_ht_{$subtype}"
                : "SUM(0) AS total_ht_{$subtype}";
        }

        $autoSubtypes = array_values(array_filter(
            $selectedSubtypes,
            static fn (string $subtype): bool => self::SYNTHESIS_TYPE_METRICS[$subtype]['vehicle'] === 'VL'
        ));
        $motoSubtypes = array_values(array_filter(
            $selectedSubtypes,
            static fn (string $subtype): bool => self::SYNTHESIS_TYPE_METRICS[$subtype]['vehicle'] === 'CL'
        ));

        return implode(",\n            ", array_merge(
            [
                "SUM(" . $this->buildAdditionExpression($selectedSubtypes, 'nb_') . ") AS nb_controles",
                "SUM(" . $this->buildAdditionExpression($selectedSubtypes, 'nb_', $selectedSubtypes, '_factures') . ") AS nb_controles_factures",
            ],
            $perSubtypeMetrics,
            [
                "SUM(" . $this->buildAdditionExpression($selectedSubtypes, 'nb_', $autoSubtypes) . ") AS nb_auto",
                "SUM(" . $this->buildAdditionExpression($selectedSubtypes, 'nb_', $autoSubtypes, '_factures') . ") AS nb_auto_factures",
                "SUM(" . $this->buildAdditionExpression($selectedSubtypes, 'nb_', $motoSubtypes) . ") AS nb_moto",
                "SUM(" . $this->buildAdditionExpression($selectedSubtypes, 'nb_', $motoSubtypes, '_factures') . ") AS nb_moto_factures",
                "SUM(" . $this->buildAdditionExpression($selectedSubtypes, 'total_ht_') . ") AS total_presta_ht",
                "SUM(" . $this->buildAdditionExpression($selectedSubtypes, 'temps_total_') . ") AS temps_total",
                "SUM(" . $this->buildAdditionExpression($selectedSubtypes, 'temps_total_', $autoSubtypes) . ") AS temps_total_auto",
                "SUM(" . $this->buildAdditionExpression($selectedSubtypes, 'temps_total_', $motoSubtypes) . ") AS temps_total_moto",
                "SUM(" . $this->buildAdditionExpression($selectedSubtypes, 'refus_') . ") AS taux_refus",
                "SUM(" . $this->buildAdditionExpression($selectedSubtypes, 'refus_', $autoSubtypes) . ") AS refus_auto",
                "SUM(" . $this->buildAdditionExpression($selectedSubtypes, 'refus_', $motoSubtypes) . ") AS refus_moto",
                "SUM(" . $this->buildAdditionExpression($selectedSubtypes, 'nb_', $selectedSubtypes, '_particuliers') . ") AS nb_particuliers",
                "SUM(" . $this->buildAdditionExpression($selectedSubtypes, 'nb_', $selectedSubtypes, '_professionnels') . ") AS nb_professionnels",
            ]
        ));
    }

    /**
     * Returns a zeroed metrics projection for impossible filter combinations.
     *
     * @return string SQL projection fragment.
     */
    private function getSyntheseZeroMetricsSelect(): string
    {
        $metrics = ['nb_controles'];
        $metrics[] = 'nb_controles_factures';
        foreach (array_keys(self::SYNTHESIS_TYPE_METRICS) as $subtype) {
            $metrics[] = 'nb_' . $subtype;
        }
        foreach (array_keys(self::SYNTHESIS_TYPE_METRICS) as $subtype) {
            $metrics[] = 'nb_' . $subtype . '_factures';
        }
        $metrics[] = 'nb_auto';
        $metrics[] = 'nb_auto_factures';
        $metrics[] = 'nb_moto';
        $metrics[] = 'nb_moto_factures';
        $metrics[] = 'total_presta_ht';
        foreach (array_keys(self::SYNTHESIS_TYPE_METRICS) as $subtype) {
            $metrics[] = 'total_ht_' . $subtype;
        }
        $metrics[] = 'temps_total';
        $metrics[] = 'temps_total_auto';
        $metrics[] = 'temps_total_moto';
        $metrics[] = 'taux_refus';
        $metrics[] = 'refus_auto';
        $metrics[] = 'refus_moto';
        $metrics[] = 'nb_particuliers';
        $metrics[] = 'nb_professionnels';

        return implode(",\n            ", array_map(
            static fn (string $metric): string => "SUM(0) AS {$metric}",
            $metrics
        ));
    }

    /**
     * Builds an additive SQL expression for selected subtype columns.
     *
     * @param array<int, string> $selectedSubtypes Selected subtype suffixes.
     * @param string $prefix Column prefix.
     * @param array<int, string>|null $subset Optional subset override.
     * @param string $suffix Optional column suffix.
     *
     * @return string SQL expression.
     */
    private function buildAdditionExpression(
        array $selectedSubtypes,
        string $prefix,
        ?array $subset = null,
        string $suffix = ''
    ): string {
        $targetSubtypes = $subset ?? $selectedSubtypes;
        if ($targetSubtypes === []) {
            return '0';
        }

        return implode(' + ', array_map(
            static fn (string $subtype): string => "{$prefix}{$subtype}{$suffix}",
            $targetSubtypes
        ));
    }

    /**
     * Builds the aggregation SQL over `synthese_controles` with provided metrics projection.
     *
     * @param string $metricsSelect SQL projection for metrics columns.
     * @param array<int, string> $where WHERE clauses.
     *
     * @return string SQL query.
     */
    private function buildSyntheseControlesAggregationSql(string $metricsSelect, array $where): string
    {
        $sql = "
            SELECT
                MIN(societe_nom) AS societe_nom,
                agr_centre,
                MIN(agr_centre_cl) AS agr_centre_cl,
                MIN(centre_ville) AS centre_ville,
                MIN(reseau_nom) AS reseau_nom,
                salarie_id,
                MIN(salarie_agr) AS salarie_agr,
                MIN(salarie_agr_cl) AS salarie_agr_cl,
                MIN(salarie_nom) AS salarie_nom,
                MIN(salarie_prenom) AS salarie_prenom,
                {$metricsSelect}
            FROM synthese_controles
        ";

        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= "
            GROUP BY salarie_id, salarie_agr, agr_centre, societe_nom
            ORDER BY societe_nom, centre_ville, salarie_nom, salarie_prenom
        ";

        return $sql;
    }

    /**
     * Returns the default SQL metrics projection for full synthesized aggregation.
     *
     * @return string SQL projection fragment.
     */
    private function getSyntheseDefaultMetricsSelect(): string
    {
        return "
            SUM(nb_controles) AS nb_controles,
            SUM(nb_controles_factures) AS nb_controles_factures,
            SUM(nb_vtp) AS nb_vtp,
            SUM(nb_vtp_factures) AS nb_vtp_factures,
            SUM(nb_clvtp) AS nb_clvtp,
            SUM(nb_clvtp_factures) AS nb_clvtp_factures,
            SUM(nb_cv) AS nb_cv,
            SUM(nb_cv_factures) AS nb_cv_factures,
            SUM(nb_clcv) AS nb_clcv,
            SUM(nb_clcv_factures) AS nb_clcv_factures,
            SUM(nb_vtc) AS nb_vtc,
            SUM(nb_vtc_factures) AS nb_vtc_factures,
            SUM(nb_vol) AS nb_vol,
            SUM(nb_vol_factures) AS nb_vol_factures,
            SUM(nb_clvol) AS nb_clvol,
            SUM(nb_clvol_factures) AS nb_clvol_factures,
            SUM(nb_auto) AS nb_auto,
            SUM(nb_auto_factures) AS nb_auto_factures,
            SUM(nb_moto) AS nb_moto,
            SUM(nb_moto_factures) AS nb_moto_factures,
            SUM(total_presta_ht) AS total_presta_ht,
            SUM(total_ht_vtp) AS total_ht_vtp,
            SUM(total_ht_clvtp) AS total_ht_clvtp,
            SUM(total_ht_cv) AS total_ht_cv,
            SUM(total_ht_clcv) AS total_ht_clcv,
            SUM(total_ht_vtc) AS total_ht_vtc,
            SUM(total_ht_vol) AS total_ht_vol,
            SUM(total_ht_clvol) AS total_ht_clvol,
            SUM(temps_total) AS temps_total,
            SUM(temps_total_auto) AS temps_total_auto,
            SUM(temps_total_moto) AS temps_total_moto,
            SUM(taux_refus) AS taux_refus,
            SUM(refus_auto) AS refus_auto,
            SUM(refus_moto) AS refus_moto,
            SUM(nb_particuliers) AS nb_particuliers,
            SUM(nb_professionnels) AS nb_professionnels
        ";
    }

}
