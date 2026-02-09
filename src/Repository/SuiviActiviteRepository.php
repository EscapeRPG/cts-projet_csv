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
        $rows = $this->fetchRows($filters);
        $data = $this->buildDataStructure($rows);
        $this->calculateCentreAverages($data);

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

    public function getMonth(): array
    {
        return [
            1 => 'Janvier',
            2 => 'Février',
            3 => 'Mars',
            4 => 'Avril',
            5 => 'Mai',
            6 => 'Juin',
            7 => 'Juillet',
            8 => 'Août',
            9 => 'Septembre',
            10 => 'Octobre',
            11 => 'Novembre',
            12 => 'Décembre'
        ];
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
            'SELECT id, nom, prenom, agr_controleur FROM salarie ORDER BY nom'
        );
    }

    /**
     * @throws Exception
     */
    private function fetchRows(array $filters): array
    {
        $where = ['sa.is_active = 1 AND ce.id IS NOT NULL'];
        $params = [];
        $types = [];

        // Filtre année
        $annee = !empty($filters['annee']) ? [(int)$filters['annee']] : [date('Y') - 2, date('Y') - 1, date('Y')];
        $where[] = 'YEAR(ctrl.data_date) IN (:annee)';
        $params['annee'] = $annee;
        $types['annee'] = ArrayParameterType::STRING;

        // Filtre mois
        $mois = !empty($filters['mois'])
            ? array_map('intval', $filters['mois'])
            : range(1, 12);

        $where[] = 'MONTH(ctrl.data_date) IN (:mois)';
        $params['mois'] = $mois;
        $types['mois'] = ArrayParameterType::INTEGER;

        // Filtre sociétés
        if (!empty($filters['societe'])) {
            $where[] = 'so.nom IN (:societes)';
            $params['societes'] = $filters['societe'];
            $types['societes'] = ArrayParameterType::STRING;
        }

        // Filtre centres
        if (!empty($filters['centre'])) {
            $where[] = 'ce.agr_centre IN (:centres)';
            $params['centres'] = $filters['centre'];
            $types['centres'] = ArrayParameterType::STRING;
        }

        // Filtre contrôleurs (par id)
        if (!empty($filters['controleur'])) {
            $where[] = 'sa.id IN (:controleurs)';
            $params['controleurs'] = $filters['controleur'];
            $types['controleurs'] = ArrayParameterType::INTEGER;
        }

        // Requête principale
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
        GROUP BY
            sa.id,
            sa.nom,
            sa.prenom,
            sa.agr_controleur,
            ce.agr_centre,
            ce.ville,
            ce.reseau_nom,
            so.nom

        ORDER BY
            so.nom,
            ce.ville,
            sa.nom
    ";

        return $this->connection->executeQuery($sql, $params, $types)->fetchAllAssociative();
    }

    private function buildDataStructure(array $rows): array
    {
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
                        'nb_clvtp' => 0,
                        'nb_cv' => 0,
                        'nb_clcv' => 0,
                        'nb_vtc' => 0,
                        'nb_vol' => 0,
                        'ca_total_ht' => 0,
                        'ca_total_ht_vtp' => 0,
                        'ca_total_ht_clvtp' => 0,
                        'ca_total_ht_cv' => 0,
                        'ca_total_ht_clcv' => 0,
                        'ca_total_ht_vtc' => 0,
                        'ca_total_ht_vol' => 0,
                        'prix_moyen_vtp' => 0,
                        'prix_moyen_clvtp' => 0,
                        'prix_moyen_cv' => 0,
                        'prix_moyen_clcv' => 0,
                        'prix_moyen_vtc' => 0,
                        'prix_moyen_vol' => 0,
                        'temps_total' => 0,
                        'taux_refus' => 0,
                        'nb_particuliers' => 0,
                        'nb_professionnels' => 0,
                    ]
                ];
            }

            $nbVtp = (int)$row['nb_vtp'];
            $nbClvtp = (int)$row['nb_clvtp'];
            $nbCv = (int)$row['nb_cv'];
            $nbClcv = (int)$row['nb_clcv'];
            $nbVtc = (int)$row['nb_vtc'];
            $nbVol = (int)$row['nb_vol'];
            $caVtp = (float)$row['total_ht_vtp'];
            $caClvtp = (float)$row['total_ht_clvtp'];
            $caCv = (float)$row['total_ht_cv'];
            $caClcv = (float)$row['total_ht_clcv'];
            $caVtc = (float)$row['total_ht_vtc'];
            $caVol = (float)$row['total_ht_vol'];
            $caTotal = (float)$row['total_presta_ht'];

            $data[$societe][$centre]['salaries'][] = [
                'id' => $row['salarie_id'],
                'nom' => mb_strtoupper($row['salarie_nom']),
                'prenom' => mb_ucfirst($row['salarie_prenom']),
                'agr' => $row['agr'],
                'nb_controles' => (int)$row['nb_controles'],
                'nb_vtp' => $nbVtp,
                'nb_clvtp' => $nbClvtp,
                'nb_cv' => $nbCv,
                'nb_clcv' => $nbClcv,
                'nb_vtc' => $nbVtc,
                'nb_vol' => $nbVol,
                'total_presta_ht' => $caTotal,
                'total_ht_vtp' => $caVtp,
                'total_ht_clvtp' => $caClvtp,
                'total_ht_cv' => $caCv,
                'total_ht_clcv' => $caClcv,
                'total_ht_vtc' => $caVtc,
                'total_ht_vol' => $caVol,
                'prix_moyen_vtp' => $nbVtp > 0 ? $caVtp / $nbVtp : 0,
                'prix_moyen_clvtp' => $nbClvtp > 0 ? $caClvtp / $nbClvtp : 0,
                'prix_moyen_cv' => $nbCv > 0 ? $caCv / $nbCv : 0,
                'prix_moyen_clcv' => $nbClcv > 0 ? $caClcv / $nbClcv : 0,
                'prix_moyen_vtc' => $nbVtc > 0 ? $caVtc / $nbVtc : 0,
                'prix_moyen_vol' => $nbVol > 0 ? $caVol / $nbVol : 0,
                'temps_total' => (int)$row['temps_total'],
                'taux_refus' => (float)$row['taux_refus'],
                'nb_particuliers' => (int)$row['nb_particuliers'],
                'nb_professionnels' => (int)$row['nb_professionnels'],
            ];

            $data[$societe][$centre]['totaux']['nb_controles'] += (int)$row['nb_controles'];
            $data[$societe][$centre]['totaux']['nb_vtp'] += $nbVtp;
            $data[$societe][$centre]['totaux']['nb_clvtp'] += $nbClvtp;
            $data[$societe][$centre]['totaux']['nb_cv'] += $nbCv;
            $data[$societe][$centre]['totaux']['nb_clcv'] += $nbClcv;
            $data[$societe][$centre]['totaux']['nb_vtc'] += $nbVtc;
            $data[$societe][$centre]['totaux']['nb_vol'] += $nbVol;
            $data[$societe][$centre]['totaux']['ca_total_ht'] += $caTotal;
            $data[$societe][$centre]['totaux']['ca_total_ht_vtp'] += $caVtp;
            $data[$societe][$centre]['totaux']['ca_total_ht_clvtp'] += $caClvtp;
            $data[$societe][$centre]['totaux']['ca_total_ht_cv'] += $caCv;
            $data[$societe][$centre]['totaux']['ca_total_ht_clcv'] += $caClcv;
            $data[$societe][$centre]['totaux']['ca_total_ht_vtc'] += $caVtc;
            $data[$societe][$centre]['totaux']['ca_total_ht_vol'] += $caVol;
        }

        return $data;
    }

    private function calculateCentreAverages(array &$data): void
    {
        foreach ($data as $societe => &$centres) {
            foreach ($centres as &$centre) {
                $centre['totaux']['prix_moyen_vtp'] = $centre['totaux']['nb_vtp'] > 0
                    ? $centre['totaux']['ca_total_ht_vtp'] / $centre['totaux']['nb_vtp']
                    : 0;

                $centre['totaux']['prix_moyen_clvtp'] = $centre['totaux']['nb_clvtp'] > 0
                    ? $centre['totaux']['ca_total_ht_clvtp'] / $centre['totaux']['nb_clvtp']
                    : 0;

                $centre['totaux']['prix_moyen_cv'] = $centre['totaux']['nb_cv'] > 0
                    ? $centre['totaux']['ca_total_ht_cv'] / $centre['totaux']['nb_cv']
                    : 0;

                $centre['totaux']['prix_moyen_clcv'] = $centre['totaux']['nb_clcv'] > 0
                    ? $centre['totaux']['ca_total_ht_clcv'] / $centre['totaux']['nb_clcv']
                    : 0;

                $centre['totaux']['prix_moyen_vtc'] = $centre['totaux']['nb_vtc'] > 0
                    ? $centre['totaux']['ca_total_ht_vtc'] / $centre['totaux']['nb_vtc']
                    : 0;

                $centre['totaux']['prix_moyen_vol'] = $centre['totaux']['nb_vol'] > 0
                    ? $centre['totaux']['ca_total_ht_vol'] / $centre['totaux']['nb_vol']
                    : 0;
            }
        }
        unset($centres, $centre);
    }
}
