<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

/**
 * Provides metadata about synthesized table refreshes.
 */
final readonly class SyntheseMetaProvider
{
    /**
     * @param Connection $connection Database connection used to query `synthese_meta`.
     */
    public function __construct(private Connection $connection)
    {
    }

    /**
     * Returns the latest known refresh timestamp across synthesis jobs.
     *
     * @return \DateTimeImmutable|null Last refresh date, or null when unavailable.
     */
    public function getLastDatabaseUpdateAt(): ?\DateTimeImmutable
    {
        try {
            $lastRunAt = $this->connection->fetchOne(
                "
                    SELECT MAX(last_run_at)
                    FROM synthese_meta
                    WHERE meta_key IN ('synthese_controles', 'synthese_pros')
                "
            );
        } catch (\Throwable) {
            return null;
        }

        if ($lastRunAt === false || $lastRunAt === null || trim((string) $lastRunAt) === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable((string) $lastRunAt);
        } catch (\Throwable) {
            return null;
        }
    }
}
