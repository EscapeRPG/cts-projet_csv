<?php

namespace App\Service\Suivi;

/**
 * Computes analytics datasets for professional-client views.
 */
final class SuiviProAnalyticsService
{
    /**
     * Computes global summary totals and year-over-year percentages.
     *
     * @param array<int, array<string, mixed>> $clients Professional-client rows.
     *
     * @return array<string, float|int> Summary metrics.
     */
    public function buildSummary(array $clients): array
    {
        $summary = [
            'ca_now' => 0.0,
            'ca_n1' => 0.0,
            'ca_n2' => 0.0,
            'vol_now' => 0,
            'vol_n1' => 0,
            'vol_n2' => 0,
            'per_ctrl_n1' => 0.0,
            'per_ctrl_n2' => 0.0,
            'per_ca_n1' => 0.0,
            'per_ca_n2' => 0.0,
        ];

        foreach ($clients as $client) {
            $summary['ca_now'] += (float) ($client['ca_now'] ?? 0);
            $summary['ca_n1'] += (float) ($client['ca_n1'] ?? 0);
            $summary['ca_n2'] += (float) ($client['ca_n2'] ?? 0);
            $summary['vol_now'] += (int) ($client['nb_ctrl_now'] ?? 0);
            $summary['vol_n1'] += (int) ($client['nb_ctrl_n1'] ?? 0);
            $summary['vol_n2'] += (int) ($client['nb_ctrl_n2'] ?? 0);
        }

        $summary['per_ctrl_n1'] = $summary['vol_n1'] !== 0
            ? (($summary['vol_now'] - $summary['vol_n1']) / $summary['vol_n1']) * 100
            : 0.0;
        $summary['per_ctrl_n2'] = $summary['vol_n2'] !== 0
            ? (($summary['vol_now'] - $summary['vol_n2']) / $summary['vol_n2']) * 100
            : 0.0;
        $summary['per_ca_n1'] = $summary['ca_n1'] !== 0.0
            ? (($summary['ca_now'] - $summary['ca_n1']) / $summary['ca_n1']) * 100
            : 0.0;
        $summary['per_ca_n2'] = $summary['ca_n2'] !== 0.0
            ? (($summary['ca_now'] - $summary['ca_n2']) / $summary['ca_n2']) * 100
            : 0.0;

        return $summary;
    }

    /**
     * Builds monthly chart datasets over a rolling 3-year window.
     *
     * @param array<int, array<string, mixed>> $rows Raw monthly rows.
     * @param int|null $referenceYear Reference year for N / N-1 / N-2 charts.
     *
     * @return array{years:array<int,int>,ca:array<int,array<int,float>>,volumes:array<int,array<int,int>>} Chart payload.
     */
    public function buildMonthlyCharts(array $rows, ?int $referenceYear = null): array
    {
        $yearNow = $referenceYear ?? (int) date('Y');
        $years = [$yearNow, $yearNow - 1, $yearNow - 2];

        $ca = [];
        $volumes = [];
        foreach ($years as $year) {
            $ca[$year] = array_fill(1, 12, 0.0);
            $volumes[$year] = array_fill(1, 12, 0);
        }

        foreach ($rows as $row) {
            $year = (int) ($row['annee'] ?? 0);
            $month = (int) ($row['mois'] ?? 0);

            if (!in_array($year, $years, true) || $month < 1 || $month > 12) {
                continue;
            }

            $ca[$year][$month] += (float) ($row['ca'] ?? 0);
            $volumes[$year][$month] += (int) ($row['nb_controles'] ?? 0);
        }

        return [
            'years' => $years,
            'ca' => $ca,
            'volumes' => $volumes,
        ];
    }
}
