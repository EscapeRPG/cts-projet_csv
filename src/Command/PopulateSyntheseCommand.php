<?php

namespace App\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:synthese:populate',
    description: 'Crée et remplit la table de synthèse avec toutes les données par salarié, centre et mois/année.'
)]
class PopulateSyntheseCommand extends Command
{
    public function __construct(
        private readonly Connection $connection
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Début de la création et du remplissage de la table de synthèse...');

        try {
            // Création de la table si elle n'existe pas
            $this->connection->executeStatement("
                DROP TABLE IF EXISTS synthese_controles;

                CREATE TABLE IF NOT EXISTS synthese_controles (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    societe_nom VARCHAR(255) NOT NULL,
                    agr_centre VARCHAR(50) NOT NULL,
                    centre_ville VARCHAR(255) DEFAULT '',
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
                    total_presta_ht DECIMAL(12,2) NOT NULL DEFAULT 0,
                    total_ht_vtp DECIMAL(12,2) NOT NULL DEFAULT 0,
                    total_ht_clvtp DECIMAL(12,2) NOT NULL DEFAULT 0,
                    total_ht_cv DECIMAL(12,2) NOT NULL DEFAULT 0,
                    total_ht_clcv DECIMAL(12,2) NOT NULL DEFAULT 0,
                    total_ht_vtc DECIMAL(12,2) NOT NULL DEFAULT 0,
                    total_ht_vol DECIMAL(12,2) NOT NULL DEFAULT 0,
                    temps_total INT NOT NULL DEFAULT 0,
                    taux_refus DECIMAL(5,2) NOT NULL DEFAULT 0,
                    nb_particuliers INT NOT NULL DEFAULT 0,
                    nb_professionnels INT NOT NULL DEFAULT 0,
                    UNIQUE KEY unique_salarie_mois_annee (salarie_id, agr_centre, annee, mois)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");
            $output->writeln('Table synthese_controles créée ou déjà existante.');

            // Insertion / mise à jour des données
            $sql = "
                INSERT INTO synthese_controles (
                    societe_nom, agr_centre, centre_ville, reseau_nom,
                    salarie_id, salarie_agr, salarie_nom, salarie_prenom,
                    annee, mois,
                    nb_controles, nb_vtp, nb_clvtp, nb_cv, nb_clcv, nb_vtc, nb_vol,
                    total_presta_ht, total_ht_vtp, total_ht_clvtp, total_ht_cv, total_ht_clcv, total_ht_vtc, total_ht_vol,
                    temps_total, taux_refus, nb_particuliers, nb_professionnels
                )
                SELECT
                    so.nom AS societe_nom,
                    ce.agr_centre AS agr_centre,
                    ce.ville AS centre_ville,
                    ce.reseau_nom AS reseau_nom,
                    sa.id AS salarie_id,
                    sa.agr_controleur AS salarie_agr,
                    sa.nom AS salarie_nom,
                    sa.prenom AS salarie_prenom,
                    YEAR(ctrl.data_date) AS annee,
                    MONTH(ctrl.data_date) AS mois,
                    COUNT(DISTINCT ctrl.idcontrole) AS nb_controles,
                    COUNT(DISTINCT IF(ctrl.type_ctrl = 'VTP', ctrl.idcontrole, NULL)) AS nb_vtp,
                    COUNT(DISTINCT IF(ctrl.type_ctrl = 'CLVTP', ctrl.idcontrole, NULL)) AS nb_clvtp,
                    COUNT(DISTINCT IF(ctrl.type_ctrl = 'CV', ctrl.idcontrole, NULL)) AS nb_cv,
                    COUNT(DISTINCT IF(ctrl.type_ctrl = 'CLCV', ctrl.idcontrole, NULL)) AS nb_clcv,
                    COUNT(DISTINCT IF(ctrl.type_ctrl = 'VTC', ctrl.idcontrole, NULL)) AS nb_vtc,
                    COUNT(DISTINCT IF(ctrl.type_ctrl = 'VOL', ctrl.idcontrole, NULL)) AS nb_vol,
                    SUM(fa.montant_presta_ht) AS total_presta_ht,
                    SUM(IF(ctrl.type_ctrl = 'VTP', fa.montant_presta_ht, 0)) AS total_ht_vtp,
                    SUM(IF(ctrl.type_ctrl = 'CLVTP', fa.montant_presta_ht, 0)) AS total_ht_clvtp,
                    SUM(IF(ctrl.type_ctrl = 'CV', fa.montant_presta_ht, 0)) AS total_ht_cv,
                    SUM(IF(ctrl.type_ctrl = 'CLCV', fa.montant_presta_ht, 0)) AS total_ht_clcv,
                    SUM(IF(ctrl.type_ctrl = 'VTC', fa.montant_presta_ht, 0)) AS total_ht_vtc,
                    SUM(IF(ctrl.type_ctrl = 'VOL', fa.montant_presta_ht, 0)) AS total_ht_vol,
                    SUM(ctrl.temps_ctrl) AS temps_total,
                    SUM(IF(ctrl.res_ctrl IN ('S','R','SP'), 1, 0)) AS taux_refus,
                    SUM(IF(cli.nom_code_client IS NULL OR cli.nom_code_client = '', 1, 0)) AS nb_particuliers,
                    SUM(IF(cli.nom_code_client IS NOT NULL AND cli.nom_code_client != '', 1, 0)) AS nb_professionnels
                FROM salarie sa
                JOIN clients_controles cc
                    ON cc.agr_controleur = sa.agr_controleur
                    OR (sa.agr_cl_controleur IS NOT NULL AND cc.agr_controleur = sa.agr_cl_controleur)
                JOIN controles ctrl ON ctrl.idcontrole = cc.idcontrole
                LEFT JOIN controles_factures cf ON cf.idcontrole = ctrl.idcontrole
                LEFT JOIN factures fa ON fa.idfacture = cf.idfacture
                LEFT JOIN centre ce ON ce.agr_centre = cc.agr_centre
                LEFT JOIN societe so ON so.id = ce.societe_id
                LEFT JOIN clients cli ON cli.idclient = cc.idclient
                WHERE sa.is_active = 1 AND ce.id IS NOT NULL
                GROUP BY sa.id, ce.agr_centre, YEAR(ctrl.data_date), MONTH(ctrl.data_date)
                ON DUPLICATE KEY UPDATE
                    nb_controles = VALUES(nb_controles),
                    nb_vtp = VALUES(nb_vtp),
                    nb_clvtp = VALUES(nb_clvtp),
                    nb_cv = VALUES(nb_cv),
                    nb_clcv = VALUES(nb_clcv),
                    nb_vtc = VALUES(nb_vtc),
                    nb_vol = VALUES(nb_vol),
                    total_presta_ht = VALUES(total_presta_ht),
                    total_ht_vtp = VALUES(total_ht_vtp),
                    total_ht_clvtp = VALUES(total_ht_clvtp),
                    total_ht_cv = VALUES(total_ht_cv),
                    total_ht_clcv = VALUES(total_ht_clcv),
                    total_ht_vtc = VALUES(total_ht_vtc),
                    total_ht_vol = VALUES(total_ht_vol),
                    temps_total = VALUES(temps_total),
                    taux_refus = VALUES(taux_refus),
                    nb_particuliers = VALUES(nb_particuliers),
                    nb_professionnels = VALUES(nb_professionnels);
            ";

            $this->connection->executeStatement($sql);

            $output->writeln('Table de synthèse remplie avec succès !');
            return Command::SUCCESS;

        } catch (Exception $e) {
            $output->writeln('<error>Erreur : ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }
}
