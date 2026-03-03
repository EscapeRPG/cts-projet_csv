<?php

namespace App\Repository;

use App\Repository\Suivi\SuiviCentresRepository;
use App\Repository\Suivi\SuiviProsRepository;
use App\Repository\Suivi\SuiviSyntheseRepository;
use Doctrine\DBAL\Exception;

/**
 * Facade repository for activity-monitoring data access.
 */
readonly class SuiviActiviteRepository
{
    /**
     * @param SuiviSyntheseRepository $syntheseRepository Repository for synthesized controller activity.
     * @param SuiviProsRepository $prosRepository Repository for professional-client activity.
     * @param SuiviCentresRepository $centresRepository Repository for center-level activity.
     */
    public function __construct(
        private SuiviSyntheseRepository $syntheseRepository,
        private SuiviProsRepository $prosRepository,
        private SuiviCentresRepository $centresRepository
    ) {
    }

    /**
     * Returns synthesized activity rows for the selected filters.
     *
     * @param array<string, mixed> $filters Selected filters.
     *
     * @return array<int, array<string, mixed>> Synthesized activity rows.
     *
     * @throws Exception
     */
    public function fetchSyntheseRows(array $filters = []): array
    {
        return $this->syntheseRepository->fetchSyntheseRows($filters);
    }

    /**
     * Returns professional-client rows for the selected filters.
     *
     * @param array<string, mixed> $filters Selected filters.
     *
     * @return array<int, array<string, mixed>> Professional-client rows.
     *
     * @throws Exception
     */
    public function fetchProClients(array $filters = []): array
    {
        return $this->prosRepository->fetchProClients($filters);
    }

    /**
     * Returns center-level rows for the selected filters.
     *
     * @param array<string, mixed> $filters Selected filters.
     *
     * @return array<int, array<string, mixed>> Center-level rows.
     *
     * @throws Exception
     */
    public function fetchCentres(array $filters = []): array
    {
        return $this->centresRepository->fetchCentres($filters);
    }
}
