<?php

namespace App\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:db:ensure-indexes',
    description: 'Vérifie et crée les index de performance manquants.'
)]
/**
 * Ensures required database performance indexes exist across core tables.
 */
class EnsureIndexesCommand extends Command
{
    /**
     * @var array<int, array{table:string,name:string,signature:string,sql:string}>
     */
    private const array INDEX_SPECS = [
        // controles
        ['table' => 'controles', 'name' => 'idx_controles_idcontrole', 'signature' => 'idcontrole', 'sql' => 'CREATE INDEX idx_controles_idcontrole ON controles (idcontrole)'],
        ['table' => 'controles', 'name' => 'idx_controles_date_ctrl', 'signature' => 'date_ctrl', 'sql' => 'CREATE INDEX idx_controles_date_ctrl ON controles (date_ctrl)'],
        ['table' => 'controles', 'name' => 'idx_controles_date_type', 'signature' => 'date_ctrl,type_ctrl', 'sql' => 'CREATE INDEX idx_controles_date_type ON controles (date_ctrl, type_ctrl)'],
        ['table' => 'controles', 'name' => 'idx_controles_immat', 'signature' => 'immat_vehicule', 'sql' => 'CREATE INDEX idx_controles_immat ON controles (immat_vehicule)'],
        ['table' => 'controles', 'name' => 'idx_controles_type_res', 'signature' => 'type_ctrl,res_ctrl', 'sql' => 'CREATE INDEX idx_controles_type_res ON controles (type_ctrl, res_ctrl)'],
        ['table' => 'controles', 'name' => 'idx_perf_controles_date_reseau_type', 'signature' => 'date_ctrl,reseau_id,type_ctrl', 'sql' => 'CREATE INDEX idx_perf_controles_date_reseau_type ON controles (date_ctrl, reseau_id, type_ctrl)'],

        // controles_factures
        ['table' => 'controles_factures', 'name' => 'idx_cf_idcontrole', 'signature' => 'idcontrole', 'sql' => 'CREATE INDEX idx_cf_idcontrole ON controles_factures (idcontrole)'],
        ['table' => 'controles_factures', 'name' => 'idx_cf_idfacture', 'signature' => 'idfacture', 'sql' => 'CREATE INDEX idx_cf_idfacture ON controles_factures (idfacture)'],
        ['table' => 'controles_factures', 'name' => 'idx_cf_idcontrole_idfacture', 'signature' => 'idcontrole,idfacture', 'sql' => 'CREATE INDEX idx_cf_idcontrole_idfacture ON controles_factures (idcontrole, idfacture)'],
        ['table' => 'controles_factures', 'name' => 'idx_cf_idfacture_idcontrole', 'signature' => 'idfacture,idcontrole', 'sql' => 'CREATE INDEX idx_cf_idfacture_idcontrole ON controles_factures (idfacture, idcontrole)'],

        // clients_controles
        ['table' => 'clients_controles', 'name' => 'idx_cc_idcontrole', 'signature' => 'idcontrole', 'sql' => 'CREATE INDEX idx_cc_idcontrole ON clients_controles (idcontrole)'],
        ['table' => 'clients_controles', 'name' => 'idx_cc_idclient', 'signature' => 'idclient', 'sql' => 'CREATE INDEX idx_cc_idclient ON clients_controles (idclient)'],
        ['table' => 'clients_controles', 'name' => 'idx_cc_idcontrole_idclient', 'signature' => 'idcontrole,idclient', 'sql' => 'CREATE INDEX idx_cc_idcontrole_idclient ON clients_controles (idcontrole, idclient)'],
        ['table' => 'clients_controles', 'name' => 'idx_cc_agr_centre', 'signature' => 'agr_centre', 'sql' => 'CREATE INDEX idx_cc_agr_centre ON clients_controles (agr_centre)'],
        ['table' => 'clients_controles', 'name' => 'idx_perf_clients_controles_ctrl_client_centre', 'signature' => 'idcontrole,idclient,agr_centre', 'sql' => 'CREATE INDEX idx_perf_clients_controles_ctrl_client_centre ON clients_controles (idcontrole, idclient, agr_centre)'],

        // factures
        ['table' => 'factures', 'name' => 'idx_factures_idfacture', 'signature' => 'idfacture', 'sql' => 'CREATE INDEX idx_factures_idfacture ON factures (idfacture)'],
        ['table' => 'factures', 'name' => 'idx_factures_type_facture', 'signature' => 'type_facture', 'sql' => 'CREATE INDEX idx_factures_type_facture ON factures (type_facture)'],
        ['table' => 'factures', 'name' => 'idx_factures_date_facture', 'signature' => 'date_facture', 'sql' => 'CREATE INDEX idx_factures_date_facture ON factures (date_facture)'],
        ['table' => 'factures', 'name' => 'idx_factures_type_date', 'signature' => 'type_facture,date_facture', 'sql' => 'CREATE INDEX idx_factures_type_date ON factures (type_facture, date_facture)'],
        ['table' => 'factures', 'name' => 'idx_factures_total_ht', 'signature' => 'total_ht', 'sql' => 'CREATE INDEX idx_factures_total_ht ON factures (total_ht)'],
        ['table' => 'factures', 'name' => 'idx_factures_type_date_total', 'signature' => 'type_facture,date_facture,total_ht', 'sql' => 'CREATE INDEX idx_factures_type_date_total ON factures (type_facture, date_facture, total_ht)'],
        ['table' => 'factures', 'name' => 'idx_perf_factures_idfacture_type_total', 'signature' => 'idfacture,type_facture,total_ht', 'sql' => 'CREATE INDEX idx_perf_factures_idfacture_type_total ON factures (idfacture, type_facture, total_ht)'],

        // clients
        ['table' => 'clients', 'name' => 'idx_clients_idclient', 'signature' => 'idclient', 'sql' => 'CREATE INDEX idx_clients_idclient ON clients (idclient)'],
        ['table' => 'clients', 'name' => 'idx_clients_code_client', 'signature' => 'code_client', 'sql' => 'CREATE INDEX idx_clients_code_client ON clients (code_client)'],
        ['table' => 'clients', 'name' => 'idx_clients_nom_code', 'signature' => 'nom_code_client(191)', 'sql' => 'CREATE INDEX idx_clients_nom_code ON clients (nom_code_client(191))'],
        ['table' => 'clients', 'name' => 'idx_perf_clients_idclient_code', 'signature' => 'idclient,code_client,nom_code_client(120)', 'sql' => 'CREATE INDEX idx_perf_clients_idclient_code ON clients (idclient, code_client, nom_code_client(120))'],

        // salarie
        ['table' => 'salarie', 'name' => 'idx_perf_salarie_agr_controleur', 'signature' => 'agr_controleur', 'sql' => 'CREATE INDEX idx_perf_salarie_agr_controleur ON salarie (agr_controleur)'],
        ['table' => 'salarie', 'name' => 'idx_perf_salarie_agr_cl_controleur', 'signature' => 'agr_cl_controleur', 'sql' => 'CREATE INDEX idx_perf_salarie_agr_cl_controleur ON salarie (agr_cl_controleur)'],

        // synthese_controles
        ['table' => 'synthese_controles', 'name' => 'idx_sc_filter_dimensions', 'signature' => 'annee,mois,reseau_id,societe_nom,agr_centre,salarie_id', 'sql' => 'CREATE INDEX idx_sc_filter_dimensions ON synthese_controles (annee, mois, reseau_id, societe_nom, agr_centre, salarie_id)'],
        ['table' => 'synthese_controles', 'name' => 'idx_sc_filter_dimensions_by_name', 'signature' => 'annee,mois,reseau_nom,societe_nom,agr_centre,salarie_id', 'sql' => 'CREATE INDEX idx_sc_filter_dimensions_by_name ON synthese_controles (annee, mois, reseau_nom, societe_nom, agr_centre, salarie_id)'],
        ['table' => 'synthese_controles', 'name' => 'idx_sc_societe_centre_salarie', 'signature' => 'societe_nom,agr_centre,salarie_id', 'sql' => 'CREATE INDEX idx_sc_societe_centre_salarie ON synthese_controles (societe_nom, agr_centre, salarie_id)'],

        // synthese_pros
        ['table' => 'synthese_pros', 'name' => 'idx_sp_filter_dimensions', 'signature' => 'annee,mois,reseau_id,societe_nom,agr_centre', 'sql' => 'CREATE INDEX idx_sp_filter_dimensions ON synthese_pros (annee, mois, reseau_id, societe_nom, agr_centre)'],
        ['table' => 'synthese_pros', 'name' => 'idx_sp_filter_dimensions_by_name', 'signature' => 'annee,mois,reseau_nom,societe_nom,agr_centre', 'sql' => 'CREATE INDEX idx_sp_filter_dimensions_by_name ON synthese_pros (annee, mois, reseau_nom, societe_nom, agr_centre)'],
        ['table' => 'synthese_pros', 'name' => 'idx_sp_societe_centre', 'signature' => 'societe_nom,agr_centre', 'sql' => 'CREATE INDEX idx_sp_societe_centre ON synthese_pros (societe_nom, agr_centre)'],
    ];

