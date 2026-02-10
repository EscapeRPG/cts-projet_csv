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

        // À compléter suivant le besoin
        $totauxGlobaux = [
            'nb_controles' => 0,
            'prix_moyen' => 0,
            'temps_moyen' => 0,
            'taux_refus' => 0,
        ];

        foreach ($synthese as $societe => $centres) {
            foreach ($centres as $centre) {
                foreach ($centre['salaries'] as $salarie) {
                    $id = $salarie['id'];

                    if (!isset($controleursStats[$id])) {
                        $controleursStats[$id] = [
                            'nom' => $salarie['nom'] . ' ' . $salarie['prenom'],
                            'nb_controles' => 0,
                            'total_presta_ht' => 0,
                            'temps_moyen' => 0,
                            'taux_refus' => 0,
                            'nb_particuliers' => 0,
                            'nb_professionnels' => 0,
                        ];
                    }

                    // --- Nombre de contrôles ---
                    $controleursStats[$id]['nb_controles'] += $salarie['nb_controles'];
                    $totauxGlobaux['nb_controles'] += $salarie['nb_controles'];

                    // --- Prix total (pour moyenne) ---
                    $controleursStats[$id]['total_presta_ht'] += $salarie['total_presta_ht'];
                    $totauxGlobaux['prix_moyen'] += $salarie['total_presta_ht'];

                    // --- Temps total (pour moyenne) ---
                    $controleursStats[$id]['temps_moyen'] += $salarie['temps_total'];
                    $totauxGlobaux['temps_moyen'] += $salarie['temps_total'];

                    // --- Taux de refus ---
                    $controleursStats[$id]['taux_refus'] += $salarie['taux_refus'];
                    $totauxGlobaux['taux_refus'] += $salarie['taux_refus'];

                    // --- Nombre de clients particuliers/pros
                    $controleursStats[$id]['nb_particuliers'] += $salarie['nb_particuliers'];
                    $controleursStats[$id]['nb_professionnels'] += $salarie['nb_professionnels'];
                }
            }
        }

        // --- Moyenne de prix des contrôleurs ---
        foreach ($controleursStats as &$c) {
            $c['prix_moyen'] = $c['nb_controles'] > 0 ? $c['total_presta_ht'] / $c['nb_controles'] : 0;
        }

        // --- Moyenne de temps des contrôleurs ---
        foreach ($controleursStats as &$c) {
            $c['temps_moyen'] = $c['nb_controles'] > 0 ? $c['temps_moyen'] / $c['nb_controles'] : 0;
        }

        // --- Moyenne de refus des contrôleurs ---
        foreach ($controleursStats as &$c) {
            $c['taux_refus'] = $c['nb_controles'] > 0 ? ($c['taux_refus'] / $c['nb_controles'] * 100) : 0;
        }

        // --- Répartition particuliers/pros
        foreach ($controleursStats as &$c) {
            $total = $c['nb_particuliers'] + $c['nb_professionnels'];
            $c['pct_part'] = $total > 0 ? ($c['nb_particuliers'] / $total * 100) : 0;
            $c['pct_pro'] = $total > 0 ? ($c['nb_professionnels'] / $total * 100) : 0;
        }

        // --- Moyennes globales pour chaque stat ---
        $statsPourMoyennes = ['nb_controles', 'prix_moyen', 'temps_moyen', 'taux_refus'];
        $moyennesGlobales = [];
        foreach ($statsPourMoyennes as $stat) {
            $valeurs = array_column($controleursStats, $stat);
            $moyennesGlobales[$stat] = count($valeurs) > 0 ? array_sum($valeurs) / count($valeurs) : 0;
        }

        // --- Trier par nombre de contrôles décroissant ---
        usort($controleursStats, fn($a, $b) => $b['nb_controles'] <=> $a['nb_controles']);

        return [$controleursStats, $moyennesGlobales];
    }
}
