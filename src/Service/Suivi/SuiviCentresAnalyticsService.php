<?php

namespace App\Service\Suivi;

/**
 * Computes center-focused analytics aggregates for reporting views.
 */
final class SuiviCentresAnalyticsService
{
    /**
     * Builds center rows with year-over-year metrics and split counts/revenues.
     *
     * @param array<int, array<string, mixed>> $rows Raw center rows.
     * @param int|null $referenceYear Reference year for N / N-1 / N-2 calculations.
     *
     * @return array<int, array<string, mixed>> Aggregated and sorted center rows.
     */
    public function buildCentresRows(array $rows, ?int $referenceYear = null): array
    {
        $yearNow = $referenceYear ?? (int) date('Y');
        $yearN1 = $yearNow - 1;
        $yearN2 = $yearNow - 2;

        $centres = [];

        foreach ($rows as $row) {
            $centre = strtoupper((string) ($row['agr_centre'] ?? 'Centre inconnu'));
            $societe = (string) ($row['societe_nom'] ?? 'Société inconnue');
            $reseau = (string) ($row['reseau_nom'] ?? '');
            $key = $societe . '|' . $centre;

            if (!isset($centres[$key])) {
                $centres[$key] = [
                    'nom' => $centre,
                    'societe' => $societe,
                    'reseau' => $reseau,
                    'ca_client_pro' => 0.0,
                    'ca_now' => 0.0,
                    'ca_n1' => 0.0,
                    'ca_n2' => 0.0,
                    'ca_pro_now' => 0.0,
                    'ca_pro_n1' => 0.0,
                    'ca_pro_n2' => 0.0,
                    'ca_part_now' => 0.0,
                    'ca_part_n1' => 0.0,
                    'ca_part_n2' => 0.0,
                    'nb_ctrl_now' => 0,
                    'nb_ctrl_n1' => 0,
                    'nb_ctrl_n2' => 0,
                    'nb_ctrl_pro_now' => 0,
                    'nb_ctrl_pro_n1' => 0,
                    'nb_ctrl_pro_n2' => 0,
                    'nb_ctrl_part_now' => 0,
                    'nb_ctrl_part_n1' => 0,
                    'nb_ctrl_part_n2' => 0,
                    'per_n1' => 0.0,
                    'per_n2' => 0.0,
                    'per_ca_n1' => 0.0,
                    'per_ca_n2' => 0.0,
                ];
            }

            $annee = (int) ($row['annee'] ?? 0);
            $ca = (float) ($row['ca'] ?? 0);
            $caPro = (float) ($row['ca_pro'] ?? 0);
            $caPart = (float) ($row['ca_part'] ?? 0);
            $nbControles = (int) ($row['nb_controles'] ?? 0);
            $nbPro = (int) ($row['nb_pro'] ?? 0);
            $nbPart = (int) ($row['nb_part'] ?? 0);

            if ($annee === $yearNow) {
                $centres[$key]['ca_now'] += $ca;
                $centres[$key]['ca_pro_now'] += $caPro;
                $centres[$key]['ca_part_now'] += $caPart;
                $centres[$key]['nb_ctrl_now'] += $nbControles;
                $centres[$key]['nb_ctrl_pro_now'] += $nbPro;
                $centres[$key]['nb_ctrl_part_now'] += $nbPart;
            } elseif ($annee === $yearN1) {
                $centres[$key]['ca_n1'] += $ca;
                $centres[$key]['ca_pro_n1'] += $caPro;
                $centres[$key]['ca_part_n1'] += $caPart;
                $centres[$key]['nb_ctrl_n1'] += $nbControles;
                $centres[$key]['nb_ctrl_pro_n1'] += $nbPro;
                $centres[$key]['nb_ctrl_part_n1'] += $nbPart;
            } elseif ($annee === $yearN2) {
                $centres[$key]['ca_n2'] += $ca;
                $centres[$key]['ca_pro_n2'] += $caPro;
                $centres[$key]['ca_part_n2'] += $caPart;
                $centres[$key]['nb_ctrl_n2'] += $nbControles;
                $centres[$key]['nb_ctrl_pro_n2'] += $nbPro;
                $centres[$key]['nb_ctrl_part_n2'] += $nbPart;
            }

            $centres[$key]['ca_client_pro'] += $ca;
        }

        foreach ($centres as &$centre) {
            $centre['per_n1'] = $centre['nb_ctrl_n1'] !== 0
                ? (($centre['nb_ctrl_now'] - $centre['nb_ctrl_n1']) / $centre['nb_ctrl_n1']) * 100
                : ($centre['nb_ctrl_now'] === 0 ? 0.0 : 100.0);

            $centre['per_n2'] = $centre['nb_ctrl_n2'] !== 0
                ? (($centre['nb_ctrl_now'] - $centre['nb_ctrl_n2']) / $centre['nb_ctrl_n2']) * 100
                : ($centre['nb_ctrl_now'] === 0 ? 0.0 : 100.0);

            $centre['per_ca_n1'] = $centre['ca_n1'] !== 0.0
                ? (($centre['ca_now'] - $centre['ca_n1']) / $centre['ca_n1']) * 100
                : ($centre['ca_now'] === 0.0 ? 0.0 : 100.0);

            $centre['per_ca_n2'] = $centre['ca_n2'] !== 0.0
                ? (($centre['ca_now'] - $centre['ca_n2']) / $centre['ca_n2']) * 100
                : ($centre['ca_now'] === 0.0 ? 0.0 : 100.0);
        }
        unset($centre);

        usort($centres, static fn (array $a, array $b) => $a['nom'] <=> $b['nom']);

        return $centres;
    }

