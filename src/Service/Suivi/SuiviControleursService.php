<?php

namespace App\Service\Suivi;

/**
 * Computes per-controller and global controller performance metrics.
 */
class SuiviControleursService
{
    /**
     * Builds controller statistics and global averages from synthesized data.
     *
     * @param array<string, mixed> $synthese Synthesized activity tree.
     *
     * @return array{0:array<int, array<string, mixed>>, 1:array<string, float>} Per-controller stats and global averages.
     */
    public function getControleursStats(array $synthese): array
    {
        $controleursStats = [];

        foreach ($synthese as $societe => $centres) {
            foreach ($centres as $centre) {
                foreach ($centre['salaries'] as $salarie) {
                    $id = (string)($salarie['id'] ?? '0');
                    $agr = (string)($salarie['agr'] ?? 'Agrément inconnu');
                    $key = $id . '|' . $agr;

                    if (!isset($controleursStats[$key])) {
                        $controleursStats[$key] = [
                            'nom' => $salarie['nom'] . ' ' . $salarie['prenom'],
                            'agr' => $agr,
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

                    $controleursStats[$key]['nb_controles_auto'] += (int)($salarie['nb_auto'] ?? 0);
                    $controleursStats[$key]['nb_controles_moto'] += (int)($salarie['nb_moto'] ?? 0);
                    $controleursStats[$key]['ca_auto'] += (float)($salarie['total_ht_vtp'] + $salarie['total_ht_cv'] + $salarie['total_ht_vtc'] + $salarie['total_ht_vol']);
                    $controleursStats[$key]['ca_moto'] += (float)($salarie['total_ht_clvtp'] + $salarie['total_ht_clcv'] + $salarie['total_ht_clvol']);
                    $controleursStats[$key]['temps_total_auto'] += (float)($salarie['temps_total_auto'] ?? 0);
                    $controleursStats[$key]['temps_total_moto'] += (float)($salarie['temps_total_moto'] ?? 0);
                    $controleursStats[$key]['refus_auto'] += (int)($salarie['refus_auto'] ?? 0);
                    $controleursStats[$key]['refus_moto'] += (int)($salarie['refus_moto'] ?? 0);

                    // Nombre de clients particuliers/pros
                    $controleursStats[$key]['nb_particuliers'] += $salarie['nb_particuliers'];
                    $controleursStats[$key]['nb_professionnels'] += $salarie['nb_professionnels'];
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

        $sumNbAuto = (float)array_sum(array_column($controleursStats, 'nb_controles_auto'));
        $sumNbMoto = (float)array_sum(array_column($controleursStats, 'nb_controles_moto'));
        $sumCaAuto = (float)array_sum(array_column($controleursStats, 'ca_auto'));
        $sumCaMoto = (float)array_sum(array_column($controleursStats, 'ca_moto'));
        $sumTempsAuto = (float)array_sum(array_column($controleursStats, 'temps_total_auto'));
        $sumTempsMoto = (float)array_sum(array_column($controleursStats, 'temps_total_moto'));
        $sumRefusAuto = (float)array_sum(array_column($controleursStats, 'refus_auto'));
        $sumRefusMoto = (float)array_sum(array_column($controleursStats, 'refus_moto'));
        $nbControleursAutoActifs = count(array_filter($controleursStats, fn($c) => $c['nb_controles_auto'] > 0));
        $nbControleursMotoActifs = count(array_filter($controleursStats, fn($c) => $c['nb_controles_moto'] > 0));

        $moyennesGlobales = [
            'nb_controles_auto' => $nbControleursAutoActifs > 0 ? $sumNbAuto / $nbControleursAutoActifs : 0.0,
            'nb_controles_moto' => $nbControleursMotoActifs > 0 ? $sumNbMoto / $nbControleursMotoActifs : 0.0,
            'prix_moyen_auto' => $sumNbAuto > 0 ? $sumCaAuto / $sumNbAuto : 0.0,
            'prix_moyen_moto' => $sumNbMoto > 0 ? $sumCaMoto / $sumNbMoto : 0.0,
            'temps_moyen_auto' => $sumNbAuto > 0 ? $sumTempsAuto / $sumNbAuto : 0.0,
            'temps_moyen_moto' => $sumNbMoto > 0 ? $sumTempsMoto / $sumNbMoto : 0.0,
            'taux_refus_auto' => $sumNbAuto > 0 ? ($sumRefusAuto / $sumNbAuto) * 100 : 0.0,
            'taux_refus_moto' => $sumNbMoto > 0 ? ($sumRefusMoto / $sumNbMoto) * 100 : 0.0,
        ];

        usort($controleursStats, fn($a, $b) =>
            ($b['nb_controles_auto'] + $b['nb_controles_moto']) <=> ($a['nb_controles_auto'] + $a['nb_controles_moto'])
        );

        return [$controleursStats, $moyennesGlobales];
    }
}
