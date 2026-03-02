<?php

namespace App\Repository\Suivi;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Exception;

final readonly class SuiviProsRepository extends AbstractSuiviQueryRepository
{
    /**
     * @throws Exception
     */
    public function fetchProClients(array $filters = []): array
    {
        $selectedTypeFamilies = $this->normalizeTypeFamilies($filters['type'] ?? []);
        $selectedVehicleTypes = $this->normalizeVehicleTypes($filters['vehicule'] ?? []);

        $hasTypeFilter = !empty($filters['type']) && !$this->isAllTypeFamiliesSelected($selectedTypeFamilies);

        if ($hasTypeFilter) {
            return $this->fetchProClientsByTypeFromRaw(
                $filters,
                $selectedTypeFamilies,
                $selectedVehicleTypes,
                $hasTypeFilter
            );
        }

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

        $selectCa = 'ca';
        $selectNbControles = 'nb_controles';
        if ($selectedVehicleTypes === ['CL']) {
            $selectCa = 'ca_moto';
            $selectNbControles = 'nb_controles_moto';
        } elseif ($selectedVehicleTypes === ['VL']) {
            $selectCa = 'ca_auto';
            $selectNbControles = 'nb_controles_auto';
        }

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

        return $this->connection->executeQuery($sql, $params, $types)->fetchAllAssociative();
    }

    /**
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

