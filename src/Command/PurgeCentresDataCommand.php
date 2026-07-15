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

            $this->prepareTargetScopeTempTables($agrs);
            $stats = $this->collectStats($agrs);

            $io->definitionList(
                ['Mode' => $execute ? 'EXÉCUTION' : 'APERÇU'],
                ['Source' => $inputAgrs !== [] ? 'Ligne de commande (--agr)' : 'DEFAULT_TARGET_AGRS'],
                ['Liste agr_centre ciblée' => implode(', ', $agrs)],
                ['Clients candidats' => $stats['target_clients']],
                ['Contrôles correspondants' => $stats['target_controls']],
                ['Factures candidates' => $stats['target_factures']],
                ['Règlements candidats' => $stats['target_reglements']],
                ['Lignes centres_clients correspondantes' => $stats['target_centres_clients']],
                ['Lignes clients_controles correspondantes' => $stats['target_clients_controles']],
                ['Lignes controles_factures correspondantes' => $stats['target_controles_factures']],
                ['Lignes controles_non_factures correspondantes' => $stats['target_controles_non_factures']],
                ['Lignes factures_reglements correspondantes' => $stats['target_factures_reglements']],
                ['Lignes prestas_non_facturees candidates' => $stats['target_prestas_non_facturees']],
                ['Lignes synthese_controles à supprimer' => $stats['target_synthese_controles']],
                ['Lignes synthese_pros à supprimer' => $stats['target_synthese_pros']],
            );

            if (!$execute) {
                $this->connection->rollBack();
                $io->success('[purge-centres] Aperçu terminé. Relancez avec --execute pour appliquer.');
                return Command::SUCCESS;
            }

            $deletedFacturesReglements = $this->deleteTargetFacturesReglements($agrs);
            $deletedControlesFacturesDirect = $this->deleteTargetControlesFactures($agrs);
            $deletedControlesNonFacturesDirect = $this->deleteTargetControlesNonFacturesIfExists($agrs);
            $deletedPrestasNonFacturees = $this->deleteTargetPrestasNonFacturees();
            $deletedClientsControles = $this->deleteTargetClientsControles($agrs);
            $deletedCentresClients = $this->deleteTargetCentresClients($agrs);
            $deletedControlesFactures = $this->deleteOrphanControlesFactures();
            $deletedControlesNonFactures = $this->deleteOrphanControlesNonFacturesIfExists();
            $deletedFactures = $this->deleteOrphanFactures();
            $deletedReglements = $this->deleteOrphanReglements();
            $deletedClients = $this->deleteOrphanClients();
            $deletedControles = $this->deleteOrphanControles();
            $deletedSyntheseControles = $this->deleteSyntheseControles($agrs);
            $deletedSynthesePros = $this->deleteSynthesePros($agrs);

            $this->connection->commit();

            $io->definitionList(
                ['factures_reglements supprimées' => $deletedFacturesReglements],
                ['controles_factures ciblées supprimées' => $deletedControlesFacturesDirect],
                ['controles_non_factures ciblées supprimées' => $deletedControlesNonFacturesDirect],
                ['prestas_non_facturees supprimées' => $deletedPrestasNonFacturees],
                ['clients_controles supprimées' => $deletedClientsControles],
                ['centres_clients supprimées' => $deletedCentresClients],
                ['controles_factures supprimées' => $deletedControlesFactures],
                ['controles_non_factures supprimées' => $deletedControlesNonFactures],
                ['factures supprimées' => $deletedFactures],
                ['reglements supprimés' => $deletedReglements],
                ['clients supprimés' => $deletedClients],
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
     * In synthese tables, unknown centres are stored as "Centre inconnu (<AGR>)" (built in PopulateSyntheseCommand).
     *
     * We upper-case these labels because purge queries use UPPER(TRIM(...)).
     *
     * @param array<int, string> $agrs Normalized centre approvals (already uppercased).
     *
     * @return array<int, string> Upper-cased synthetic labels matching the "Centre inconnu (...)" format.
     */
    private function buildUnknownCentreLabels(array $agrs): array
    {
        return array_map(
            static fn(string $agr) => 'CENTRE INCONNU (' . $agr . ')',
            $agrs
        );
    }

    /**
     * Checks whether a table exists in the current database.
     *
     * @param string $tableName Table name.
     *
     * @return bool True when the table exists.
     *
     * @throws Exception
     */
    private function tableExists(string $tableName): bool
    {
        return (int)$this->connection->fetchOne(
            "
                SELECT COUNT(*)
                FROM information_schema.tables
                WHERE table_schema = DATABASE()
                  AND table_name = :table_name
            ",
            ['table_name' => $tableName]
        ) > 0;
    }

    /**
     * Builds temporary tables of impacted business identifiers before any deletion.
     *
     * @param array<int, string> $agrs Normalized target approvals.
     *
     * @return void
     *
     * @throws Exception
     */
    private function prepareTargetScopeTempTables(array $agrs): void
    {
        $this->connection->executeStatement('DROP TEMPORARY TABLE IF EXISTS tmp_purge_centres_clients');
        $this->connection->executeStatement('DROP TEMPORARY TABLE IF EXISTS tmp_purge_centres_controls');
        $this->connection->executeStatement('DROP TEMPORARY TABLE IF EXISTS tmp_purge_centres_factures');
        $this->connection->executeStatement('DROP TEMPORARY TABLE IF EXISTS tmp_purge_centres_reglements');

        $this->connection->executeStatement(
            'CREATE TEMPORARY TABLE tmp_purge_centres_clients (idclient VARCHAR(50) PRIMARY KEY)'
        );
        $this->connection->executeStatement(
            'CREATE TEMPORARY TABLE tmp_purge_centres_controls (idcontrole VARCHAR(50) PRIMARY KEY)'
        );
        $this->connection->executeStatement(
            'CREATE TEMPORARY TABLE tmp_purge_centres_factures (idfacture VARCHAR(50) PRIMARY KEY)'
        );
        $this->connection->executeStatement(
            'CREATE TEMPORARY TABLE tmp_purge_centres_reglements (idreglement VARCHAR(50) PRIMARY KEY)'
        );

        $this->connection->executeStatement(
            "
                INSERT IGNORE INTO tmp_purge_centres_clients (idclient)
                SELECT DISTINCT cc.idclient
                FROM centres_clients cc
                WHERE cc.idclient IS NOT NULL
                  AND cc.agr_centre IN (:agrs)
            ",
            ['agrs' => $agrs],
            ['agrs' => ArrayParameterType::STRING]
        );

        $this->connection->executeStatement(
            "
                INSERT IGNORE INTO tmp_purge_centres_clients (idclient)
                SELECT DISTINCT cc.idclient
                FROM clients_controles cc
                WHERE cc.idclient IS NOT NULL
                  AND cc.agr_centre IN (:agrs)
            ",
            ['agrs' => $agrs],
            ['agrs' => ArrayParameterType::STRING]
        );

        $this->connection->executeStatement(
            "
                INSERT IGNORE INTO tmp_purge_centres_clients (idclient)
                SELECT DISTINCT cf.idclient
                FROM controles_factures cf
                WHERE cf.idclient IS NOT NULL
                  AND cf.agr_centre IN (:agrs)
            ",
            ['agrs' => $agrs],
            ['agrs' => ArrayParameterType::STRING]
        );

        if ($this->tableExists('controles_non_factures')) {
            $this->connection->executeStatement(
                "
                    INSERT IGNORE INTO tmp_purge_centres_clients (idclient)
                    SELECT DISTINCT cnf.idclient
                    FROM controles_non_factures cnf
                    WHERE cnf.idclient IS NOT NULL
                      AND cnf.agr_centre IN (:agrs)
                ",
                ['agrs' => $agrs],
                ['agrs' => ArrayParameterType::STRING]
            );
        }

        $this->connection->executeStatement(
            "
                INSERT IGNORE INTO tmp_purge_centres_clients (idclient)
                SELECT DISTINCT fr.idclient
                FROM factures_reglements fr
                WHERE fr.idclient IS NOT NULL
                  AND fr.agr_centre IN (:agrs)
            ",
            ['agrs' => $agrs],
            ['agrs' => ArrayParameterType::STRING]
        );

        $this->connection->executeStatement(
            "
                INSERT IGNORE INTO tmp_purge_centres_controls (idcontrole)
                SELECT DISTINCT cc.idcontrole
                FROM clients_controles cc
                WHERE cc.idcontrole IS NOT NULL
                  AND cc.agr_centre IN (:agrs)
            ",
            ['agrs' => $agrs],
            ['agrs' => ArrayParameterType::STRING]
        );

        $this->connection->executeStatement(
            "
                INSERT IGNORE INTO tmp_purge_centres_controls (idcontrole)
                SELECT DISTINCT cf.idcontrole
                FROM controles_factures cf
                WHERE cf.idcontrole IS NOT NULL
                  AND cf.agr_centre IN (:agrs)
            ",
            ['agrs' => $agrs],
            ['agrs' => ArrayParameterType::STRING]
        );

        if ($this->tableExists('controles_non_factures')) {
            $this->connection->executeStatement(
                "
                    INSERT IGNORE INTO tmp_purge_centres_controls (idcontrole)
                    SELECT DISTINCT cnf.idcontrole
                    FROM controles_non_factures cnf
                    WHERE cnf.idcontrole IS NOT NULL
                      AND cnf.agr_centre IN (:agrs)
                ",
                ['agrs' => $agrs],
                ['agrs' => ArrayParameterType::STRING]
            );
        }

        $this->connection->executeStatement(
            "
                INSERT IGNORE INTO tmp_purge_centres_factures (idfacture)
                SELECT DISTINCT cf.idfacture
                FROM controles_factures cf
                WHERE cf.idfacture IS NOT NULL
                  AND cf.agr_centre IN (:agrs)
            ",
            ['agrs' => $agrs],
            ['agrs' => ArrayParameterType::STRING]
        );

        $this->connection->executeStatement(
            "
                INSERT IGNORE INTO tmp_purge_centres_factures (idfacture)
                SELECT DISTINCT fr.idfacture
                FROM factures_reglements fr
                WHERE fr.idfacture IS NOT NULL
                  AND fr.agr_centre IN (:agrs)
            ",
            ['agrs' => $agrs],
            ['agrs' => ArrayParameterType::STRING]
        );

        $this->connection->executeStatement(
            "
                INSERT IGNORE INTO tmp_purge_centres_reglements (idreglement)
                SELECT DISTINCT fr.idreglement
                FROM factures_reglements fr
                WHERE fr.idreglement IS NOT NULL
                  AND fr.agr_centre IN (:agrs)
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
     * @return array<string, int>
     *
     * @throws Exception
     */
    private function collectStats(array $agrs): array
    {
        $unknownCentreLabels = $this->buildUnknownCentreLabels($agrs);

        return [
            'target_controls' => (int)$this->connection->fetchOne(
                'SELECT COUNT(*) FROM tmp_purge_centres_controls'
            ),
            'target_clients' => (int)$this->connection->fetchOne(
                'SELECT COUNT(*) FROM tmp_purge_centres_clients'
            ),
            'target_factures' => (int)$this->connection->fetchOne(
                'SELECT COUNT(*) FROM tmp_purge_centres_factures'
            ),
            'target_reglements' => (int)$this->connection->fetchOne(
                'SELECT COUNT(*) FROM tmp_purge_centres_reglements'
            ),
            'target_centres_clients' => (int)$this->connection->fetchOne(
                "
                    SELECT COUNT(*)
                    FROM centres_clients cc
                    WHERE cc.agr_centre IN (:agrs)
                ",
                ['agrs' => $agrs],
                ['agrs' => ArrayParameterType::STRING]
            ),
            'target_clients_controles' => (int)$this->connection->fetchOne(
                "
                    SELECT COUNT(*)
                    FROM clients_controles cc
                    WHERE cc.agr_centre IN (:agrs)
                ",
                ['agrs' => $agrs],
                ['agrs' => ArrayParameterType::STRING]
            ),
            'target_controles_factures' => (int)$this->connection->fetchOne(
                "
                    SELECT COUNT(*)
                    FROM controles_factures cf
                    WHERE cf.agr_centre IN (:agrs)
                ",
                ['agrs' => $agrs],
                ['agrs' => ArrayParameterType::STRING]
            ),
            'target_controles_non_factures' => $this->countTargetControlesNonFactures($agrs),
            'target_factures_reglements' => (int)$this->connection->fetchOne(
                "
                    SELECT COUNT(*)
                    FROM factures_reglements fr
                    WHERE fr.agr_centre IN (:agrs)
                ",
                ['agrs' => $agrs],
                ['agrs' => ArrayParameterType::STRING]
            ),
            'target_prestas_non_facturees' => (int)$this->connection->fetchOne(
                "
                    SELECT COUNT(*)
                    FROM prestas_non_facturees pnf
                    INNER JOIN tmp_purge_centres_controls t ON t.idcontrole = pnf.idcontrole
                "
            ),
            'target_synthese_controles' => (int)$this->connection->fetchOne(
                "
                    SELECT COUNT(*)
                    FROM synthese_controles sc
                    WHERE UPPER(TRIM(sc.agr_centre)) IN (:agrs)
                       OR UPPER(TRIM(sc.agr_centre)) IN (:unknown_centre_labels)
                ",
                [
                    'agrs' => $agrs,
                    'unknown_centre_labels' => $unknownCentreLabels,
                ],
                [
                    'agrs' => ArrayParameterType::STRING,
                    'unknown_centre_labels' => ArrayParameterType::STRING,
                ]
            ),
            'target_synthese_pros' => (int)$this->connection->fetchOne(
                "
                    SELECT COUNT(*)
                    FROM synthese_pros sp
                    WHERE UPPER(TRIM(sp.agr_centre)) IN (:agrs)
                       OR UPPER(TRIM(sp.agr_centre)) IN (:unknown_centre_labels)
                ",
                [
                    'agrs' => $agrs,
                    'unknown_centre_labels' => $unknownCentreLabels,
                ],
                [
                    'agrs' => ArrayParameterType::STRING,
                    'unknown_centre_labels' => ArrayParameterType::STRING,
                ]
            ),
        ];
    }

    /**
     * Counts controles_non_factures rows matching target approvals when table exists.
     *
     * @param array<int, string> $agrs Normalized target approvals.
     *
     * @return int Number of matching rows.
     *
     * @throws Exception
     */
    private function countTargetControlesNonFactures(array $agrs): int
    {
        if (!$this->tableExists('controles_non_factures')) {
            return 0;
        }

        return (int)$this->connection->fetchOne(
            "
                SELECT COUNT(*)
                FROM controles_non_factures cnf
                WHERE cnf.agr_centre IN (:agrs)
            ",
            ['agrs' => $agrs],
            ['agrs' => ArrayParameterType::STRING]
        );
    }

    /**
     * Deletes centres_clients rows matching target approvals.
     *
     * @param array<int, string> $agrs Normalized target approvals.
     *
     * @return int Number of deleted rows.
     *
     * @throws Exception
     */
    private function deleteTargetCentresClients(array $agrs): int
    {
        return $this->connection->executeStatement(
            "
                DELETE cc
                FROM centres_clients cc
                WHERE cc.agr_centre IN (:agrs)
            ",
            ['agrs' => $agrs],
            ['agrs' => ArrayParameterType::STRING]
        );
    }

    /**
     * Deletes controles_factures rows matching target approvals.
     *
     * @param array<int, string> $agrs Normalized target approvals.
     *
     * @return int Number of deleted rows.
     *
     * @throws Exception
     */
    private function deleteTargetControlesFactures(array $agrs): int
    {
        return $this->connection->executeStatement(
            "
                DELETE cf
                FROM controles_factures cf
                WHERE cf.agr_centre IN (:agrs)
            ",
            ['agrs' => $agrs],
            ['agrs' => ArrayParameterType::STRING]
        );
    }

    /**
     * Deletes controles_non_factures rows matching target approvals when table exists.
     *
     * @param array<int, string> $agrs Normalized target approvals.
     *
     * @return int Number of deleted rows.
     *
     * @throws Exception
     */
    private function deleteTargetControlesNonFacturesIfExists(array $agrs): int
    {
        if (!$this->tableExists('controles_non_factures')) {
            return 0;
        }

        return $this->connection->executeStatement(
            "
                DELETE cnf
                FROM controles_non_factures cnf
                WHERE cnf.agr_centre IN (:agrs)
            ",
            ['agrs' => $agrs],
            ['agrs' => ArrayParameterType::STRING]
        );
    }

    /**
     * Deletes factures_reglements rows matching target approvals.
     *
     * @param array<int, string> $agrs Normalized target approvals.
     *
     * @return int Number of deleted rows.
     *
     * @throws Exception
     */
    private function deleteTargetFacturesReglements(array $agrs): int
    {
        return $this->connection->executeStatement(
            "
                DELETE fr
                FROM factures_reglements fr
                WHERE fr.agr_centre IN (:agrs)
            ",
            ['agrs' => $agrs],
            ['agrs' => ArrayParameterType::STRING]
        );
    }

    /**
     * Deletes prestas_non_facturees rows attached to target controls.
     *
     * @return int Number of deleted rows.
     *
     * @throws Exception
     */
    private function deleteTargetPrestasNonFacturees(): int
    {
        return $this->connection->executeStatement(
            "
                DELETE pnf
                FROM prestas_non_facturees pnf
                INNER JOIN tmp_purge_centres_controls t ON t.idcontrole = pnf.idcontrole
            "
        );
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
                WHERE cc.agr_centre IN (:agrs)
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
        if (!$this->tableExists('controles_non_factures')) {
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
     * Deletes factures candidates no longer linked to controls or payments.
     *
     * @return int Number of deleted rows.
     *
     * @throws Exception
     */
    private function deleteOrphanFactures(): int
    {
        return $this->connection->executeStatement(
            "
                DELETE f
                FROM factures f
                INNER JOIN tmp_purge_centres_factures t ON t.idfacture = f.idfacture
                WHERE NOT EXISTS (
                    SELECT 1
                    FROM controles_factures cf
                    WHERE cf.idfacture = f.idfacture
                )
                  AND NOT EXISTS (
                    SELECT 1
                    FROM factures_reglements fr
                    WHERE fr.idfacture = f.idfacture
                )
            "
        );
    }

    /**
     * Deletes reglements candidates no longer linked to invoices.
     *
     * @return int Number of deleted rows.
     *
     * @throws Exception
     */
    private function deleteOrphanReglements(): int
    {
        return $this->connection->executeStatement(
            "
                DELETE r
                FROM reglements r
                INNER JOIN tmp_purge_centres_reglements t ON t.idreglement = r.idreglement
                WHERE NOT EXISTS (
                    SELECT 1
                    FROM factures_reglements fr
                    WHERE fr.idreglement = r.idreglement
                )
            "
        );
    }

    /**
     * Deletes clients candidates no longer linked to any imported centre/control/invoice data.
     *
     * @return int Number of deleted rows.
     *
     * @throws Exception
     */
    private function deleteOrphanClients(): int
    {
        $controlesNonFacturesCondition = $this->tableExists('controles_non_factures')
            ? "
                  AND NOT EXISTS (
                      SELECT 1
                      FROM controles_non_factures cnf
                      WHERE cnf.idclient = c.idclient
                  )
              "
            : '';

        return $this->connection->executeStatement(
            "
                DELETE c
                FROM clients c
                INNER JOIN tmp_purge_centres_clients t ON t.idclient = c.idclient
                WHERE NOT EXISTS (
                    SELECT 1
                    FROM centres_clients cc
                    WHERE cc.idclient = c.idclient
                )
                  AND NOT EXISTS (
                    SELECT 1
                    FROM clients_controles cc
                    WHERE cc.idclient = c.idclient
                )
                  AND NOT EXISTS (
                    SELECT 1
                    FROM controles_factures cf
                    WHERE cf.idclient = c.idclient
                )
                  AND NOT EXISTS (
                    SELECT 1
                    FROM factures_reglements fr
                    WHERE fr.idclient = c.idclient
                )
                {$controlesNonFacturesCondition}
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
        $controlesNonFacturesCondition = $this->tableExists('controles_non_factures')
            ? "
                  AND NOT EXISTS (
                      SELECT 1
                      FROM controles_non_factures cnf
                      WHERE cnf.idcontrole = c.idcontrole
                  )
              "
            : '';

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
                  AND NOT EXISTS (
                    SELECT 1
                    FROM controles_factures cf
                    WHERE cf.idcontrole = c.idcontrole
                )
                {$controlesNonFacturesCondition}
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
        $unknownCentreLabels = $this->buildUnknownCentreLabels($agrs);

        return $this->connection->executeStatement(
            "
                DELETE sc
                FROM synthese_controles sc
                WHERE UPPER(TRIM(sc.agr_centre)) IN (:agrs)
                   OR UPPER(TRIM(sc.agr_centre)) IN (:unknown_centre_labels)
            ",
            [
                'agrs' => $agrs,
                'unknown_centre_labels' => $unknownCentreLabels,
            ],
            [
                'agrs' => ArrayParameterType::STRING,
                'unknown_centre_labels' => ArrayParameterType::STRING,
            ]
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
        $unknownCentreLabels = $this->buildUnknownCentreLabels($agrs);

        return $this->connection->executeStatement(
            "
                DELETE sp
                FROM synthese_pros sp
                WHERE UPPER(TRIM(sp.agr_centre)) IN (:agrs)
                   OR UPPER(TRIM(sp.agr_centre)) IN (:unknown_centre_labels)
            ",
            [
                'agrs' => $agrs,
                'unknown_centre_labels' => $unknownCentreLabels,
            ],
            [
                'agrs' => ArrayParameterType::STRING,
                'unknown_centre_labels' => ArrayParameterType::STRING,
            ]
        );
    }
}
