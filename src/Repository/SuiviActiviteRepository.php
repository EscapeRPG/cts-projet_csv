<?php

namespace App\Repository;

use App\Repository\Suivi\SuiviCentresRepository;
use App\Repository\Suivi\SuiviProsRepository;
use App\Repository\Suivi\SuiviSyntheseRepository;
use Doctrine\DBAL\Exception;

readonly class SuiviActiviteRepository
{
    public function __construct(
        private SuiviSyntheseRepository $syntheseRepository,
        private SuiviProsRepository $prosRepository,
        private SuiviCentresRepository $centresRepository
    ) {
    }

    /**
     * @throws Exception
     */
    public function fetchSyntheseRows(array $filters = []): array
    {
        return $this->syntheseRepository->fetchSyntheseRows($filters);
    }

    /**
     * @throws Exception
     */
    public function fetchProClients(array $filters = []): array
    {
        return $this->prosRepository->fetchProClients($filters);
    }

    /**
     * @throws Exception
     */
    public function fetchCentres(array $filters = []): array
    {
        return $this->centresRepository->fetchCentres($filters);
    }
}

