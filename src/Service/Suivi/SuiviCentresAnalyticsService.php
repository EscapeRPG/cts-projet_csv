<?php

namespace App\Service\Suivi;

/**
 * Computes center-focused analytics aggregates for reporting views.
 */
final class SuiviCentresAnalyticsService
{
    private const array MONTH_LABELS = [
        1 => 'Jan',
        2 => 'Fev',
        3 => 'Mar',
        4 => 'Avr',
        5 => 'Mai',
        6 => 'Juin',
        7 => 'Juil',
        8 => 'Aout',
        9 => 'Sep',
        10 => 'Oct',
        11 => 'Nov',
        12 => 'Dec',
    ];

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
            if ($row['agr_centre_cl']) $centre .= ' - ' . strtoupper((string) ($row['agr_centre_cl']));
            $societe = (string) ($row['societe_nom'] ?? 'Société inconnue');
            $reseau = (string) ($row['reseau_nom'] ?? '');
            $key = $societe . '|' . $centre;

            if (!isset($centres[$key])) {
                $centres[$key] = [
                    'societe' => $societe,
                    'reseau' => $reseau,
                    'ville' => $row['centre_ville'],
                    'agrement' => $centre,
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

        usort($centres, static fn (array $a, array $b) => $a['agrement'] <=> $b['agrement']);

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

    /**
     * Builds monthly CA/control matrices by controller.
     *
     * @param array<int, array<string, mixed>> $rows Monthly controller rows.
     *
     * @return array{
     *     months: array<int, string>,
     *     salaries: array<int, array{name: string, ca: array<int, float>, volumes: array<int, int>, ca_total: float, volumes_total: int}>,
     *     max_ca: float,
     *     max_volumes: int
     * }
     */
    public function buildMonthlySalarieBreakdown(array $rows): array
    {
        $salaries = [];
        $maxCa = 0.0;
        $maxVolumes = 0;

        foreach ($rows as $row) {
            $id = (string) ($row['salarie_id'] ?? '');
            if ($id === '') {
                continue;
            }

            if (!isset($salaries[$id])) {
                $name = trim(sprintf(
                    '%s %s',
                    $this->safeUpper((string) ($row['salarie_nom'] ?? '')),
                    $this->safeUcfirst((string) ($row['salarie_prenom'] ?? ''))
                ));

                $salaries[$id] = [
                    'name' => $name !== '' ? $name : 'Salarié inconnu',
                    'ca' => array_fill(1, 12, 0.0),
                    'volumes' => array_fill(1, 12, 0),
                    'ca_total' => 0.0,
                    'volumes_total' => 0,
                ];
            }

            $month = (int) ($row['mois'] ?? 0);
            if ($month < 1 || $month > 12) {
                continue;
            }

            $ca = (float) ($row['ca_total_ht'] ?? 0);
            $volumes = (int) ($row['nb_controles'] ?? 0);

            $salaries[$id]['ca'][$month] += $ca;
            $salaries[$id]['volumes'][$month] += $volumes;
            $salaries[$id]['ca_total'] += $ca;
            $salaries[$id]['volumes_total'] += $volumes;

            $maxCa = max($maxCa, $salaries[$id]['ca'][$month]);
            $maxVolumes = max($maxVolumes, $salaries[$id]['volumes'][$month]);
        }

        $salaries = array_values($salaries);
        usort($salaries, static fn (array $a, array $b): int => $a['name'] <=> $b['name']);

        return [
            'months' => self::MONTH_LABELS,
            'salaries' => $salaries,
            'max_ca' => $maxCa,
            'max_volumes' => $maxVolumes,
        ];
    }

    /**
     * Uppercases text using multibyte support when available.
     */
    private function safeUpper(string $value): string
    {
        if (function_exists('mb_strtoupper')) {
            return mb_strtoupper($value);
        }

        return strtoupper($value);
    }

    /**
     * Capitalizes text using multibyte support when available.
     */
    private function safeUcfirst(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (function_exists('mb_ucfirst')) {
            return mb_ucfirst($value);
        }

        if (function_exists('mb_strtoupper') && function_exists('mb_substr')) {
            return mb_strtoupper(mb_substr($value, 0, 1)) . mb_substr($value, 1);
        }

        return ucfirst($value);
    }
}
