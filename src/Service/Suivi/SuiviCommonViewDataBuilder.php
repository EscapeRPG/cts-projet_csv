<?php

namespace App\Service\Suivi;

use Doctrine\DBAL\Exception;
use Psr\Cache\InvalidArgumentException;

/**
 * Builds shared filter-related payload for activity monitoring Twig views.
 */
readonly class SuiviCommonViewDataBuilder
{
    /**
     * @param SuiviFiltersProvider $filtersProvider Service resolving available filter values.
     */
    public function __construct(private SuiviFiltersProvider $filtersProvider)
    {
    }

    /**
     * Builds common view data from selected filters.
     *
     * @param array<string, mixed> $filters Selected filters.
     *
     * @return array<string, mixed> Common Twig payload.
     *
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public function build(array $filters): array
    {
        $filtersData = $this->filtersProvider->getFilters($filters);

        return [
            'filters' => $filters,
            'selected' => $filters,
            'anneeCourante' => $filters['annee'],
            'annees' => $filtersData['annees'],
            'mois' => $filtersData['mois'],
            'reseaux' => $filtersData['reseaux'],
            'societes' => $filtersData['societes'],
            'centres' => $filtersData['centres'],
            'controleurs' => $filtersData['controleurs'],
            'types_controles' => $filtersData['types_controles'] ?? ['VTP', 'VTC', 'CV', 'VOL'],
            'vehicules' => $filtersData['vehicules'] ?? ['VL', 'CL'],
        ];
    }
}
