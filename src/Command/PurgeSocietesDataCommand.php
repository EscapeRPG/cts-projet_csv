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
    name: 'app:data:purge-societes',
    description: 'Purge les données importées liées à une liste de sociétés (avant une date).'
)]
/**
 * Deletes imported records linked to specific companies before a cutoff date.
 *
 * This is meant to be used when synthese tables are regenerated from raw imports and need a clean cutoff.
 */
final class PurgeSocietesDataCommand extends Command
{
    /**
     * Default target companies to purge when --societe is not provided.
     *
     * @var array<int, string>
     */
    private const array DEFAULT_TARGET_SOCIETES = [
        'AYD 85',
        'AUTO CONTROLE DU GOLFE (ACG)',
    ];

    public function __construct(private readonly Connection $connection)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'societe',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Société(s) à purger (option répétable: --societe="AYD 85" --societe="AUTO CONTROLE DU GOLFE (ACG)").'
            )
            ->addOption(
                'before',
                null,
                InputOption::VALUE_REQUIRED,
                'Date/heure limite (exclue) au format YYYY-MM-DD[ HH:MM:SS]. Ex: 2026-01-01.',
                '2026-01-01 00:00:00'
            )
            ->addOption(
                'execute',
                null,
                InputOption::VALUE_NONE,
                'Exécute la purge (sans ce flag, le traitement est en mode aperçu).'
            );
    }

    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('[purge-societes] Démarrage de la purge des sociétés ciblées.');

        $rawSocietes = $input->getOption('societe');
        $inputSocietes = $this->normalizeSocietes(is_array($rawSocietes) ? $rawSocietes : []);
        $societes = $inputSocietes !== [] ? $inputSocietes : $this->normalizeSocietes(self::DEFAULT_TARGET_SOCIETES);
        $execute = (bool)$input->getOption('execute');

        $beforeRaw = trim((string)$input->getOption('before'));
        $before = $this->parseBeforeDate($beforeRaw);
        if ($before === null) {
            $io->writeln('<error>[purge-societes] Paramètre --before invalide. Exemple: 2026-01-01</error>');
            return Command::INVALID;
        }

        if ($societes === []) {
            $io->writeln('<error>[purge-societes] Aucune société cible. Passez --societe ou configurez DEFAULT_TARGET_SOCIETES.</error>');
            return Command::INVALID;
        }

        try {
            $this->connection->beginTransaction();

            $this->prepareTargetControlsTempTable($societes, $before);
            $stats = $this->collectStats($societes, $before);

            $io->definitionList(
                ['Mode' => $execute ? 'EXÉCUTION' : 'APERÇU'],
                ['Source' => $inputSocietes !== [] ? 'Ligne de commande (--societe)' : 'DEFAULT_TARGET_SOCIETES'],
                ['Sociétés ciblées' => implode(', ', $societes)],
                ['Avant (exclu)' => $before->format('Y-m-d H:i:s')],
                ['Contrôles correspondants' => $stats['target_controls']],
                ['Lignes clients_controles correspondantes' => $stats['target_clients_controles']],
                ['Lignes synthese_controles à supprimer' => $stats['target_synthese_controles']],
                ['Lignes synthese_pros à supprimer' => $stats['target_synthese_pros']],
            );

            if (!$execute) {
                $this->connection->rollBack();
                $io->success('[purge-societes] Aperçu terminé. Relancez avec --execute pour appliquer.');
                return Command::SUCCESS;
            }

            $deletedClientsControles = $this->deleteTargetClientsControles();
            $deletedControlesFactures = $this->deleteOrphanControlesFactures();
            $deletedControlesNonFactures = $this->deleteOrphanControlesNonFacturesIfExists();
            $deletedControles = $this->deleteOrphanControles();
            $deletedSyntheseControles = $this->deleteSyntheseControles($societes, $before);
            $deletedSynthesePros = $this->deleteSynthesePros($societes, $before);

            $this->connection->commit();

            $io->definitionList(
                ['clients_controles supprimées' => $deletedClientsControles],
                ['controles_factures supprimées' => $deletedControlesFactures],
                ['controles_non_factures supprimées' => $deletedControlesNonFactures],
                ['controles supprimés' => $deletedControles],
                ['synthese_controles supprimées' => $deletedSyntheseControles],
                ['synthese_pros supprimées' => $deletedSynthesePros],
            );

            $io->success('[purge-societes] Purge terminée.');
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }
            $io->error('<error>[purge-societes] Échec: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }

    /**
     * @return array<int, string>
     */
    private function normalizeSocietes(array $values): array
    {
        $values = array_values(array_filter(array_map(
            static fn($v): string => trim((string)$v),
            $values
        ), static fn(string $v): bool => $v !== ''));
        $values = array_values(array_unique($values));
        sort($values, SORT_NATURAL | SORT_FLAG_CASE);
        return $values;
    }

    private function parseBeforeDate(string $raw): ?\DateTimeImmutable
    {
        $raw = trim($raw);
        if ($raw === '') return null;

        // Accept YYYY-MM-DD or full datetime.
        $formats = ['Y-m-d H:i:s', 'Y-m-d'];
        foreach ($formats as $fmt) {
            $dt = \DateTimeImmutable::createFromFormat($fmt, $raw);
            if ($dt instanceof \DateTimeImmutable) {
                // When only a date is provided, interpret it as start of day.
                if ($fmt === 'Y-m-d') {
                    return $dt->setTime(0, 0, 0);
                }
                return $dt;
            }
        }
        return null;
    }

    /**
     * @throws Exception
     */
    private function prepareTargetControlsTempTable(array $societes, \DateTimeImmutable $before): void
    {
        $this->connection->executeStatement('DROP TEMPORARY TABLE IF EXISTS tmp_purge_societes_controls');
        $this->connection->executeStatement(
            'CREATE TEMPORARY TABLE tmp_purge_societes_controls (idcontrole BIGINT PRIMARY KEY)'
        );

        $centreJoinCondition = $this->hasSecondaryCentreAgreementColumn()
            ? '(ce.agr_centre = cc.agr_centre OR ce.agr_cl_centre = cc.agr_centre)'
            : 'ce.agr_centre = cc.agr_centre';

        $this->connection->executeStatement(
            "
                INSERT IGNORE INTO tmp_purge_societes_controls (idcontrole)
                SELECT DISTINCT c.idcontrole
                FROM controles c
                INNER JOIN clients_controles cc ON cc.idcontrole = c.idcontrole
                INNER JOIN centre ce ON {$centreJoinCondition}
                INNER JOIN societe so ON so.id = ce.societe_id
                WHERE so.nom IN (:societes)
                  AND c.date_ctrl < :before
            ",
            [
                'societes' => $societes,
                'before' => $before->format('Y-m-d H:i:s'),
            ],
            [
                'societes' => ArrayParameterType::STRING,
            ]
        );
    }

    /**
     * @return array{target_controls:int,target_clients_controles:int,target_synthese_controles:int,target_synthese_pros:int}
     * @throws Exception
     */
    private function collectStats(array $societes, \DateTimeImmutable $before): array
    {
        $periodWhere = $this->buildPeriodWhereForSynthese('annee', 'mois', $before);

        return [
            'target_controls' => (int)$this->connection->fetchOne(
                'SELECT COUNT(*) FROM tmp_purge_societes_controls'
            ),
            'target_clients_controles' => (int)$this->connection->fetchOne(
                "
                    SELECT COUNT(*)
                    FROM clients_controles cc
                    INNER JOIN tmp_purge_societes_controls t ON t.idcontrole = cc.idcontrole
                "
            ),
            'target_synthese_controles' => (int)$this->connection->fetchOne(
                "
                    SELECT COUNT(*)
                    FROM synthese_controles sc
                    WHERE sc.societe_nom IN (:societes)
                      AND {$periodWhere}
                ",
                [
                    'societes' => $societes,
                    'y' => (int)$before->format('Y'),
                    'm' => (int)$before->format('n'),
                ],
                [
                    'societes' => ArrayParameterType::STRING,
                ]
            ),
            'target_synthese_pros' => (int)$this->connection->fetchOne(
                "
                    SELECT COUNT(*)
                    FROM synthese_pros sp
                    WHERE sp.societe_nom IN (:societes)
                      AND {$periodWhere}
                ",
                [
                    'societes' => $societes,
                    'y' => (int)$before->format('Y'),
                    'm' => (int)$before->format('n'),
                ],
                [
                    'societes' => ArrayParameterType::STRING,
                ]
            ),
        ];
    }

    private function buildPeriodWhereForSynthese(string $yearCol, string $monthCol, \DateTimeImmutable $before): string
    {
        // "before" is an exclusive datetime; synthese tables are monthly. Cut at (year, month) of before.
        // Example: before=2026-01-01 => all rows with year<2026 are removed.
        // Example: before=2026-03-01 => year<2026 OR (year=2026 AND month<3)
        return sprintf('(%s < :y OR (%s = :y AND %s < :m))', $yearCol, $yearCol, $monthCol);
    }

    /**
     * @throws Exception
     */
    private function deleteTargetClientsControles(): int
    {
        return $this->connection->executeStatement(
            "
                DELETE cc
                FROM clients_controles cc
                INNER JOIN tmp_purge_societes_controls t ON t.idcontrole = cc.idcontrole
            "
        );
    }

    /**
     * @throws Exception
     */
    private function deleteOrphanControlesFactures(): int
    {
        return $this->connection->executeStatement(
            "
                DELETE cf
                FROM controles_factures cf
                INNER JOIN tmp_purge_societes_controls t ON t.idcontrole = cf.idcontrole
                WHERE NOT EXISTS (
                    SELECT 1
                    FROM clients_controles cc
                    WHERE cc.idcontrole = cf.idcontrole
                )
            "
        );
    }

    /**
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
                INNER JOIN tmp_purge_societes_controls t ON t.idcontrole = cnf.idcontrole
                WHERE NOT EXISTS (
                    SELECT 1
                    FROM clients_controles cc
                    WHERE cc.idcontrole = cnf.idcontrole
                )
            "
        );
    }

    /**
     * @throws Exception
     */
    private function deleteOrphanControles(): int
    {
        return $this->connection->executeStatement(
            "
                DELETE c
                FROM controles c
                INNER JOIN tmp_purge_societes_controls t ON t.idcontrole = c.idcontrole
                WHERE NOT EXISTS (
                    SELECT 1
                    FROM clients_controles cc
                    WHERE cc.idcontrole = c.idcontrole
                )
            "
        );
    }

    /**
     * @throws Exception
     */
    private function deleteSyntheseControles(array $societes, \DateTimeImmutable $before): int
    {
        $periodWhere = $this->buildPeriodWhereForSynthese('sc.annee', 'sc.mois', $before);

        return $this->connection->executeStatement(
            "
                DELETE sc
                FROM synthese_controles sc
                WHERE sc.societe_nom IN (:societes)
                  AND {$periodWhere}
            ",
            [
                'societes' => $societes,
                'y' => (int)$before->format('Y'),
                'm' => (int)$before->format('n'),
            ],
            [
                'societes' => ArrayParameterType::STRING,
            ]
        );
    }

    /**
     * @throws Exception
     */
    private function deleteSynthesePros(array $societes, \DateTimeImmutable $before): int
    {
        $periodWhere = $this->buildPeriodWhereForSynthese('sp.annee', 'sp.mois', $before);

        return $this->connection->executeStatement(
            "
                DELETE sp
                FROM synthese_pros sp
                WHERE sp.societe_nom IN (:societes)
                  AND {$periodWhere}
            ",
            [
                'societes' => $societes,
                'y' => (int)$before->format('Y'),
                'm' => (int)$before->format('n'),
            ],
            [
                'societes' => ArrayParameterType::STRING,
            ]
        );
    }

    /**
     * Detects whether centre table has the secondary agreement column.
     *
     * @throws Exception
     */
    private function hasSecondaryCentreAgreementColumn(): bool
    {
        $count = (int)$this->connection->fetchOne(
            "
                SELECT COUNT(*)
                FROM information_schema.columns
                WHERE table_schema = DATABASE()
                  AND table_name = 'centre'
                  AND column_name = 'agr_cl_centre'
            "
        );

        return $count > 0;
    }
}

