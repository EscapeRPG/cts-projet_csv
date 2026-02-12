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
            : [date('Y') - 2, date('Y') - 1, date('Y')];

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

        $sql = "SELECT * FROM synthese_controles";

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= "
            GROUP BY salarie_id, agr_centre, societe_nom
            ORDER BY societe_nom, centre_ville, salarie_nom
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

        $sql = "SELECT * FROM client_pro_summary";

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= "
            GROUP BY nom_code_client
            ORDER BY ca DESC
        ";

        return $this->connection->executeQuery($sql, $params, $types)->fetchAllAssociative();
    }
}
