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
    description: 'Crée et remplit la table de synthèse avec tous les contrôles, montant et répartitions fiables (CA opérationnel).'
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
        $output->writeln('Début du remplissage de la table de synthèse opérationnelle...');

        try {
            // Création de la table
            $this->connection->executeStatement("
                DROP TABLE IF EXISTS synthese_controles;
                CREATE TABLE synthese_controles (
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
                    nb_auto INT NOT NULL DEFAULT 0,
                    nb_moto INT NOT NULL DEFAULT 0,
                    total_presta_ht DECIMAL(12,2) NOT NULL DEFAULT 0,
                    total_ht_vtp DECIMAL(12,2) NOT NULL DEFAULT 0,
                    total_ht_clvtp DECIMAL(12,2) NOT NULL DEFAULT 0,
                    total_ht_cv DECIMAL(12,2) NOT NULL DEFAULT 0,
                    total_ht_clcv DECIMAL(12,2) NOT NULL DEFAULT 0,
                    total_ht_vtc DECIMAL(12,2) NOT NULL DEFAULT 0,
                    total_ht_vol DECIMAL(12,2) NOT NULL DEFAULT 0,
                    temps_total INT NOT NULL DEFAULT 0,
                    temps_total_auto INT NOT NULL DEFAULT 0,
                    temps_total_moto INT NOT NULL DEFAULT 0,
                    taux_refus DECIMAL(5,2) NOT NULL DEFAULT 0,
                    refus_auto INT NOT NULL DEFAULT 0,
                    refus_moto INT NOT NULL DEFAULT 0,
                    nb_particuliers INT NOT NULL DEFAULT 0,
                    nb_professionnels INT NOT NULL DEFAULT 0,
                    UNIQUE KEY unique_salarie_mois_annee (salarie_id, agr_centre, annee, mois)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");

            $output->writeln('Table synthese_controles créée.');

            // Insertion des données opérationnelles
            $sql = "
                INSERT INTO synthese_controles (
                    societe_nom, agr_centre, centre_ville, reseau_nom,
                    salarie_id, salarie_agr, salarie_nom, salarie_prenom,
                    annee, mois,
                    nb_controles, nb_vtp, nb_clvtp, nb_cv, nb_clcv, nb_vtc, nb_vol, nb_auto, nb_moto,
                    total_presta_ht, total_ht_vtp, total_ht_clvtp, total_ht_cv, total_ht_clcv, total_ht_vtc, total_ht_vol,
                    temps_total, temps_total_auto, temps_total_moto, taux_refus, refus_auto, refus_moto, nb_particuliers, nb_professionnels
                )
                SELECT
                    COALESCE(so.nom, 'Société inconnue') AS societe_nom,
                    COALESCE(ce.agr_centre, 'Centre inconnu') AS agr_centre,
                    COALESCE(ce.ville, '') AS centre_ville,
                    COALESCE(ce.reseau_nom, '') AS reseau_nom,
                    COALESCE(sa.id, 0) AS salarie_id,
                    COALESCE(sa.agr_controleur, 'Agrément inconnu') AS salarie_agr,
                    COALESCE(sa.nom, 'Salarié inconnu') AS salarie_nom,
                    COALESCE(sa.prenom, '') AS salarie_prenom,
                    YEAR(ctrl.date_ctrl) AS annee,
                    MONTH(ctrl.date_ctrl) AS mois,
                    COUNT(DISTINCT ctrl.idcontrole) AS nb_controles,
                    COUNT(DISTINCT IF(ctrl.type_ctrl IN ('VTP','VLCTP','VLVT','VLVP'), ctrl.idcontrole, NULL)) AS nb_vtp,
                    COUNT(DISTINCT IF(ctrl.type_ctrl IN ('CLVTP','CLVT','CLCTP'), ctrl.idcontrole, NULL)) AS nb_clvtp,
                    COUNT(DISTINCT IF(ctrl.type_ctrl IN ('CV','VLCV','VLCVC'), ctrl.idcontrole, NULL)) AS nb_cv,
                    COUNT(DISTINCT IF(ctrl.type_ctrl IN ('CLCV'), ctrl.idcontrole, NULL)) AS nb_clcv,
                    COUNT(DISTINCT IF(ctrl.type_ctrl IN ('VTC','VLCTC'), ctrl.idcontrole, NULL)) AS nb_vtc,
                    COUNT(DISTINCT IF(ctrl.type_ctrl IN ('VOL'), ctrl.idcontrole, NULL)) AS nb_vol,
                    COUNT(DISTINCT IF(ctrl.type_ctrl IN ('VTP','VLCTP','VLVT','VLVP','CV','VLCV','VLCVC','VTC','VLCTC','VOL'), ctrl.idcontrole, NULL)) AS nb_auto,
                    COUNT(DISTINCT IF(ctrl.type_ctrl IN ('CLVTP','CLVT','CLCTP','CLCV'), ctrl.idcontrole, NULL)) AS nb_moto,
                    SUM(IF(f.type_facture='F', f.total_ht / t.nb_ctrl_facture, 0)) AS total_presta_ht,
                    SUM(IF(ctrl.type_ctrl IN ('VTP','VLCTP','VLVT','VLVP') AND f.type_facture='F', f.total_ht / t.nb_ctrl_facture, 0)) AS total_ht_vtp,
                    SUM(IF(ctrl.type_ctrl IN ('CLVTP','CLVT','CLCTP') AND f.type_facture='F', f.total_ht / t.nb_ctrl_facture, 0)) AS total_ht_clvtp,
                    SUM(IF(ctrl.type_ctrl IN ('CV','VLCV','VLCVC') AND f.type_facture='F', f.total_ht / t.nb_ctrl_facture, 0)) AS total_ht_cv,
                    SUM(IF(ctrl.type_ctrl='CLCV' AND f.type_facture='F', f.total_ht / t.nb_ctrl_facture, 0)) AS total_ht_clcv,
                    SUM(IF(ctrl.type_ctrl IN ('VTC','VLCTC') AND f.type_facture='F', f.total_ht / t.nb_ctrl_facture, 0)) AS total_ht_vtc,
                    SUM(IF(ctrl.type_ctrl='VOL' AND f.type_facture='F', f.total_ht / t.nb_ctrl_facture, 0)) AS total_ht_vol,
                    SUM(ctrl.temps_ctrl) AS temps_total,
                    SUM(IF(ctrl.type_ctrl IN ('VTP','VLCTP','VLVT','VLVP','CV','VLCV','VLCVC','VTC','VLCTC','VOL'), ctrl.temps_ctrl, 0)) AS temps_total_auto,
                    SUM(IF(ctrl.type_ctrl IN ('CLVTP','CLVT','CLCTP','CLCV'), ctrl.temps_ctrl, 0)) AS temps_total_moto,
                    COUNT(DISTINCT IF(ctrl.res_ctrl IN ('S','R','SP'), ctrl.idcontrole, NULL)) AS taux_refus,
                    COUNT(DISTINCT IF(ctrl.type_ctrl IN ('VTP','VLCTP','VLVT','VLVP','CV','VLCV','VLCVC','VTC','VLCTC','VOL') AND ctrl.res_ctrl IN ('S','R','SP'), ctrl.idcontrole, NULL)) AS refus_auto,
                    COUNT(DISTINCT IF(ctrl.type_ctrl IN ('CLVTP','CLVT','CLCTP','CLCV') AND ctrl.res_ctrl IN ('S','R','SP'), ctrl.idcontrole, NULL)) AS refus_moto,
                    COUNT(DISTINCT IF(COALESCE(cc.has_pro_client, 0) = 0, ctrl.idcontrole, NULL)) AS nb_particuliers,
                    COUNT(DISTINCT IF(COALESCE(cc.has_pro_client, 0) = 1, ctrl.idcontrole, NULL)) AS nb_professionnels
                FROM controles ctrl
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
                LEFT JOIN salarie sa
                    ON sa.agr_controleur = cc.agr_controleur OR (sa.agr_cl_controleur IS NOT NULL AND sa.agr_cl_controleur = cc.agr_controleur)
                LEFT JOIN centre ce ON ce.agr_centre = cc.agr_centre
                LEFT JOIN societe so ON so.id = ce.societe_id
                LEFT JOIN (
                    SELECT DISTINCT idcontrole, idfacture
                    FROM controles_factures
                ) cf ON cf.idcontrole = ctrl.idcontrole
                LEFT JOIN factures f ON f.idfacture = cf.idfacture
                -- Sous-requête pour compter tous les contrôles de la facture
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
                GROUP BY salarie_id, agr_centre, annee, mois
                ON DUPLICATE KEY UPDATE
                    nb_controles=VALUES(nb_controles),
                    nb_vtp=VALUES(nb_vtp),
                    nb_clvtp=VALUES(nb_clvtp),
                    nb_cv=VALUES(nb_cv),
                    nb_clcv=VALUES(nb_clcv),
                    nb_vtc=VALUES(nb_vtc),
                    nb_vol=VALUES(nb_vol),
                    nb_auto=VALUES(nb_auto),
                    nb_moto=VALUES(nb_moto),
                    total_presta_ht=VALUES(total_presta_ht),
                    total_ht_vtp=VALUES(total_ht_vtp),
                    total_ht_clvtp=VALUES(total_ht_clvtp),
                    total_ht_cv=VALUES(total_ht_cv),
                    total_ht_clcv=VALUES(total_ht_clcv),
                    total_ht_vtc=VALUES(total_ht_vtc),
                    total_ht_vol=VALUES(total_ht_vol),
                    temps_total=VALUES(temps_total),
                    temps_total_auto=VALUES(temps_total_auto),
                    temps_total_moto=VALUES(temps_total_moto),
                    taux_refus=VALUES(taux_refus),
                    refus_auto=VALUES(refus_auto),
                    refus_moto=VALUES(refus_moto),
                    nb_particuliers=VALUES(nb_particuliers),
                    nb_professionnels=VALUES(nb_professionnels);
            ";

            $this->connection->executeStatement($sql);
            $output->writeln('Table de synthèse remplie correctement (CA opérationnel).');

            return Command::SUCCESS;
        } catch (Exception $e) {
            $output->writeln('<error>Erreur : '.$e->getMessage().'</error>');
            return Command::FAILURE;
        }
    }
}
