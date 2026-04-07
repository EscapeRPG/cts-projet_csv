<?php

namespace App\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:synthese:summary',
    description: 'Met à jour de manière incrémentale la table synthese_controles.'
)]
/**
 * Rebuilds incremental monthly aggregates into `synthese_controles`.
 */
class PopulateSyntheseCommand extends Command
{
    private const string META_KEY = 'synthese_controles';
    private bool $forceFullRefresh = false;

    /**
     * @param Connection $connection DBAL connection used for DDL/DML operations.
     */
    public function __construct(
        private readonly Connection $connection
    ) {
        parent::__construct();
    }

    /**
     * Runs the incremental refresh workflow for controls summary data.
     *
     * @param InputInterface $input Console input.
     * @param OutputInterface $output Console output.
     *
     * @return int Command exit status.
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('[synthese-summary] Démarrage de la mise à jour incrémentale de synthese_controles.');

        try {
            $startedAt = microtime(true);

            $io->writeln('<comment>Vérification des tables techniques...</comment>');
            $stepStartedAt = microtime(true);
            $this->ensureTables();
            $io->writeln(sprintf(
                '<info>Tables techniques prêtes.</info> <comment>(%.3f s)</comment>',
                microtime(true) - $stepStartedAt
            ));

            $io->writeln('<comment>Ouverture de la transaction...</comment>');
            $this->connection->beginTransaction();

            $lastRunAt = $this->connection->fetchOne(
                'SELECT last_run_at FROM synthese_meta WHERE meta_key = :meta_key',
                ['meta_key' => self::META_KEY]
            );
            $io->writeln(sprintf(
                '<info>Dernière exécution enregistrée :</info> %s.',
                $lastRunAt ?: 'aucune'
            ));

            $io->writeln('<comment>Détection des périodes impactées...</comment>');
            $stepStartedAt = microtime(true);
            $periods = $this->fetchPeriodsToRefresh($this->forceFullRefresh ? null : ($lastRunAt ?: null));
            $io->writeln(sprintf(
                '<info>Périodes impactées détectées :</info> %d. <comment>(%.3f s)</comment>',
                count($periods),
                microtime(true) - $stepStartedAt
            ));

            if ($periods === []) {
                $this->touchMeta();
                $this->connection->commit();
                $io->success(sprintf(
                    'Aucune période à recalculer. Exécution terminée (%.3f s).',
                    microtime(true) - $startedAt
                ));
                return Command::SUCCESS;
            }

            $io->writeln('<comment>Préparation de la table temporaire des périodes...</comment>');
            $stepStartedAt = microtime(true);
            $this->populateTempPeriods($periods);
            $io->writeln(sprintf(
                '<info>Table temporaire alimentée.</info> <comment>(%.3f s)</comment>',
                microtime(true) - $stepStartedAt
            ));

            $io->writeln('<comment>Suppression des agrégats existants pour les périodes impactées...</comment>');
            $stepStartedAt = microtime(true);
            $this->deleteExistingPeriods();
            $io->writeln(sprintf(
                '<info>Agrégats précédents supprimés.</info> <comment>(%.3f s)</comment>',
                microtime(true) - $stepStartedAt
            ));

            $io->writeln('<comment>Recalcul des agrégats...</comment>');
            $stepStartedAt = microtime(true);
            $this->insertAggregatesForPeriods();
            $io->writeln(sprintf(
                '<info>Agrégats recalculés et insérés.</info> <comment>(%.3f s)</comment>',
                microtime(true) - $stepStartedAt
            ));

            $this->touchMeta();

            $this->connection->commit();

            $io->writeln(sprintf('<info>Périodes recalculées :</info> %d.', count($periods)));
            $io->success(sprintf(
                'Mise à jour terminée avec succès (%.3f s).',
                microtime(true) - $startedAt
            ));
            return Command::SUCCESS;
        } catch (Exception $e) {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }

            $io->error('<error>Échec: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }

    /**
     * Ensures summary and metadata tables are present and compatible.
     *
     * @return void
     *
     * @throws Exception
     */
    private function ensureTables(): void
    {
        $this->connection->executeStatement("
            CREATE TABLE IF NOT EXISTS synthese_controles (
                id INT AUTO_INCREMENT PRIMARY KEY,
                societe_nom VARCHAR(255) NOT NULL,
                agr_centre VARCHAR(50) NOT NULL,
                agr_centre_cl VARCHAR(50) NOT NULL DEFAULT '',
                centre_ville VARCHAR(255) DEFAULT '',
                reseau_id INT NOT NULL,
                reseau_nom VARCHAR(50) DEFAULT '',
                salarie_id INT NOT NULL,
                salarie_agr VARCHAR(20) NOT NULL,
                salarie_agr_cl VARCHAR(20) NOT NULL DEFAULT '',
                salarie_nom VARCHAR(255) NOT NULL,
                salarie_prenom VARCHAR(255) NOT NULL,
                annee INT NOT NULL,
                mois INT NOT NULL,
                nb_controles INT NOT NULL DEFAULT 0,
                nb_controles_factures INT NOT NULL DEFAULT 0,
                nb_vtp INT NOT NULL DEFAULT 0,
                nb_vtp_factures INT NOT NULL DEFAULT 0,
                nb_vtp_particuliers INT NOT NULL DEFAULT 0,
                nb_vtp_professionnels INT NOT NULL DEFAULT 0,
                nb_clvtp INT NOT NULL DEFAULT 0,
                nb_clvtp_factures INT NOT NULL DEFAULT 0,
                nb_clvtp_particuliers INT NOT NULL DEFAULT 0,
                nb_clvtp_professionnels INT NOT NULL DEFAULT 0,
                nb_cv INT NOT NULL DEFAULT 0,
                nb_cv_factures INT NOT NULL DEFAULT 0,
                nb_cv_particuliers INT NOT NULL DEFAULT 0,
                nb_cv_professionnels INT NOT NULL DEFAULT 0,
                nb_clcv INT NOT NULL DEFAULT 0,
                nb_clcv_factures INT NOT NULL DEFAULT 0,
                nb_clcv_particuliers INT NOT NULL DEFAULT 0,
                nb_clcv_professionnels INT NOT NULL DEFAULT 0,
                nb_vtc INT NOT NULL DEFAULT 0,
                nb_vtc_factures INT NOT NULL DEFAULT 0,
                nb_vtc_particuliers INT NOT NULL DEFAULT 0,
                nb_vtc_professionnels INT NOT NULL DEFAULT 0,
                nb_vol INT NOT NULL DEFAULT 0,
                nb_vol_factures INT NOT NULL DEFAULT 0,
                nb_vol_particuliers INT NOT NULL DEFAULT 0,
                nb_vol_professionnels INT NOT NULL DEFAULT 0,
                nb_clvol INT NOT NULL DEFAULT 0,
                nb_clvol_factures INT NOT NULL DEFAULT 0,
                nb_clvol_particuliers INT NOT NULL DEFAULT 0,
                nb_clvol_professionnels INT NOT NULL DEFAULT 0,
                nb_auto INT NOT NULL DEFAULT 0,
                nb_auto_factures INT NOT NULL DEFAULT 0,
                nb_moto INT NOT NULL DEFAULT 0,
                nb_moto_factures INT NOT NULL DEFAULT 0,
                total_presta_ht DECIMAL(12,2) NOT NULL DEFAULT 0,
                total_presta_ht_particuliers DECIMAL(12,2) NOT NULL DEFAULT 0,
                total_presta_ht_professionnels DECIMAL(12,2) NOT NULL DEFAULT 0,
                total_ht_vtp DECIMAL(12,2) NOT NULL DEFAULT 0,
                total_ht_vtp_particuliers DECIMAL(12,2) NOT NULL DEFAULT 0,
                total_ht_vtp_professionnels DECIMAL(12,2) NOT NULL DEFAULT 0,
                total_ht_clvtp DECIMAL(12,2) NOT NULL DEFAULT 0,
                total_ht_clvtp_particuliers DECIMAL(12,2) NOT NULL DEFAULT 0,
                total_ht_clvtp_professionnels DECIMAL(12,2) NOT NULL DEFAULT 0,
                total_ht_cv DECIMAL(12,2) NOT NULL DEFAULT 0,
                total_ht_cv_particuliers DECIMAL(12,2) NOT NULL DEFAULT 0,
                total_ht_cv_professionnels DECIMAL(12,2) NOT NULL DEFAULT 0,
                total_ht_clcv DECIMAL(12,2) NOT NULL DEFAULT 0,
                total_ht_clcv_particuliers DECIMAL(12,2) NOT NULL DEFAULT 0,
                total_ht_clcv_professionnels DECIMAL(12,2) NOT NULL DEFAULT 0,
                total_ht_vtc DECIMAL(12,2) NOT NULL DEFAULT 0,
                total_ht_vtc_particuliers DECIMAL(12,2) NOT NULL DEFAULT 0,
                total_ht_vtc_professionnels DECIMAL(12,2) NOT NULL DEFAULT 0,
                total_ht_vol DECIMAL(12,2) NOT NULL DEFAULT 0,
                total_ht_vol_particuliers DECIMAL(12,2) NOT NULL DEFAULT 0,
                total_ht_vol_professionnels DECIMAL(12,2) NOT NULL DEFAULT 0,
                total_ht_clvol DECIMAL(12,2) NOT NULL DEFAULT 0,
                total_ht_clvol_particuliers DECIMAL(12,2) NOT NULL DEFAULT 0,
                total_ht_clvol_professionnels DECIMAL(12,2) NOT NULL DEFAULT 0,
                temps_total INT NOT NULL DEFAULT 0,
                temps_total_auto INT NOT NULL DEFAULT 0,
                temps_total_moto INT NOT NULL DEFAULT 0,
                temps_total_vtp INT NOT NULL DEFAULT 0,
                temps_total_clvtp INT NOT NULL DEFAULT 0,
                temps_total_cv INT NOT NULL DEFAULT 0,
                temps_total_clcv INT NOT NULL DEFAULT 0,
                temps_total_vtc INT NOT NULL DEFAULT 0,
                temps_total_vol INT NOT NULL DEFAULT 0,
                temps_total_clvol INT NOT NULL DEFAULT 0,
                taux_refus DECIMAL(5,2) NOT NULL DEFAULT 0,
                refus_auto INT NOT NULL DEFAULT 0,
                refus_moto INT NOT NULL DEFAULT 0,
                refus_vtp INT NOT NULL DEFAULT 0,
                refus_clvtp INT NOT NULL DEFAULT 0,
                refus_cv INT NOT NULL DEFAULT 0,
                refus_clcv INT NOT NULL DEFAULT 0,
                refus_vtc INT NOT NULL DEFAULT 0,
                refus_vol INT NOT NULL DEFAULT 0,
                refus_clvol INT NOT NULL DEFAULT 0,
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
        $this->ensureColumn('agr_centre_cl', "VARCHAR(50) NOT NULL DEFAULT ''");
        $this->ensureColumn('salarie_agr_cl', "VARCHAR(20) NOT NULL DEFAULT ''");
        $this->ensureRevenueSplitColumns();
        $this->ensureDetailedControllerMetricsColumns();
        $this->ensureBilledControlCountColumns();

        $this->connection->executeStatement("
            CREATE TABLE IF NOT EXISTS synthese_meta (
                meta_key VARCHAR(64) PRIMARY KEY,
                last_run_at DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    /**
     * Returns affected year/month periods to refresh.
     *
     * @param string|null $lastRunAt Last successful run timestamp.
     *
     * @return array<int, array{annee:int, mois:int}>
     *
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
     * Creates and fills the temporary periods table used by incremental SQL.
     *
     * @param array<int, array{annee:int, mois:int}> $periods
     *
     * @return void
     *
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
     * Deletes existing aggregates for impacted periods only.
     *
     * @return void
     *
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
     * Inserts recalculated aggregates for impacted periods.
     *
     * @return void
     *
     * @throws Exception
     */
    private function insertAggregatesForPeriods(): void
    {
        $centreJoinCondition = $this->hasSecondaryCentreAgreementColumn()
            ? '(ce.agr_centre = cc.agr_centre OR ce.agr_cl_centre = cc.agr_centre)'
            : 'ce.agr_centre = cc.agr_centre';
        $distinctControleFactureSql = "
            SELECT DISTINCT idcontrole, idfacture
            FROM controles_factures
        ";
        $factureControlCountSql = "
            SELECT cf_count.idfacture, COUNT(DISTINCT cf_count.idcontrole) AS nb_ctrl_facture
            FROM ({$distinctControleFactureSql}) cf_count
            GROUP BY cf_count.idfacture
        ";
        $controlRevenueReferenceSql = "
            SELECT
                cf_ref.idcontrole,
                SUM(COALESCE(f_ref.montant_presta_ht, f_ref.total_ht) / NULLIF(t_ref.nb_ctrl_facture, 0)) AS ref_ca
            FROM ({$distinctControleFactureSql}) cf_ref
            INNER JOIN factures f_ref ON f_ref.idfacture = cf_ref.idfacture
            INNER JOIN ({$factureControlCountSql}) t_ref ON t_ref.idfacture = f_ref.idfacture
            WHERE f_ref.type_facture IN ('F', 'D')
              AND COALESCE(f_ref.montant_presta_ht, f_ref.total_ht) > 0
            GROUP BY cf_ref.idcontrole
        ";
        $avoirReferenceTotalSql = "
            SELECT
                cf_credit.idfacture,
                SUM(COALESCE(ctrl_ref.ref_ca, 0)) AS total_ref_ca
            FROM ({$distinctControleFactureSql}) cf_credit
            LEFT JOIN ({$controlRevenueReferenceSql}) ctrl_ref ON ctrl_ref.idcontrole = cf_credit.idcontrole
            GROUP BY cf_credit.idfacture
        ";
        $allocatedAmountExpr = "
            CASE
                WHEN f.type_facture = 'A' THEN
                    COALESCE(f.montant_presta_ht, f.total_ht) * (
                        CASE
                            WHEN COALESCE(art.total_ref_ca, 0) > 0 THEN COALESCE(cpr.ref_ca, 0) / art.total_ref_ca
                            ELSE 1 / NULLIF(t.nb_ctrl_facture, 0)
                        END
                    )
                ELSE
                    COALESCE(f.montant_presta_ht, f.total_ht) / NULLIF(t.nb_ctrl_facture, 0)
            END
        ";

        $sql = "
            INSERT INTO synthese_controles (
                societe_nom, agr_centre, agr_centre_cl, centre_ville, reseau_id, reseau_nom,
                salarie_id, salarie_agr, salarie_agr_cl, salarie_nom, salarie_prenom,
                annee, mois,
                nb_controles, nb_controles_factures,
                nb_vtp, nb_vtp_factures, nb_vtp_particuliers, nb_vtp_professionnels,
                nb_clvtp, nb_clvtp_factures, nb_clvtp_particuliers, nb_clvtp_professionnels,
                nb_cv, nb_cv_factures, nb_cv_particuliers, nb_cv_professionnels,
                nb_clcv, nb_clcv_factures, nb_clcv_particuliers, nb_clcv_professionnels,
                nb_vtc, nb_vtc_factures, nb_vtc_particuliers, nb_vtc_professionnels,
                nb_vol, nb_vol_factures, nb_vol_particuliers, nb_vol_professionnels,
                nb_clvol, nb_clvol_factures, nb_clvol_particuliers, nb_clvol_professionnels,
                nb_auto, nb_auto_factures, nb_moto, nb_moto_factures,
                total_presta_ht, total_presta_ht_particuliers, total_presta_ht_professionnels,
                total_ht_vtp, total_ht_vtp_particuliers, total_ht_vtp_professionnels,
                total_ht_clvtp, total_ht_clvtp_particuliers, total_ht_clvtp_professionnels,
                total_ht_cv, total_ht_cv_particuliers, total_ht_cv_professionnels,
                total_ht_clcv, total_ht_clcv_particuliers, total_ht_clcv_professionnels,
                total_ht_vtc, total_ht_vtc_particuliers, total_ht_vtc_professionnels,
                total_ht_vol, total_ht_vol_particuliers, total_ht_vol_professionnels,
                total_ht_clvol, total_ht_clvol_particuliers, total_ht_clvol_professionnels,
                temps_total, temps_total_auto, temps_total_moto,
                temps_total_vtp, temps_total_clvtp, temps_total_cv, temps_total_clcv, temps_total_vtc, temps_total_vol, temps_total_clvol,
                taux_refus, refus_auto, refus_moto,
                refus_vtp, refus_clvtp, refus_cv, refus_clcv, refus_vtc, refus_vol, refus_clvol,
                nb_particuliers, nb_professionnels,
                nb_particuliers_auto, nb_particuliers_moto, nb_professionnels_auto, nb_professionnels_moto
            )
            SELECT
                MAX(COALESCE(so.nom, 'Société inconnue')) AS societe_nom,
                IF(ce.agr_centre IS NULL, CONCAT('Centre inconnu (', COALESCE(cc.agr_centre, '?'), ')'), ce.agr_centre) AS agr_centre,
                MAX(COALESCE(ce.agr_cl_centre, '')) AS agr_centre_cl,
                MAX(COALESCE(ce.ville, '')) AS centre_ville,
                MAX(ctrl.reseau_id) AS reseau_id,
                MAX(COALESCE(ce.reseau_nom, '')) AS reseau_nom,
                COALESCE(sa.id, 0) AS salarie_id,
                COALESCE(sa.agr_controleur, cc.agr_controleur, 'Agrément inconnu') AS salarie_agr,
                MAX(COALESCE(sa.agr_cl_controleur, '')) AS salarie_agr_cl,
                MAX(IF(sa.id IS NULL, CONCAT('Salarié inconnu (', COALESCE(cc.agr_controleur, '?'), ')'), COALESCE(sa.nom, 'Salarié inconnu'))) AS salarie_nom,
                MAX(COALESCE(sa.prenom, '')) AS salarie_prenom,
                YEAR(ctrl.date_ctrl) AS annee,
                MONTH(ctrl.date_ctrl) AS mois,
                COUNT(DISTINCT ctrl.idcontrole) AS nb_controles,
                COUNT(DISTINCT IF(f.type_facture IN ('F','A','D'), ctrl.idcontrole, NULL)) AS nb_controles_factures,
                COUNT(DISTINCT IF(ctrl.type_ctrl IN ('VTP','VLCTP','VLVT','VLVP'), ctrl.idcontrole, NULL)) AS nb_vtp,
                COUNT(DISTINCT IF(ctrl.type_ctrl IN ('VTP','VLCTP','VLVT','VLVP') AND f.type_facture IN ('F','A','D'), ctrl.idcontrole, NULL)) AS nb_vtp_factures,
                COUNT(DISTINCT IF(ctrl.type_ctrl IN ('VTP','VLCTP','VLVT','VLVP') AND COALESCE(cc.has_pro_client, 0) = 0, ctrl.idcontrole, NULL)) AS nb_vtp_particuliers,
                COUNT(DISTINCT IF(ctrl.type_ctrl IN ('VTP','VLCTP','VLVT','VLVP') AND COALESCE(cc.has_pro_client, 0) = 1, ctrl.idcontrole, NULL)) AS nb_vtp_professionnels,
                COUNT(DISTINCT IF(ctrl.type_ctrl IN ('CLVTP','CLCTP'), ctrl.idcontrole, NULL)) AS nb_clvtp,
                COUNT(DISTINCT IF(ctrl.type_ctrl IN ('CLVTP','CLCTP') AND f.type_facture IN ('F','A','D'), ctrl.idcontrole, NULL)) AS nb_clvtp_factures,
                COUNT(DISTINCT IF(ctrl.type_ctrl IN ('CLVTP','CLCTP') AND COALESCE(cc.has_pro_client, 0) = 0, ctrl.idcontrole, NULL)) AS nb_clvtp_particuliers,
                COUNT(DISTINCT IF(ctrl.type_ctrl IN ('CLVTP','CLCTP') AND COALESCE(cc.has_pro_client, 0) = 1, ctrl.idcontrole, NULL)) AS nb_clvtp_professionnels,
                COUNT(DISTINCT IF(ctrl.type_ctrl IN ('CV','VLCV','VLCVC'), ctrl.idcontrole, NULL)) AS nb_cv,
                COUNT(DISTINCT IF(ctrl.type_ctrl IN ('CV','VLCV','VLCVC') AND f.type_facture IN ('F','A','D'), ctrl.idcontrole, NULL)) AS nb_cv_factures,
                COUNT(DISTINCT IF(ctrl.type_ctrl IN ('CV','VLCV','VLCVC') AND COALESCE(cc.has_pro_client, 0) = 0, ctrl.idcontrole, NULL)) AS nb_cv_particuliers,
                COUNT(DISTINCT IF(ctrl.type_ctrl IN ('CV','VLCV','VLCVC') AND COALESCE(cc.has_pro_client, 0) = 1, ctrl.idcontrole, NULL)) AS nb_cv_professionnels,
                COUNT(DISTINCT IF(ctrl.type_ctrl IN ('CLCV'), ctrl.idcontrole, NULL)) AS nb_clcv,
                COUNT(DISTINCT IF(ctrl.type_ctrl IN ('CLCV') AND f.type_facture IN ('F','A','D'), ctrl.idcontrole, NULL)) AS nb_clcv_factures,
                COUNT(DISTINCT IF(ctrl.type_ctrl IN ('CLCV') AND COALESCE(cc.has_pro_client, 0) = 0, ctrl.idcontrole, NULL)) AS nb_clcv_particuliers,
                COUNT(DISTINCT IF(ctrl.type_ctrl IN ('CLCV') AND COALESCE(cc.has_pro_client, 0) = 1, ctrl.idcontrole, NULL)) AS nb_clcv_professionnels,
                COUNT(DISTINCT IF(ctrl.type_ctrl IN ('VTC','VLCTC'), ctrl.idcontrole, NULL)) AS nb_vtc,
                COUNT(DISTINCT IF(ctrl.type_ctrl IN ('VTC','VLCTC') AND f.type_facture IN ('F','A','D'), ctrl.idcontrole, NULL)) AS nb_vtc_factures,
                COUNT(DISTINCT IF(ctrl.type_ctrl IN ('VTC','VLCTC') AND COALESCE(cc.has_pro_client, 0) = 0, ctrl.idcontrole, NULL)) AS nb_vtc_particuliers,
                COUNT(DISTINCT IF(ctrl.type_ctrl IN ('VTC','VLCTC') AND COALESCE(cc.has_pro_client, 0) = 1, ctrl.idcontrole, NULL)) AS nb_vtc_professionnels,
                COUNT(DISTINCT IF(ctrl.type_ctrl IN ('VOL','VP','VT'), ctrl.idcontrole, NULL)) AS nb_vol,
                COUNT(DISTINCT IF(ctrl.type_ctrl IN ('VOL','VP','VT') AND f.type_facture IN ('F','A','D'), ctrl.idcontrole, NULL)) AS nb_vol_factures,
                COUNT(DISTINCT IF(ctrl.type_ctrl IN ('VOL','VP','VT') AND COALESCE(cc.has_pro_client, 0) = 0, ctrl.idcontrole, NULL)) AS nb_vol_particuliers,
                COUNT(DISTINCT IF(ctrl.type_ctrl IN ('VOL','VP','VT') AND COALESCE(cc.has_pro_client, 0) = 1, ctrl.idcontrole, NULL)) AS nb_vol_professionnels,
                COUNT(DISTINCT IF(ctrl.type_ctrl IN ('CLVP','CLVT'), ctrl.idcontrole, NULL)) AS nb_clvol,
                COUNT(DISTINCT IF(ctrl.type_ctrl IN ('CLVP','CLVT') AND f.type_facture IN ('F','A','D'), ctrl.idcontrole, NULL)) AS nb_clvol_factures,
                COUNT(DISTINCT IF(ctrl.type_ctrl IN ('CLVP','CLVT') AND COALESCE(cc.has_pro_client, 0) = 0, ctrl.idcontrole, NULL)) AS nb_clvol_particuliers,
                COUNT(DISTINCT IF(ctrl.type_ctrl IN ('CLVP','CLVT') AND COALESCE(cc.has_pro_client, 0) = 1, ctrl.idcontrole, NULL)) AS nb_clvol_professionnels,
                COUNT(DISTINCT IF(ctrl.type_ctrl IN ('VTP','VLCTP','VLVT','VLVP','CV','VLCV','VLCVC','VTC','VLCTC','VOL','VP','VT'), ctrl.idcontrole, NULL)) AS nb_auto,
                COUNT(DISTINCT IF(ctrl.type_ctrl IN ('VTP','VLCTP','VLVT','VLVP','CV','VLCV','VLCVC','VTC','VLCTC','VOL','VP','VT') AND f.type_facture IN ('F','A','D'), ctrl.idcontrole, NULL)) AS nb_auto_factures,
                COUNT(DISTINCT IF(ctrl.type_ctrl LIKE 'CL%', ctrl.idcontrole, NULL)) AS nb_moto,
                COUNT(DISTINCT IF(ctrl.type_ctrl LIKE 'CL%' AND f.type_facture IN ('F','A','D'), ctrl.idcontrole, NULL)) AS nb_moto_factures,
                SUM(IF(f.type_facture IN ('F','A','D'), {$allocatedAmountExpr}, 0)) AS total_presta_ht,
                SUM(IF(f.type_facture IN ('F','A','D') AND COALESCE(cc.has_pro_client, 0) = 0, {$allocatedAmountExpr}, 0)) AS total_presta_ht_particuliers,
                SUM(IF(f.type_facture IN ('F','A','D') AND COALESCE(cc.has_pro_client, 0) = 1, {$allocatedAmountExpr}, 0)) AS total_presta_ht_professionnels,
                SUM(IF(ctrl.type_ctrl IN ('VTP','VLCTP','VLVT','VLVP') AND f.type_facture IN ('F','A','D'), {$allocatedAmountExpr}, 0)) AS total_ht_vtp,
                SUM(IF(ctrl.type_ctrl IN ('VTP','VLCTP','VLVT','VLVP') AND f.type_facture IN ('F','A','D') AND COALESCE(cc.has_pro_client, 0) = 0, {$allocatedAmountExpr}, 0)) AS total_ht_vtp_particuliers,
                SUM(IF(ctrl.type_ctrl IN ('VTP','VLCTP','VLVT','VLVP') AND f.type_facture IN ('F','A','D') AND COALESCE(cc.has_pro_client, 0) = 1, {$allocatedAmountExpr}, 0)) AS total_ht_vtp_professionnels,
                SUM(IF(ctrl.type_ctrl IN ('CLVTP','CLCTP') AND f.type_facture IN ('F','A','D'), {$allocatedAmountExpr}, 0)) AS total_ht_clvtp,
                SUM(IF(ctrl.type_ctrl IN ('CLVTP','CLCTP') AND f.type_facture IN ('F','A','D') AND COALESCE(cc.has_pro_client, 0) = 0, {$allocatedAmountExpr}, 0)) AS total_ht_clvtp_particuliers,
                SUM(IF(ctrl.type_ctrl IN ('CLVTP','CLCTP') AND f.type_facture IN ('F','A','D') AND COALESCE(cc.has_pro_client, 0) = 1, {$allocatedAmountExpr}, 0)) AS total_ht_clvtp_professionnels,
                SUM(IF(ctrl.type_ctrl IN ('CV','VLCV','VLCVC') AND f.type_facture IN ('F','A','D'), {$allocatedAmountExpr}, 0)) AS total_ht_cv,
                SUM(IF(ctrl.type_ctrl IN ('CV','VLCV','VLCVC') AND f.type_facture IN ('F','A','D') AND COALESCE(cc.has_pro_client, 0) = 0, {$allocatedAmountExpr}, 0)) AS total_ht_cv_particuliers,
                SUM(IF(ctrl.type_ctrl IN ('CV','VLCV','VLCVC') AND f.type_facture IN ('F','A','D') AND COALESCE(cc.has_pro_client, 0) = 1, {$allocatedAmountExpr}, 0)) AS total_ht_cv_professionnels,
                SUM(IF(ctrl.type_ctrl IN ('CLCV') AND f.type_facture IN ('F','A','D'), {$allocatedAmountExpr}, 0)) AS total_ht_clcv,
                SUM(IF(ctrl.type_ctrl IN ('CLCV') AND f.type_facture IN ('F','A','D') AND COALESCE(cc.has_pro_client, 0) = 0, {$allocatedAmountExpr}, 0)) AS total_ht_clcv_particuliers,
                SUM(IF(ctrl.type_ctrl IN ('CLCV') AND f.type_facture IN ('F','A','D') AND COALESCE(cc.has_pro_client, 0) = 1, {$allocatedAmountExpr}, 0)) AS total_ht_clcv_professionnels,
                SUM(IF(ctrl.type_ctrl IN ('VTC','VLCTC') AND f.type_facture IN ('F','A','D'), {$allocatedAmountExpr}, 0)) AS total_ht_vtc,
                SUM(IF(ctrl.type_ctrl IN ('VTC','VLCTC') AND f.type_facture IN ('F','A','D') AND COALESCE(cc.has_pro_client, 0) = 0, {$allocatedAmountExpr}, 0)) AS total_ht_vtc_particuliers,
                SUM(IF(ctrl.type_ctrl IN ('VTC','VLCTC') AND f.type_facture IN ('F','A','D') AND COALESCE(cc.has_pro_client, 0) = 1, {$allocatedAmountExpr}, 0)) AS total_ht_vtc_professionnels,
                SUM(IF(ctrl.type_ctrl IN ('VOL','VP','VT') AND f.type_facture IN ('F','A','D'), {$allocatedAmountExpr}, 0)) AS total_ht_vol,
                SUM(IF(ctrl.type_ctrl IN ('VOL','VP','VT') AND f.type_facture IN ('F','A','D') AND COALESCE(cc.has_pro_client, 0) = 0, {$allocatedAmountExpr}, 0)) AS total_ht_vol_particuliers,
                SUM(IF(ctrl.type_ctrl IN ('VOL','VP','VT') AND f.type_facture IN ('F','A','D') AND COALESCE(cc.has_pro_client, 0) = 1, {$allocatedAmountExpr}, 0)) AS total_ht_vol_professionnels,
                SUM(IF(ctrl.type_ctrl IN ('CLVP','CLVT') AND f.type_facture IN ('F','A','D'), {$allocatedAmountExpr}, 0)) AS total_ht_clvol,
                SUM(IF(ctrl.type_ctrl IN ('CLVP','CLVT') AND f.type_facture IN ('F','A','D') AND COALESCE(cc.has_pro_client, 0) = 0, {$allocatedAmountExpr}, 0)) AS total_ht_clvol_particuliers,
                SUM(IF(ctrl.type_ctrl IN ('CLVP','CLVT') AND f.type_facture IN ('F','A','D') AND COALESCE(cc.has_pro_client, 0) = 1, {$allocatedAmountExpr}, 0)) AS total_ht_clvol_professionnels,
                SUM(ctrl.temps_ctrl) AS temps_total,
                SUM(IF(ctrl.type_ctrl IN ('VTP','VLCTP','VLVT','VLVP','CV','VLCV','VLCVC','VTC','VLCTC','VOL','VP','VT'), ctrl.temps_ctrl, 0)) AS temps_total_auto,
                SUM(IF(ctrl.type_ctrl LIKE 'CL%', ctrl.temps_ctrl, 0)) AS temps_total_moto,
                SUM(IF(ctrl.type_ctrl IN ('VTP','VLCTP','VLVT','VLVP'), ctrl.temps_ctrl, 0)) AS temps_total_vtp,
                SUM(IF(ctrl.type_ctrl IN ('CLVTP','CLCTP'), ctrl.temps_ctrl, 0)) AS temps_total_clvtp,
                SUM(IF(ctrl.type_ctrl IN ('CV','VLCV','VLCVC'), ctrl.temps_ctrl, 0)) AS temps_total_cv,
                SUM(IF(ctrl.type_ctrl IN ('CLCV'), ctrl.temps_ctrl, 0)) AS temps_total_clcv,
                SUM(IF(ctrl.type_ctrl IN ('VTC','VLCTC'), ctrl.temps_ctrl, 0)) AS temps_total_vtc,
                SUM(IF(ctrl.type_ctrl IN ('VOL','VP','VT'), ctrl.temps_ctrl, 0)) AS temps_total_vol,
                SUM(IF(ctrl.type_ctrl IN ('CLVP','CLVT'), ctrl.temps_ctrl, 0)) AS temps_total_clvol,
                COUNT(DISTINCT IF(ctrl.res_ctrl IN ('S','R','SP'), ctrl.idcontrole, NULL)) AS taux_refus,
                COUNT(DISTINCT IF(ctrl.type_ctrl IN ('VTP','VLCTP','VLVT','VLVP','CV','VLCV','VLCVC','VTC','VLCTC','VOL', 'VP', 'VT') AND ctrl.res_ctrl IN ('S','R','SP'), ctrl.idcontrole, NULL)) AS refus_auto,
                COUNT(DISTINCT IF(ctrl.type_ctrl LIKE 'CL%' AND ctrl.res_ctrl IN ('S','R','SP'), ctrl.idcontrole, NULL)) AS refus_moto,
                COUNT(DISTINCT IF(ctrl.type_ctrl IN ('VTP','VLCTP','VLVT','VLVP') AND ctrl.res_ctrl IN ('S','R','SP'), ctrl.idcontrole, NULL)) AS refus_vtp,
                COUNT(DISTINCT IF(ctrl.type_ctrl IN ('CLVTP','CLCTP') AND ctrl.res_ctrl IN ('S','R','SP'), ctrl.idcontrole, NULL)) AS refus_clvtp,
                COUNT(DISTINCT IF(ctrl.type_ctrl IN ('CV','VLCV','VLCVC') AND ctrl.res_ctrl IN ('S','R','SP'), ctrl.idcontrole, NULL)) AS refus_cv,
                COUNT(DISTINCT IF(ctrl.type_ctrl IN ('CLCV') AND ctrl.res_ctrl IN ('S','R','SP'), ctrl.idcontrole, NULL)) AS refus_clcv,
                COUNT(DISTINCT IF(ctrl.type_ctrl IN ('VTC','VLCTC') AND ctrl.res_ctrl IN ('S','R','SP'), ctrl.idcontrole, NULL)) AS refus_vtc,
                COUNT(DISTINCT IF(ctrl.type_ctrl IN ('VOL','VP','VT') AND ctrl.res_ctrl IN ('S','R','SP'), ctrl.idcontrole, NULL)) AS refus_vol,
                COUNT(DISTINCT IF(ctrl.type_ctrl IN ('CLVP','CLVT') AND ctrl.res_ctrl IN ('S','R','SP'), ctrl.idcontrole, NULL)) AS refus_clvol,
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
                SELECT id, nom, prenom, agr_controleur, agr_cl_controleur, agr_key
                FROM (
                    SELECT
                        s.id,
                        s.nom,
                        s.prenom,
                        s.agr_controleur,
                        s.agr_cl_controleur,
                        s.agr_key,
                        ROW_NUMBER() OVER (PARTITION BY s.agr_key ORDER BY s.is_primary DESC, s.id ASC) AS rn
                    FROM (
                        SELECT
                            id,
                            nom,
                            prenom,
                            agr_controleur,
                            agr_cl_controleur,
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
                            agr_cl_controleur,
                            agr_cl_controleur AS agr_key,
                            0 AS is_primary
                        FROM salarie
                        WHERE agr_cl_controleur IS NOT NULL AND TRIM(agr_cl_controleur) <> ''
                    ) s
                ) ranked
                WHERE ranked.rn = 1
            ) sa ON sa.agr_key = cc.agr_controleur
            LEFT JOIN centre ce ON {$centreJoinCondition}
            LEFT JOIN societe so ON so.id = ce.societe_id
            LEFT JOIN (
                {$distinctControleFactureSql}
            ) cf ON cf.idcontrole = ctrl.idcontrole
            LEFT JOIN factures f ON f.idfacture = cf.idfacture
            LEFT JOIN (
                {$factureControlCountSql}
            ) t ON t.idfacture = f.idfacture
            LEFT JOIN (
                {$controlRevenueReferenceSql}
            ) cpr ON cpr.idcontrole = ctrl.idcontrole
            LEFT JOIN (
                {$avoirReferenceTotalSql}
            ) art ON art.idfacture = f.idfacture
            GROUP BY
                COALESCE(sa.id, 0),
                COALESCE(sa.agr_controleur, cc.agr_controleur, 'Agrément inconnu'),
                IF(ce.agr_centre IS NULL, CONCAT('Centre inconnu (', COALESCE(cc.agr_centre, '?'), ')'), ce.agr_centre),
                YEAR(ctrl.date_ctrl),
                MONTH(ctrl.date_ctrl)
            ON DUPLICATE KEY UPDATE
                nb_controles=VALUES(nb_controles),
                nb_controles_factures=VALUES(nb_controles_factures),
                nb_vtp=VALUES(nb_vtp),
                nb_vtp_factures=VALUES(nb_vtp_factures),
                nb_vtp_particuliers=VALUES(nb_vtp_particuliers),
                nb_vtp_professionnels=VALUES(nb_vtp_professionnels),
                nb_clvtp=VALUES(nb_clvtp),
                nb_clvtp_factures=VALUES(nb_clvtp_factures),
                nb_clvtp_particuliers=VALUES(nb_clvtp_particuliers),
                nb_clvtp_professionnels=VALUES(nb_clvtp_professionnels),
                nb_cv=VALUES(nb_cv),
                nb_cv_factures=VALUES(nb_cv_factures),
                nb_cv_particuliers=VALUES(nb_cv_particuliers),
                nb_cv_professionnels=VALUES(nb_cv_professionnels),
                nb_clcv=VALUES(nb_clcv),
                nb_clcv_factures=VALUES(nb_clcv_factures),
                nb_clcv_particuliers=VALUES(nb_clcv_particuliers),
                nb_clcv_professionnels=VALUES(nb_clcv_professionnels),
                nb_vtc=VALUES(nb_vtc),
                nb_vtc_factures=VALUES(nb_vtc_factures),
                nb_vtc_particuliers=VALUES(nb_vtc_particuliers),
                nb_vtc_professionnels=VALUES(nb_vtc_professionnels),
                nb_vol=VALUES(nb_vol),
                nb_vol_factures=VALUES(nb_vol_factures),
                nb_vol_particuliers=VALUES(nb_vol_particuliers),
                nb_vol_professionnels=VALUES(nb_vol_professionnels),
                nb_clvol=VALUES(nb_clvol),
                nb_clvol_factures=VALUES(nb_clvol_factures),
                nb_clvol_particuliers=VALUES(nb_clvol_particuliers),
                nb_clvol_professionnels=VALUES(nb_clvol_professionnels),
                nb_auto=VALUES(nb_auto),
                nb_auto_factures=VALUES(nb_auto_factures),
                nb_moto=VALUES(nb_moto),
                nb_moto_factures=VALUES(nb_moto_factures),
                total_presta_ht=VALUES(total_presta_ht),
                total_presta_ht_particuliers=VALUES(total_presta_ht_particuliers),
                total_presta_ht_professionnels=VALUES(total_presta_ht_professionnels),
                total_ht_vtp=VALUES(total_ht_vtp),
                total_ht_vtp_particuliers=VALUES(total_ht_vtp_particuliers),
                total_ht_vtp_professionnels=VALUES(total_ht_vtp_professionnels),
                total_ht_clvtp=VALUES(total_ht_clvtp),
                total_ht_clvtp_particuliers=VALUES(total_ht_clvtp_particuliers),
                total_ht_clvtp_professionnels=VALUES(total_ht_clvtp_professionnels),
                total_ht_cv=VALUES(total_ht_cv),
                total_ht_cv_particuliers=VALUES(total_ht_cv_particuliers),
                total_ht_cv_professionnels=VALUES(total_ht_cv_professionnels),
                total_ht_clcv=VALUES(total_ht_clcv),
                total_ht_clcv_particuliers=VALUES(total_ht_clcv_particuliers),
                total_ht_clcv_professionnels=VALUES(total_ht_clcv_professionnels),
                total_ht_vtc=VALUES(total_ht_vtc),
                total_ht_vtc_particuliers=VALUES(total_ht_vtc_particuliers),
                total_ht_vtc_professionnels=VALUES(total_ht_vtc_professionnels),
                total_ht_vol=VALUES(total_ht_vol),
                total_ht_vol_particuliers=VALUES(total_ht_vol_particuliers),
                total_ht_vol_professionnels=VALUES(total_ht_vol_professionnels),
                total_ht_clvol=VALUES(total_ht_clvol),
                total_ht_clvol_particuliers=VALUES(total_ht_clvol_particuliers),
                total_ht_clvol_professionnels=VALUES(total_ht_clvol_professionnels),
                temps_total=VALUES(temps_total),
                temps_total_auto=VALUES(temps_total_auto),
                temps_total_moto=VALUES(temps_total_moto),
                temps_total_vtp=VALUES(temps_total_vtp),
                temps_total_clvtp=VALUES(temps_total_clvtp),
                temps_total_cv=VALUES(temps_total_cv),
                temps_total_clcv=VALUES(temps_total_clcv),
                temps_total_vtc=VALUES(temps_total_vtc),
                temps_total_vol=VALUES(temps_total_vol),
                temps_total_clvol=VALUES(temps_total_clvol),
                taux_refus=VALUES(taux_refus),
                refus_auto=VALUES(refus_auto),
                refus_moto=VALUES(refus_moto),
                refus_vtp=VALUES(refus_vtp),
                refus_clvtp=VALUES(refus_clvtp),
                refus_cv=VALUES(refus_cv),
                refus_clcv=VALUES(refus_clcv),
                refus_vtc=VALUES(refus_vtc),
                refus_vol=VALUES(refus_vol),
                refus_clvol=VALUES(refus_clvol),
                nb_particuliers=VALUES(nb_particuliers),
                nb_professionnels=VALUES(nb_professionnels),
                nb_particuliers_auto=VALUES(nb_particuliers_auto),
                nb_particuliers_moto=VALUES(nb_particuliers_moto),
                nb_professionnels_auto=VALUES(nb_professionnels_auto),
                nb_professionnels_moto=VALUES(nb_professionnels_moto),
                agr_centre_cl=VALUES(agr_centre_cl),
                salarie_agr_cl=VALUES(salarie_agr_cl)
        ";

        $this->connection->executeStatement($sql);
    }

    /**
     * @throws Exception
     */
    private function hasSecondaryCentreAgreementColumn(): bool
    {
        return (int) $this->connection->fetchOne(
            "
                SELECT COUNT(*)
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'centre'
                  AND COLUMN_NAME = 'agr_cl_centre'
            "
        ) > 0;
    }

    /**
     * Ensures the unique key includes center and controller fallback fields.
     *
     * @return void
     *
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
     * Ensures all split revenue and split volume columns are available.
     *
     * @return void
     *
     * @throws Exception
     */
    private function ensureRevenueSplitColumns(): void
    {
        $this->ensureColumn('nb_vtp_particuliers', 'INT NOT NULL DEFAULT 0');
        $this->ensureColumn('nb_vtp_professionnels', 'INT NOT NULL DEFAULT 0');
        $this->ensureColumn('nb_clvtp_particuliers', 'INT NOT NULL DEFAULT 0');
        $this->ensureColumn('nb_clvtp_professionnels', 'INT NOT NULL DEFAULT 0');
        $this->ensureColumn('nb_cv_particuliers', 'INT NOT NULL DEFAULT 0');
        $this->ensureColumn('nb_cv_professionnels', 'INT NOT NULL DEFAULT 0');
        $this->ensureColumn('nb_clcv_particuliers', 'INT NOT NULL DEFAULT 0');
        $this->ensureColumn('nb_clcv_professionnels', 'INT NOT NULL DEFAULT 0');
        $this->ensureColumn('nb_vtc_particuliers', 'INT NOT NULL DEFAULT 0');
        $this->ensureColumn('nb_vtc_professionnels', 'INT NOT NULL DEFAULT 0');
        $this->ensureColumn('nb_vol_particuliers', 'INT NOT NULL DEFAULT 0');
        $this->ensureColumn('nb_vol_professionnels', 'INT NOT NULL DEFAULT 0');
        $this->ensureColumn('nb_clvol_particuliers', 'INT NOT NULL DEFAULT 0');
        $this->ensureColumn('nb_clvol_professionnels', 'INT NOT NULL DEFAULT 0');

        $this->ensureColumn('total_presta_ht_particuliers', 'DECIMAL(12,2) NOT NULL DEFAULT 0');
        $this->ensureColumn('total_presta_ht_professionnels', 'DECIMAL(12,2) NOT NULL DEFAULT 0');
        $this->ensureColumn('total_ht_vtp_particuliers', 'DECIMAL(12,2) NOT NULL DEFAULT 0');
        $this->ensureColumn('total_ht_vtp_professionnels', 'DECIMAL(12,2) NOT NULL DEFAULT 0');
        $this->ensureColumn('total_ht_clvtp_particuliers', 'DECIMAL(12,2) NOT NULL DEFAULT 0');
        $this->ensureColumn('total_ht_clvtp_professionnels', 'DECIMAL(12,2) NOT NULL DEFAULT 0');
        $this->ensureColumn('total_ht_cv_particuliers', 'DECIMAL(12,2) NOT NULL DEFAULT 0');
        $this->ensureColumn('total_ht_cv_professionnels', 'DECIMAL(12,2) NOT NULL DEFAULT 0');
        $this->ensureColumn('total_ht_clcv_particuliers', 'DECIMAL(12,2) NOT NULL DEFAULT 0');
        $this->ensureColumn('total_ht_clcv_professionnels', 'DECIMAL(12,2) NOT NULL DEFAULT 0');
        $this->ensureColumn('total_ht_vtc_particuliers', 'DECIMAL(12,2) NOT NULL DEFAULT 0');
        $this->ensureColumn('total_ht_vtc_professionnels', 'DECIMAL(12,2) NOT NULL DEFAULT 0');
        $this->ensureColumn('total_ht_vol_particuliers', 'DECIMAL(12,2) NOT NULL DEFAULT 0');
        $this->ensureColumn('total_ht_vol_professionnels', 'DECIMAL(12,2) NOT NULL DEFAULT 0');
        $this->ensureColumn('total_ht_clvol_particuliers', 'DECIMAL(12,2) NOT NULL DEFAULT 0');
        $this->ensureColumn('total_ht_clvol_professionnels', 'DECIMAL(12,2) NOT NULL DEFAULT 0');
    }

    /**
     * Ensures detailed time and refusal metrics are available per synthesized subtype.
     *
     * @return void
     *
     * @throws Exception
     */
    private function ensureDetailedControllerMetricsColumns(): void
    {
        $this->ensureColumn('temps_total_vtp', 'INT NOT NULL DEFAULT 0');
        $this->ensureColumn('temps_total_clvtp', 'INT NOT NULL DEFAULT 0');
        $this->ensureColumn('temps_total_cv', 'INT NOT NULL DEFAULT 0');
        $this->ensureColumn('temps_total_clcv', 'INT NOT NULL DEFAULT 0');
        $this->ensureColumn('temps_total_vtc', 'INT NOT NULL DEFAULT 0');
        $this->ensureColumn('temps_total_vol', 'INT NOT NULL DEFAULT 0');
        $this->ensureColumn('temps_total_clvol', 'INT NOT NULL DEFAULT 0');

        $this->ensureColumn('refus_vtp', 'INT NOT NULL DEFAULT 0');
        $this->ensureColumn('refus_clvtp', 'INT NOT NULL DEFAULT 0');
        $this->ensureColumn('refus_cv', 'INT NOT NULL DEFAULT 0');
        $this->ensureColumn('refus_clcv', 'INT NOT NULL DEFAULT 0');
        $this->ensureColumn('refus_vtc', 'INT NOT NULL DEFAULT 0');
        $this->ensureColumn('refus_vol', 'INT NOT NULL DEFAULT 0');
        $this->ensureColumn('refus_clvol', 'INT NOT NULL DEFAULT 0');
    }

    /**
     * Ensures billed control count columns are available for controller price metrics.
     *
     * @return void
     *
     * @throws Exception
     */
    private function ensureBilledControlCountColumns(): void
    {
        $this->ensureColumn('nb_controles_factures', 'INT NOT NULL DEFAULT 0');
        $this->ensureColumn('nb_vtp_factures', 'INT NOT NULL DEFAULT 0');
        $this->ensureColumn('nb_clvtp_factures', 'INT NOT NULL DEFAULT 0');
        $this->ensureColumn('nb_cv_factures', 'INT NOT NULL DEFAULT 0');
        $this->ensureColumn('nb_clcv_factures', 'INT NOT NULL DEFAULT 0');
        $this->ensureColumn('nb_vtc_factures', 'INT NOT NULL DEFAULT 0');
        $this->ensureColumn('nb_vol_factures', 'INT NOT NULL DEFAULT 0');
        $this->ensureColumn('nb_clvol_factures', 'INT NOT NULL DEFAULT 0');
        $this->ensureColumn('nb_auto_factures', 'INT NOT NULL DEFAULT 0');
        $this->ensureColumn('nb_moto_factures', 'INT NOT NULL DEFAULT 0');
    }

    /**
     * Adds a missing column to `synthese_controles` and marks full refresh as required.
     *
     * @param string $column Column name to ensure.
     * @param string $definition SQL type and constraints definition.
     *
     * @return void
     *
     * @throws Exception
     */
    private function ensureColumn(string $column, string $definition): void
    {
        $exists = (int)$this->connection->fetchOne(
            "
                SELECT COUNT(*)
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'synthese_controles'
                  AND COLUMN_NAME = :column
            ",
            ['column' => $column]
        ) > 0;

        if ($exists) {
            return;
        }

        $this->connection->executeStatement(sprintf(
            'ALTER TABLE synthese_controles ADD COLUMN %s %s',
            $column,
            $definition
        ));
        $this->forceFullRefresh = true;
    }

    /**
     * Updates the meta table timestamp for this command.
     *
     * @return void
     *
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
