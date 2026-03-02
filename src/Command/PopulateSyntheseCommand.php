<?php

namespace App\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:synthese:summary',
    description: 'Met à jour de manière incrémentale la table synthese_controles.'
)]
class PopulateSyntheseCommand extends Command
{
    private const string META_KEY = 'synthese_controles';

    public function __construct(
        private readonly Connection $connection
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('[synthese:summary] Démarrage de la mise à jour incrémentale de synthese_controles.');

        try {
            $startedAt = microtime(true);

            $output->writeln('[synthese:summary] Vérification des tables techniques...');
            $stepStartedAt = microtime(true);
            $this->ensureTables();
            $output->writeln(sprintf(
                '[synthese:summary] Tables techniques prêtes (%.3f s).',
                microtime(true) - $stepStartedAt
            ));

            $output->writeln('[synthese:summary] Ouverture de la transaction...');
            $this->connection->beginTransaction();

            $lastRunAt = $this->connection->fetchOne(
                'SELECT last_run_at FROM synthese_meta WHERE meta_key = :meta_key',
                ['meta_key' => self::META_KEY]
            );
            $output->writeln(sprintf(
                '[synthese:summary] Dernière exécution enregistrée: %s.',
                $lastRunAt ?: 'aucune'
            ));

            $output->writeln('[synthese:summary] Détection des périodes impactées...');
            $stepStartedAt = microtime(true);
            $periods = $this->fetchPeriodsToRefresh($lastRunAt ?: null);
            $output->writeln(sprintf(
                '[synthese:summary] Périodes impactées détectées: %d (%.3f s).',
                count($periods),
                microtime(true) - $stepStartedAt
            ));

            if ($periods === []) {
                $this->touchMeta();
                $this->connection->commit();
                $output->writeln(sprintf(
                    '[synthese:summary] Aucune période à recalculer. Exécution terminée (%.3f s).',
                    microtime(true) - $startedAt
                ));
                return Command::SUCCESS;
            }

            $output->writeln('[synthese:summary] Préparation de la table temporaire des périodes...');
            $stepStartedAt = microtime(true);
            $this->populateTempPeriods($periods);
            $output->writeln(sprintf(
                '[synthese:summary] Table temporaire alimentée (%.3f s).',
                microtime(true) - $stepStartedAt
            ));

            $output->writeln('[synthese:summary] Suppression des agrégats existants pour les périodes impactées...');
            $stepStartedAt = microtime(true);
            $this->deleteExistingPeriods();
            $output->writeln(sprintf(
                '[synthese:summary] Agrégats précédents supprimés (%.3f s).',
                microtime(true) - $stepStartedAt
            ));

            $output->writeln('[synthese:summary] Recalcul des agrégats...');
            $stepStartedAt = microtime(true);
            $this->insertAggregatesForPeriods();
            $output->writeln(sprintf(
                '[synthese:summary] Agrégats recalculés et insérés (%.3f s).',
                microtime(true) - $stepStartedAt
            ));

            $this->touchMeta();

            $this->connection->commit();

            $output->writeln(sprintf('[synthese:summary] Périodes recalculées: %d.', count($periods)));
            $output->writeln(sprintf(
                '[synthese:summary] Mise à jour terminée avec succès (%.3f s).',
                microtime(true) - $startedAt
            ));
            return Command::SUCCESS;
        } catch (Exception $e) {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }

            $output->writeln('<error>[synthese:summary] Échec: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }

    /**
     * @throws Exception
     */
    private function ensureTables(): void
    {
        $this->connection->executeStatement("
            CREATE TABLE IF NOT EXISTS synthese_controles (
                id INT AUTO_INCREMENT PRIMARY KEY,
                societe_nom VARCHAR(255) NOT NULL,
                agr_centre VARCHAR(50) NOT NULL,
                centre_ville VARCHAR(255) DEFAULT '',
                reseau_id INT NOT NULL,
                reseau_nom VARCHAR(50) DEFAULT '',
                salarie_id INT NOT NULL,
                salarie_agr VARCHAR(20) NOT NULL,
                salarie_nom VARCHAR(255) NOT NULL,
                salarie_prenom VARCHAR(255) NOT NULL,
                annee INT NOT NULL,
                mois INT NOT NULL,
                nb_controles INT NOT NULL DEFAULT 0,
                nb_vtp INT NOT NULL DEFAULT 0,
                nb_clvtp INT NOT NULL DEFAULT 0,
                nb_cv INT NOT NULL DEFAULT 0,
                nb_clcv INT NOT NULL DEFAULT 0,
                nb_vtc INT NOT NULL DEFAULT 0,
                nb_vol INT NOT NULL DEFAULT 0,
                nb_clvol INT NOT NULL DEFAULT 0,
                nb_auto INT NOT NULL DEFAULT 0,
                nb_moto INT NOT NULL DEFAULT 0,
                total_presta_ht DECIMAL(12,2) NOT NULL DEFAULT 0,
                total_ht_vtp DECIMAL(12,2) NOT NULL DEFAULT 0,
                total_ht_clvtp DECIMAL(12,2) NOT NULL DEFAULT 0,
                total_ht_cv DECIMAL(12,2) NOT NULL DEFAULT 0,
                total_ht_clcv DECIMAL(12,2) NOT NULL DEFAULT 0,
                total_ht_vtc DECIMAL(12,2) NOT NULL DEFAULT 0,
                total_ht_vol DECIMAL(12,2) NOT NULL DEFAULT 0,
                total_ht_clvol DECIMAL(12,2) NOT NULL DEFAULT 0,
                temps_total INT NOT NULL DEFAULT 0,
                temps_total_auto INT NOT NULL DEFAULT 0,
                temps_total_moto INT NOT NULL DEFAULT 0,
                taux_refus DECIMAL(5,2) NOT NULL DEFAULT 0,
                refus_auto INT NOT NULL DEFAULT 0,
                refus_moto INT NOT NULL DEFAULT 0,
                nb_particuliers INT NOT NULL DEFAULT 0,
                nb_professionnels INT NOT NULL DEFAULT 0,
                nb_particuliers_auto INT NOT NULL DEFAULT 0,
                nb_particuliers_moto INT NOT NULL DEFAULT 0,
                nb_professionnels_auto INT NOT NULL DEFAULT 0,
                nb_professionnels_moto INT NOT NULL DEFAULT 0,
                UNIQUE KEY unique_salarie_mois_annee (salarie_id, salarie_agr, agr_centre, annee, mois),
                KEY idx_synthese_periode (annee, mois)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $this->ensureUniqueKeyForUnknownSalaries();

        $this->connection->executeStatement("
            CREATE TABLE IF NOT EXISTS synthese_meta (
                meta_key VARCHAR(64) PRIMARY KEY,
                last_run_at DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    /**
     * @return array<int, array{annee:int, mois:int}>
     * @throws Exception
     */
    private function fetchPeriodsToRefresh(?string $lastRunAt): array
    {
        if ($lastRunAt === null) {
            return $this->connection->fetchAllAssociative("
                SELECT DISTINCT YEAR(date_ctrl) AS annee, MONTH(date_ctrl) AS mois
                FROM controles
                ORDER BY annee, mois
            ");
        }

        return $this->connection->fetchAllAssociative(
            "
                SELECT DISTINCT YEAR(date_ctrl) AS annee, MONTH(date_ctrl) AS mois
                FROM controles
                WHERE date_export > :last_run_at
                ORDER BY annee, mois
            ",
            ['last_run_at' => $lastRunAt]
        );
    }

    /**
     * @param array<int, array{annee:int, mois:int}> $periods
     * @throws Exception
     */
    private function populateTempPeriods(array $periods): void
    {
        $this->connection->executeStatement('DROP TEMPORARY TABLE IF EXISTS tmp_synthese_periods');
        $this->connection->executeStatement('CREATE TEMPORARY TABLE tmp_synthese_periods (annee INT NOT NULL, mois INT NOT NULL, PRIMARY KEY (annee, mois))');

        foreach ($periods as $period) {
            $this->connection->insert('tmp_synthese_periods', [
                'annee' => (int)$period['annee'],
                'mois' => (int)$period['mois'],
            ]);
        }
    }

    /**
     * @throws Exception
     */
    private function deleteExistingPeriods(): void
    {
        $this->connection->executeStatement("
            DELETE sc
            FROM synthese_controles sc
            INNER JOIN tmp_synthese_periods p
                ON p.annee = sc.annee AND p.mois = sc.mois
        ");
    }

    /**
     * @throws Exception
     */
    private function insertAggregatesForPeriods(): void
    {
        $sql = "
            INSERT INTO synthese_controles (
                societe_nom, agr_centre, centre_ville, reseau_id, reseau_nom,
                salarie_id, salarie_agr, salarie_nom, salarie_prenom,
                annee, mois,
                nb_controles, nb_vtp, nb_clvtp, nb_cv, nb_clcv, nb_vtc, nb_vol, nb_clvol, nb_auto, nb_moto,
                total_presta_ht, total_ht_vtp, total_ht_clvtp, total_ht_cv, total_ht_clcv, total_ht_vtc, total_ht_vol, total_ht_clvol,
                temps_total, temps_total_auto, temps_total_moto, taux_refus, refus_auto, refus_moto, nb_particuliers, nb_professionnels,
                nb_particuliers_auto, nb_particuliers_moto, nb_professionnels_auto, nb_professionnels_moto
            )
            SELECT
                COALESCE(so.nom, 'Société inconnue') AS societe_nom,
                IF(ce.agr_centre IS NULL, CONCAT('Centre inconnu (', COALESCE(cc.agr_centre, '?'), ')'), ce.agr_centre) AS agr_centre,
                COALESCE(ce.ville, '') AS centre_ville,
                ctrl.reseau_id AS reseau_id,
                COALESCE(ce.reseau_nom, '') AS reseau_nom,
                COALESCE(sa.id, 0) AS salarie_id,
                COALESCE(sa.agr_controleur, cc.agr_controleur, 'Agrément inconnu') AS salarie_agr,
                IF(sa.id IS NULL, CONCAT('Salarié inconnu (', COALESCE(cc.agr_controleur, '?'), ')'), COALESCE(sa.nom, 'Salarié inconnu')) AS salarie_nom,
                COALESCE(sa.prenom, '') AS salarie_prenom,
                YEAR(ctrl.date_ctrl) AS annee,
                MONTH(ctrl.date_ctrl) AS mois,
                COUNT(DISTINCT ctrl.idcontrole) AS nb_controles,
                COUNT(DISTINCT IF(ctrl.type_ctrl IN ('VTP','VLCTP','VLVT','VLVP'), ctrl.idcontrole, NULL)) AS nb_vtp,
                COUNT(DISTINCT IF(ctrl.type_ctrl IN ('CLVTP','CLCTP'), ctrl.idcontrole, NULL)) AS nb_clvtp,
                COUNT(DISTINCT IF(ctrl.type_ctrl IN ('CV','VLCV','VLCVC'), ctrl.idcontrole, NULL)) AS nb_cv,
                COUNT(DISTINCT IF(ctrl.type_ctrl IN ('CLCV'), ctrl.idcontrole, NULL)) AS nb_clcv,
                COUNT(DISTINCT IF(ctrl.type_ctrl IN ('VTC','VLCTC'), ctrl.idcontrole, NULL)) AS nb_vtc,
                COUNT(DISTINCT IF(ctrl.type_ctrl IN ('VOL','VP','VT'), ctrl.idcontrole, NULL)) AS nb_vol,
                COUNT(DISTINCT IF(ctrl.type_ctrl IN ('CLVP','CLVT'), ctrl.idcontrole, NULL)) AS nb_clvol,
                COUNT(DISTINCT IF(ctrl.type_ctrl IN ('VTP','VLCTP','VLVT','VLVP','CV','VLCV','VLCVC','VTC','VLCTC','VOL','VP','VT'), ctrl.idcontrole, NULL)) AS nb_auto,
                COUNT(DISTINCT IF(ctrl.type_ctrl LIKE 'CL%', ctrl.idcontrole, NULL)) AS nb_moto,
                SUM(IF(f.type_facture='F', COALESCE(f.montant_presta_ht, f.total_ht) / t.nb_ctrl_facture, 0)) AS total_presta_ht,
                SUM(IF(ctrl.type_ctrl IN ('VTP','VLCTP','VLVT','VLVP') AND f.type_facture='F', COALESCE(f.montant_presta_ht, f.total_ht) / t.nb_ctrl_facture, 0)) AS total_ht_vtp,
                SUM(IF(ctrl.type_ctrl IN ('CLVTP','CLCTP') AND f.type_facture='F', COALESCE(f.montant_presta_ht, f.total_ht) / t.nb_ctrl_facture, 0)) AS total_ht_clvtp,
                SUM(IF(ctrl.type_ctrl IN ('CV','VLCV','VLCVC') AND f.type_facture='F', COALESCE(f.montant_presta_ht, f.total_ht) / t.nb_ctrl_facture, 0)) AS total_ht_cv,
                SUM(IF(ctrl.type_ctrl IN ('CLCV') AND f.type_facture='F', COALESCE(f.montant_presta_ht, f.total_ht) / t.nb_ctrl_facture, 0)) AS total_ht_clcv,
                SUM(IF(ctrl.type_ctrl IN ('VTC','VLCTC') AND f.type_facture='F', COALESCE(f.montant_presta_ht, f.total_ht) / t.nb_ctrl_facture, 0)) AS total_ht_vtc,
                SUM(IF(ctrl.type_ctrl IN ('VOL','VP','VT') AND f.type_facture='F', COALESCE(f.montant_presta_ht, f.total_ht) / t.nb_ctrl_facture, 0)) AS total_ht_vol,
                SUM(IF(ctrl.type_ctrl IN ('CLVP','CLVT') AND f.type_facture='F', COALESCE(f.montant_presta_ht, f.total_ht) / t.nb_ctrl_facture, 0)) AS total_ht_clvol,
                SUM(ctrl.temps_ctrl) AS temps_total,
                SUM(IF(ctrl.type_ctrl IN ('VTP','VLCTP','VLVT','VLVP','CV','VLCV','VLCVC','VTC','VLCTC','VOL','VP','VT'), ctrl.temps_ctrl, 0)) AS temps_total_auto,
                SUM(IF(ctrl.type_ctrl LIKE 'CL%', ctrl.temps_ctrl, 0)) AS temps_total_moto,
                COUNT(DISTINCT IF(ctrl.res_ctrl IN ('S','R','SP'), ctrl.idcontrole, NULL)) AS taux_refus,
                COUNT(DISTINCT IF(ctrl.type_ctrl IN ('VTP','VLCTP','VLVT','VLVP','CV','VLCV','VLCVC','VTC','VLCTC','VOL', 'VP', 'VT') AND ctrl.res_ctrl IN ('S','R','SP'), ctrl.idcontrole, NULL)) AS refus_auto,
                COUNT(DISTINCT IF(ctrl.type_ctrl LIKE 'CL%' AND ctrl.res_ctrl IN ('S','R','SP'), ctrl.idcontrole, NULL)) AS refus_moto,
                COUNT(DISTINCT IF(COALESCE(cc.has_pro_client, 0) = 0, ctrl.idcontrole, NULL)) AS nb_particuliers,
                COUNT(DISTINCT IF(COALESCE(cc.has_pro_client, 0) = 1, ctrl.idcontrole, NULL)) AS nb_professionnels,
                COUNT(DISTINCT IF(ctrl.type_ctrl NOT LIKE 'CL%' AND COALESCE(cc.has_pro_client, 0) = 0, ctrl.idcontrole, NULL)) AS nb_particuliers_auto,
                COUNT(DISTINCT IF(ctrl.type_ctrl LIKE 'CL%' AND COALESCE(cc.has_pro_client, 0) = 0, ctrl.idcontrole, NULL)) AS nb_particuliers_moto,
                COUNT(DISTINCT IF(ctrl.type_ctrl NOT LIKE 'CL%' AND COALESCE(cc.has_pro_client, 0) = 1, ctrl.idcontrole, NULL)) AS nb_professionnels_auto,
                COUNT(DISTINCT IF(ctrl.type_ctrl LIKE 'CL%' AND COALESCE(cc.has_pro_client, 0) = 1, ctrl.idcontrole, NULL)) AS nb_professionnels_moto
            FROM controles ctrl
            INNER JOIN tmp_synthese_periods p
                ON p.annee = YEAR(ctrl.date_ctrl) AND p.mois = MONTH(ctrl.date_ctrl)
            LEFT JOIN (
                SELECT
                    cc.idcontrole,
                    MIN(cc.agr_centre) AS agr_centre,
                    MIN(cc.agr_controleur) AS agr_controleur,
                    MAX(COALESCE(cli_ref.has_pro, 0)) AS has_pro_client
                FROM clients_controles cc
                LEFT JOIN (
                    SELECT
                        idclient,
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
                ) cli_ref ON cli_ref.idclient = cc.idclient
                GROUP BY cc.idcontrole
            ) cc ON cc.idcontrole = ctrl.idcontrole
            LEFT JOIN (
                SELECT id, nom, prenom, agr_controleur, agr_key
                FROM (
                    SELECT
                        s.id,
                        s.nom,
                        s.prenom,
                        s.agr_controleur,
                        s.agr_key,
                        ROW_NUMBER() OVER (PARTITION BY s.agr_key ORDER BY s.is_primary DESC, s.id ASC) AS rn
                    FROM (
                        SELECT
                            id,
                            nom,
                            prenom,
                            agr_controleur,
                            agr_controleur AS agr_key,
                            1 AS is_primary
                        FROM salarie
                        WHERE agr_controleur IS NOT NULL AND TRIM(agr_controleur) <> ''
                        UNION ALL
                        SELECT
                            id,
                            nom,
                            prenom,
                            agr_controleur,
                            agr_cl_controleur AS agr_key,
                            0 AS is_primary
                        FROM salarie
                        WHERE agr_cl_controleur IS NOT NULL AND TRIM(agr_cl_controleur) <> ''
                    ) s
                ) ranked
                WHERE ranked.rn = 1
            ) sa ON sa.agr_key = cc.agr_controleur
            LEFT JOIN centre ce ON ce.agr_centre = cc.agr_centre
            LEFT JOIN societe so ON so.id = ce.societe_id
            LEFT JOIN (
                SELECT DISTINCT idcontrole, idfacture
                FROM controles_factures
            ) cf ON cf.idcontrole = ctrl.idcontrole
            LEFT JOIN factures f ON f.idfacture = cf.idfacture
            LEFT JOIN (
                SELECT cf.idfacture, COUNT(DISTINCT cf.idcontrole) AS nb_ctrl_facture
                FROM (
                    SELECT DISTINCT idcontrole, idfacture
                    FROM controles_factures
                ) cf
                INNER JOIN factures f2 ON f2.idfacture = cf.idfacture
                WHERE f2.type_facture='F'
                GROUP BY cf.idfacture
            ) t ON t.idfacture = f.idfacture
            GROUP BY salarie_id, salarie_agr, agr_centre, annee, mois
            ON DUPLICATE KEY UPDATE
                nb_controles=VALUES(nb_controles),
                nb_vtp=VALUES(nb_vtp),
                nb_clvtp=VALUES(nb_clvtp),
                nb_cv=VALUES(nb_cv),
                nb_clcv=VALUES(nb_clcv),
                nb_vtc=VALUES(nb_vtc),
                nb_vol=VALUES(nb_vol),
                nb_clvol=VALUES(nb_clvol),
                nb_auto=VALUES(nb_auto),
                nb_moto=VALUES(nb_moto),
                total_presta_ht=VALUES(total_presta_ht),
                total_ht_vtp=VALUES(total_ht_vtp),
                total_ht_clvtp=VALUES(total_ht_clvtp),
                total_ht_cv=VALUES(total_ht_cv),
                total_ht_clcv=VALUES(total_ht_clcv),
                total_ht_vtc=VALUES(total_ht_vtc),
                total_ht_vol=VALUES(total_ht_vol),
                total_ht_clvol=VALUES(total_ht_clvol),
                temps_total=VALUES(temps_total),
                temps_total_auto=VALUES(temps_total_auto),
                temps_total_moto=VALUES(temps_total_moto),
                taux_refus=VALUES(taux_refus),
                refus_auto=VALUES(refus_auto),
                refus_moto=VALUES(refus_moto),
                nb_particuliers=VALUES(nb_particuliers),
                nb_professionnels=VALUES(nb_professionnels),
                nb_particuliers_auto=VALUES(nb_particuliers_auto),
                nb_particuliers_moto=VALUES(nb_particuliers_moto),
                nb_professionnels_auto=VALUES(nb_professionnels_auto),
                nb_professionnels_moto=VALUES(nb_professionnels_moto)
        ";

        $this->connection->executeStatement($sql);
    }

    /**
     * @throws Exception
     */
    private function ensureUniqueKeyForUnknownSalaries(): void
    {
        $columns = $this->connection->fetchFirstColumn(
            "
                SELECT COLUMN_NAME
                FROM information_schema.statistics
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'synthese_controles'
                  AND INDEX_NAME = 'unique_salarie_mois_annee'
                ORDER BY SEQ_IN_INDEX
            "
        );

        $expected = ['salarie_id', 'salarie_agr', 'agr_centre', 'annee', 'mois'];
        if ($columns === $expected) {
            return;
        }

        if (!empty($columns)) {
            $this->connection->executeStatement('ALTER TABLE synthese_controles DROP INDEX unique_salarie_mois_annee');
        }

        $this->connection->executeStatement(
            'ALTER TABLE synthese_controles ADD UNIQUE KEY unique_salarie_mois_annee (salarie_id, salarie_agr, agr_centre, annee, mois)'
        );
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
