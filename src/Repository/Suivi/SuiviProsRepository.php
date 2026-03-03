<?php

namespace App\Repository\Suivi;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Exception;

/**
 * Provides professional-client aggregates for activity monitoring pages.
 */
final readonly class SuiviProsRepository extends AbstractSuiviQueryRepository
{
    /**
     * Returns professional-client aggregates for selected filters.
     *
     * @param array<string, mixed> $filters Selected filters.
     *
     * @return array<int, array<string, mixed>> Aggregated professional-client rows.
     *
     * @throws Exception
     */
    public function fetchProClients(array $filters = []): array
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
            yearParam: null,
            includeControleur: false
        );

        $hasTypeFilter = !empty($filters['type']) && !$this->isAllTypeFamiliesSelected($selectedTypeFamilies);
        if ($hasTypeFilter && !$this->hasDetailedTypeColumns()) {
            return $this->fetchProClientsByTypeFromRaw(
                $filters,
                $selectedTypeFamilies,
                $selectedVehicleTypes,
                true
            );
        }

        [$selectNbControles, $selectCa] = $this->buildProsMetricExpressions($selectedTypeFamilies, $selectedVehicleTypes);

        $sql = "
            SELECT
                code_client,
                annee,
                mois,
                {$selectCa} AS ca,
                {$selectNbControles} AS nb_controles,
                agr_centre,
                societe_nom,
                reseau_id,
                reseau_nom
            FROM synthese_pros
        ";
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= " ORDER BY {$selectCa} DESC";

        return $this->cachedRows(
            'suivi_pros_synthese',
            ['filters' => $filters, 'types' => $selectedTypeFamilies, 'vehicle' => $selectedVehicleTypes],
            fn() => $this->connection->executeQuery($sql, $params, $types)->fetchAllAssociative()
        );
    }

    /**
     * Builds SQL expressions for controls count and revenue metrics.
     *
     * @param array<int, string> $selectedTypeFamilies Selected type families.
     * @param array<int, string> $selectedVehicleTypes Selected vehicle categories.
     *
     * @return array{0:string,1:string} Count expression and revenue expression.
     */
    private function buildProsMetricExpressions(array $selectedTypeFamilies, array $selectedVehicleTypes): array
    {
        $nbByFamilyVehicle = [
            'VTP' => ['VL' => 'nb_vtp', 'CL' => 'nb_clvtp'],
            'CV' => ['VL' => 'nb_cv', 'CL' => 'nb_clcv'],
            'VTC' => ['VL' => 'nb_vtc'],
            'VOL' => ['VL' => 'nb_vol', 'CL' => 'nb_clvol'],
        ];

        $caByFamilyVehicle = [
            'VTP' => ['VL' => 'ca_vtp', 'CL' => 'ca_clvtp'],
            'CV' => ['VL' => 'ca_cv', 'CL' => 'ca_clcv'],
            'VTC' => ['VL' => 'ca_vtc'],
            'VOL' => ['VL' => 'ca_vol', 'CL' => 'ca_clvol'],
        ];

        $selectedFamilies = $selectedTypeFamilies === [] ? self::TYPE_FAMILIES : $selectedTypeFamilies;
        $selectedVehicles = $selectedVehicleTypes === [] ? self::VEHICLE_TYPES : $selectedVehicleTypes;

        $nbColumns = [];
        $caColumns = [];

        foreach ($selectedFamilies as $family) {
            foreach ($selectedVehicles as $vehicle) {
                if (isset($nbByFamilyVehicle[$family][$vehicle])) {
                    $nbColumns[] = $nbByFamilyVehicle[$family][$vehicle];
                }
                if (isset($caByFamilyVehicle[$family][$vehicle])) {
                    $caColumns[] = $caByFamilyVehicle[$family][$vehicle];
                }
            }
        }

        $nbColumns = array_values(array_unique($nbColumns));
        $caColumns = array_values(array_unique($caColumns));

        $nbExpr = $nbColumns === [] ? '0' : implode(' + ', $nbColumns);
        $caExpr = $caColumns === [] ? '0' : implode(' + ', $caColumns);

        return [$nbExpr, $caExpr];
    }

    /**
     * Indicates whether detailed per-type columns are available in `synthese_pros`.
     *
     * @return bool True when all detailed type columns are present.
     *
     * @throws Exception
     */
    private function hasDetailedTypeColumns(): bool
    {
        return (int)$this->connection->fetchOne(
            "
                SELECT COUNT(*)
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'synthese_pros'
                  AND COLUMN_NAME IN ('ca_vtp','ca_clvtp','ca_cv','ca_clcv','ca_vtc','ca_vol','ca_clvol','nb_vtp','nb_clvtp','nb_cv','nb_clcv','nb_vtc','nb_vol','nb_clvol')
            "
        ) === 14;
    }

    /**
     * Returns professional-client aggregates from raw source joins.
     *
     * @param array<string, mixed> $filters Selected filters.
     * @param array<int, string> $selectedTypeFamilies Selected type families.
     * @param array<int, string> $selectedVehicleTypes Selected vehicle categories.
     * @param bool $applyTypeFilter Whether to apply explicit raw type filtering.
     *
     * @return array<int, array<string, mixed>> Aggregated rows from raw sources.
     *
     * @throws Exception
     */
    private function fetchProClientsByTypeFromRaw(
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
            'c.date_ctrl',
            "COALESCE(ce.reseau_nom, '')",
            "COALESCE(so.nom, 'Société inconnue')",
            "COALESCE(cc.agr_centre, 'Centre inconnu')"
        );

        if ($applyTypeFilter) {
            $rawTypes = $this->buildRawTypesFromFamilies($selectedTypeFamilies);
            $where[] = 'c.type_ctrl IN (:raw_types)';
            $params['raw_types'] = $rawTypes;
            $types['raw_types'] = ArrayParameterType::STRING;
        }

        $this->applyVehicleFilter($where, 'c.type_ctrl', $selectedVehicleTypes);
        $where[] = "fa.type_facture = 'F'";
        $where[] = "fa.total_ht > 0";
        $where[] = "c.res_ctrl IN ('A','AP')";
        $where[] = 'cli_ref.has_pro = 1';

        $sql = "
            SELECT
                cli_ref.nom_code_client AS code_client,
                YEAR(c.date_ctrl) AS annee,
                MONTH(c.date_ctrl) AS mois,
                SUM(COALESCE(fa.montant_presta_ht, fa.total_ht) / t.nb_ctrl_facture) AS ca,
                COUNT(DISTINCT c.idcontrole) AS nb_controles,
                COALESCE(cc.agr_centre, 'Centre inconnu') AS agr_centre,
                COALESCE(so.nom, 'Société inconnue') AS societe_nom,
                c.reseau_id AS reseau_id,
                COALESCE(ce.reseau_nom, '') AS reseau_nom
            " . $this->getRawProsFromJoinsSql();

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= "
            GROUP BY
                cli_ref.nom_code_client,
                YEAR(c.date_ctrl),
                MONTH(c.date_ctrl),
                COALESCE(cc.agr_centre, 'Centre inconnu'),
                COALESCE(so.nom, 'Société inconnue'),
                c.reseau_id,
                COALESCE(ce.reseau_nom, '')
            ORDER BY ca DESC
        ";

        return $this->cachedRows(
            'suivi_raw_pros',
            ['filters' => $filters, 'types' => $selectedTypeFamilies, 'vehicle' => $selectedVehicleTypes],
            fn() => $this->connection->executeQuery($sql, $params, $types)->fetchAllAssociative()
        );
    }

    /**
     * Returns the reusable FROM/JOIN SQL block for raw professional-client aggregation.
     *
     * @return string SQL fragment.
     */
    private function getRawProsFromJoinsSql(): string
    {
        return "
            FROM controles c
            INNER JOIN (
                SELECT DISTINCT idcontrole, idfacture
                FROM controles_factures
            ) cf ON cf.idcontrole = c.idcontrole
            INNER JOIN factures fa ON fa.idfacture = cf.idfacture
            INNER JOIN (
                SELECT DISTINCT idcontrole, idclient, agr_centre
                FROM clients_controles
            ) cc ON cc.idcontrole = c.idcontrole
            INNER JOIN (
                SELECT
                    idclient,
                    COALESCE(NULLIF(MAX(TRIM(nom_code_client)), ''), CONCAT('Client ', idclient)) AS nom_code_client,
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
            LEFT JOIN centre ce ON ce.agr_centre = cc.agr_centre
            LEFT JOIN societe so ON so.id = ce.societe_id
            INNER JOIN (
                SELECT idfacture, COUNT(DISTINCT idcontrole) AS nb_ctrl_facture
                FROM (
                    SELECT DISTINCT idcontrole, idfacture
                    FROM controles_factures
                ) cf2
                GROUP BY idfacture
            ) t ON t.idfacture = fa.idfacture
        ";
    }
}
