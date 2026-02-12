<?php

namespace App\Command;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:pro:summary',
    description: 'Crée et remplit la table client_pro_summary avec les clients pro par mois et année.'
)]
class PopulateClientProSummaryCommand extends Command
{
    public function __construct(
        private readonly Connection $connection
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Début de la création et du remplissage de client_pro_summary...');

        try {
            // Création de la table si elle n'existe pas
            $this->connection->executeStatement("
                DROP TABLE IF EXISTS client_pro_summary;

                CREATE TABLE IF NOT EXISTS client_pro_summary (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    nom_code_client VARCHAR(50) NOT NULL,
                    annee INT NOT NULL,
                    mois INT NOT NULL,
                    ca DECIMAL(10,2) NOT NULL DEFAULT 0,
                    nb_controles INT NOT NULL DEFAULT 0,
                    agr_centre VARCHAR(50) NOT NULL,
                    societe_nom VARCHAR(255) NOT NULL,
                    UNIQUE KEY unique_client_annee_mois (nom_code_client, annee, mois)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");
            $output->writeln('Table créée ou déjà existante.');

            // Définir les années N, N-1, N-2
            $years = [date('Y') - 2, date('Y') - 1, date('Y')];

            // Insertion / mise à jour des données
            $sql = "
                INSERT INTO client_pro_summary (
                    nom_code_client, annee, mois, ca, nb_controles, agr_centre, societe_nom
                )
                SELECT
                    cli.nom_code_client,
                    YEAR(fa.data_date),
                    MONTH(fa.data_date),

                    SUM(fa.montant_presta_ht * c.nb_ctrl_client / t.nb_ctrl_total) AS ca,
                    SUM(c.nb_ctrl_client) AS nb_controles,

                    dim.agr_centre,
                    dim.societe_nom

                FROM factures fa

                JOIN (
                    SELECT cf.idfacture, cc.idclient, COUNT(DISTINCT cf.idcontrole) AS nb_ctrl_client
                    FROM controles_factures cf
                    JOIN clients_controles cc ON cc.idcontrole = cf.idcontrole
                    GROUP BY cf.idfacture, cc.idclient
                ) c ON c.idfacture = fa.idfacture

                JOIN (
                    SELECT idfacture, COUNT(DISTINCT idcontrole) AS nb_ctrl_total
                    FROM controles_factures
                    GROUP BY idfacture
                ) t ON t.idfacture = fa.idfacture

                JOIN clients cli ON cli.idclient = c.idclient

                LEFT JOIN (
                    SELECT
                        cc.idclient,
                        MIN(cc.agr_centre) AS agr_centre,
                        MIN(so.nom) AS societe_nom
                    FROM clients_controles cc
                    LEFT JOIN centre ce ON ce.agr_centre = cc.agr_centre
                    LEFT JOIN societe so ON so.id = ce.societe_id
                    GROUP BY cc.idclient
                ) dim ON dim.idclient = cli.idclient

                WHERE YEAR(fa.data_date) IN (:years)
                  AND cli.nom_code_client IS NOT NULL
                  AND cli.nom_code_client != ''

                GROUP BY
                    cli.nom_code_client,
                    YEAR(fa.data_date),
                    MONTH(fa.data_date)

                ON DUPLICATE KEY UPDATE
                    ca = VALUES(ca),
                    nb_controles = VALUES(nb_controles);
            ";

            $this->connection->executeStatement($sql, [
                'years' => $years
            ], [
                'years' => ArrayParameterType::INTEGER
            ]);

            $output->writeln('Mise à jour terminée pour les clients pro sur les 3 dernières années !');
            return Command::SUCCESS;

        } catch (Exception $e) {
            $output->writeln('<error>Erreur : ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }
}
