<?php

namespace App\Command;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:data:purge-centres',
    description: 'Purge les données importées liées à une liste d’agréments centre (agr_centre).'
)]
/**
 * Deletes imported records linked to specific center approvals.
 */
final class PurgeCentresDataCommand extends Command
{
    /**
     * Default centre approvals to purge when --agr is not provided.
     *
     * Replace these placeholders with your real target approvals.
     *
     * @var array<int, string>
     */
    private const array DEFAULT_TARGET_AGRS = [
        'L056C219',
        'L085T269',
        'S044C106',
        'S044T084',
        'S044T262',
        'S049C125',
        'S056C007',
        'S056C235',
        'S085T052',
        'S053T072',
        'S056T153',
        'S085C098',
        'S056C060',
        'S056C160',
        'S085T146',
        'S085T152',
        'S085T194',
        'S044C301',
        'L044T429'
    ];

    /**
     * @param Connection $connection DBAL connection used for purge operations.
     */
    public function __construct(private readonly Connection $connection)
    {
        parent::__construct();
    }

    /**
     * Configures command options.
     *
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->addOption(
                'agr',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Agrément centre à purger (option répétable: --agr=S085T150 --agr=L085T271).'
            )
            ->addOption(
                'execute',
                null,
                InputOption::VALUE_NONE,
                'Exécute la purge (sans ce flag, le traitement est en mode aperçu).'
            );
    }

    /**
     * Executes preview or purge workflow.
     *
     * @param InputInterface $input Console input.
     * @param OutputInterface $output Console output.
     *
     * @return int Command exit code.
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('[purge-centres] Démarrage de la purge des centres non CTS.');
        $rawAgrs = $input->getOption('agr');
        $inputAgrs = $this->normalizeAgrs(is_array($rawAgrs) ? $rawAgrs : []);
        $agrs = $inputAgrs !== [] ? $inputAgrs : $this->normalizeAgrs(self::DEFAULT_TARGET_AGRS);
        $execute = (bool)$input->getOption('execute');

        if ($agrs === []) {
            $io->writeln('<error>[purge-centres] Aucun agrément cible configuré. Renseignez DEFAULT_TARGET_AGRS ou passez --agr.</error>');
            return Command::INVALID;
        }

        try {
            $this->connection->beginTransaction();

            $this->prepareTargetControlsTempTable($agrs);
            $stats = $this->collectStats($agrs);

            $io->definitionList(
                ['Mode' => $execute ? 'EXÉCUTION' : 'APERÇU'],
                ['Source' => $inputAgrs !== [] ? 'Ligne de commande (--agr)' : 'DEFAULT_TARGET_AGRS'],
                ['Liste agr_centre ciblée' => implode(', ', $agrs)],
                ['Contrôles correspondants' => $stats['target_controls']],
                ['Lignes clients_controles correspondantes' => $stats['target_clients_controles']],
                ['Lignes synthese_controles à supprimer' => $stats['target_synthese_controles']],
                ['Lignes synthese_pros à supprimer' => $stats['target_synthese_pros']],
            );

            if (!$execute) {
                $this->connection->rollBack();
                $io->success('[purge-centres] Aperçu terminé. Relancez avec --execute pour appliquer.');
                return Command::SUCCESS;
            }

            $deletedClientsControles = $this->deleteTargetClientsControles($agrs);
            $deletedControlesFactures = $this->deleteOrphanControlesFactures();
            $deletedControlesNonFactures = $this->deleteOrphanControlesNonFacturesIfExists();
            $deletedControles = $this->deleteOrphanControles();
            $deletedSyntheseControles = $this->deleteSyntheseControles($agrs);
            $deletedSynthesePros = $this->deleteSynthesePros($agrs);

            $this->connection->commit();

            $io->definitionList(
                ['clients_controles supprimées' => $deletedClientsControles],
                ['controles_factures supprimées' => $deletedControlesFactures],
                ['controles_non_factures supprimées' => $deletedControlesNonFactures],
                ['controles supprimés' => $deletedControles],
                ['synthese_controles supprimées' => $deletedSyntheseControles],
                ['synthese_pros supprimées' => $deletedSynthesePros],
            );

            $io->success('[purge-centres] Purge terminée.');

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }

            $io->error('<error>[purge-centres] Échec: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }

    /**
     * Normalizes agr list values.
     *
     * @param array<int, mixed> $rawAgrs Raw CLI values.
     *
     * @return array<int, string> Uppercased unique non-empty values.
     */
    private function normalizeAgrs(array $rawAgrs): array
    {
        $normalized = array_map(
            static fn($value) => strtoupper(trim((string)$value)),
            $rawAgrs
        );

        return array_values(array_unique(array_filter($normalized, static fn(string $value) => $value !== '')));
    }

    /**
     * Builds temporary table of impacted controls.
     *
     * @param array<int, string> $agrs Normalized target approvals.
     *
     * @return void
     *
     * @throws Exception
     */
    private function prepareTargetControlsTempTable(array $agrs): void
    {
        $this->connection->executeStatement('DROP TEMPORARY TABLE IF EXISTS tmp_purge_centres_controls');
        $this->connection->executeStatement(
            'CREATE TEMPORARY TABLE tmp_purge_centres_controls (idcontrole BIGINT PRIMARY KEY)'
        );

        $this->connection->executeStatement(
            "
                INSERT IGNORE INTO tmp_purge_centres_controls (idcontrole)
                SELECT DISTINCT cc.idcontrole
                FROM clients_controles cc
                WHERE UPPER(TRIM(cc.agr_centre)) IN (:agrs)
            ",
            ['agrs' => $agrs],
            ['agrs' => ArrayParameterType::STRING]
        );
    }

