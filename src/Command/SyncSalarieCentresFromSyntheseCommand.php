<?php

namespace App\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cts:sync-salarie-centres',
    description: 'Associe les salaries aux centres via synthese_controles (salarie_id + agr_centre).'
)]
/**
 * Synchronizes employee-to-centre associations from `synthese_controles`.
 */
final class SyncSalarieCentresFromSyntheseCommand extends Command
{
    /**
     * @param Connection $connection DBAL connection used to read/write association data.
     */
    public function __construct(private readonly Connection $connection)
    {
        parent::__construct();
    }

    /**
     * Configures command options.
     */
    protected function configure(): void
    {
        $this
            ->addOption(
                'execute',
                null,
                InputOption::VALUE_NONE,
                'Effectue les INSERT en base (sinon dry-run).'
            )
            ->addOption(
                'reset',
                null,
                InputOption::VALUE_NONE,
                'Vide la table salarie_centre avant synchronisation (uniquement avec --execute).'
            )
            ->addOption(
                'year',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Limite la source synthese_controles a une ou plusieurs annees (ex: --year=2026 --year=2025).'
            );
    }

    /**
     * Executes synchronization from `synthese_controles` into `salarie_centre`.
     *
     * @param InputInterface $input Console input.
     * @param OutputInterface $output Console output.
     *
     * @return int Command exit status.
     *
     * @throws Exception
     * @throws \Throwable
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $execute = (bool) $input->getOption('execute');
        $reset = (bool) $input->getOption('reset');
        /** @var array<int, string|int> $yearsRaw */
        $yearsRaw = $input->getOption('year') ?? [];
        $years = array_values(array_filter(array_map(
            static fn ($v): int => (int) $v,
            is_array($yearsRaw) ? $yearsRaw : []
        ), static fn (int $y): bool => $y > 0));

        if ($reset && !$execute) {
            $io->error('Option --reset interdite sans --execute (simulation).');
            return Command::INVALID;
        }

        $io->title('Sync salarie_centre depuis synthese_controles');

        $where = [
            "sc.salarie_id IS NOT NULL",
            "sc.salarie_id <> 0",
            "sc.agr_centre IS NOT NULL",
            "TRIM(sc.agr_centre) <> ''",
        ];
        $params = [];
        $types = [];

        if ($years !== []) {
            $where[] = 'sc.annee IN (:years)';
            $params['years'] = $years;
            $types['years'] = Connection::PARAM_INT_ARRAY;
        }

        $whereSql = implode(' AND ', $where);

        $distinctPairs = (int) $this->connection->fetchOne(
            "
                SELECT COUNT(*) FROM (
                    SELECT DISTINCT sc.salarie_id, sc.agr_centre
                    FROM synthese_controles sc
                    WHERE {$whereSql}
                ) t
            ",
            $params,
            $types
        );

        $joinablePairs = (int) $this->connection->fetchOne(
            "
                SELECT COUNT(*) FROM (
                    SELECT DISTINCT sc.salarie_id, c.id AS centre_id
                    FROM synthese_controles sc
                    INNER JOIN centre c ON c.agr_centre = sc.agr_centre
                    INNER JOIN salarie s ON s.id = sc.salarie_id
                    WHERE {$whereSql}
                ) t
            ",
            $params,
            $types
        );

        $missingCentrePairs = (int) $this->connection->fetchOne(
            "
                SELECT COUNT(*) FROM (
                    SELECT DISTINCT sc.salarie_id, sc.agr_centre
                    FROM synthese_controles sc
                    LEFT JOIN centre c ON c.agr_centre = sc.agr_centre
                    WHERE {$whereSql} AND c.id IS NULL
                ) t
            ",
            $params,
            $types
        );

        $missingSalariePairs = (int) $this->connection->fetchOne(
            "
                SELECT COUNT(*) FROM (
                    SELECT DISTINCT sc.salarie_id, sc.agr_centre
                    FROM synthese_controles sc
                    LEFT JOIN salarie s ON s.id = sc.salarie_id
                    WHERE {$whereSql} AND s.id IS NULL
                ) t
            ",
            $params,
            $types
        );

        $io->definitionList(
            ['Mode' => $execute ? 'EXÉCUTION' : 'SIMULATION'],
            ['Filtre année' => $years !== [] ? implode(', ', $years) : 'aucun'],
            ['Couples distincts (salarie_id, agr_centre)' => $distinctPairs],
            ['Couples joignables (salarie + centre)' => $joinablePairs],
            ['Couples sans centre correspondant' => $missingCentrePairs],
            ['Couples sans salarie correspondant' => $missingSalariePairs],
        );

        if (!$execute) {
            $io->note('Aucune écriture en base (passer --execute pour appliquer).');
            return Command::SUCCESS;
        }

        $this->connection->beginTransaction();
        try {
            if ($reset) {
                $io->warning('Reset: suppression du contenu de salarie_centre...');
                $this->connection->executeStatement('DELETE FROM salarie_centre');
            }

            // MySQL: primary key (salarie_id, centre_id) => IGNORE évite les doublons.
            $sql = "
                INSERT IGNORE INTO salarie_centre (salarie_id, centre_id)
                SELECT DISTINCT sc.salarie_id, c.id AS centre_id
                FROM synthese_controles sc
                INNER JOIN centre c ON c.agr_centre = sc.agr_centre
                INNER JOIN salarie s ON s.id = sc.salarie_id
                WHERE {$whereSql}
            ";

            $affected = $this->connection->executeStatement($sql, $params, $types);

            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        }

        $io->success(sprintf('Synchronisation terminée. %d association(s) insérée(s) (doublons ignorés).', $affected));

        return Command::SUCCESS;
    }
}
