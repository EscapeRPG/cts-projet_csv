<?php

namespace App\Service\Suivi;

class SuiviProService
{
    public function getFocusPro(array $synthese): array
    {
        $clients = [];

        foreach ($synthese as $societe => $centres) {
            foreach ($centres as $centre) {
                foreach ($centre['client_pro'] as $client_pro) {

                    $id = $client_pro['nom'];

                    if (!isset($clients[$id])) {
                        $clients[$id] = [
                            'nom' => $client_pro['nom'],
                            'ca_client_pro' => 0,
                            'ca_now' => 0,
                            'ca_n1' => 0,
                            'ca_n2' => 0,
                            'nb_ctrl_now' => 0,
                            'nb_ctrl_n1' => 0,
                            'nb_ctrl_n2' => 0,
                            'per_n1' => null,
                            'per_n2' => null,
                        ];
                    }

                    $clients[$id]['ca_client_pro'] += (float)$client_pro['ca_client_pro'];
                    $clients[$id]['ca_now'] += (float)$client_pro['ca_now'];
                    $clients[$id]['ca_n1'] += (float)$client_pro['ca_n1'];
                    $clients[$id]['ca_n2'] += (float)$client_pro['ca_n2'];
                    $clients[$id]['nb_ctrl_now'] += (int)$client_pro['nb_ctrl_now'];
                    $clients[$id]['nb_ctrl_n1'] += (int)$client_pro['nb_ctrl_n1'];
                    $clients[$id]['nb_ctrl_n2'] += (int)$client_pro['nb_ctrl_n2'];
                }
            }
        }

        // Calcul des pourcentages APRÈS aggregation
        foreach ($clients as &$client) {
            if ($client['nb_ctrl_n1'] != 0) {
                $client['per_n1'] = (($client['nb_ctrl_now'] - $client['nb_ctrl_n1']) / $client['nb_ctrl_n1']) * 100;
            } else {
                $client['per_n1'] = $client['nb_ctrl_now'] == 0 ? 0: 100;
            }

            if ($client['nb_ctrl_n2'] != 0) {
                $client['per_n2'] = (($client['nb_ctrl_now'] - $client['nb_ctrl_n2']) / $client['nb_ctrl_n2']) * 100;
            } else {
                $client['per_n2'] = $client['nb_ctrl_now'] == 0 ? 0: 100;
            }
        }

        // Trier par CA décroissant
        usort($clients, fn($a, $b) => $b['ca_client_pro'] <=> $a['ca_client_pro']);

        return $clients;
    }
}
