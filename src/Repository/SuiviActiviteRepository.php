<?php

namespace App\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ArrayParameterType;

class SuiviActiviteRepository
{
    public function __construct(private Connection $connection)
    {
    }

    /**
     * @throws Exception
     */
    public function getSyntheseGlobale(array $filters = []): array
    {
        $where = ['sa.is_active = 1 AND ce.id IS NOT NULL'];
        $params = [];
        $types = [];

        $annee = !empty($filters['annee']) ? (int)$filters['annee'] : null;

        if (!empty($filters['societe'])) {
            $where[] = 'so.nom IN (:societes)';
            $params['societes'] = $filters['societe'];
            $types['societes'] = ArrayParameterType::STRING;
        }

        if (!empty($filters['centre'])) {
            $where[] = 'ce.agr_centre IN (:centres)';
            $params['centres'] = $filters['centre'];
            $types['centres'] = ArrayParameterType::STRING;
        }

        if (!empty($filters['controleur'])) {
            $where[] = 'sa.agr_controleur IN (:controleurs)';
            $params['controleurs'] = $filters['controleur'];
            $types['controleurs'] = ArrayParameterType::STRING;
        }


        $sql = "
            SELECT
                so.nom        AS societe_nom,
                ce.agr_centre AS centre_agrement,
                ce.ville       AS centre_ville,
                ce.reseau_nom  AS reseau_nom,

                sa.nom         AS salarie_nom,
                sa.prenom      AS salarie_prenom,
                sa.agr_controleur,

                COUNT(DISTINCT ctrl.idcontrole) AS nb_controles,
                COUNT(DISTINCT CASE WHEN ctrl.type_ctrl = 'VTP' THEN ctrl.idcontrole END) AS nb_vtp,
                COUNT(DISTINCT CASE WHEN ctrl.type_ctrl = 'CV'  THEN ctrl.idcontrole END) AS nb_cv,
                COUNT(DISTINCT CASE WHEN ctrl.type_ctrl = 'VTC' THEN ctrl.idcontrole END) AS nb_vtc

            FROM salarie sa

            LEFT JOIN clients_controles cc
                ON cc.agr_controleur = sa.agr_controleur

            LEFT JOIN controles ctrl
                ON ctrl.idcontrole = cc.idcontrole
                " . ($annee !== null ? "AND YEAR(ctrl.data_date) = :annee" : "") . "

            LEFT JOIN centre ce
                ON ce.agr_centre = cc.agr_centre

            LEFT JOIN societe so
                ON so.id = ce.societe_id

            " . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . "

            GROUP BY
                so.nom,
                ce.agr_centre,
                ce.ville,
                ce.reseau_nom,
                sa.agr_controleur,
                sa.nom,
                sa.prenom

            ORDER BY so.nom, ce.ville, sa.nom;
            ";

        if ($annee !== null) {
            $params['annee'] = $annee;
        }

        $rows = $this->connection->executeQuery(
            $sql,
            $params,
            $types
        )->fetchAllAssociative();

        // Transformation en structure imbriquée
        $data = [];

        foreach ($rows as $row) {
            $societe = $row['societe_nom'] ?? 'Société inconnue';
            $centre = strtoupper($row['centre_agrement']) ?? 'Centre inconnu';

            if (!isset($data[$societe])) {
                $data[$societe] = [];
            }

            $reseauCode = match ($row['reseau_nom']) {
                'Dekra' => 'DE',
                'Norisko' => 'NO',
                'Auto-Sécurité' => 'AS',
                'Autovision' => 'AU',
                'Sécuritest' => 'SE',
                'Vérif\'Autos' => 'VA',
                default => '',
            };

            if (!isset($data[$societe][$centre])) {
                $data[$societe][$centre] = [
                    'centre' => [
                        'ville' => $row['centre_ville'] ?? '',
                        'reseau' => $row['reseau_nom'] ?? '',
                        'reseau_code' => $reseauCode
                    ],
                    'salaries' => [],
                    'totaux' => [
                        'nb_controles' => 0,
                        'nb_vtp' => 0,
                        'nb_vtc' => 0,
                        'nb_cv' => 0,
                    ]
                ];
            }

            $data[$societe][$centre]['salaries'][] = [
                'nom' => $row['salarie_nom'],
                'prenom' => $row['salarie_prenom'],
                'agr' => $row['agr_controleur'],
                'nb_controles' => (int)$row['nb_controles'],
                'nb_vtp' => (int)$row['nb_vtp'],
                'nb_vtc' => (int)$row['nb_vtc'],
                'nb_cv' => (int)$row['nb_cv'],
            ];

            $data[$societe][$centre]['totaux']['nb_controles'] += (int)$row['nb_controles'];
            $data[$societe][$centre]['totaux']['nb_vtp'] += (int)$row['nb_vtp'];
            $data[$societe][$centre]['totaux']['nb_vtc'] += (int)$row['nb_vtc'];
            $data[$societe][$centre]['totaux']['nb_cv'] += (int)$row['nb_cv'];
        }

        return $data;
    }

    /**
     * @throws Exception
     */
    public function getYear(): array
    {
        return $this->connection->fetchFirstColumn(
            'SELECT DISTINCT YEAR(data_date) FROM controles'
        );
    }

    /**
     * @throws Exception
     */
    public function getSocietes(): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT nom FROM societe ORDER BY nom'
        );
    }

    /**
     * @throws Exception
     */
    public function getCentres(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT ville, reseau_nom, agr_centre FROM centre ORDER BY reseau_nom, ville'
        );

        $data = [];

        foreach ($rows as $row) {
            $reseauCode = match ($row['reseau_nom']) {
                'Dekra' => 'DE',
                'Norisko' => 'NO',
                'Auto-Sécurité' => 'AS',
                'Autovision' => 'AU',
                'Sécuritest' => 'SE',
                'Vérif\'Autos' => 'VA',
                default => '',
            };

            $data[] = [
                'nom' => $reseauCode,
                'ville' => $row['ville'],
                'agr_centre' => $row['agr_centre']
            ];
        }

        return $data;
    }

    /**
     * @throws Exception
     */
    public function getControleurs(): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT nom, prenom, agr_controleur FROM salarie ORDER BY nom'
        );
    }
}