    /**
     * @param Connection $connection DBAL connection used to inspect and create indexes.
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
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Affiche les actions sans créer d’index.');
    }

    /**
     * Executes index verification and optional index creation.
     *
     * @param InputInterface $input Console input.
     * @param OutputInterface $output Console output.
     *
     * @return int Command exit status.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dryRun = (bool)$input->getOption('dry-run');
        $created = 0;
        $skipped = 0;

        $output->writeln(sprintf(
            '[db:ensure-indexes] Démarrage (%s).',
            $dryRun ? 'dry-run' : 'execution'
        ));

        foreach (self::INDEX_SPECS as $spec) {
            try {
                $status = $this->ensureIndex(
                    $spec['table'],
                    $spec['name'],
                    $spec['signature'],
                    $spec['sql'],
                    $dryRun
                );
            } catch (\Throwable $e) {
                $output->writeln(sprintf(
                    '<error>ERROR: %s.%s (%s)</error>',
                    $spec['table'],
                    $spec['name'],
                    $e->getMessage()
                ));
                return Command::FAILURE;
            }

            $output->writeln($status);
            if (str_starts_with($status, 'CREATED:')) {
                $created++;
            } else {
                $skipped++;
            }
        }

        $output->writeln(sprintf(
            '[db:ensure-indexes] Terminé. created=%d, skipped=%d',
            $created,
            $skipped
        ));

        return Command::SUCCESS;
    }

    /**
     * Ensures a given index exists on a table.
     *
     * @param string $table Target table name.
     * @param string $indexName Expected index name.
     * @param string $signature Canonical indexed columns signature.
     * @param string $createSql SQL statement used to create the index.
     * @param bool $dryRun Whether to simulate creation.
     *
     * @return string Operation status line.
     *
     * @throws Exception
     */
    private function ensureIndex(
        string $table,
        string $indexName,
        string $signature,
        string $createSql,
        bool $dryRun
    ): string {
        $tableExists = (int)$this->connection->fetchOne(
            '
                SELECT COUNT(*)
                FROM information_schema.tables
                WHERE table_schema = DATABASE()
                  AND table_name = :table_name
            ',
            ['table_name' => $table]
        ) > 0;

        if (!$tableExists) {
            return sprintf('SKIPPED: %s.%s (table missing)', $table, $indexName);
        }

        $existing = $this->connection->fetchAllAssociative(
            '
                SELECT
                    index_name,
                    GROUP_CONCAT(
                        CASE
                            WHEN sub_part IS NULL THEN column_name
                            ELSE CONCAT(column_name, "(", sub_part, ")")
                        END
                        ORDER BY seq_in_index
                        SEPARATOR ","
                    ) AS sig
                FROM information_schema.statistics
                WHERE table_schema = DATABASE()
                  AND table_name = :table_name
                GROUP BY index_name
            ',
            ['table_name' => $table]
        );

        foreach ($existing as $row) {
            $existingName = (string)($row['index_name'] ?? '');
            $existingSig = (string)($row['sig'] ?? '');
            if ($existingName === $indexName || $existingSig === $signature) {
                return sprintf(
                    'SKIPPED: %s.%s (already exists or equivalent)',
                    $table,
                    $indexName
                );
            }
        }

        if ($dryRun) {
            return sprintf('CREATED: %s.%s (dry-run)', $table, $indexName);
        }

        $this->connection->executeStatement($createSql);

        return sprintf('CREATED: %s.%s', $table, $indexName);
    }
}
