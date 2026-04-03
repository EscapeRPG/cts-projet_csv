<?php

namespace App\Service\Suivi;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Psr\Cache\InvalidArgumentException;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Provides filter metadata and dependent filter options for activity monitoring.
 */
readonly class SuiviFiltersProvider
{
    /**
     * @param Connection $connection DBAL connection used to query filter sources.
     * @param CacheInterface $cache Cache used to memoize base filter datasets.
     */
    public function __construct(
        private Connection     $connection,
        private CacheInterface $cache
    )
    {
    }

    /**
     * Returns filter datasets, optionally constrained by selected company/center values.
     *
     * @param array<string, mixed> $currentFilters Current filter selections.
     *
     * @return array<string, mixed> Filter metadata and dependent options.
     *
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public function getFilters(array $currentFilters = []): array
    {
        // Décommenter si problème de cache
        // $this->cache->delete('suivi_filters');

        // Garde en cache les informations nécessaires aux filtres pour éviter de relancer les requêtes SQL
        $baseFilters = $this->cache->get('suivi_filters', function () {
            $centres = $this->connection->fetchAllAssociative("
                SELECT c.agr_centre, c.reseau_nom, c.ville, s.nom AS societe_nom
                FROM centre c
                LEFT JOIN societe s ON s.id = c.societe_id
                ORDER BY c.reseau_nom, c.ville
            ");

            $controleurs = $this->connection->fetchAllAssociative("
                SELECT sa.id, sa.nom, sa.prenom, so.nom AS societe_nom
                FROM salarie sa
                LEFT JOIN societe so ON so.id = sa.societe_id
                ORDER BY sa.nom, sa.prenom
            ");

            return [
                'annees' => $this->connection->fetchFirstColumn('SELECT DISTINCT YEAR(date_ctrl) FROM controles ORDER BY YEAR(date_ctrl)'),
                'mois' => [
                    1 => 'Janvier',
                    2 => 'Février',
                    3 => 'Mars',
                    4 => 'Avril',
                    5 => 'Mai',
                    6 => 'Juin',
                    7 => 'Juillet',
                    8 => 'Août',
                    9 => 'Septembre',
                    10 => 'Octobre',
                    11 => 'Novembre',
                    12 => 'Décembre',
                ],
                'reseaux' => $this->connection->fetchFirstColumn('SELECT DISTINCT reseau_nom FROM centre ORDER BY reseau_nom'),
                'societes' => $this->connection->fetchFirstColumn("SELECT nom FROM societe WHERE nom != 'CTS' AND nom != 'KERMILO' ORDER BY nom"),
                'centres' => array_map(function ($c) {
                    // mapping réseau -> code
                    $codes = [
                        'Dekra' => 'DE',
                        'Norisko' => 'NO',
                        'Auto-Sécurité' => 'AS',
                        'Autovision' => 'AU',
                        'Sécuritest' => 'SE',
                        'Vérif\'Autos' => 'VA'
                    ];

                    $reseauCode = $codes[$c['reseau_nom']] ?? strtoupper(substr($c['reseau_nom'], 0, 2));

                    return [
                        'agr_centre' => $c['agr_centre'],
                        'reseau_nom' => $c['reseau_nom'],
                        'label' => $reseauCode . ' ' . $c['ville'],
                        'societe_nom' => $c['societe_nom'],
                    ];
                }, $centres),
                'controleurs' => $controleurs,
                'types_controles' => ['VTP', 'VTC', 'CV', 'VOL'],
            ];
        });

        $allowedCentres = array_values(array_filter(array_map(
            static fn ($value): string => trim((string) $value),
            $currentFilters['allowed_centres'] ?? []
        )));
        if ($allowedCentres === []) {
            $allowedCentres = null;
        }

        $scopedReseaux = $baseFilters['reseaux'];
        $scopedSocietes = $baseFilters['societes'];

        if ($allowedCentres !== null) {
            $allowedSet = array_flip($allowedCentres);
            $scopedCentresAll = array_values(array_filter(
                $baseFilters['centres'],
                static fn (array $centre): bool => isset($allowedSet[$centre['agr_centre'] ?? ''])
            ));

            $scopedReseaux = array_values(array_unique(array_filter(array_map(
                static fn (array $centre): string => trim((string) ($centre['reseau_nom'] ?? '')),
                $scopedCentresAll
            ))));
            sort($scopedReseaux, SORT_NATURAL | SORT_FLAG_CASE);

            $scopedSocietes = array_values(array_unique(array_filter(array_map(
                static fn (array $centre): string => trim((string) ($centre['societe_nom'] ?? '')),
                $scopedCentresAll
            ))));
            // Keep existing global exclusions.
            $scopedSocietes = array_values(array_diff($scopedSocietes, ['CTS', 'KERMILO']));
            sort($scopedSocietes, SORT_NATURAL | SORT_FLAG_CASE);
        }

        $selectedSocietes = array_values(array_filter(array_map(
            static fn($s) => trim((string)$s),
            $currentFilters['societe'] ?? []
        )));
        $selectedCentres = array_values(array_filter(array_map(
            static fn($c) => trim((string)$c),
            $currentFilters['centre'] ?? []
        )));

        if (empty($selectedSocietes) && empty($selectedCentres)) {
            $centres = $baseFilters['centres'];
            if ($allowedCentres !== null) {
                $allowedSet = array_flip($allowedCentres);
                $centres = array_values(array_filter(
                    $centres,
                    static fn (array $centre): bool => isset($allowedSet[$centre['agr_centre'] ?? ''])
                ));
            }

            $controleurs = $baseFilters['controleurs'];
            if ($allowedCentres !== null) {
                $controleurs = $this->connection->executeQuery(
                    "
                        SELECT DISTINCT sa.id, sa.nom, sa.prenom
                        FROM synthese_controles sc
                        INNER JOIN salarie sa ON sa.id = sc.salarie_id
                        WHERE sc.agr_centre IN (:allowed_centres)
                        ORDER BY sa.nom, sa.prenom
                    ",
                    ['allowed_centres' => $allowedCentres],
                    ['allowed_centres' => ArrayParameterType::STRING]
                )->fetchAllAssociative();
            }

            return [
                ...$baseFilters,
                'reseaux' => $scopedReseaux,
                'societes' => $scopedSocietes,
                'centres' => array_map(fn($c) => [
                    'agr_centre' => $c['agr_centre'],
                    'label' => $c['label'],
                ], $centres),
                'controleurs' => array_map(fn($c) => [
                    'id' => $c['id'],
                    'nom' => $c['nom'],
                    'prenom' => $c['prenom'],
                ], $controleurs),
            ];
        }

        $centresParams = [];
        $centresTypes = [];

        if ($selectedSocietes === []) {
            $centresSql = "
                SELECT c.agr_centre, c.reseau_nom, c.ville
                FROM centre c
            ";
            $centresWhere = [];
        } else {
            $centresSql = "
                SELECT c.agr_centre, c.reseau_nom, c.ville
                FROM centre c
                INNER JOIN societe s ON s.id = c.societe_id
            ";
            $centresWhere = ['s.nom IN (:societes)'];
            $centresParams['societes'] = $selectedSocietes;
            $centresTypes['societes'] = ArrayParameterType::STRING;
        }

        if ($allowedCentres !== null) {
            $centresWhere[] = 'c.agr_centre IN (:allowed_centres)';
            $centresParams['allowed_centres'] = $allowedCentres;
            $centresTypes['allowed_centres'] = ArrayParameterType::STRING;
        }

        if ($centresWhere !== []) {
            $centresSql .= ' WHERE ' . implode(' AND ', $centresWhere);
        }
        $centresSql .= ' ORDER BY c.reseau_nom, c.ville';

        $filteredCentresRaw = $this->connection->executeQuery(
            $centresSql,
            $centresParams,
            $centresTypes
        )->fetchAllAssociative();

        $controleursWhere = [];
        $controleursParams = [];
        $controleursTypes = [];

        if ($selectedSocietes !== []) {
            $controleursWhere[] = 'sc.societe_nom IN (:societes)';
            $controleursParams['societes'] = $selectedSocietes;
            $controleursTypes['societes'] = ArrayParameterType::STRING;
        }
        if ($selectedCentres !== []) {
            $controleursWhere[] = 'sc.agr_centre IN (:centres)';
            $controleursParams['centres'] = $selectedCentres;
            $controleursTypes['centres'] = ArrayParameterType::STRING;
        }
        if ($allowedCentres !== null) {
            $controleursWhere[] = 'sc.agr_centre IN (:allowed_centres)';
            $controleursParams['allowed_centres'] = $allowedCentres;
            $controleursTypes['allowed_centres'] = ArrayParameterType::STRING;
        }

        $controleursSql = "
            SELECT DISTINCT sa.id, sa.nom, sa.prenom
            FROM synthese_controles sc
            INNER JOIN salarie sa ON sa.id = sc.salarie_id
        ";
        if ($controleursWhere !== []) {
            $controleursSql .= ' WHERE ' . implode(' AND ', $controleursWhere);
        }
        $controleursSql .= ' ORDER BY sa.nom, sa.prenom';

        $filteredControleursRaw = $this->connection->executeQuery(
            $controleursSql,
            $controleursParams,
            $controleursTypes
        )->fetchAllAssociative();

        $codes = [
            'Dekra' => 'DE',
            'Norisko' => 'NO',
            'Auto-Sécurité' => 'AS',
            'Autovision' => 'AU',
            'Sécuritest' => 'SE',
            'Vérif\'Autos' => 'VA'
        ];

        return [
            ...$baseFilters,
            'reseaux' => $scopedReseaux,
            'societes' => $scopedSocietes,
            'centres' => array_map(fn($c) => [
                'agr_centre' => $c['agr_centre'],
                'label' => (($codes[$c['reseau_nom']] ?? strtoupper(substr($c['reseau_nom'], 0, 2))) . ' ' . $c['ville']),
            ], $filteredCentresRaw),
            'controleurs' => array_map(fn($c) => [
                'id' => $c['id'],
                'nom' => $c['nom'],
                'prenom' => $c['prenom'],
            ], $filteredControleursRaw),
        ];
    }
}
