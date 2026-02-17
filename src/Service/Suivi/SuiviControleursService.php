<?php

namespace App\Service\Suivi;

class SuiviControleursService
{
    /*
     * Traite les données récupérées en SQL pour obtenir une moyenne pour chaque salarié
     */
    public function getControleursStats(array $synthese): array
    {
        $controleursStats = [];

        foreach ($synthese as $societe => $centres) {
            foreach ($centres as $centre) {
                foreach ($centre['salaries'] as $salarie) {
                    $id = $salarie['id'];

                    if (!isset($controleursStats[$id])) {
                        $controleursStats[$id] = [
                            'nom' => $salarie['nom'] . ' ' . $salarie['prenom'],
                            'nb_controles_auto' => 0,
                            'nb_controles_moto' => 0,
                            'ca_auto' => 0.0,
                            'ca_moto' => 0.0,
                            'temps_total_auto' => 0.0,
                            'temps_total_moto' => 0.0,
                            'refus_auto' => 0,
                            'refus_moto' => 0,
                            'prix_moyen_auto' => 0.0,
                            'prix_moyen_moto' => 0.0,
                            'temps_moyen_auto' => 0.0,
                            'temps_moyen_moto' => 0.0,
                            'taux_refus_auto' => 0.0,
                            'taux_refus_moto' => 0.0,
                            'nb_particuliers' => 0,
                            'nb_professionnels' => 0,
                        ];
                    }

                    $controleursStats[$id]['nb_controles_auto'] += (int)($salarie['nb_auto'] ?? 0);
                    $controleursStats[$id]['nb_controles_moto'] += (int)($salarie['nb_moto'] ?? 0);
                    $controleursStats[$id]['ca_auto'] += (float)($salarie['total_ht_vtp'] + $salarie['total_ht_cv'] + $salarie['total_ht_vtc'] + $salarie['total_ht_vol']);
                    $controleursStats[$id]['ca_moto'] += (float)($salarie['total_ht_clvtp'] + $salarie['total_ht_clcv']);
                    $controleursStats[$id]['temps_total_auto'] += (float)($salarie['temps_total_auto'] ?? 0);
                    $controleursStats[$id]['temps_total_moto'] += (float)($salarie['temps_total_moto'] ?? 0);
                    $controleursStats[$id]['refus_auto'] += (int)($salarie['refus_auto'] ?? 0);
                    $controleursStats[$id]['refus_moto'] += (int)($salarie['refus_moto'] ?? 0);

                    // Nombre de clients particuliers/pros
                    $controleursStats[$id]['nb_particuliers'] += $salarie['nb_particuliers'];
                    $controleursStats[$id]['nb_professionnels'] += $salarie['nb_professionnels'];
                }
            }
        }

        foreach ($controleursStats as &$c) {
            $c['prix_moyen_auto'] = $c['nb_controles_auto'] > 0 ? $c['ca_auto'] / $c['nb_controles_auto'] : 0.0;
            $c['prix_moyen_moto'] = $c['nb_controles_moto'] > 0 ? $c['ca_moto'] / $c['nb_controles_moto'] : 0.0;
            $c['temps_moyen_auto'] = $c['nb_controles_auto'] > 0 ? $c['temps_total_auto'] / $c['nb_controles_auto'] : 0.0;
            $c['temps_moyen_moto'] = $c['nb_controles_moto'] > 0 ? $c['temps_total_moto'] / $c['nb_controles_moto'] : 0.0;
            $c['taux_refus_auto'] = $c['nb_controles_auto'] > 0 ? ($c['refus_auto'] / $c['nb_controles_auto']) * 100 : 0.0;
            $c['taux_refus_moto'] = $c['nb_controles_moto'] > 0 ? ($c['refus_moto'] / $c['nb_controles_moto']) * 100 : 0.0;

            // Répartition particuliers/pros
            $total = $c['nb_particuliers'] + $c['nb_professionnels'];
            $c['pct_part'] = $total > 0 ? ($c['nb_particuliers'] / $total * 100) : 0;
            $c['pct_pro'] = $total > 0 ? ($c['nb_professionnels'] / $total * 100) : 0;
        }
        unset($c);

        $statsPourMoyennes = [
            'nb_controles_auto', 'nb_controles_moto',
            'prix_moyen_auto', 'prix_moyen_moto',
            'temps_moyen_auto', 'temps_moyen_moto',
            'taux_refus_auto', 'taux_refus_moto',
        ];
        $moyennesGlobales = [];

        foreach ($statsPourMoyennes as $stat) {
            $valeurs = array_column($controleursStats, $stat);
            $moyennesGlobales[$stat] = count($valeurs) > 0 ? array_sum($valeurs) / count($valeurs) : 0;
        }

        usort($controleursStats, fn($a, $b) =>
            ($b['nb_controles_auto'] + $b['nb_controles_moto']) <=> ($a['nb_controles_auto'] + $a['nb_controles_moto'])
        );

        return [$controleursStats, $moyennesGlobales];
    }
}
