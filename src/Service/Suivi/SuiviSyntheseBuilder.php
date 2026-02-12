<?php

namespace App\Service\Suivi;

class SuiviSyntheseBuilder
{
    public function buildSynthese(array $rows): array
    {
        $data = $this->buildDataStructure($rows);
        $this->calculateCentreAverages($data);

        return $data;
    }

    public function buildClientPro(array $rows): array
    {
        return $this->buildDataClientsProStructure($rows);
    }

    /*
     * Mappe les résultats de la requête SQL avec les données désirées sous forme de tableau
     *  - Sociétés
     *  --- Centres
     *  ------ Salariés
     *  --------- Données récupérées et calculées ici
     */
    private function buildDataStructure(array $rows): array
    {
        $data = [];

        foreach ($rows as $row) {
            $societe = $row['societe_nom'] ?? 'Société inconnue';
            $centre = strtoupper($row['centre_agrement'] ?? '???');

            // Change le nom des réseaux pour affichage optimisé
            $reseauCode = match ($row['reseau_nom']) {
                'Dekra' => 'DE',
                'Norisko' => 'NO',
                'Auto-Sécurité' => 'AS',
                'Autovision' => 'AU',
                'Sécuritest' => 'SE',
                "Vérif'Autos" => 'VA',
                default => '',
            };

            // Si la ligne tombe sur un nouveau centre, on remet les informations précédentes à 0
            if (!isset($data[$societe][$centre])) {
                $data[$societe][$centre] = [
                    'centre' => [
                        'ville' => $row['centre_ville'] ?? '',
                        'reseau' => $row['reseau_nom'] ?? '',
                        'reseau_code' => $reseauCode,
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
                    ],
                ];
            }

            // Extraire les valeurs individuelles
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

            // Ajouter le salarié
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
                'prix_moyen_vtp' => $nbVtp ? $caVtp / $nbVtp : 0,
                'prix_moyen_clvtp' => $nbClvtp ? $caClvtp / $nbClvtp : 0,
                'prix_moyen_cv' => $nbCv ? $caCv / $nbCv : 0,
                'prix_moyen_clcv' => $nbClcv ? $caClcv / $nbClcv : 0,
                'prix_moyen_vtc' => $nbVtc ? $caVtc / $nbVtc : 0,
                'prix_moyen_vol' => $nbVol ? $caVol / $nbVol : 0,
                'temps_total' => (int)$row['temps_total'],
                'taux_refus' => (float)$row['taux_refus'],
                'nb_particuliers' => (int)$row['nb_particuliers'],
                'nb_professionnels' => (int)$row['nb_professionnels'],
            ];

            // Cumuler les totaux par centre
            $totaux =& $data[$societe][$centre]['totaux'];
            $totaux['nb_controles'] += (int)$row['nb_controles'];
            $totaux['nb_vtp'] += $nbVtp;
            $totaux['nb_clvtp'] += $nbClvtp;
            $totaux['nb_cv'] += $nbCv;
            $totaux['nb_clcv'] += $nbClcv;
            $totaux['nb_vtc'] += $nbVtc;
            $totaux['nb_vol'] += $nbVol;
            $totaux['ca_total_ht'] += $caTotal;
            $totaux['ca_total_ht_vtp'] += $caVtp;
            $totaux['ca_total_ht_clvtp'] += $caClvtp;
            $totaux['ca_total_ht_cv'] += $caCv;
            $totaux['ca_total_ht_clcv'] += $caClcv;
            $totaux['ca_total_ht_vtc'] += $caVtc;
            $totaux['ca_total_ht_vol'] += $caVol;
        }

        return $data;
    }

    /*
     * Calculer les prix moyens de chaque centre, en fonction des prix moyens de chacun de leur salarié
     */
    private function calculateCentreAverages(array &$data): void
    {
        foreach ($data as &$centres) {
            foreach ($centres as &$centre) {
                $totaux = $centre['totaux'];
                $centre['totaux']['prix_moyen_vtp'] = $totaux['nb_vtp'] ? $totaux['ca_total_ht_vtp'] / $totaux['nb_vtp'] : 0;
                $centre['totaux']['prix_moyen_clvtp'] = $totaux['nb_clvtp'] ? $totaux['ca_total_ht_clvtp'] / $totaux['nb_clvtp'] : 0;
                $centre['totaux']['prix_moyen_cv'] = $totaux['nb_cv'] ? $totaux['ca_total_ht_cv'] / $totaux['nb_cv'] : 0;
                $centre['totaux']['prix_moyen_clcv'] = $totaux['nb_clcv'] ? $totaux['ca_total_ht_clcv'] / $totaux['nb_clcv'] : 0;
                $centre['totaux']['prix_moyen_vtc'] = $totaux['nb_vtc'] ? $totaux['ca_total_ht_vtc'] / $totaux['nb_vtc'] : 0;
                $centre['totaux']['prix_moyen_vol'] = $totaux['nb_vol'] ? $totaux['ca_total_ht_vol'] / $totaux['nb_vol'] : 0;
            }
        }
        unset($centre, $centres);
    }

    private function buildDataClientsProStructure(array $rows): array
    {
        $data = [];

        foreach ($rows as $row) {
            $societe = $row['societe_nom'] ?? 'Société inconnue';
            $centre = strtoupper($row['centre_agrement'] ?? '???');

            // Ajouter le client
            $data[$societe][$centre]['client_pro'][] = [
                'nom' => $row['nom'],
                'ca_client_pro' => $row['ca_client_pro'],
                'ca_now' => $row['ca_now'],
                'ca_n1' => $row['ca_n1'],
                'ca_n2' => $row['ca_n2'],
                'nb_ctrl_now' => $row['nb_ctrl_now'],
                'nb_ctrl_n1' => $row['nb_ctrl_n1'],
                'nb_ctrl_n2' => $row['nb_ctrl_n2'],
            ];
        }

        return $data;
    }
}
