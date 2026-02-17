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

                    $id = mb_strtoupper(trim((string)$client_pro['nom']));

                    if ($id === '') {
                        continue;
                    }

                    if (!isset($clients[$id])) {
                        // Initialisation du client
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

                    // Remplir les années avec la correspondance fixe
                    $clients[$id]['ca_now'] += $client_pro['ca_now'] ?? 0;
                    $clients[$id]['ca_n1'] += $client_pro['ca_n1'] ?? 0;
                    $clients[$id]['ca_n2'] += $client_pro['ca_n2'] ?? 0;

                    $clients[$id]['nb_ctrl_now'] += $client_pro['nb_ctrl_now'] ?? 0;
                    $clients[$id]['nb_ctrl_n1'] += $client_pro['nb_ctrl_n1'] ?? 0;
                    $clients[$id]['nb_ctrl_n2'] += $client_pro['nb_ctrl_n2'] ?? 0;

                    $clients[$id]['ca_client_pro'] += $client_pro['ca_client_pro'] ?? 0;
                }
            }
        }

        // Calcul des pourcentages après aggrégation
        foreach ($clients as &$client) {
            $client['per_n1'] = $client['nb_ctrl_n1'] != 0
                ? (($client['nb_ctrl_now'] - $client['nb_ctrl_n1']) / $client['nb_ctrl_n1']) * 100
                : ($client['nb_ctrl_now'] == 0 ? 0 : 100);

            $client['per_n2'] = $client['nb_ctrl_n2'] != 0
                ? (($client['nb_ctrl_now'] - $client['nb_ctrl_n2']) / $client['nb_ctrl_n2']) * 100
                : ($client['nb_ctrl_now'] == 0 ? 0 : 100);
        }
        unset($client);

        // Trier par CA décroissant
        usort($clients, fn($a, $b) => $b['ca_client_pro'] <=> $a['ca_client_pro']);

        return $clients;
    }
}
