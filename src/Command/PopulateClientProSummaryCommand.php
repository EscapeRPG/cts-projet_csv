<?php

namespace App\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:synthese:pros',
    description: 'Met à jour la table synthese_pros sur une fenêtre glissante N..N-2.'
)]
class PopulateClientProSummaryCommand extends Command
{
    private const META_KEY = 'synthese_pros';

    public function __construct(
        private readonly Connection $connection
    )
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('[synthese:pros] Démarrage de la mise à jour glissante de synthese_pros.');

        try {
            $startedAt = microtime(true);

            $output->writeln('[synthese:pros] Vérification de la table cible...');
            $stepStartedAt = microtime(true);
            $this->ensureTable();
            $output->writeln(sprintf(
                '[synthese:pros] Table cible prête (%.3f s).',
                microtime(true) - $stepStartedAt
            ));

            $output->writeln('[synthese:pros] Ouverture de la transaction...');
            $this->connection->beginTransaction();

            $yearNow = (int)date('Y');
            $yearN2 = $yearNow - 2;
            $dateFrom = sprintf('%d-01-01 00:00:00', $yearN2);
            $dateTo = sprintf('%d-01-01 00:00:00', $yearNow + 1);
            $output->writeln(sprintf(
                '[synthese:pros] Fenêtre de recalcul: %d à %d.',
                $yearN2,
                $yearNow
            ));

            $lastRunAt = $this->connection->fetchOne(
                'SELECT last_run_at FROM synthese_meta WHERE meta_key = :meta_key',
                ['meta_key' => self::META_KEY]
            );
            $output->writeln(sprintf(
                '[synthese:pros] Dernière exécution enregistrée: %s.',
                $lastRunAt ?: 'aucune'
            ));

            // Purge des années obsolètes
            $output->writeln('[synthese:pros] Purge des années obsolètes...');
            $stepStartedAt = microtime(true);
            $this->connection->executeStatement(
                'DELETE FROM synthese_pros WHERE annee < :annee_min',
                ['annee_min' => $yearN2]
            );
            $output->writeln(sprintf(
                '[synthese:pros] Purge des années obsolètes terminée (%.3f s).',
                microtime(true) - $stepStartedAt
            ));

            $output->writeln('[synthese:pros] Détection des périodes impactées...');
            $stepStartedAt = microtime(true);
            $periods = $this->fetchPeriodsToRefresh($lastRunAt ?: null, $yearN2, $yearNow);
            $output->writeln(sprintf(
                '[synthese:pros] Périodes impactées détectées: %d (%.3f s).',
                count($periods),
                microtime(true) - $stepStartedAt
            ));

            if ($periods === []) {
                $this->touchMeta();
                $this->connection->commit();
                $output->writeln(sprintf(
                    '[synthese:pros] Aucune période à recalculer. Exécution terminée (%.3f s).',
                    microtime(true) - $startedAt
                ));
                return Command::SUCCESS;
            }

            $output->writeln('[synthese:pros] Préparation de la table temporaire des périodes...');
            $stepStartedAt = microtime(true);
            $this->populateTempPeriods($periods);
            $output->writeln(sprintf(
                '[synthese:pros] Table temporaire alimentée (%.3f s).',
                microtime(true) - $stepStartedAt
            ));

            $output->writeln('[synthese:pros] Suppression des agrégats existants sur les périodes impactées...');
            $stepStartedAt = microtime(true);
            $this->connection->executeStatement("
                DELETE sp
                FROM synthese_pros sp
                INNER JOIN tmp_synthese_pros_periods p
                    ON p.annee = sp.annee AND p.mois = sp.mois
            ");
            $output->writeln(sprintf(
                '[synthese:pros] Suppression des agrégats existants terminée (%.3f s).',
                microtime(true) - $stepStartedAt
            ));

            $sql = "
                INSERT INTO synthese_pros (
                    code_client, annee, mois, ca, ca_auto, ca_moto, nb_controles, nb_controles_auto, nb_controles_moto, agr_centre, societe_nom, reseau_id, reseau_nom
                )
                SELECT
                    cli_ref.nom_code_client AS code_client,
                    YEAR(c.date_ctrl) AS annee,
                    MONTH(c.date_ctrl) AS mois,
                    SUM(COALESCE(fa.montant_presta_ht, fa.total_ht) / t.nb_ctrl_facture) AS ca,
                    SUM(
                        IF(
                            c.type_ctrl NOT LIKE 'CL%',
                            COALESCE(fa.montant_presta_ht, fa.total_ht) / t.nb_ctrl_facture,
                            0
                        )
                    ) AS ca_auto,
                    SUM(
                        IF(
                            c.type_ctrl LIKE 'CL%',
                            COALESCE(fa.montant_presta_ht, fa.total_ht) / t.nb_ctrl_facture,
                            0
                        )
                    ) AS ca_moto,
                    COUNT(DISTINCT c.idcontrole) AS nb_controles,
                    COUNT(DISTINCT IF(c.type_ctrl NOT LIKE 'CL%', c.idcontrole, NULL)) AS nb_controles_auto,
                    COUNT(DISTINCT IF(c.type_ctrl LIKE 'CL%', c.idcontrole, NULL)) AS nb_controles_moto,
                    COALESCE(cc.agr_centre, 'Centre inconnu') AS agr_centre,
                    COALESCE(so.nom, 'Société inconnue') AS societe_nom,
                    c.reseau_id AS reseau_id,
                    COALESCE(ce.reseau_nom, '') AS reseau_nom
                FROM controles c
                INNER JOIN tmp_synthese_pros_periods p
                    ON p.annee = YEAR(c.date_ctrl) AND p.mois = MONTH(c.date_ctrl)
                INNER JOIN (
                    SELECT DISTINCT idcontrole, idfacture
                    FROM controles_factures
                ) cf
                    ON cf.idcontrole = c.idcontrole
                INNER JOIN factures fa
                    ON fa.idfacture = cf.idfacture
                INNER JOIN (
                    SELECT DISTINCT idcontrole, idclient, agr_centre
                    FROM clients_controles
                ) cc
                    ON cc.idcontrole = c.idcontrole
                INNER JOIN (
                    SELECT
                        idclient,
                        COALESCE(NULLIF(MAX(TRIM(nom_code_client)), ''), CONCAT('Client ', idclient)) AS nom_code_client,
                        MAX(
                            CASE
                                WHEN TRIM(COALESCE(code_client, '')) <> ''
                                     AND TRIM(code_client) NOT REGEXP '^0+$'
                                THEN 1
                                ELSE 0
                            END
                        ) AS has_pro
                    FROM clients
                    GROUP BY idclient
                ) cli_ref
                    ON cli_ref.idclient = cc.idclient
                LEFT JOIN centre ce
                    ON ce.agr_centre = cc.agr_centre
                LEFT JOIN societe so
                    ON so.id = ce.societe_id
                JOIN (
                    SELECT
                        idfacture,
                        COUNT(DISTINCT idcontrole) AS nb_ctrl_facture
                    FROM (
                        SELECT DISTINCT idcontrole, idfacture
                        FROM controles_factures
                    ) cf2
                    GROUP BY idfacture
                ) t ON t.idfacture = fa.idfacture
                WHERE fa.type_facture = 'F'
                    AND fa.total_ht > 0
                    AND c.date_ctrl >= :date_from
                    AND c.date_ctrl < :date_to
                    AND c.res_ctrl IN ('A','AP')
                    AND cli_ref.has_pro = 1
                GROUP BY
                    cli_ref.nom_code_client,
                    YEAR(c.date_ctrl),
                    MONTH(c.date_ctrl),
                    COALESCE(cc.agr_centre, 'Centre inconnu'),
                    COALESCE(so.nom, 'Société inconnue'),
                    c.reseau_id,
                    COALESCE(ce.reseau_nom, '')
            ";

            $output->writeln('[synthese:pros] Recalcul et insertion des agrégats...');
            $stepStartedAt = microtime(true);
            $this->connection->executeStatement($sql, [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ]);
            $output->writeln(sprintf(
                '[synthese:pros] Recalcul terminé (%.3f s).',
                microtime(true) - $stepStartedAt
            ));

            $this->touchMeta();
            $this->connection->commit();

            $output->writeln(sprintf(
                '[synthese:pros] Mise à jour terminée avec succès (%.3f s).',
                microtime(true) - $startedAt
            ));
            return Command::SUCCESS;
        } catch (Exception $e) {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }

            $output->writeln('<error>[synthese:pros] Échec: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }

    /**
     * @throws Exception
     */
    private function ensureTable(): void
    {
        $this->connection->executeStatement("
            CREATE TABLE IF NOT EXISTS synthese_pros (
                id INT AUTO_INCREMENT PRIMARY KEY,
                code_client VARCHAR(50) NOT NULL,
                annee INT NOT NULL,
                mois INT NOT NULL,
                ca DECIMAL(12,2) NOT NULL DEFAULT 0,
                ca_auto DECIMAL(12,2) NOT NULL DEFAULT 0,
                ca_moto DECIMAL(12,2) NOT NULL DEFAULT 0,
                nb_controles INT NOT NULL DEFAULT 0,
                nb_controles_auto INT NOT NULL DEFAULT 0,
                nb_controles_moto INT NOT NULL DEFAULT 0,
                agr_centre VARCHAR(50) NOT NULL,
                societe_nom VARCHAR(255) NOT NULL,
                reseau_id INT NOT NULL,
                reseau_nom VARCHAR(50) NOT NULL DEFAULT '',
                UNIQUE KEY unique_client_annee_mois_centre_societe (code_client, annee, mois, agr_centre, societe_nom),
                KEY idx_synthese_pros_periode (annee, mois)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        if (!$this->columnExists('synthese_pros', 'reseau_nom')) {
            $this->connection->executeStatement("ALTER TABLE synthese_pros ADD COLUMN reseau_nom VARCHAR(50) NOT NULL DEFAULT ''");
        }

        $this->connection->executeStatement("
            CREATE TABLE IF NOT EXISTS synthese_meta (
                meta_key VARCHAR(64) PRIMARY KEY,
                last_run_at DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    /**
     * @throws Exception
     */
    private function columnExists(string $tableName, string $columnName): bool
    {
        return (int)$this->connection->fetchOne(
            "
                SELECT COUNT(*)
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = :table_name
                  AND COLUMN_NAME = :column_name
            ",
            [
                'table_name' => $tableName,
                'column_name' => $columnName,
            ]
        ) > 0;
    }

    /**
     * @return array<int, array{annee:int, mois:int}>
     * @throws Exception
     */
    private function fetchPeriodsToRefresh(?string $lastRunAt, int $yearMin, int $yearMax): array
    {
        if ($lastRunAt === null) {
            return $this->connection->fetchAllAssociative(
                "
                    SELECT DISTINCT YEAR(date_ctrl) AS annee, MONTH(date_ctrl) AS mois
                    FROM controles
                    WHERE YEAR(date_ctrl) BETWEEN :year_min AND :year_max
                    ORDER BY annee, mois
                ",
                ['year_min' => $yearMin, 'year_max' => $yearMax]
            );
        }

        return $this->connection->fetchAllAssociative(
            "
                SELECT DISTINCT YEAR(date_ctrl) AS annee, MONTH(date_ctrl) AS mois
                FROM controles
                WHERE YEAR(date_ctrl) BETWEEN :year_min AND :year_max
                  AND date_export > :last_run_at
                ORDER BY annee, mois
            ",
            [
                'year_min' => $yearMin,
                'year_max' => $yearMax,
                'last_run_at' => $lastRunAt,
            ]
        );
    }

    /**
     * @param array<int, array{annee:int, mois:int}> $periods
     * @throws Exception
     */
    private function populateTempPeriods(array $periods): void
    {
        $this->connection->executeStatement('DROP TEMPORARY TABLE IF EXISTS tmp_synthese_pros_periods');
        $this->connection->executeStatement('CREATE TEMPORARY TABLE tmp_synthese_pros_periods (annee INT NOT NULL, mois INT NOT NULL, PRIMARY KEY (annee, mois))');

        foreach ($periods as $period) {
            $this->connection->insert('tmp_synthese_pros_periods', [
                'annee' => (int)$period['annee'],
                'mois' => (int)$period['mois'],
            ]);
        }
    }

    /**
     * @throws Exception
     */
    private function touchMeta(): void
    {
        $this->connection->executeStatement(
            "
                INSERT INTO synthese_meta (meta_key, last_run_at)
                VALUES (:meta_key, NOW())
                ON DUPLICATE KEY UPDATE last_run_at = VALUES(last_run_at)
            ",
            ['meta_key' => self::META_KEY]
        );
    }
}
