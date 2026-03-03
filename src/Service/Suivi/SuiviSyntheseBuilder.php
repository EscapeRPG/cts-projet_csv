<?php

namespace App\Service\Suivi;

/**
 * Builds synthesized activity structures and totals for reporting views.
 */
class SuiviSyntheseBuilder
{
    private array $types = ['vtp', 'clvtp', 'cv', 'clcv', 'vtc', 'vol', 'clvol'];
    private array $reseaux = [
        'Dekra' => 'DE',
        'Norisko' => 'NO',
        'Auto-Sécurité' => 'AS',
        'Autovision' => 'AU',
        'Sécuritest' => 'SE',
        "Vérif'Autos" => 'VA',
    ];

    /**
     * Builds the nested synthesized structure from flat SQL rows.
     *
     * @param array<int, array<string, mixed>> $rows Raw synthesized rows.
     *
     * @return array<string, mixed> Nested structure grouped by company and center.
     */
    public function buildSynthese(array $rows): array
    {
        $data = $this->buildDataStructure($rows);
        $this->calculateCentreAverages($data);

        return $data;
    }

    /**
     * Computes company-level and global totals from synthesized data.
     *
     * @param array<string, mixed> $synthese Nested synthesized structure.
     *
     * @return array{societes:array<string, array<string, float|int>>,global:array<string, float|int>} Totals payload.
     */
    public function buildActivityTotals(array $synthese): array
    {
        $societeTotals = [];
        $globalTotals = $this->initActivityTotals();

        foreach ($synthese as $societe => $centres) {
            $totals = $this->initActivityTotals();

            foreach ($centres as $centreData) {
                $centreTotals = $centreData['totaux'];
                foreach ($this->types as $type) {
                    $totals['nb_' . $type] += (int)$centreTotals['nb_' . $type];
                    $totals['ca_total_ht_' . $type] += (float)$centreTotals['ca_total_ht_' . $type];
                }
                $totals['nb_controles'] += (int)$centreTotals['nb_controles'];
                $totals['ca_total_ht'] += (float)$centreTotals['ca_total_ht'];
            }

            $this->computeActivityAverages($totals);
            $societeTotals[$societe] = $totals;

            foreach ($this->types as $type) {
                $globalTotals['nb_' . $type] += $totals['nb_' . $type];
                $globalTotals['ca_total_ht_' . $type] += $totals['ca_total_ht_' . $type];
            }
            $globalTotals['nb_controles'] += $totals['nb_controles'];
            $globalTotals['ca_total_ht'] += $totals['ca_total_ht'];
        }

        $this->computeActivityAverages($globalTotals);

        return [
            'societes' => $societeTotals,
            'global' => $globalTotals,
        ];
    }

    /**
     * Builds professional-client structure from flat SQL rows.
     *
     * @param array<int, array<string, mixed>> $rows Raw professional-client rows.
     *
     * @return array<string, mixed> Nested professional-client structure.
     */
    public function buildClientPro(array $rows): array
    {
        return $this->buildDataClientsProStructure($rows);
    }

    /**
     * Builds the core synthesized structure grouped by company and center.
     *
     * @param array<int, array<string, mixed>> $rows Raw synthesized rows.
     *
     * @return array<string, mixed> Nested synthesized structure.
     */
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
                'nom' => $this->safeUpper((string)$row['salarie_nom']),
                'prenom' => $this->safeUcfirst((string)$row['salarie_prenom']),
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

    /**
     * Computes center-level average prices by control type.
     *
     * @param array<string, mixed> $data Synthesized structure passed by reference.
     *
     * @return void
     */
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

    /**
     * Builds professional-client nested structure with N / N-1 / N-2 accumulation.
     *
     * @param array<int, array<string, mixed>> $rows Raw professional-client rows.
     *
     * @return array<string, mixed> Nested professional-client structure.
     */
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

    /**
     * Initializes activity totals structure.
     *
     * @return array<string, float|int> Empty totals structure.
     */
    private function initActivityTotals(): array
    {
        return array_merge(
            array_fill_keys(array_map(fn($t) => 'nb_' . $t, $this->types), 0),
            array_fill_keys(array_map(fn($t) => 'ca_total_ht_' . $t, $this->types), 0.0),
            [
                'nb_controles' => 0,
                'ca_total_ht' => 0.0,
            ],
            array_fill_keys(array_map(fn($t) => 'prix_moyen_' . $t, $this->types), 0.0),
        );
    }

    /**
     * Computes average prices for each control type.
     *
     * @param array<string, float|int> $totals Totals array passed by reference.
     *
     * @return void
     */
    private function computeActivityAverages(array &$totals): void
    {
        foreach ($this->types as $type) {
            $totals['prix_moyen_' . $type] = $totals['nb_' . $type] > 0
                ? $totals['ca_total_ht_' . $type] / $totals['nb_' . $type]
                : 0.0;
        }
    }

    /**
     * Uppercases text using multibyte support when available.
     *
     * @param string $value Input value.
     *
     * @return string Uppercased string.
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
     *
     * @param string $value Input value.
     *
     * @return string Capitalized string.
     */
    private function safeUcfirst(string $value): string
    {
        $value = trim($value);
        if ($value == '') {
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
