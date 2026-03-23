<?php

namespace App\Repository\Suivi;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Exception;

/**
 * Provides synthesized controller activity rows for monitoring pages.
 */
final readonly class SuiviSyntheseRepository extends AbstractSuiviQueryRepository
{
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

        if ($hasTypeFilter) {
            return $this->fetchSyntheseRowsByTypeFromRaw(
                $filters,
                $selectedTypeFamilies,
                $selectedVehicleTypes,
                $hasTypeFilter
            );
        }

        if ($hasVehicleFilter) {
            return $this->fetchSyntheseRowsByVehicleFromSynthese($filters, $selectedVehicleTypes);
        }

        $where = [];
        $params = [];
        $types = [];
        $this->applySyntheseDimensionFilters($filters, $where, $params, $types);

        $sql = $this->buildSyntheseControlesAggregationSql($this->getSyntheseDefaultMetricsSelect(), $where);

        return $this->connection->executeQuery($sql, $params, $types)->fetchAllAssociative();
    }

    /**
     * Returns synthesized rows filtered by vehicle categories using aggregated table columns.
     *
     * @param array<string, mixed> $filters Selected filters.
     * @param array<int, string> $selectedVehicleTypes Selected vehicle categories.
     *
     * @return array<int, array<string, mixed>> Synthesized activity rows.
     *
     * @throws Exception
     */
    private function fetchSyntheseRowsByVehicleFromSynthese(array $filters, array $selectedVehicleTypes): array
    {
        $where = [];
        $params = [];
        $types = [];
        $this->applySyntheseDimensionFilters($filters, $where, $params, $types);

        $isClOnly = $selectedVehicleTypes === ['CL'];
        $isVlOnly = $selectedVehicleTypes === ['VL'];

        if (!$isClOnly && !$isVlOnly) {
            return $this->fetchSyntheseRows($filters);
        }

        $sql = $this->buildSyntheseControlesAggregationSql(
            $this->getSyntheseVehicleMetricsSelect($isClOnly),
            $where
        );

        return $this->connection->executeQuery($sql, $params, $types)->fetchAllAssociative();
    }

    /**
     * Returns synthesized rows from raw source joins when type filtering is required.
     *
     * @param array<string, mixed> $filters Selected filters.
     * @param array<int, string> $selectedTypeFamilies Selected type families.
     * @param array<int, string> $selectedVehicleTypes Selected vehicle categories.
     * @param bool $applyTypeFilter Whether to apply explicit raw type filtering.
     *
     * @return array<int, array<string, mixed>> Synthesized activity rows from raw sources.
     *
     * @throws Exception
     */
    private function fetchSyntheseRowsByTypeFromRaw(
        array $filters,
        array $selectedTypeFamilies,
        array $selectedVehicleTypes,
        bool $applyTypeFilter
    ): array {
        $where = [];
        $params = [];
        $types = [];
        $this->applyRawDateAndDimensionFilters(
            $filters,
            $where,
            $params,
            $types,
            'ctrl.date_ctrl',
            "COALESCE(ce.reseau_nom, '')",
            "COALESCE(so.nom, 'Société inconnue')",
            "
                CASE
                    WHEN ce.agr_centre IS NULL
                        THEN CONCAT('Centre inconnu (', COALESCE(cc.agr_centre, '?'), ')')
                    ELSE ce.agr_centre
                END
            ",
            'COALESCE(sa.id, 0)'
        );

        if ($applyTypeFilter) {
            $rawTypes = $this->buildRawTypesFromFamilies($selectedTypeFamilies);
            $where[] = 'ctrl.type_ctrl IN (:raw_types)';
            $params['raw_types'] = $rawTypes;
            $types['raw_types'] = ArrayParameterType::STRING;
        }

        $this->applyVehicleFilter($where, 'ctrl.type_ctrl', $selectedVehicleTypes);

        $sql = "
            SELECT
                COALESCE(so.nom, 'Société inconnue') AS societe_nom,
                CASE
                    WHEN ce.agr_centre IS NULL
                        THEN CONCAT('Centre inconnu (', COALESCE(cc.agr_centre, '?'), ')')
                    ELSE ce.agr_centre
                END AS agr_centre,
                MIN(COALESCE(ce.ville, '')) AS centre_ville,
                MIN(COALESCE(ce.reseau_nom, '')) AS reseau_nom,
                COALESCE(sa.id, 0) AS salarie_id,
                COALESCE(sa.agr_controleur, cc.agr_controleur, 'Agrément inconnu') AS salarie_agr,
                MIN(CASE
                    WHEN sa.id IS NULL
                        THEN CONCAT('Salarié inconnu (', COALESCE(cc.agr_controleur, '?'), ')')
                    ELSE COALESCE(sa.nom, 'Salarié inconnu')
                END) AS salarie_nom,
                MIN(COALESCE(sa.prenom, '')) AS salarie_prenom,
                COUNT(DISTINCT ctrl.idcontrole) AS nb_controles,
                COUNT(DISTINCT IF(ctrl.type_ctrl IN ('VTP','VLCTP','VLVT','VLVP'), ctrl.idcontrole, NULL)) AS nb_vtp,
                COUNT(DISTINCT IF(ctrl.type_ctrl IN ('CLVTP','CLCTP'), ctrl.idcontrole, NULL)) AS nb_clvtp,
                COUNT(DISTINCT IF(ctrl.type_ctrl IN ('CV','VLCV','VLCVC'), ctrl.idcontrole, NULL)) AS nb_cv,
                COUNT(DISTINCT IF(ctrl.type_ctrl IN ('CLCV'), ctrl.idcontrole, NULL)) AS nb_clcv,
                COUNT(DISTINCT IF(ctrl.type_ctrl IN ('VTC','VLCTC'), ctrl.idcontrole, NULL)) AS nb_vtc,
                COUNT(DISTINCT IF(ctrl.type_ctrl IN ('VOL','VP','VT'), ctrl.idcontrole, NULL)) AS nb_vol,
                COUNT(DISTINCT IF(ctrl.type_ctrl IN ('CLVP','CLVT'), ctrl.idcontrole, NULL)) AS nb_clvol,
                COUNT(DISTINCT IF(ctrl.type_ctrl IN ('VTP','VLCTP','VLVT','VLVP','CV','VLCV','VLCVC','VTC','VLCTC','VOL','VP','VT'), ctrl.idcontrole, NULL)) AS nb_auto,
                COUNT(DISTINCT IF(ctrl.type_ctrl LIKE 'CL%', ctrl.idcontrole, NULL)) AS nb_moto,
                SUM(IF(f.type_facture IN ('F','A','D'), COALESCE(f.montant_presta_ht, f.total_ht) / t.nb_ctrl_facture, 0)) AS total_presta_ht,
                SUM(IF(ctrl.type_ctrl IN ('VTP','VLCTP','VLVT','VLVP') AND f.type_facture IN ('F','A','D'), COALESCE(f.montant_presta_ht, f.total_ht) / t.nb_ctrl_facture, 0)) AS total_ht_vtp,
                SUM(IF(ctrl.type_ctrl IN ('CLVTP','CLCTP') AND f.type_facture IN ('F','A','D'), COALESCE(f.montant_presta_ht, f.total_ht) / t.nb_ctrl_facture, 0)) AS total_ht_clvtp,
                SUM(IF(ctrl.type_ctrl IN ('CV','VLCV','VLCVC') AND f.type_facture IN ('F','A','D'), COALESCE(f.montant_presta_ht, f.total_ht) / t.nb_ctrl_facture, 0)) AS total_ht_cv,
                SUM(IF(ctrl.type_ctrl IN ('CLCV') AND f.type_facture IN ('F','A','D'), COALESCE(f.montant_presta_ht, f.total_ht) / t.nb_ctrl_facture, 0)) AS total_ht_clcv,
                SUM(IF(ctrl.type_ctrl IN ('VTC','VLCTC') AND f.type_facture IN ('F','A','D'), COALESCE(f.montant_presta_ht, f.total_ht) / t.nb_ctrl_facture, 0)) AS total_ht_vtc,
                SUM(IF(ctrl.type_ctrl IN ('VOL','VP','VT') AND f.type_facture IN ('F','A','D'), COALESCE(f.montant_presta_ht, f.total_ht) / t.nb_ctrl_facture, 0)) AS total_ht_vol,
                SUM(IF(ctrl.type_ctrl IN ('CLVP','CLVT') AND f.type_facture IN ('F','A','D'), COALESCE(f.montant_presta_ht, f.total_ht) / t.nb_ctrl_facture, 0)) AS total_ht_clvol,
                SUM(ctrl.temps_ctrl) AS temps_total,
                SUM(IF(ctrl.type_ctrl IN ('VTP','VLCTP','VLVT','VLVP','CV','VLCV','VLCVC','VTC','VLCTC','VOL','VP','VT'), ctrl.temps_ctrl, 0)) AS temps_total_auto,
                SUM(IF(ctrl.type_ctrl LIKE 'CL%', ctrl.temps_ctrl, 0)) AS temps_total_moto,
                COUNT(DISTINCT IF(ctrl.res_ctrl IN ('S','R','SP'), ctrl.idcontrole, NULL)) AS taux_refus,
                COUNT(DISTINCT IF(ctrl.type_ctrl IN ('VTP','VLCTP','VLVT','VLVP','CV','VLCV','VLCVC','VTC','VLCTC','VOL','VP','VT') AND ctrl.res_ctrl IN ('S','R','SP'), ctrl.idcontrole, NULL)) AS refus_auto,
                COUNT(DISTINCT IF(ctrl.type_ctrl LIKE 'CL%' AND ctrl.res_ctrl IN ('S','R','SP'), ctrl.idcontrole, NULL)) AS refus_moto,
                COUNT(DISTINCT IF(COALESCE(cc.has_pro_client, 0) = 0, ctrl.idcontrole, NULL)) AS nb_particuliers,
                COUNT(DISTINCT IF(COALESCE(cc.has_pro_client, 0) = 1, ctrl.idcontrole, NULL)) AS nb_professionnels
            " . $this->getRawSyntheseFromJoinsSql();

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= "
            GROUP BY
                COALESCE(so.nom, 'Société inconnue'),
                CASE
                    WHEN ce.agr_centre IS NULL
                        THEN CONCAT('Centre inconnu (', COALESCE(cc.agr_centre, '?'), ')')
                    ELSE ce.agr_centre
                END,
                COALESCE(sa.id, 0),
                COALESCE(sa.agr_controleur, cc.agr_controleur, 'Agrément inconnu')
            ORDER BY societe_nom, centre_ville, salarie_nom, salarie_prenom
        ";

        return $this->cachedRows(
            'suivi_raw_synthese',
            ['filters' => $filters, 'types' => $selectedTypeFamilies, 'vehicle' => $selectedVehicleTypes],
            fn() => $this->connection->executeQuery($sql, $params, $types)->fetchAllAssociative()
        );
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
                MIN(centre_ville) AS centre_ville,
                MIN(reseau_nom) AS reseau_nom,
                salarie_id,
                MIN(salarie_agr) AS salarie_agr,
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
            SUM(nb_vtp) AS nb_vtp,
            SUM(nb_clvtp) AS nb_clvtp,
            SUM(nb_cv) AS nb_cv,
            SUM(nb_clcv) AS nb_clcv,
            SUM(nb_vtc) AS nb_vtc,
            SUM(nb_vol) AS nb_vol,
            SUM(nb_clvol) AS nb_clvol,
            SUM(nb_auto) AS nb_auto,
            SUM(nb_moto) AS nb_moto,
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

    /**
     * Returns vehicle-specific SQL metrics projection for CL-only or VL-only mode.
     *
     * @param bool $isClOnly Whether to generate CL-only projection (otherwise VL-only).
     *
     * @return string SQL projection fragment.
     */
    private function getSyntheseVehicleMetricsSelect(bool $isClOnly): string
    {
        if ($isClOnly) {
            return "
                SUM(0) AS nb_vtp,
                SUM(nb_clvtp) AS nb_clvtp,
                SUM(0) AS nb_cv,
                SUM(nb_clcv) AS nb_clcv,
                SUM(0) AS nb_vtc,
                SUM(0) AS nb_vol,
                SUM(nb_clvol) AS nb_clvol,
                SUM(0) AS nb_auto,
                SUM(nb_moto) AS nb_moto,
                SUM(total_ht_clvtp + total_ht_clcv + total_ht_clvol) AS total_presta_ht,
                SUM(0) AS total_ht_vtp,
                SUM(total_ht_clvtp) AS total_ht_clvtp,
                SUM(0) AS total_ht_cv,
                SUM(total_ht_clcv) AS total_ht_clcv,
                SUM(0) AS total_ht_vtc,
                SUM(0) AS total_ht_vol,
                SUM(total_ht_clvol) AS total_ht_clvol,
                SUM(temps_total_moto) AS temps_total,
                SUM(0) AS temps_total_auto,
                SUM(temps_total_moto) AS temps_total_moto,
                SUM(refus_moto) AS taux_refus,
                SUM(0) AS refus_auto,
                SUM(refus_moto) AS refus_moto,
                SUM(nb_particuliers_moto) AS nb_particuliers,
                SUM(nb_professionnels_moto) AS nb_professionnels,
                SUM(nb_clvtp + nb_clcv + nb_clvol) AS nb_controles
            ";
        }

        return "
            SUM(nb_vtp) AS nb_vtp,
            SUM(0) AS nb_clvtp,
            SUM(nb_cv) AS nb_cv,
            SUM(0) AS nb_clcv,
            SUM(nb_vtc) AS nb_vtc,
            SUM(nb_vol) AS nb_vol,
            SUM(0) AS nb_clvol,
            SUM(nb_auto) AS nb_auto,
            SUM(0) AS nb_moto,
            SUM(total_ht_vtp + total_ht_cv + total_ht_vtc + total_ht_vol) AS total_presta_ht,
            SUM(total_ht_vtp) AS total_ht_vtp,
            SUM(0) AS total_ht_clvtp,
            SUM(total_ht_cv) AS total_ht_cv,
            SUM(0) AS total_ht_clcv,
            SUM(total_ht_vtc) AS total_ht_vtc,
            SUM(total_ht_vol) AS total_ht_vol,
            SUM(0) AS total_ht_clvol,
            SUM(temps_total_auto) AS temps_total,
            SUM(temps_total_auto) AS temps_total_auto,
            SUM(0) AS temps_total_moto,
            SUM(refus_auto) AS taux_refus,
            SUM(refus_auto) AS refus_auto,
            SUM(0) AS refus_moto,
            SUM(nb_particuliers_auto) AS nb_particuliers,
            SUM(nb_professionnels_auto) AS nb_professionnels,
            SUM(nb_vtp + nb_cv + nb_vtc + nb_vol) AS nb_controles
        ";
    }

    /**
     * Returns the reusable FROM/JOIN SQL block for raw synthesized aggregation.
     *
     * @return string SQL fragment.
     */
    private function getRawSyntheseFromJoinsSql(): string
    {
        return "
            FROM controles ctrl
            LEFT JOIN (
                SELECT
                    cc.idcontrole,
                    MIN(cc.agr_centre) AS agr_centre,
                    MIN(cc.agr_controleur) AS agr_controleur,
                    MAX(COALESCE(cli_ref.has_pro, 0)) AS has_pro_client
                FROM clients_controles cc
                LEFT JOIN (
                    SELECT
                        idclient,
                        MAX(
                            CASE
                                WHEN TRIM(COALESCE(code_client, '')) <> ''
                                     AND TRIM(code_client) NOT REGEXP '^0+$'
                                THEN 1
                                ELSE 0
                            END
                        ) AS has_pro
                    FROM clients
                    GROUP BY idclient
                ) cli_ref ON cli_ref.idclient = cc.idclient
                GROUP BY cc.idcontrole
            ) cc ON cc.idcontrole = ctrl.idcontrole
            LEFT JOIN (
                SELECT id, nom, prenom, agr_controleur, agr_key
                FROM (
                    SELECT
                        s.id,
                        s.nom,
                        s.prenom,
                        s.agr_controleur,
                        s.agr_key,
                        ROW_NUMBER() OVER (PARTITION BY s.agr_key ORDER BY s.is_primary DESC, s.id ASC) AS rn
                    FROM (
                        SELECT
                            id,
                            nom,
                            prenom,
                            agr_controleur,
                            agr_controleur AS agr_key,
                            1 AS is_primary
                        FROM salarie
                        WHERE agr_controleur IS NOT NULL AND TRIM(agr_controleur) <> ''
                        UNION ALL
                        SELECT
                            id,
                            nom,
                            prenom,
                            agr_controleur,
                            agr_cl_controleur AS agr_key,
                            0 AS is_primary
                        FROM salarie
                        WHERE agr_cl_controleur IS NOT NULL AND TRIM(agr_cl_controleur) <> ''
                    ) s
                ) ranked
                WHERE ranked.rn = 1
            ) sa ON sa.agr_key = cc.agr_controleur
            LEFT JOIN centre ce ON ce.agr_centre = cc.agr_centre
            LEFT JOIN societe so ON so.id = ce.societe_id
            LEFT JOIN (
                SELECT DISTINCT idcontrole, idfacture
                FROM controles_factures
            ) cf ON cf.idcontrole = ctrl.idcontrole
            LEFT JOIN factures f ON f.idfacture = cf.idfacture
            LEFT JOIN (
                SELECT cf.idfacture, COUNT(DISTINCT cf.idcontrole) AS nb_ctrl_facture
                FROM (
                    SELECT DISTINCT idcontrole, idfacture
                    FROM controles_factures
                ) cf
                INNER JOIN factures f2 ON f2.idfacture = cf.idfacture
                WHERE f2.type_facture IN ('F','A','D')
                GROUP BY cf.idfacture
            ) t ON t.idfacture = f.idfacture
        ";
    }
}
