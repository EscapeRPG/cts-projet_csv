<?php

namespace App\Service\Suivi;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Psr\Cache\InvalidArgumentException;
use Symfony\Contracts\Cache\CacheInterface;

readonly class SuiviFiltersProvider
{
    public function __construct(
        private Connection     $connection,
        private CacheInterface $cache
    )
    {
    }

    /**
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
                        'label' => $reseauCode . ' ' . $c['ville'],
                        'societe_nom' => $c['societe_nom'],
                    ];
                }, $centres),
                'controleurs' => $controleurs,
                'types_controles' => ['VTP', 'VTC', 'CV', 'VOL'],
            ];
        });

        $selectedSocietes = array_values(array_filter(array_map(
            static fn($s) => trim((string)$s),
            $currentFilters['societe'] ?? []
        )));
        $selectedCentres = array_values(array_filter(array_map(
            static fn($c) => trim((string)$c),
            $currentFilters['centre'] ?? []
        )));

        if (empty($selectedSocietes) && empty($selectedCentres)) {
            return [
                ...$baseFilters,
                'centres' => array_map(fn($c) => [
                    'agr_centre' => $c['agr_centre'],
                    'label' => $c['label'],
                ], $baseFilters['centres']),
                'controleurs' => array_map(fn($c) => [
                    'id' => $c['id'],
                    'nom' => $c['nom'],
                    'prenom' => $c['prenom'],
                ], $baseFilters['controleurs']),
            ];
        }

        $filteredCentresRaw = $selectedSocietes === []
            ? $this->connection->fetchAllAssociative("
                SELECT c.agr_centre, c.reseau_nom, c.ville
                FROM centre c
                ORDER BY c.reseau_nom, c.ville
            ")
            : $this->connection->executeQuery(
                "
                    SELECT c.agr_centre, c.reseau_nom, c.ville
                    FROM centre c
                    INNER JOIN societe s ON s.id = c.societe_id
                    WHERE s.nom IN (:societes)
                    ORDER BY c.reseau_nom, c.ville
                ",
                ['societes' => $selectedSocietes],
                ['societes' => ArrayParameterType::STRING]
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