    /**
     * Computes global professional/individual revenue and volume split totals.
     *
     * @param array<int, array<string, mixed>> $centres Aggregated center rows.
     *
     * @return array<string, float|int> Split summary totals.
     */
    public function buildRevenueSplitSummary(array $centres): array
    {
        $summary = [
            'ca_pro_now' => 0.0,
            'ca_pro_n1' => 0.0,
            'ca_pro_n2' => 0.0,
            'ca_part_now' => 0.0,
            'ca_part_n1' => 0.0,
            'ca_part_n2' => 0.0,
            'vol_pro_now' => 0,
            'vol_pro_n1' => 0,
            'vol_pro_n2' => 0,
            'vol_part_now' => 0,
            'vol_part_n1' => 0,
            'vol_part_n2' => 0,
        ];

        foreach ($centres as $centre) {
            $summary['ca_pro_now'] += (float) ($centre['ca_pro_now'] ?? 0);
            $summary['ca_pro_n1'] += (float) ($centre['ca_pro_n1'] ?? 0);
            $summary['ca_pro_n2'] += (float) ($centre['ca_pro_n2'] ?? 0);
            $summary['ca_part_now'] += (float) ($centre['ca_part_now'] ?? 0);
            $summary['ca_part_n1'] += (float) ($centre['ca_part_n1'] ?? 0);
            $summary['ca_part_n2'] += (float) ($centre['ca_part_n2'] ?? 0);
            $summary['vol_pro_now'] += (int) ($centre['nb_ctrl_pro_now'] ?? 0);
            $summary['vol_pro_n1'] += (int) ($centre['nb_ctrl_pro_n1'] ?? 0);
            $summary['vol_pro_n2'] += (int) ($centre['nb_ctrl_pro_n2'] ?? 0);
            $summary['vol_part_now'] += (int) ($centre['nb_ctrl_part_now'] ?? 0);
            $summary['vol_part_n1'] += (int) ($centre['nb_ctrl_part_n1'] ?? 0);
            $summary['vol_part_n2'] += (int) ($centre['nb_ctrl_part_n2'] ?? 0);
        }

        return $summary;
    }
}
