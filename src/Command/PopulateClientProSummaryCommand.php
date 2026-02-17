<?php

namespace App\Command;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:synthese:pros',
    description: 'Crée et remplit la table synthese_pros avec les clients pro par mois et année.'
)]
class PopulateClientProSummaryCommand extends Command
{
    public function __construct(
        private readonly Connection $connection
    )
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Début de la création et du remplissage de synthese_pros...');

        try {
            // Création de la table
            $this->connection->executeStatement("
                DROP TABLE IF EXISTS synthese_pros;

                CREATE TABLE synthese_pros (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    code_client VARCHAR(50) NOT NULL,
                    annee INT NOT NULL,
                    mois INT NOT NULL,
                    ca DECIMAL(12,2) NOT NULL DEFAULT 0,
                    nb_controles INT NOT NULL DEFAULT 0,
                    agr_centre VARCHAR(50) NOT NULL,
                    societe_nom VARCHAR(255) NOT NULL,
                    UNIQUE KEY unique_client_annee_mois_centre_societe (code_client, annee, mois, agr_centre, societe_nom)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");
            $output->writeln('Table synthese_pros créée.');

            $now = new DateTimeImmutable();
            $dateFrom = $now->modify('-2 years')->setDate((int)$now->format('Y') - 2, 1, 1)->setTime(0, 0, 0);
            $dateTo = new DateTimeImmutable('first day of january next year')->setTime(0, 0, 0);

            // Insertion / mise à jour
            $sql = "
                INSERT INTO synthese_pros (
                    code_client, annee, mois, ca, nb_controles, agr_centre, societe_nom
                )
                SELECT
                    cli_ref.nom_code_client AS code_client,
                    YEAR(c.date_ctrl) AS annee,
                    MONTH(c.date_ctrl) AS mois,
                    SUM(COALESCE(fa.montant_presta_ht, fa.total_ht) / t.nb_ctrl_facture) AS ca,
                    COUNT(DISTINCT c.idcontrole) AS nb_controles,
                    COALESCE(cc.agr_centre, 'Centre inconnu') AS agr_centre,
                    COALESCE(so.nom, 'Société inconnue') AS societe_nom
                FROM controles c
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
                    COALESCE(so.nom, 'Société inconnue')
                ON DUPLICATE KEY UPDATE
                    ca = VALUES(ca),
                    nb_controles = VALUES(nb_controles),
                    agr_centre = VALUES(agr_centre),
                    societe_nom = VALUES(societe_nom);
            ";

            $this->connection->executeStatement($sql, [
                'date_from' => $dateFrom->format('Y-m-d H:i:s'),
                'date_to' => $dateTo->format('Y-m-d H:i:s'),
            ]);

            $output->writeln('Mise à jour terminée pour les clients pro sur les 3 dernières années et par mois !');
            return Command::SUCCESS;

        } catch (Exception $e) {
            $output->writeln('<error>Erreur : ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }
}
