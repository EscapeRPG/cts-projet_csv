<?php

namespace App\Repository;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

readonly class SuiviActiviteRepository
{
    public function __construct(
        private Connection $connection
    ) {}

    /**
     * @throws Exception
     *
     * Récupère toutes les données exploitables dans la bdd pour traitement
     */
    public function fetchSyntheseRows(array $filters = []): array
    {
        $where = ['sa.is_active = 1 AND ce.id IS NOT NULL'];
        $params = [];
        $types = [];

        // Années
        $annees = !empty($filters['annee'])
            ? [(int)$filters['annee']]
            : [date('Y') - 2, date('Y') - 1, date('Y')];

        $where[] = 'YEAR(ctrl.data_date) IN (:annee)';
        $params['annee'] = $annees;
        $types['annee'] = ArrayParameterType::INTEGER;

        // Mois
        $mois = !empty($filters['mois'])
            ? array_map('intval', $filters['mois'])
            : range(1, 12);

        $where[] = 'MONTH(ctrl.data_date) IN (:mois)';
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

        // Contrôleurs
        if (!empty($filters['controleur'])) {
            $where[] = 'sa.id IN (:controleurs)';
            $params['controleurs'] = $filters['controleur'];
            $types['controleurs'] = ArrayParameterType::INTEGER;
        }

        $sql = "
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
        ";

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= "
        GROUP BY sa.id, ce.agr_centre, so.nom
        ORDER BY so.nom, ce.ville, sa.nom
        ";

        return $this->connection->executeQuery($sql, $params, $types)->fetchAllAssociative();
    }
}
