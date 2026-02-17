<?php

namespace App\Service\Suivi;

class SuiviSyntheseBuilder
{
    private array $types = ['vtp', 'clvtp', 'cv', 'clcv', 'vtc', 'vol'];
    private array $reseaux = [
        'Dekra' => 'DE',
        'Norisko' => 'NO',
        'Auto-Sécurité' => 'AS',
        'Autovision' => 'AU',
        'Sécuritest' => 'SE',
        "Vérif'Autos" => 'VA',
    ];

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

    private function buildDataStructure(array $rows): array
    {
        $data = [];

        foreach ($rows as $row) {
            $societe = $row['societe_nom'] ?? 'Société inconnue';
            $centre = strtoupper($row['agr_centre'] ?? '???');

            $reseauCode = $this->reseaux[$row['reseau_nom']] ?? '';

            if (!isset($data[$societe][$centre])) {
                $data[$societe][$centre] = [
                    'centre' => [
                        'ville' => $row['centre_ville'] ?? '',
                        'reseau' => $row['reseau_nom'] ?? '',
                        'reseau_code' => $reseauCode,
                    ],
                    'salaries' => [],
                    'totaux' => array_merge(
                        array_fill_keys(array_map(fn($t) => 'nb_' . $t, $this->types), 0),
                        array_fill_keys(array_map(fn($t) => 'ca_total_ht_' . $t, $this->types), 0),
                        [
                            'nb_controles' => 0,
                            'ca_total_ht' => 0,
                            'temps_total' => 0,
                            'taux_refus' => 0,
                            'nb_particuliers' => 0,
                            'nb_professionnels' => 0,
                            'prix_moyen_vtp' => 0,
                            'prix_moyen_clvtp' => 0,
                            'prix_moyen_cv' => 0,
                            'prix_moyen_clcv' => 0,
                            'prix_moyen_vtc' => 0,
                            'prix_moyen_vol' => 0,
                        ]
                    ),
                ];
            }

            // Construire les données du salarié
            $salarieData = [
                'id' => $row['salarie_id'],
                'nom' => mb_strtoupper($row['salarie_nom']),
                'prenom' => mb_ucfirst($row['salarie_prenom']),
                'agr' => $row['salarie_agr'],
                'nb_controles' => (int)$row['nb_controles'],
                'nb_auto' => (int)($row['nb_auto'] ?? 0),
                'nb_moto' => (int)($row['nb_moto'] ?? 0),
                'temps_total' => (int)$row['temps_total'],
                'temps_total_auto' => (int)($row['temps_total_auto'] ?? 0),
                'temps_total_moto' => (int)($row['temps_total_moto'] ?? 0),
                'taux_refus' => (float)$row['taux_refus'],
                'refus_auto' => (int)($row['refus_auto'] ?? 0),
                'refus_moto' => (int)($row['refus_moto'] ?? 0),
                'nb_particuliers' => (int)$row['nb_particuliers'],
                'nb_professionnels' => (int)$row['nb_professionnels'],
                'total_presta_ht' => (float)$row['total_presta_ht'],
            ];

            foreach ($this->types as $type) {
                $nbCol = 'nb_' . $type;
                $caCol = 'total_ht_' . $type;

                $nb = (int)$row[$nbCol];
                $ca = (float)$row[$caCol];

                $salarieData[$nbCol] = $nb;
                $salarieData[$caCol] = $ca;
                $salarieData['prix_moyen_' . $type] = $nb ? $ca / $nb : 0;
            }

            $data[$societe][$centre]['salaries'][] = $salarieData;

            // Cumuler les totaux par centre
            $totaux =& $data[$societe][$centre]['totaux'];
            foreach ($this->types as $type) {
                $totaux['nb_' . $type] += $salarieData['nb_' . $type];
                $totaux['ca_total_ht_' . $type] += $salarieData['total_ht_' . $type];
            }

            $totaux['nb_controles'] += $salarieData['nb_controles'];
            $totaux['ca_total_ht'] += $salarieData['total_presta_ht'];
            $totaux['temps_total'] += $salarieData['temps_total'];
            $totaux['taux_refus'] += $salarieData['taux_refus'];
            $totaux['nb_particuliers'] += $salarieData['nb_particuliers'];
            $totaux['nb_professionnels'] += $salarieData['nb_professionnels'];
        }

        return $data;
    }

    private function calculateCentreAverages(array &$data): void
    {
        foreach ($data as &$centres) {
            foreach ($centres as &$centre) {
                $totaux = $centre['totaux'];
                foreach ($this->types as $type) {
                    $totaux['prix_moyen_' . $type] = $totaux['nb_' . $type]
                        ? $totaux['ca_total_ht_' . $type] / $totaux['nb_' . $type]
                        : 0;
                }
                $centre['totaux'] = $totaux;
            }
        }
        unset($centre, $centres);
    }

    private function buildDataClientsProStructure(array $rows): array
    {
        $data = [];
        $yearNow = (int)date('Y');
        $yearN1  = $yearNow - 1;
        $yearN2  = $yearNow - 2;

        foreach ($rows as $row) {
            $nomClient = $row['code_client'];
            $societe   = $row['societe_nom'] ?: 'Société inconnue';
            $centre    = strtoupper($row['agr_centre'] ?? '???');

            // clé unique pour chaque client + société + centre
            $keyClient = $nomClient . '|' . $societe . '|' . $centre;

            // Initialisation si client inexistant pour cette société/centre
            if (!isset($data[$societe][$centre]['client_pro'][$keyClient])) {
                $data[$societe][$centre]['client_pro'][$keyClient] = [
                    'nom'           => $nomClient,
                    'societe'       => $societe,
                    'centre'        => $centre,
                    'ca_client_pro' => 0,
                    'ca_now'        => 0,
                    'ca_n1'         => 0,
                    'ca_n2'         => 0,
                    'nb_ctrl_now'   => 0,
                    'nb_ctrl_n1'    => 0,
                    'nb_ctrl_n2'    => 0,
                ];
            }

            // Référence vers le client pour accumulation
            $clientData = &$data[$societe][$centre]['client_pro'][$keyClient];

            $annee = (int)$row['annee'];

            // Accumulation du CA et du nombre de contrôles selon l'année
            if ($annee === $yearNow) {
                $clientData['ca_now']      += (float)$row['ca'];
                $clientData['nb_ctrl_now'] += (int)$row['nb_controles'];
            } elseif ($annee === $yearN1) {
                $clientData['ca_n1']      += (float)$row['ca'];
                $clientData['nb_ctrl_n1'] += (int)$row['nb_controles'];
            } elseif ($annee === $yearN2) {
                $clientData['ca_n2']      += (float)$row['ca'];
                $clientData['nb_ctrl_n2'] += (int)$row['nb_controles'];
            }

            // Recalcul du CA total pour le client
            $clientData['ca_client_pro'] = $clientData['ca_now'] + $clientData['ca_n1'] + $clientData['ca_n2'];

            unset($clientData);
        }

        return $data;
    }
}