    /**
     * Computes preview statistics for impacted rows.
     *
     * @param array<int, string> $agrs Normalized target approvals.
     *
     * @return array{target_controls:int,target_clients_controles:int,target_synthese_controles:int,target_synthese_pros:int}
     *
     * @throws Exception
     */
    private function collectStats(array $agrs): array
    {
        return [
            'target_controls' => (int)$this->connection->fetchOne(
                'SELECT COUNT(*) FROM tmp_purge_centres_controls'
            ),
            'target_clients_controles' => (int)$this->connection->fetchOne(
                "
                    SELECT COUNT(*)
                    FROM clients_controles cc
                    WHERE UPPER(TRIM(cc.agr_centre)) IN (:agrs)
                ",
                ['agrs' => $agrs],
                ['agrs' => ArrayParameterType::STRING]
            ),
            'target_synthese_controles' => (int)$this->connection->fetchOne(
                "
                    SELECT COUNT(*)
                    FROM synthese_controles sc
                    WHERE UPPER(TRIM(sc.agr_centre)) IN (:agrs)
                ",
                ['agrs' => $agrs],
                ['agrs' => ArrayParameterType::STRING]
            ),
            'target_synthese_pros' => (int)$this->connection->fetchOne(
                "
                    SELECT COUNT(*)
                    FROM synthese_pros sp
                    WHERE UPPER(TRIM(sp.agr_centre)) IN (:agrs)
                ",
                ['agrs' => $agrs],
                ['agrs' => ArrayParameterType::STRING]
            ),
        ];
    }

    /**
     * Deletes clients_controles rows matching target approvals.
     *
     * @param array<int, string> $agrs Normalized target approvals.
     *
     * @return int Number of deleted rows.
     *
     * @throws Exception
     */
    private function deleteTargetClientsControles(array $agrs): int
    {
        return $this->connection->executeStatement(
            "
                DELETE cc
                FROM clients_controles cc
                WHERE UPPER(TRIM(cc.agr_centre)) IN (:agrs)
            ",
            ['agrs' => $agrs],
            ['agrs' => ArrayParameterType::STRING]
        );
    }

    /**
     * Deletes controles_factures rows orphaned from clients_controles.
     *
     * @return int Number of deleted rows.
     *
     * @throws Exception
     */
    private function deleteOrphanControlesFactures(): int
    {
        return $this->connection->executeStatement(
            "
                DELETE cf
                FROM controles_factures cf
                INNER JOIN tmp_purge_centres_controls t ON t.idcontrole = cf.idcontrole
                WHERE NOT EXISTS (
                    SELECT 1
                    FROM clients_controles cc
                    WHERE cc.idcontrole = cf.idcontrole
                )
            "
        );
    }

    /**
     * Deletes controles_non_factures rows orphaned from clients_controles when table exists.
     *
     * @return int Number of deleted rows.
     *
     * @throws Exception
     */
    private function deleteOrphanControlesNonFacturesIfExists(): int
    {
        $exists = (int)$this->connection->fetchOne(
            "
                SELECT COUNT(*)
                FROM information_schema.tables
                WHERE table_schema = DATABASE()
                  AND table_name = 'controles_non_factures'
            "
        ) > 0;

        if (!$exists) {
            return 0;
        }

        return $this->connection->executeStatement(
            "
                DELETE cnf
                FROM controles_non_factures cnf
                INNER JOIN tmp_purge_centres_controls t ON t.idcontrole = cnf.idcontrole
                WHERE NOT EXISTS (
                    SELECT 1
                    FROM clients_controles cc
                    WHERE cc.idcontrole = cnf.idcontrole
                )
            "
        );
    }

    /**
     * Deletes controls orphaned from clients_controles.
     *
     * @return int Number of deleted rows.
     *
     * @throws Exception
     */
    private function deleteOrphanControles(): int
    {
        return $this->connection->executeStatement(
            "
                DELETE c
                FROM controles c
                INNER JOIN tmp_purge_centres_controls t ON t.idcontrole = c.idcontrole
                WHERE NOT EXISTS (
                    SELECT 1
                    FROM clients_controles cc
                    WHERE cc.idcontrole = c.idcontrole
                )
            "
        );
    }

    /**
     * Deletes summary rows from synthese_controles for target approvals.
     *
     * @param array<int, string> $agrs Normalized target approvals.
     *
     * @return int Number of deleted rows.
     *
     * @throws Exception
     */
    private function deleteSyntheseControles(array $agrs): int
    {
        return $this->connection->executeStatement(
            "
                DELETE sc
                FROM synthese_controles sc
                WHERE UPPER(TRIM(sc.agr_centre)) IN (:agrs)
            ",
            ['agrs' => $agrs],
            ['agrs' => ArrayParameterType::STRING]
        );
    }

    /**
     * Deletes summary rows from synthese_pros for target approvals.
     *
     * @param array<int, string> $agrs Normalized target approvals.
     *
     * @return int Number of deleted rows.
     *
     * @throws Exception
     */
    private function deleteSynthesePros(array $agrs): int
    {
        return $this->connection->executeStatement(
            "
                DELETE sp
                FROM synthese_pros sp
                WHERE UPPER(TRIM(sp.agr_centre)) IN (:agrs)
            ",
            ['agrs' => $agrs],
            ['agrs' => ArrayParameterType::STRING]
        );
    }
}
