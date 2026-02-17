<?php

namespace App\Service\Suivi;

use Doctrine\DBAL\Connection;
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
     */
    public function getFilters(): array
    {
        // Décommenter si problème de cache
        // $this->cache->delete('suivi_filters');

        // Garde en cache les informations nécessaires aux filtres pour éviter de relancer les requêtes SQL
        return $this->cache->get('suivi_filters', function () {
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
                    ];
                }, $this->connection->fetchAllAssociative('SELECT agr_centre, reseau_nom, ville FROM centre ORDER BY reseau_nom, ville')),
                'controleurs' => $this->connection->fetchAllAssociative('SELECT id, nom, prenom FROM salarie'),
            ];
        });
    }
}
