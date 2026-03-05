<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260304090438 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE synthese_controles');
        $this->addSql('DROP TABLE synthese_meta');
        $this->addSql('DROP TABLE synthese_pros');
        $this->addSql('ALTER TABLE centre ADD agr_cl_centre VARCHAR(50) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C6A0EA752EDF2C34 ON centre (agr_cl_centre)');
        $this->addSql('DROP INDEX idx_clients_code_client ON clients');
        $this->addSql('DROP INDEX idx_clients_idclient ON clients');
        $this->addSql('DROP INDEX idx_clients_nom_code ON clients');
        $this->addSql('DROP INDEX idx_perf_clients_idclient_code ON clients');
        $this->addSql('DROP INDEX idx_cc_agr_centre ON clients_controles');
        $this->addSql('DROP INDEX idx_cc_idclient ON clients_controles');
        $this->addSql('DROP INDEX idx_cc_idcontrole ON clients_controles');
        $this->addSql('DROP INDEX idx_cc_idcontrole_idclient ON clients_controles');
        $this->addSql('DROP INDEX idx_perf_clients_controles_ctrl_client_centre ON clients_controles');
        $this->addSql('DROP INDEX idx_controles_date_ctrl ON controles');
        $this->addSql('DROP INDEX idx_controles_date_type ON controles');
        $this->addSql('DROP INDEX idx_controles_idcontrole ON controles');
        $this->addSql('DROP INDEX idx_controles_immat ON controles');
        $this->addSql('DROP INDEX idx_controles_type_res ON controles');
        $this->addSql('DROP INDEX idx_perf_controles_date_reseau_type ON controles');
        $this->addSql('DROP INDEX idx_cf_idcontrole ON controles_factures');
        $this->addSql('DROP INDEX idx_cf_idcontrole_idfacture ON controles_factures');
        $this->addSql('DROP INDEX idx_cf_idfacture ON controles_factures');
        $this->addSql('DROP INDEX idx_cf_idfacture_idcontrole ON controles_factures');
        $this->addSql('DROP INDEX idx_factures_date_facture ON factures');
        $this->addSql('DROP INDEX idx_factures_idfacture ON factures');
        $this->addSql('DROP INDEX idx_factures_total_ht ON factures');
        $this->addSql('DROP INDEX idx_factures_type_date ON factures');
        $this->addSql('DROP INDEX idx_factures_type_date_total ON factures');
        $this->addSql('DROP INDEX idx_factures_type_facture ON factures');
        $this->addSql('DROP INDEX idx_perf_factures_idfacture_type_total ON factures');
        $this->addSql('DROP INDEX idx_perf_salarie_agr_cl_controleur ON salarie');
        $this->addSql('DROP INDEX idx_perf_salarie_agr_controleur ON salarie');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE synthese_controles (id INT AUTO_INCREMENT NOT NULL, societe_nom VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, agr_centre VARCHAR(50) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, centre_ville VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'\' COLLATE `utf8mb4_0900_ai_ci`, reseau_id INT NOT NULL, reseau_nom VARCHAR(50) CHARACTER SET utf8mb4 DEFAULT \'\' COLLATE `utf8mb4_0900_ai_ci`, salarie_id INT NOT NULL, salarie_agr VARCHAR(20) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, salarie_nom VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, salarie_prenom VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, annee INT NOT NULL, mois INT NOT NULL, nb_controles INT DEFAULT 0 NOT NULL, nb_vtp INT DEFAULT 0 NOT NULL, nb_vtp_particuliers INT DEFAULT 0 NOT NULL, nb_vtp_professionnels INT DEFAULT 0 NOT NULL, nb_clvtp INT DEFAULT 0 NOT NULL, nb_clvtp_particuliers INT DEFAULT 0 NOT NULL, nb_clvtp_professionnels INT DEFAULT 0 NOT NULL, nb_cv INT DEFAULT 0 NOT NULL, nb_cv_particuliers INT DEFAULT 0 NOT NULL, nb_cv_professionnels INT DEFAULT 0 NOT NULL, nb_clcv INT DEFAULT 0 NOT NULL, nb_clcv_particuliers INT DEFAULT 0 NOT NULL, nb_clcv_professionnels INT DEFAULT 0 NOT NULL, nb_vtc INT DEFAULT 0 NOT NULL, nb_vtc_particuliers INT DEFAULT 0 NOT NULL, nb_vtc_professionnels INT DEFAULT 0 NOT NULL, nb_vol INT DEFAULT 0 NOT NULL, nb_vol_particuliers INT DEFAULT 0 NOT NULL, nb_vol_professionnels INT DEFAULT 0 NOT NULL, nb_clvol INT DEFAULT 0 NOT NULL, nb_clvol_particuliers INT DEFAULT 0 NOT NULL, nb_clvol_professionnels INT DEFAULT 0 NOT NULL, nb_auto INT DEFAULT 0 NOT NULL, nb_moto INT DEFAULT 0 NOT NULL, total_presta_ht NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL, total_presta_ht_particuliers NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL, total_presta_ht_professionnels NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL, total_ht_vtp NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL, total_ht_vtp_particuliers NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL, total_ht_vtp_professionnels NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL, total_ht_clvtp NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL, total_ht_clvtp_particuliers NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL, total_ht_clvtp_professionnels NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL, total_ht_cv NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL, total_ht_cv_particuliers NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL, total_ht_cv_professionnels NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL, total_ht_clcv NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL, total_ht_clcv_particuliers NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL, total_ht_clcv_professionnels NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL, total_ht_vtc NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL, total_ht_vtc_particuliers NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL, total_ht_vtc_professionnels NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL, total_ht_vol NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL, total_ht_vol_particuliers NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL, total_ht_vol_professionnels NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL, total_ht_clvol NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL, total_ht_clvol_particuliers NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL, total_ht_clvol_professionnels NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL, temps_total INT DEFAULT 0 NOT NULL, temps_total_auto INT DEFAULT 0 NOT NULL, temps_total_moto INT DEFAULT 0 NOT NULL, taux_refus NUMERIC(5, 2) DEFAULT \'0.00\' NOT NULL, refus_auto INT DEFAULT 0 NOT NULL, refus_moto INT DEFAULT 0 NOT NULL, nb_particuliers INT DEFAULT 0 NOT NULL, nb_professionnels INT DEFAULT 0 NOT NULL, nb_particuliers_auto INT DEFAULT 0 NOT NULL, nb_particuliers_moto INT DEFAULT 0 NOT NULL, nb_professionnels_auto INT DEFAULT 0 NOT NULL, nb_professionnels_moto INT DEFAULT 0 NOT NULL, INDEX idx_synthese_periode (annee, mois), UNIQUE INDEX unique_salarie_mois_annee (salarie_id, salarie_agr, agr_centre, annee, mois), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE synthese_meta (meta_key VARCHAR(64) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, last_run_at DATETIME NOT NULL, PRIMARY KEY (meta_key)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE synthese_pros (id INT AUTO_INCREMENT NOT NULL, code_client VARCHAR(50) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, annee INT NOT NULL, mois INT NOT NULL, ca NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL, ca_auto NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL, ca_moto NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL, nb_controles INT DEFAULT 0 NOT NULL, nb_controles_auto INT DEFAULT 0 NOT NULL, nb_controles_moto INT DEFAULT 0 NOT NULL, ca_vtp NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL, ca_clvtp NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL, ca_cv NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL, ca_clcv NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL, ca_vtc NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL, ca_vol NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL, ca_clvol NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL, nb_vtp INT DEFAULT 0 NOT NULL, nb_clvtp INT DEFAULT 0 NOT NULL, nb_cv INT DEFAULT 0 NOT NULL, nb_clcv INT DEFAULT 0 NOT NULL, nb_vtc INT DEFAULT 0 NOT NULL, nb_vol INT DEFAULT 0 NOT NULL, nb_clvol INT DEFAULT 0 NOT NULL, agr_centre VARCHAR(50) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, societe_nom VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, reseau_id INT NOT NULL, reseau_nom VARCHAR(50) CHARACTER SET utf8mb4 DEFAULT \'\' NOT NULL COLLATE `utf8mb4_0900_ai_ci`, INDEX idx_synthese_pros_periode (annee, mois), UNIQUE INDEX unique_client_annee_mois_centre_societe (code_client, annee, mois, agr_centre, societe_nom), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('DROP INDEX UNIQ_C6A0EA752EDF2C34 ON centre');
        $this->addSql('ALTER TABLE centre DROP agr_cl_centre');
        $this->addSql('CREATE INDEX idx_clients_code_client ON clients (code_client)');
        $this->addSql('CREATE INDEX idx_clients_idclient ON clients (idclient)');
        $this->addSql('CREATE INDEX idx_clients_nom_code ON clients (nom_code_client(191))');
        $this->addSql('CREATE INDEX idx_perf_clients_idclient_code ON clients (idclient, code_client, nom_code_client(120))');
        $this->addSql('CREATE INDEX idx_cc_agr_centre ON clients_controles (agr_centre)');
        $this->addSql('CREATE INDEX idx_cc_idclient ON clients_controles (idclient)');
        $this->addSql('CREATE INDEX idx_cc_idcontrole ON clients_controles (idcontrole)');
        $this->addSql('CREATE INDEX idx_cc_idcontrole_idclient ON clients_controles (idcontrole, idclient)');
        $this->addSql('CREATE INDEX idx_perf_clients_controles_ctrl_client_centre ON clients_controles (idcontrole, idclient, agr_centre)');
        $this->addSql('CREATE INDEX idx_controles_date_ctrl ON controles (date_ctrl)');
        $this->addSql('CREATE INDEX idx_controles_date_type ON controles (date_ctrl, type_ctrl)');
        $this->addSql('CREATE INDEX idx_controles_idcontrole ON controles (idcontrole)');
        $this->addSql('CREATE INDEX idx_controles_immat ON controles (immat_vehicule)');
        $this->addSql('CREATE INDEX idx_controles_type_res ON controles (type_ctrl, res_ctrl)');
        $this->addSql('CREATE INDEX idx_perf_controles_date_reseau_type ON controles (date_ctrl, reseau_id, type_ctrl)');
        $this->addSql('CREATE INDEX idx_cf_idcontrole ON controles_factures (idcontrole)');
        $this->addSql('CREATE INDEX idx_cf_idcontrole_idfacture ON controles_factures (idcontrole, idfacture)');
        $this->addSql('CREATE INDEX idx_cf_idfacture ON controles_factures (idfacture)');
        $this->addSql('CREATE INDEX idx_cf_idfacture_idcontrole ON controles_factures (idfacture, idcontrole)');
        $this->addSql('CREATE INDEX idx_factures_date_facture ON factures (date_facture)');
        $this->addSql('CREATE INDEX idx_factures_idfacture ON factures (idfacture)');
        $this->addSql('CREATE INDEX idx_factures_total_ht ON factures (total_ht)');
        $this->addSql('CREATE INDEX idx_factures_type_date ON factures (type_facture, date_facture)');
        $this->addSql('CREATE INDEX idx_factures_type_date_total ON factures (type_facture, date_facture, total_ht)');
        $this->addSql('CREATE INDEX idx_factures_type_facture ON factures (type_facture)');
        $this->addSql('CREATE INDEX idx_perf_factures_idfacture_type_total ON factures (idfacture, type_facture, total_ht)');
        $this->addSql('CREATE INDEX idx_perf_salarie_agr_cl_controleur ON salarie (agr_cl_controleur)');
        $this->addSql('CREATE INDEX idx_perf_salarie_agr_controleur ON salarie (agr_controleur)');
    }
}
