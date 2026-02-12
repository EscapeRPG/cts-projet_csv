<?php

namespace App\Repository;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

readonly class SuiviActiviteRepository
{
    public const string FETCH_GLOBALS = "
        SELECT
            so.nom AS societe_nom,

            ce.agr_centre AS centre_agrement,
            ce.ville AS centre_ville,
            ce.reseau_nom AS reseau_nom,

            sa.id AS salarie_id,
            sa.nom AS salarie_nom,
            sa.prenom AS salarie_prenom,
            sa.agr_controleur AS agr,

            COUNT(DISTINCT ctrl.idcontrole) AS nb_controles,
            COUNT(DISTINCT IF(ctrl.type_ctrl = 'VTP', ctrl.idcontrole, NULL)) AS nb_vtp,
            COUNT(DISTINCT IF(ctrl.type_ctrl = 'CLVTP', ctrl.idcontrole, NULL)) AS nb_clvtp,
            COUNT(DISTINCT IF(ctrl.type_ctrl = 'CV', ctrl.idcontrole, NULL)) AS nb_cv,
            COUNT(DISTINCT IF(ctrl.type_ctrl = 'CLCV', ctrl.idcontrole, NULL)) AS nb_clcv,
            COUNT(DISTINCT IF(ctrl.type_ctrl = 'VTC', ctrl.idcontrole, NULL)) AS nb_vtc,
            COUNT(DISTINCT IF(ctrl.type_ctrl = 'VOL', ctrl.idcontrole, NULL)) AS nb_vol,

            SUM(fa.montant_presta_ht) AS total_presta_ht,
            SUM(IF(ctrl.type_ctrl = 'VTP', fa.montant_presta_ht, 0)) AS total_ht_vtp,
            SUM(IF(ctrl.type_ctrl = 'CLVTP', fa.montant_presta_ht, 0)) AS total_ht_clvtp,
            SUM(IF(ctrl.type_ctrl = 'CV', fa.montant_presta_ht, 0)) AS total_ht_cv,
            SUM(IF(ctrl.type_ctrl = 'CLCV', fa.montant_presta_ht, 0)) AS total_ht_clcv,
            SUM(IF(ctrl.type_ctrl = 'VTC', fa.montant_presta_ht, 0)) AS total_ht_vtc,
            SUM(IF(ctrl.type_ctrl = 'VOL', fa.montant_presta_ht, 0)) AS total_ht_vol,

            SUM(ctrl.temps_ctrl) AS temps_total,
            SUM(IF(ctrl.res_ctrl IN ('S','R','SP'), 1, 0)) AS taux_refus,
            SUM(IF(cli.nom_code_client IS NULL OR cli.nom_code_client = '', 1, 0)) AS nb_particuliers,
            SUM(IF(cli.nom_code_client IS NOT NULL AND cli.nom_code_client != '', 1, 0)) AS nb_professionnels

        FROM salarie sa

        JOIN clients_controles cc
            ON cc.agr_controleur = sa.agr_controleur
            OR (sa.agr_cl_controleur IS NOT NULL AND cc.agr_controleur = sa.agr_cl_controleur)
        JOIN controles ctrl ON ctrl.idcontrole = cc.idcontrole
        LEFT JOIN controles_factures cf ON cf.idcontrole = ctrl.idcontrole
        LEFT JOIN factures fa ON fa.idfacture = cf.idfacture
        LEFT JOIN centre ce ON ce.agr_centre = cc.agr_centre
        LEFT JOIN societe so ON so.id = ce.societe_id
        LEFT JOIN clients cli ON cli.idclient = cc.idclient

        WHERE sa.is_active = 1 AND ce.id IS NOT NULL
    ";

    public const string FETCH_PROS_GLOBALS = "
        SELECT
            cli.nom_code_client AS nom,

            /* CA total */
            SUM(fa.montant_presta_ht * (cf.nb_ctrl_client / tf.nb_ctrl_total)) AS ca_client_pro,

            /* CA par année */
            SUM(IF(YEAR(fa.data_date) = YEAR(CURDATE()), fa.montant_presta_ht * (cf.nb_ctrl_client / tf.nb_ctrl_total), 0)) AS ca_now,
            SUM(IF(YEAR(fa.data_date) = YEAR(CURDATE()) - 1, fa.montant_presta_ht * (cf.nb_ctrl_client / tf.nb_ctrl_total), 0)) AS ca_n1,
            SUM(IF(YEAR(fa.data_date) = YEAR(CURDATE()) - 2, fa.montant_presta_ht * (cf.nb_ctrl_client / tf.nb_ctrl_total), 0)) AS ca_n2,

            /* NB contrôles */
            SUM(cf.nb_ctrl_client) AS nb_controles,
            SUM(IF(YEAR(fa.data_date) = YEAR(CURDATE()), cf.nb_ctrl_client, 0)) AS nb_ctrl_now,
            SUM(IF(YEAR(fa.data_date) = YEAR(CURDATE()) - 1, cf.nb_ctrl_client, 0)) AS nb_ctrl_n1,
            SUM(IF(YEAR(fa.data_date) = YEAR(CURDATE()) - 2, cf.nb_ctrl_client, 0)) AS nb_ctrl_n2

        FROM factures fa

        /* nb contrôles par client par facture */
        JOIN (
            SELECT
                cf.idfacture,
                cc.idclient,
                cc.agr_centre,
                cc.agr_controleur,
                COUNT(*) AS nb_ctrl_client
            FROM controles_factures cf
            JOIN clients_controles cc ON cc.idcontrole = cf.idcontrole
            GROUP BY cf.idfacture, cc.idclient, cc.agr_centre, cc.agr_controleur
        ) cf ON cf.idfacture = fa.idfacture

        /* total contrôles par facture */
        JOIN (
            SELECT
                cf.idfacture,
                COUNT(*) AS nb_ctrl_total
            FROM controles_factures cf
            GROUP BY cf.idfacture
        ) tf ON tf.idfacture = fa.idfacture

        JOIN clients cli ON cli.idclient = cf.idclient
        LEFT JOIN centre ce ON ce.agr_centre = cf.agr_centre
        LEFT JOIN societe so ON so.id = ce.societe_id

        WHERE cli.nom_code_client IS NOT NULL
        AND cli.nom_code_client != ''
    ";

    public const string FETCH_PROS_CTRL_BY_MONTH_AND_DATE = "
        SELECT
            YEAR(ctrl.date_ctrl)  AS annee,
            MONTH(ctrl.date_ctrl) AS mois,
            COUNT(DISTINCT ctrl.idcontrole) AS nb_controles

        FROM controles ctrl
        JOIN clients_controles cc ON cc.idcontrole = ctrl.idcontrole
        LEFT JOIN clients cli ON cli.idclient = cc.idclient
        LEFT JOIN centre ce ON ce.agr_centre = cc.agr_centre
        LEFT JOIN societe so ON so.id = ce.societe_id

        WHERE cli.nom_code_client IS NOT NULL
        AND cli.nom_code_client != ''
        AND YEAR(ctrl.date_ctrl) IN (YEAR(CURDATE()), YEAR(CURDATE())-1, YEAR(CURDATE())-2)
    ";

    public function __construct(
        private Connection $connection
    )
    {
    }

    /**
     * Récupère toutes les données exploitables dans la bdd pour traitement
     *
     * @throws Exception
     */
    public function fetchSyntheseRows(array $filters = []): array
    {
        $mainTable = "ctrl";

        [$where, $params, $types] = $this->returnFilters($mainTable, $filters);

        // Années
        $annees = !empty($filters['annee'])
            ? [(int)$filters['annee']]
            : [date('Y') - 2, date('Y') - 1, date('Y')];

        $where[] = 'YEAR(ctrl.data_date) IN (:annee)';
        $params['annee'] = $annees;
        $types['annee'] = ArrayParameterType::INTEGER;

        // Contrôleurs
        if (!empty($filters['controleur'])) {
            $where[] = 'sa.id IN (:controleurs)';
            $params['controleurs'] = $filters['controleur'];
            $types['controleurs'] = ArrayParameterType::INTEGER;
        }

        $sql = self::FETCH_GLOBALS;

        if ($where) {
            $sql .= ' AND ' . implode(' AND ', $where);
        }

        $sql .= "
            GROUP BY sa.id, ce.agr_centre, so.nom
            ORDER BY so.nom, ce.ville, sa.nom
        ";

        return $this->connection->executeQuery($sql, $params, $types)->fetchAllAssociative();
    }

    /**
     * Récupère toutes les données des clients pro uniquement, exploitables dans la bdd pour traitement
     *
     * @throws Exception
     */
    public function fetchProClients(string $selectedSqlKey, array $filters = []): array
    {
        $mainTable = "fa";

        [$where, $params, $types] = $this->returnFilters($mainTable, $filters);

        $controleurFilterSql = '';
        if (!empty($filters['controleur'])) {
            $controleurFilterSql = ' AND cc.agr_controleur IN (:controleurs)';
            $params['controleurs'] = $filters['controleur'];
            $types['controleurs'] = ArrayParameterType::INTEGER;
        }

        $sql = match($selectedSqlKey) {
            'pro_globals' => self::FETCH_PROS_GLOBALS,
            'ctrl_by_month' => self::FETCH_PROS_CTRL_BY_MONTH_AND_DATE,
        };
        $sql .= $controleurFilterSql;

        if ($where) {
            $sql .= ' AND ' . implode(' AND ', $where);
        }

        $sql .= "
            GROUP BY cli.nom_code_client
            ORDER BY ca_now DESC
        ";

        return $this->connection->executeQuery($sql, $params, $types)->fetchAllAssociative();
    }

    private function returnFilters(string $table, array $filters = []): array
    {
        $where = [];
        $params = [];
        $types = [];

        // Mois
        $mois = !empty($filters['mois'])
            ? array_map('intval', $filters['mois'])
            : range(1, 12);

        $where[] = 'MONTH(' . $table . '.data_date) IN (:mois)';
        $params['mois'] = $mois;
        $types['mois'] = ArrayParameterType::INTEGER;


        // Sociétés
        if (!empty($filters['societe'])) {
            $where[] = 'so.nom IN (:societes)';
            $params['societes'] = $filters['societe'];
            $types['societes'] = ArrayParameterType::STRING;
        }

        // Centres
        if (!empty($filters['centre'])) {
            $where[] = 'ce.agr_centre IN (:centres)';
            $params['centres'] = $filters['centre'];
            $types['centres'] = ArrayParameterType::STRING;
        }

        return [$where, $params, $types];
    }
}
