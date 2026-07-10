<?php

namespace App\Repository\Suivi;

use App\Repository\Suivi\AbstractSuiviQueryRepository;

readonly class SuiviModesReglementRepository extends AbstractSuiviQueryRepository
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
     * Returns payment-mode aggregates for the selected follow-up filters.
     *
     * The query keeps one row per period, payment mode, center, and controller so the service can build either
     * center-level or controller-level tables without re-querying.
     *
     * @param array<string, mixed> $filters Normalized suivi filters.
     *
     * @return array<int, array<string, mixed>> Aggregated payment-mode rows from `synthese_reglements`.
     *
     * @throws \Doctrine\DBAL\Exception If the SQL query or cache-backed fetch fails.
     * @throws \Psr\Cache\InvalidArgumentException If the generated cache key is invalid.
     */
    public function fetchModesReglement(array $filters = []): array
    {
        $selectedTypeFamilies = $this->normalizeTypeFamilies($filters['type'] ?? []);
        $selectedVehicleTypes = $this->normalizeVehicleTypes($filters['vehicule'] ?? []);

        $where = [];
        $params = [];
        $types = [];
        $this->applySyntheseDimensionFilters(
            $filters,
            $where,
            $params,
            $types,
            yearParam: 'annees',
            uniqueMonths: true
        );

        $metricsSelect = [];

        foreach (self::SYNTHESIS_TYPE_METRICS as $suffix => $config) {
            $isSelected = in_array($config['family'], $selectedTypeFamilies, true) && in_array($config['vehicle'], $selectedVehicleTypes, true);

            if ($isSelected) {
                $metricsSelect[] = "SUM(nb_{$suffix}) AS nb_{$suffix}";
                $metricsSelect[] = "SUM(montant_regle_{$suffix}) AS montant_regle_{$suffix}";
            } else {
                $metricsSelect[] = "SUM(0) AS nb_{$suffix}";
                $metricsSelect[] = "SUM(0) AS montant_regle_{$suffix}";
            }
        }

        $metricsSelectSql = implode(', ', $metricsSelect);

        $sql = "
            SELECT
                mode_reglt,
                agr_centre,
                MIN(agr_centre_cl) AS agr_centre_cl,
                MIN(centre_ville) AS centre_ville,
                MIN(societe_nom) AS societe_nom,
                MIN(reseau_nom) AS reseau_nom,
                salarie_id,
                MIN(salarie_nom) AS salarie_nom,
                MIN(salarie_prenom) AS salarie_prenom,
                salarie_agr,
                MIN(salarie_agr_cl) AS salarie_agr_cl,
                annee,
                mois,
                {$metricsSelectSql}
            FROM synthese_reglements
        ";

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= "
            GROUP BY annee, mois, mode_reglt, agr_centre, salarie_id, salarie_agr
            ORDER BY societe_nom, agr_centre, salarie_nom, salarie_prenom, annee, mois
        ";

        return $this->cachedRows(
            'suivi_modes_reglement_v3',
            ['filters' => $filters, 'types' => $selectedTypeFamilies, 'vehicle' => $selectedVehicleTypes],
            fn() => $this->connection->executeQuery($sql, $params, $types)->fetchAllAssociative()
        );
    }
}
