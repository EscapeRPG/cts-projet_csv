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
    name: 'app:synthese:reglements',
    description: 'Met à jour la table synthese_reglements.'
)]
/**
 * Rebuilds monthly payment-mode aggregates into `synthese_reglements`.
 */
class PopulateModesReglementCommand extends Command
{
    private const string META_KEY = 'synthese_reglements';
    private bool $forceFullRefresh = false;
    private ?string $forcePeriod = null;

    /**
     * @param Connection $connection DBAL connection used for DDL/DML operations.
     */
    public function __construct(
        private readonly Connection $connection
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'full',
                null,
                InputOption::VALUE_NONE,
                'Force le recalcul de toutes les périodes disponibles.'
            )
            ->addOption(
                'period',
                null,
                InputOption::VALUE_REQUIRED,
                'Force le recalcul d’une période unique (format YYYY-MM), sans tenir compte de last_run_at.'
            );
    }

    /**
     * Runs the incremental refresh for payment-mode summary data.
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
        $this->forceFullRefresh = (bool) $input->getOption('full');
        $this->forcePeriod = $input->getOption('period');

        $io->title('[synthese-reglements] Démarrage de la mise à jour de synthese_reglements.');

        try {
            $startedAt = microtime(true);

            $io->writeln('<comment>Vérification de la table cible...</comment>');
            $stepStartedAt = microtime(true);
            $this->ensureTable();
            $this->ensureSourceIndexes();
            $io->writeln(sprintf(
                '<info>Table cible prête.</info> <comment>(%.3f s)</comment>',
                microtime(true) - $stepStartedAt
            ));

            $io->writeln('<comment>Ouverture de la transaction...</comment>');
            $this->connection->beginTransaction();
            $this->connection->executeStatement('SET SESSION innodb_lock_wait_timeout = 300');

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
            $periods = $this->fetchPeriodsToRefresh($lastRunAt ?: null);
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

            $io->writeln('<comment>Suppression des agrégats existants sur les périodes impactées...</comment>');
            $stepStartedAt = microtime(true);
            $this->connection->executeStatement("
                DELETE sr
                FROM synthese_reglements sr
                INNER JOIN tmp_synthese_reglements_periods p
                    ON p.annee = sr.annee AND p.mois = sr.mois
            ");
            $io->writeln(sprintf(
                '<info>Suppression des agrégats existants terminée.</info> <comment>(%.3f s)</comment>',
                microtime(true) - $stepStartedAt
            ));

            $io->writeln('<comment>Recalcul et insertion des agrégats...</comment>');
            $stepStartedAt = microtime(true);
            [$dateFrom, $dateTo] = $this->buildDateBoundsForPeriods($periods);
            $this->insertAggregates($dateFrom, $dateTo);
            $this->insertUnlinkedInvoiceAggregates($dateFrom, $dateTo);
            $this->insertMissingInvoiceReferenceAggregates($dateFrom, $dateTo);
            $io->writeln(sprintf(
                '<info>Recalcul terminé.</info> <comment>(%.3f s)</comment>',
                microtime(true) - $stepStartedAt
            ));

            $this->touchMeta();
            $this->connection->commit();

            $io->success(sprintf(
                'Mise à jour terminée avec succès (%.3f s).',
                microtime(true) - $startedAt
            ));
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }

            $io->error('<error>Échec : ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }

    /**
     * Inserts payment-mode aggregates for periods currently present in the temp table.
     *
     * @param string $dateFrom Inclusive lower date bound.
     * @param string $dateTo Exclusive upper date bound.
     *
     * @return void
     * @throws Exception
     */
    private function insertAggregates(string $dateFrom, string $dateTo): void
    {
        $centreJoinCondition = $this->hasSecondaryCentreAgreementColumn()
            ? '(ce.agr_centre = cc.agr_centre OR ce.agr_cl_centre = cc.agr_centre)'
            : 'ce.agr_centre = cc.agr_centre';

        $reglementsLatestSql = "
            SELECT *
            FROM (
                SELECT
                    r.*,
                    ROW_NUMBER() OVER (PARTITION BY r.idreglement ORDER BY r.date_export DESC, r.id DESC) AS rn
                FROM reglements r
            ) ranked_reglements
            WHERE ranked_reglements.rn = 1
        ";

        $facturesLatestSql = "
            SELECT *
            FROM (
                SELECT
                    f.*,
                    CASE
                        WHEN f.type_facture = 'Facture' THEN 'F'
                        WHEN f.type_facture = 'Avoir' THEN 'A'
                        ELSE f.type_facture
                    END AS normalized_type_facture,
                    ROW_NUMBER() OVER (PARTITION BY f.idfacture ORDER BY f.date_export DESC, f.id DESC) AS rn
                FROM factures f
            ) ranked_factures
            WHERE ranked_factures.rn = 1
        ";

        $controlesLatestSql = "
            SELECT *
            FROM (
                SELECT
                    c.*,
                    ROW_NUMBER() OVER (PARTITION BY c.idcontrole ORDER BY c.date_export DESC, c.id DESC) AS rn
                FROM controles c
            ) ranked_controles
            WHERE ranked_controles.rn = 1
        ";

        $controleFactureSql = "
            SELECT DISTINCT cf.idcontrole, cf.idfacture, cf.agr_controleur
            FROM controles_factures cf
            WHERE cf.idcontrole IS NOT NULL
              AND cf.idfacture IS NOT NULL
        ";

        $linkedPaymentSql = "
            SELECT
                base.*,
                COUNT(*) OVER (PARTITION BY base.idreglement) AS nb_factures_reglement,
                SUM(base.facture_weight) OVER (PARTITION BY base.idreglement) AS total_factures_reglement
            FROM (
                SELECT DISTINCT
                    r.idreglement,
                    r.date_reglt,
                    r.mode_reglt,
                    r.montant_reglt,
                    r.reseau_id,
                    fr.idfacture,
                    fr.agr_centre,
                    ABS(COALESCE(f.total_ttc, f.total_ht, f.montant_presta_ht, 0)) AS facture_weight
                FROM ({$reglementsLatestSql}) r
                INNER JOIN tmp_synthese_reglements_periods p
                    ON p.annee = YEAR(r.date_reglt) AND p.mois = MONTH(r.date_reglt)
                INNER JOIN factures_reglements fr
                    ON fr.idreglement = r.idreglement
                INNER JOIN ({$facturesLatestSql}) f
                    ON f.idfacture = fr.idfacture
                WHERE r.date_reglt >= :date_from
                  AND r.date_reglt < :date_to
                  AND f.normalized_type_facture IN ('F','A','D')
            ) base
        ";

        $factureControlCountsSql = "
            SELECT idfacture, COUNT(DISTINCT idcontrole) AS nb_ctrl_facture
            FROM ({$controleFactureSql}) cf_count
            GROUP BY idfacture
        ";

        $salarieByAgreementSql = "
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
            ) ranked_salaries
            WHERE ranked_salaries.rn = 1
        ";

        $allocatedAmountExpr = "
            (
                COALESCE(lp.montant_reglt, 0)
                * CASE
                    WHEN lp.total_factures_reglement > 0
                        THEN lp.facture_weight / lp.total_factures_reglement
                    ELSE 1 / NULLIF(lp.nb_factures_reglement, 0)
                END
                / NULLIF(fcc.nb_ctrl_facture, 0)
            )
        ";
        $modeRegltExpr = $this->paymentModeFamilySql('lp');

        $sql = "
            INSERT INTO synthese_reglements (
                annee, mois, mode_reglt,
                societe_nom, agr_centre, agr_centre_cl, centre_ville, reseau_id, reseau_nom,
                salarie_id, salarie_agr, salarie_agr_cl, salarie_nom, salarie_prenom,
                nb_reglements, nb_factures, nb_controles,
                nb_auto, nb_moto, nb_vtp, nb_clvtp, nb_cv, nb_clcv, nb_vtc, nb_vol, nb_clvol,
                montant_regle, montant_regle_auto, montant_regle_moto,
                montant_regle_vtp, montant_regle_clvtp, montant_regle_cv, montant_regle_clcv,
                montant_regle_vtc, montant_regle_vol, montant_regle_clvol
            )
            SELECT
                YEAR(lp.date_reglt) AS annee,
                MONTH(lp.date_reglt) AS mois,
                {$modeRegltExpr} AS mode_reglt,
                MAX(COALESCE(so.nom, 'Société inconnue')) AS societe_nom,
                IF(ce.agr_centre IS NULL, CONCAT('Centre inconnu (', COALESCE(cc.agr_centre, lp.agr_centre, '?'), ')'), ce.agr_centre) AS agr_centre,
                MAX(COALESCE(ce.agr_cl_centre, '')) AS agr_centre_cl,
                MAX(COALESCE(ce.ville, '')) AS centre_ville,
                MAX(COALESCE(ctrl.reseau_id, lp.reseau_id, 0)) AS reseau_id,
                MAX(COALESCE(ce.reseau_nom, '')) AS reseau_nom,
                COALESCE(sa.id, 0) AS salarie_id,
                COALESCE(sa.agr_controleur, cf.agr_controleur, cc.agr_controleur, 'Agrément inconnu') AS salarie_agr,
                MAX(COALESCE(sa.agr_cl_controleur, '')) AS salarie_agr_cl,
                MAX(COALESCE(sa.nom, 'Contrôleur inconnu')) AS salarie_nom,
                MAX(COALESCE(sa.prenom, '')) AS salarie_prenom,
                COUNT(DISTINCT lp.idreglement) AS nb_reglements,
                COUNT(DISTINCT lp.idfacture) AS nb_factures,
                COUNT(DISTINCT ctrl.idcontrole) AS nb_controles,
                COUNT(DISTINCT IF(ctrl.type_ctrl NOT LIKE 'CL%', ctrl.idcontrole, NULL)) AS nb_auto,
                COUNT(DISTINCT IF(ctrl.type_ctrl LIKE 'CL%', ctrl.idcontrole, NULL)) AS nb_moto,
                COUNT(DISTINCT IF(ctrl.type_ctrl IN ('VTP','VLCTP','VLVT','VLVP'), ctrl.idcontrole, NULL)) AS nb_vtp,
                COUNT(DISTINCT IF(ctrl.type_ctrl IN ('CLVTP','CLCTP'), ctrl.idcontrole, NULL)) AS nb_clvtp,
                COUNT(DISTINCT IF(ctrl.type_ctrl IN ('CV','CVC','VLCV','VLCVC'), ctrl.idcontrole, NULL)) AS nb_cv,
                COUNT(DISTINCT IF(ctrl.type_ctrl IN ('CLCV'), ctrl.idcontrole, NULL)) AS nb_clcv,
                COUNT(DISTINCT IF(ctrl.type_ctrl IN ('VTC','VLCTC'), ctrl.idcontrole, NULL)) AS nb_vtc,
                COUNT(DISTINCT IF(ctrl.type_ctrl IN ('VOL','VP','VT'), ctrl.idcontrole, NULL)) AS nb_vol,
                COUNT(DISTINCT IF(ctrl.type_ctrl IN ('CLVP','CLVT'), ctrl.idcontrole, NULL)) AS nb_clvol,
                SUM({$allocatedAmountExpr}) AS montant_regle,
                SUM(IF(ctrl.type_ctrl NOT LIKE 'CL%', {$allocatedAmountExpr}, 0)) AS montant_regle_auto,
                SUM(IF(ctrl.type_ctrl LIKE 'CL%', {$allocatedAmountExpr}, 0)) AS montant_regle_moto,
                SUM(IF(ctrl.type_ctrl IN ('VTP','VLCTP','VLVT','VLVP'), {$allocatedAmountExpr}, 0)) AS montant_regle_vtp,
                SUM(IF(ctrl.type_ctrl IN ('CLVTP','CLCTP'), {$allocatedAmountExpr}, 0)) AS montant_regle_clvtp,
                SUM(IF(ctrl.type_ctrl IN ('CV','CVC','VLCV','VLCVC'), {$allocatedAmountExpr}, 0)) AS montant_regle_cv,
                SUM(IF(ctrl.type_ctrl IN ('CLCV'), {$allocatedAmountExpr}, 0)) AS montant_regle_clcv,
                SUM(IF(ctrl.type_ctrl IN ('VTC','VLCTC'), {$allocatedAmountExpr}, 0)) AS montant_regle_vtc,
                SUM(IF(ctrl.type_ctrl IN ('VOL','VP','VT'), {$allocatedAmountExpr}, 0)) AS montant_regle_vol,
                SUM(IF(ctrl.type_ctrl IN ('CLVP','CLVT'), {$allocatedAmountExpr}, 0)) AS montant_regle_clvol
            FROM ({$linkedPaymentSql}) lp
            INNER JOIN ({$controleFactureSql}) cf
                ON cf.idfacture = lp.idfacture
            INNER JOIN ({$factureControlCountsSql}) fcc
                ON fcc.idfacture = lp.idfacture
            INNER JOIN ({$controlesLatestSql}) ctrl
                ON ctrl.idcontrole = cf.idcontrole
            LEFT JOIN (
                SELECT idcontrole, MIN(agr_centre) AS agr_centre, MIN(agr_controleur) AS agr_controleur
                FROM clients_controles
                GROUP BY idcontrole
            ) cc ON cc.idcontrole = ctrl.idcontrole
            LEFT JOIN ({$salarieByAgreementSql}) sa
                ON sa.agr_key = COALESCE(NULLIF(cf.agr_controleur, ''), cc.agr_controleur)
            LEFT JOIN centre ce
                ON {$centreJoinCondition}
            LEFT JOIN societe so
                ON so.id = ce.societe_id
            WHERE ctrl.res_ctrl IN ('A','AP')
            GROUP BY
                YEAR(lp.date_reglt),
                MONTH(lp.date_reglt),
                {$modeRegltExpr},
                IF(ce.agr_centre IS NULL, CONCAT('Centre inconnu (', COALESCE(cc.agr_centre, lp.agr_centre, '?'), ')'), ce.agr_centre),
                COALESCE(sa.id, 0),
                COALESCE(sa.agr_controleur, cf.agr_controleur, cc.agr_controleur, 'Agrément inconnu')
        ";

        $this->connection->executeStatement($sql, [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ]);
    }

    /**
     * Inserts payment aggregates for invoices not linked to any control.
     *
     * Those rows keep the payment mode visible in the summary, but they cannot be ventilated by control type.
     *
     * @param string $dateFrom Inclusive lower date bound.
     * @param string $dateTo Exclusive upper date bound.
     *
     * @return void
     * @throws Exception
     */
    private function insertUnlinkedInvoiceAggregates(string $dateFrom, string $dateTo): void
    {
        $centreJoinCondition = $this->hasSecondaryCentreAgreementColumn()
            ? '(ce.agr_centre = up.agr_centre OR ce.agr_cl_centre = up.agr_centre)'
            : 'ce.agr_centre = up.agr_centre';

        $reglementsLatestSql = "
            SELECT *
            FROM (
                SELECT
                    r.*,
                    ROW_NUMBER() OVER (PARTITION BY r.idreglement ORDER BY r.date_export DESC, r.id DESC) AS rn
                FROM reglements r
            ) ranked_reglements
            WHERE ranked_reglements.rn = 1
        ";

        $facturesLatestSql = "
            SELECT *
            FROM (
                SELECT
                    f.*,
                    CASE
                        WHEN f.type_facture = 'Facture' THEN 'F'
                        WHEN f.type_facture = 'Avoir' THEN 'A'
                        ELSE f.type_facture
                    END AS normalized_type_facture,
                    ROW_NUMBER() OVER (PARTITION BY f.idfacture ORDER BY f.date_export DESC, f.id DESC) AS rn
                FROM factures f
            ) ranked_factures
            WHERE ranked_factures.rn = 1
        ";

        $unlinkedPaymentSql = "
            SELECT
                base.*,
                COUNT(*) OVER (PARTITION BY base.idreglement) AS nb_factures_reglement,
                SUM(base.facture_weight) OVER (PARTITION BY base.idreglement) AS total_factures_reglement
            FROM (
                SELECT DISTINCT
                    r.idreglement,
                    r.date_reglt,
                    r.mode_reglt,
                    r.montant_reglt,
                    r.reseau_id,
                    fr.idfacture,
                    fr.agr_centre,
                    f.normalized_type_facture,
                    ABS(COALESCE(f.total_ttc, f.total_ht, f.montant_presta_ht, 0)) AS facture_weight
                FROM ({$reglementsLatestSql}) r
                INNER JOIN tmp_synthese_reglements_periods p
                    ON p.annee = YEAR(r.date_reglt) AND p.mois = MONTH(r.date_reglt)
                INNER JOIN factures_reglements fr
                    ON fr.idreglement = r.idreglement
                INNER JOIN ({$facturesLatestSql}) f
                    ON f.idfacture = fr.idfacture
                LEFT JOIN controles_factures cf
                    ON cf.idfacture = f.idfacture
                WHERE r.date_reglt >= :date_from
                  AND r.date_reglt < :date_to
                  AND f.normalized_type_facture IN ('F','A','D')
                  AND cf.idfacture IS NULL
            ) base
        ";

        $allocatedAmountExpr = "
            (
                COALESCE(up.montant_reglt, 0)
                * CASE
                    WHEN up.total_factures_reglement > 0
                        THEN up.facture_weight / up.total_factures_reglement
                    ELSE 1 / NULLIF(up.nb_factures_reglement, 0)
                END
            )
        ";
        $modeRegltExpr = $this->paymentModeFamilySql('up');

        $sql = "
            INSERT INTO synthese_reglements (
                annee, mois, mode_reglt,
                societe_nom, agr_centre, agr_centre_cl, centre_ville, reseau_id, reseau_nom,
                salarie_id, salarie_agr, salarie_agr_cl, salarie_nom, salarie_prenom,
                nb_reglements, nb_factures, nb_controles,
                nb_auto, nb_moto, nb_vtp, nb_clvtp, nb_cv, nb_clcv, nb_vtc, nb_vol, nb_clvol,
                montant_regle, montant_regle_auto, montant_regle_moto,
                montant_regle_vtp, montant_regle_clvtp, montant_regle_cv, montant_regle_clcv,
                montant_regle_vtc, montant_regle_vol, montant_regle_clvol
            )
            SELECT
                YEAR(up.date_reglt) AS annee,
                MONTH(up.date_reglt) AS mois,
                {$modeRegltExpr} AS mode_reglt,
                MAX(COALESCE(so.nom, 'Société inconnue')) AS societe_nom,
                IF(ce.agr_centre IS NULL, CONCAT('Centre inconnu (', COALESCE(up.agr_centre, '?'), ')'), ce.agr_centre) AS agr_centre,
                MAX(COALESCE(ce.agr_cl_centre, '')) AS agr_centre_cl,
                MAX(COALESCE(ce.ville, '')) AS centre_ville,
                MAX(COALESCE(up.reseau_id, 0)) AS reseau_id,
                MAX(COALESCE(ce.reseau_nom, '')) AS reseau_nom,
                0 AS salarie_id,
                'Agrément inconnu' AS salarie_agr,
                '' AS salarie_agr_cl,
                'Contrôleur inconnu' AS salarie_nom,
                '' AS salarie_prenom,
                COUNT(DISTINCT up.idreglement) AS nb_reglements,
                COUNT(DISTINCT up.idfacture) AS nb_factures,
                0 AS nb_controles,
                0 AS nb_auto,
                0 AS nb_moto,
                0 AS nb_vtp,
                0 AS nb_clvtp,
                0 AS nb_cv,
                0 AS nb_clcv,
                0 AS nb_vtc,
                0 AS nb_vol,
                0 AS nb_clvol,
                SUM({$allocatedAmountExpr}) AS montant_regle,
                0 AS montant_regle_auto,
                0 AS montant_regle_moto,
                0 AS montant_regle_vtp,
                0 AS montant_regle_clvtp,
                0 AS montant_regle_cv,
                0 AS montant_regle_clcv,
                0 AS montant_regle_vtc,
                0 AS montant_regle_vol,
                0 AS montant_regle_clvol
            FROM ({$unlinkedPaymentSql}) up
            LEFT JOIN centre ce
                ON {$centreJoinCondition}
            LEFT JOIN societe so
                ON so.id = ce.societe_id
            GROUP BY
                YEAR(up.date_reglt),
                MONTH(up.date_reglt),
                {$modeRegltExpr},
                IF(ce.agr_centre IS NULL, CONCAT('Centre inconnu (', COALESCE(up.agr_centre, '?'), ')'), ce.agr_centre)
            ON DUPLICATE KEY UPDATE
                nb_reglements = nb_reglements + VALUES(nb_reglements),
                nb_factures = nb_factures + VALUES(nb_factures),
                montant_regle = montant_regle + VALUES(montant_regle)
        ";

        $this->connection->executeStatement($sql, [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ]);
    }

    /**
     * Inserts payment aggregates for factures_reglements rows whose invoice is absent from `factures`.
     *
     * @param string $dateFrom Inclusive lower date bound.
     * @param string $dateTo Exclusive upper date bound.
     *
     * @return void
     * @throws Exception
     */
    private function insertMissingInvoiceReferenceAggregates(string $dateFrom, string $dateTo): void
    {
        $centreJoinCondition = $this->hasSecondaryCentreAgreementColumn()
            ? '(ce.agr_centre = missing.agr_centre OR ce.agr_cl_centre = missing.agr_centre)'
            : 'ce.agr_centre = missing.agr_centre';

        $reglementsLatestSql = "
            SELECT *
            FROM (
                SELECT
                    r.*,
                    ROW_NUMBER() OVER (PARTITION BY r.idreglement ORDER BY r.date_export DESC, r.id DESC) AS rn
                FROM reglements r
            ) ranked_reglements
            WHERE ranked_reglements.rn = 1
        ";

        $facturesLatestSql = "
            SELECT *
            FROM (
                SELECT
                    f.*,
                    ROW_NUMBER() OVER (PARTITION BY f.idfacture ORDER BY f.date_export DESC, f.id DESC) AS rn
                FROM factures f
            ) ranked_factures
            WHERE ranked_factures.rn = 1
        ";

        $missingInvoiceSql = "
            SELECT DISTINCT
                r.idreglement,
                r.date_reglt,
                r.mode_reglt,
                r.montant_reglt,
                r.reseau_id,
                fr.idfacture,
                fr.agr_centre
            FROM ({$reglementsLatestSql}) r
            INNER JOIN tmp_synthese_reglements_periods p
                ON p.annee = YEAR(r.date_reglt) AND p.mois = MONTH(r.date_reglt)
            INNER JOIN factures_reglements fr
                ON fr.idreglement = r.idreglement
            LEFT JOIN ({$facturesLatestSql}) f
                ON f.idfacture = fr.idfacture
            WHERE r.date_reglt >= :date_from
              AND r.date_reglt < :date_to
              AND f.idfacture IS NULL
        ";
        $modeRegltExpr = $this->paymentModeFamilySql('missing');

        $sql = "
            INSERT INTO synthese_reglements (
                annee, mois, mode_reglt,
                societe_nom, agr_centre, agr_centre_cl, centre_ville, reseau_id, reseau_nom,
                salarie_id, salarie_agr, salarie_agr_cl, salarie_nom, salarie_prenom,
                nb_reglements, nb_factures, nb_controles,
                nb_auto, nb_moto, nb_vtp, nb_clvtp, nb_cv, nb_clcv, nb_vtc, nb_vol, nb_clvol,
                montant_regle, montant_regle_auto, montant_regle_moto,
                montant_regle_vtp, montant_regle_clvtp, montant_regle_cv, montant_regle_clcv,
                montant_regle_vtc, montant_regle_vol, montant_regle_clvol
            )
            SELECT
                YEAR(missing.date_reglt) AS annee,
                MONTH(missing.date_reglt) AS mois,
                {$modeRegltExpr} AS mode_reglt,
                MAX(COALESCE(so.nom, 'Société inconnue')) AS societe_nom,
                IF(ce.agr_centre IS NULL, CONCAT('Centre inconnu (', COALESCE(missing.agr_centre, '?'), ')'), ce.agr_centre) AS agr_centre,
                MAX(COALESCE(ce.agr_cl_centre, '')) AS agr_centre_cl,
                MAX(COALESCE(ce.ville, '')) AS centre_ville,
                MAX(COALESCE(missing.reseau_id, 0)) AS reseau_id,
                MAX(COALESCE(ce.reseau_nom, '')) AS reseau_nom,
                0 AS salarie_id,
                'Agrément inconnu' AS salarie_agr,
                '' AS salarie_agr_cl,
                'Contrôleur inconnu' AS salarie_nom,
                '' AS salarie_prenom,
                COUNT(DISTINCT missing.idreglement) AS nb_reglements,
                COUNT(DISTINCT missing.idfacture) AS nb_factures,
                0 AS nb_controles,
                0 AS nb_auto,
                0 AS nb_moto,
                0 AS nb_vtp,
                0 AS nb_clvtp,
                0 AS nb_cv,
                0 AS nb_clcv,
                0 AS nb_vtc,
                0 AS nb_vol,
                0 AS nb_clvol,
                SUM(missing.montant_reglt) AS montant_regle,
                0 AS montant_regle_auto,
                0 AS montant_regle_moto,
                0 AS montant_regle_vtp,
                0 AS montant_regle_clvtp,
                0 AS montant_regle_cv,
                0 AS montant_regle_clcv,
                0 AS montant_regle_vtc,
                0 AS montant_regle_vol,
                0 AS montant_regle_clvol
            FROM ({$missingInvoiceSql}) missing
            LEFT JOIN centre ce
                ON {$centreJoinCondition}
            LEFT JOIN societe so
                ON so.id = ce.societe_id
            GROUP BY
                YEAR(missing.date_reglt),
                MONTH(missing.date_reglt),
                {$modeRegltExpr},
                IF(ce.agr_centre IS NULL, CONCAT('Centre inconnu (', COALESCE(missing.agr_centre, '?'), ')'), ce.agr_centre)
            ON DUPLICATE KEY UPDATE
                nb_reglements = nb_reglements + VALUES(nb_reglements),
                nb_factures = nb_factures + VALUES(nb_factures),
                montant_regle = montant_regle + VALUES(montant_regle)
        ";

        $this->connection->executeStatement($sql, [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ]);
    }

    private function paymentModeFamilySql(string $alias): string
    {
        $normalizedModeSql = "UPPER(TRIM(COALESCE({$alias}.mode_reglt, '')))";

        return "
            CASE
                WHEN {$normalizedModeSql} IN ('CB','CX') THEN 'Carte'
                WHEN {$normalizedModeSql} IN ('E','ES') THEN 'Espèces'
                WHEN {$normalizedModeSql} IN ('C','CH') THEN 'Chèque'
                WHEN {$normalizedModeSql} IN ('PE','PI', 'IN') THEN 'Internet'
                ELSE 'Autre'
            END
        ";
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
     * Ensures required technical tables and columns exist for `synthese_reglements`.
     *
     * @return void
     *
     * @throws Exception
     */
    private function ensureTable(): void
    {
        $this->connection->executeStatement("
            CREATE TABLE IF NOT EXISTS synthese_reglements (
                id INT AUTO_INCREMENT PRIMARY KEY,
                annee INT NOT NULL,
                mois INT NOT NULL,
                mode_reglt VARCHAR(50) NOT NULL,
                societe_nom VARCHAR(255) NOT NULL,
                agr_centre VARCHAR(50) NOT NULL,
                agr_centre_cl VARCHAR(50) NOT NULL DEFAULT '',
                centre_ville VARCHAR(255) DEFAULT '',
                reseau_id INT NOT NULL DEFAULT 0,
                reseau_nom VARCHAR(50) NOT NULL DEFAULT '',
                salarie_id INT NOT NULL DEFAULT 0,
                salarie_agr VARCHAR(20) NOT NULL,
                salarie_agr_cl VARCHAR(20) NOT NULL DEFAULT '',
                salarie_nom VARCHAR(255) NOT NULL,
                salarie_prenom VARCHAR(255) NOT NULL DEFAULT '',
                nb_reglements INT NOT NULL DEFAULT 0,
                nb_factures INT NOT NULL DEFAULT 0,
                nb_controles INT NOT NULL DEFAULT 0,
                nb_auto INT NOT NULL DEFAULT 0,
                nb_moto INT NOT NULL DEFAULT 0,
                nb_vtp INT NOT NULL DEFAULT 0,
                nb_clvtp INT NOT NULL DEFAULT 0,
                nb_cv INT NOT NULL DEFAULT 0,
                nb_clcv INT NOT NULL DEFAULT 0,
                nb_vtc INT NOT NULL DEFAULT 0,
                nb_vol INT NOT NULL DEFAULT 0,
                nb_clvol INT NOT NULL DEFAULT 0,
                montant_regle DECIMAL(12,2) NOT NULL DEFAULT 0,
                montant_regle_auto DECIMAL(12,2) NOT NULL DEFAULT 0,
                montant_regle_moto DECIMAL(12,2) NOT NULL DEFAULT 0,
                montant_regle_vtp DECIMAL(12,2) NOT NULL DEFAULT 0,
                montant_regle_clvtp DECIMAL(12,2) NOT NULL DEFAULT 0,
                montant_regle_cv DECIMAL(12,2) NOT NULL DEFAULT 0,
                montant_regle_clcv DECIMAL(12,2) NOT NULL DEFAULT 0,
                montant_regle_vtc DECIMAL(12,2) NOT NULL DEFAULT 0,
                montant_regle_vol DECIMAL(12,2) NOT NULL DEFAULT 0,
                montant_regle_clvol DECIMAL(12,2) NOT NULL DEFAULT 0,
                UNIQUE KEY unique_synthese_reglements (mode_reglt, annee, mois, agr_centre, salarie_id, salarie_agr),
                KEY idx_synthese_reglements_periode (annee, mois),
                KEY idx_synthese_reglements_filters (societe_nom, agr_centre, salarie_id, reseau_nom)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $this->ensureLegacyCompatibility();
        $this->ensureColumns();
        $this->ensureUniqueKey();

        $this->connection->executeStatement("
            CREATE TABLE IF NOT EXISTS synthese_meta (
                meta_key VARCHAR(64) PRIMARY KEY,
                last_run_at DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    /**
     * Returns affected year/month payment periods to refresh.
     *
     * @param string|null $lastRunAt Last successful run timestamp.
     * @return array<int, array{annee:int, mois:int}>
     *
     * @throws Exception
     */
    private function fetchPeriodsToRefresh(?string $lastRunAt): array
    {
        if ($this->forcePeriod !== null && $this->forcePeriod !== '') {
            if (!preg_match('/^\d{4}-\d{2}$/', $this->forcePeriod)) {
                throw new \InvalidArgumentException('Option --period invalide. Attendu: YYYY-MM.');
            }

            [$year, $month] = array_map('intval', explode('-', $this->forcePeriod));
            if ($month < 1 || $month > 12) {
                throw new \InvalidArgumentException('Option --period invalide. Le mois doit être compris entre 01 et 12.');
            }

            return [
                ['annee' => $year, 'mois' => $month],
            ];
        }

        if ($this->forceFullRefresh || $lastRunAt === null) {
            return $this->connection->fetchAllAssociative(
                "
                    SELECT DISTINCT YEAR(date_reglt) AS annee, MONTH(date_reglt) AS mois
                    FROM reglements
                    WHERE date_reglt IS NOT NULL
                    ORDER BY annee, mois
                "
            );
        }

        return $this->connection->fetchAllAssociative(
            "
                SELECT DISTINCT YEAR(r.date_reglt) AS annee, MONTH(r.date_reglt) AS mois
                FROM reglements r
                WHERE r.date_reglt IS NOT NULL
                  AND r.date_export > :last_run_at
                ORDER BY annee, mois
            ",
            [
                'last_run_at' => $lastRunAt,
            ]
        );
    }

    /**
     * Builds inclusive/exclusive date bounds covering all periods to refresh.
     *
     * @param array<int, array{annee:int, mois:int}> $periods
     *
     * @return array{0:string,1:string}
     */
    private function buildDateBoundsForPeriods(array $periods): array
    {
        $firstPeriod = null;
        $lastPeriod = null;

        foreach ($periods as $period) {
            $year = (int) $period['annee'];
            $month = (int) $period['mois'];
            $periodKey = sprintf('%04d-%02d', $year, $month);

            if ($firstPeriod === null || $periodKey < $firstPeriod) {
                $firstPeriod = $periodKey;
            }

            if ($lastPeriod === null || $periodKey > $lastPeriod) {
                $lastPeriod = $periodKey;
            }
        }

        if ($firstPeriod === null || $lastPeriod === null) {
            throw new \InvalidArgumentException('Aucune période à borner.');
        }

        $dateFrom = $firstPeriod . '-01 00:00:00';
        [$lastYear, $lastMonth] = array_map('intval', explode('-', $lastPeriod));
        $nextMonth = $lastMonth === 12 ? 1 : $lastMonth + 1;
        $nextYear = $lastMonth === 12 ? $lastYear + 1 : $lastYear;
        $dateTo = sprintf('%04d-%02d-01 00:00:00', $nextYear, $nextMonth);

        return [$dateFrom, $dateTo];
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
        $this->connection->executeStatement('DROP TEMPORARY TABLE IF EXISTS tmp_synthese_reglements_periods');
        $this->connection->executeStatement('CREATE TEMPORARY TABLE tmp_synthese_reglements_periods (annee INT NOT NULL, mois INT NOT NULL, PRIMARY KEY (annee, mois))');

        foreach ($periods as $period) {
            $this->connection->insert('tmp_synthese_reglements_periods', [
                'annee' => (int) $period['annee'],
                'mois' => (int) $period['mois'],
            ]);
        }
    }

    /**
     * Keeps an older draft table shape from blocking the new insert.
     *
     * @return void
     * @throws Exception
     */
    private function ensureLegacyCompatibility(): void
    {
        if ($this->columnExists('synthese_reglements', 'code_client')) {
            $this->connection->executeStatement('ALTER TABLE synthese_reglements MODIFY code_client VARCHAR(50) NULL DEFAULT NULL');
        }

        if ($this->indexExists('synthese_reglements', 'unique_client_annee_mois_centre_societe')) {
            $this->connection->executeStatement('ALTER TABLE synthese_reglements DROP INDEX unique_client_annee_mois_centre_societe');
        }
    }

    /**
     * Ensures all expected columns exist when the table predates this command shape.
     *
     * @return void
     * @throws Exception
     */
    private function ensureColumns(): void
    {
        $columns = [
            'mode_reglt' => "ALTER TABLE synthese_reglements ADD COLUMN mode_reglt VARCHAR(50) NOT NULL DEFAULT 'Inconnu' AFTER mois",
            'agr_centre_cl' => "ALTER TABLE synthese_reglements ADD COLUMN agr_centre_cl VARCHAR(50) NOT NULL DEFAULT '' AFTER agr_centre",
            'centre_ville' => "ALTER TABLE synthese_reglements ADD COLUMN centre_ville VARCHAR(255) DEFAULT '' AFTER agr_centre_cl",
            'salarie_id' => "ALTER TABLE synthese_reglements ADD COLUMN salarie_id INT NOT NULL DEFAULT 0 AFTER reseau_nom",
            'salarie_agr' => "ALTER TABLE synthese_reglements ADD COLUMN salarie_agr VARCHAR(20) NOT NULL DEFAULT 'Agrément inconnu' AFTER salarie_id",
            'salarie_agr_cl' => "ALTER TABLE synthese_reglements ADD COLUMN salarie_agr_cl VARCHAR(20) NOT NULL DEFAULT '' AFTER salarie_agr",
            'salarie_nom' => "ALTER TABLE synthese_reglements ADD COLUMN salarie_nom VARCHAR(255) NOT NULL DEFAULT 'Contrôleur inconnu' AFTER salarie_agr_cl",
            'salarie_prenom' => "ALTER TABLE synthese_reglements ADD COLUMN salarie_prenom VARCHAR(255) NOT NULL DEFAULT '' AFTER salarie_nom",
            'nb_reglements' => "ALTER TABLE synthese_reglements ADD COLUMN nb_reglements INT NOT NULL DEFAULT 0 AFTER salarie_prenom",
            'nb_factures' => "ALTER TABLE synthese_reglements ADD COLUMN nb_factures INT NOT NULL DEFAULT 0 AFTER nb_reglements",
            'nb_auto' => "ALTER TABLE synthese_reglements ADD COLUMN nb_auto INT NOT NULL DEFAULT 0 AFTER nb_controles",
            'nb_moto' => "ALTER TABLE synthese_reglements ADD COLUMN nb_moto INT NOT NULL DEFAULT 0 AFTER nb_auto",
            'montant_regle' => "ALTER TABLE synthese_reglements ADD COLUMN montant_regle DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER nb_clvol",
            'montant_regle_auto' => "ALTER TABLE synthese_reglements ADD COLUMN montant_regle_auto DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER montant_regle",
            'montant_regle_moto' => "ALTER TABLE synthese_reglements ADD COLUMN montant_regle_moto DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER montant_regle_auto",
            'montant_regle_vtp' => "ALTER TABLE synthese_reglements ADD COLUMN montant_regle_vtp DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER montant_regle_moto",
            'montant_regle_clvtp' => "ALTER TABLE synthese_reglements ADD COLUMN montant_regle_clvtp DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER montant_regle_vtp",
            'montant_regle_cv' => "ALTER TABLE synthese_reglements ADD COLUMN montant_regle_cv DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER montant_regle_clvtp",
            'montant_regle_clcv' => "ALTER TABLE synthese_reglements ADD COLUMN montant_regle_clcv DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER montant_regle_cv",
            'montant_regle_vtc' => "ALTER TABLE synthese_reglements ADD COLUMN montant_regle_vtc DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER montant_regle_clcv",
            'montant_regle_vol' => "ALTER TABLE synthese_reglements ADD COLUMN montant_regle_vol DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER montant_regle_vtc",
            'montant_regle_clvol' => "ALTER TABLE synthese_reglements ADD COLUMN montant_regle_clvol DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER montant_regle_vol",
        ];

        foreach ($columns as $column => $alterSql) {
            $this->ensureColumn('synthese_reglements', $column, $alterSql);
        }
    }

    /**
     * Ensures the unique key matches the aggregate grain.
     *
     * @return void
     * @throws Exception
     */
    private function ensureUniqueKey(): void
    {
        if ($this->indexExists('synthese_reglements', 'unique_synthese_reglements')) {
            return;
        }

        $this->connection->executeStatement('DELETE FROM synthese_reglements');
        $this->forceFullRefresh = true;

        $this->connection->executeStatement(
            'ALTER TABLE synthese_reglements ADD UNIQUE KEY unique_synthese_reglements (mode_reglt, annee, mois, agr_centre, salarie_id, salarie_agr)'
        );
    }

    /**
     * Ensures source-table indexes required by the payment synthesis joins exist.
     *
     * @return void
     * @throws Exception
     */
    private function ensureSourceIndexes(): void
    {
        $indexes = [
            ['reglements', 'idx_reglements_idreglement', 'idreglement'],
            ['reglements', 'idx_reglements_date_reglt', 'date_reglt'],
            ['reglements', 'idx_reglements_idreglement_export', 'idreglement, date_export, id'],
            ['factures_reglements', 'idx_fr_idreglement', 'idreglement'],
            ['factures_reglements', 'idx_fr_idfacture', 'idfacture'],
            ['factures_reglements', 'idx_fr_reglement_facture', 'idreglement, idfacture'],
        ];

        foreach ($indexes as [$tableName, $indexName, $columns]) {
            if ($this->indexExists($tableName, $indexName)) {
                continue;
            }

            $this->connection->executeStatement(sprintf(
                'ALTER TABLE %s ADD INDEX %s (%s)',
                $tableName,
                $indexName,
                $columns
            ));
        }
    }

    /**
     * Checks whether a column exists in a table.
     *
     * @param string $tableName Target table name.
     * @param string $columnName Target column name.
     *
     * @return bool True when the column already exists.
     *
     * @throws Exception
     */
    private function columnExists(string $tableName, string $columnName): bool
    {
        return (int) $this->connection->fetchOne(
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
     * Checks whether an index exists in a table.
     *
     * @param string $tableName Target table name.
     * @param string $indexName Target index name.
     *
     * @return bool True when the index already exists.
     *
     * @throws Exception
     */
    private function indexExists(string $tableName, string $indexName): bool
    {
        return (int) $this->connection->fetchOne(
            "
                SELECT COUNT(*)
                FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = :table_name
                  AND INDEX_NAME = :index_name
            ",
            [
                'table_name' => $tableName,
                'index_name' => $indexName,
            ]
        ) > 0;
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

    /**
     * Adds a missing column and forces a full refresh when schema changed.
     *
     * @param string $tableName Target table name.
     * @param string $columnName Missing column name.
     * @param string $alterSql ALTER TABLE statement used to create the column.
     *
     * @return void
     *
     * @throws Exception
     */
    private function ensureColumn(string $tableName, string $columnName, string $alterSql): void
    {
        if ($this->columnExists($tableName, $columnName)) {
            return;
        }

        $this->connection->executeStatement($alterSql);
        $this->forceFullRefresh = true;
    }
}
