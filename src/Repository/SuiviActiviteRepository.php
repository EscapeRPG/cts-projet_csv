<?php

namespace App\Repository;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

readonly class SuiviActiviteRepository
{
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
        $where = [];
        $params = [];
        $types = [];

        // Années
        $annees = !empty($filters['annee'])
            ? [(int)$filters['annee']]
            : [(date('Y') - 2), (date('Y') - 1), (int)date('Y')];

        $where[] = 'annee IN (:annee)';
        $params['annee'] = $annees;
        $types['annee'] = ArrayParameterType::INTEGER;

        // Mois
        $mois = !empty($filters['mois'])
            ? array_map('intval', $filters['mois'])
            : range(1, 12);

        $where[] = 'mois IN (:mois)';
        $params['mois'] = $mois;
        $types['mois'] = ArrayParameterType::INTEGER;


        // Sociétés
        if (!empty($filters['societe'])) {
            $where[] = 'societe_nom IN (:societes)';
            $params['societes'] = $filters['societe'];
            $types['societes'] = ArrayParameterType::STRING;
        }

        // Centres
        if (!empty($filters['centre'])) {
            $where[] = 'agr_centre IN (:centres)';
            $params['centres'] = $filters['centre'];
            $types['centres'] = ArrayParameterType::STRING;
        }

        // Contrôleurs
        if (!empty($filters['controleur'])) {
            $where[] = 'salarie_id IN (:controleurs)';
            $params['controleurs'] = $filters['controleur'];
            $types['controleurs'] = ArrayParameterType::INTEGER;
        }

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
                SUM(nb_controles) AS nb_controles,
                SUM(nb_vtp) AS nb_vtp,
                SUM(nb_clvtp) AS nb_clvtp,
                SUM(nb_cv) AS nb_cv,
                SUM(nb_clcv) AS nb_clcv,
                SUM(nb_vtc) AS nb_vtc,
                SUM(nb_vol) AS nb_vol,
                SUM(nb_auto) AS nb_auto,
                SUM(nb_moto) AS nb_moto,
                SUM(total_presta_ht) AS total_presta_ht,
                SUM(total_ht_vtp) AS total_ht_vtp,
                SUM(total_ht_clvtp) AS total_ht_clvtp,
                SUM(total_ht_cv) AS total_ht_cv,
                SUM(total_ht_clcv) AS total_ht_clcv,
                SUM(total_ht_vtc) AS total_ht_vtc,
                SUM(total_ht_vol) AS total_ht_vol,
                SUM(temps_total) AS temps_total,
                SUM(temps_total_auto) AS temps_total_auto,
                SUM(temps_total_moto) AS temps_total_moto,
                SUM(taux_refus) AS taux_refus,
                SUM(refus_auto) AS refus_auto,
                SUM(refus_moto) AS refus_moto,
                SUM(nb_particuliers) AS nb_particuliers,
                SUM(nb_professionnels) AS nb_professionnels
            FROM synthese_controles
        ";

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= "
            GROUP BY salarie_id, agr_centre, societe_nom
            ORDER BY societe_nom, centre_ville, salarie_nom, salarie_prenom
        ";

        return $this->connection->executeQuery($sql, $params, $types)->fetchAllAssociative();
    }

    /**
     * Récupère toutes les données des clients pro uniquement, exploitables dans la bdd pour traitement
     *
     * @throws Exception
     */
    public function fetchProClients(array $filters = []): array
    {
        $where = [];
        $params = [];
        $types = [];

        // Mois
        $mois = !empty($filters['mois'])
            ? array_map('intval', $filters['mois'])
            : range(1, 12);

        $where[] = 'mois IN (:mois)';
        $params['mois'] = $mois;
        $types['mois'] = ArrayParameterType::INTEGER;


        // Sociétés
        if (!empty($filters['societe'])) {
            $where[] = 'societe_nom IN (:societes)';
            $params['societes'] = $filters['societe'];
            $types['societes'] = ArrayParameterType::STRING;
        }

        // Centres
        if (!empty($filters['centre'])) {
            $where[] = 'agr_centre IN (:centres)';
            $params['centres'] = $filters['centre'];
            $types['centres'] = ArrayParameterType::STRING;
        }

        $sql = "SELECT * FROM synthese_pros WHERE 1 = 1";

        if ($where) {
            $sql .= ' AND ' . implode(' AND ', $where);
        }

        $sql .= "
            ORDER BY ca DESC
        ";

        return $this->connection->executeQuery($sql, $params, $types)->fetchAllAssociative();
    }
}
