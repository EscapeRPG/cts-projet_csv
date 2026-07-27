<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260727131047 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE import_health_check DROP FOREIGN KEY `FK_D1E22214445D170C`');
        $this->addSql('DROP TABLE import_health_check');
        $this->addSql('ALTER TABLE centre ADD email_orange VARCHAR(100) DEFAULT NULL, ADD mail_orange_password VARCHAR(250) DEFAULT NULL');
        $this->addSql('DROP INDEX idx_clients_idclient ON clients');
        $this->addSql('DROP INDEX idx_clients_code_client ON clients');
        $this->addSql('DROP INDEX idx_clients_nom_code ON clients');
        $this->addSql('DROP INDEX idx_perf_clients_idclient_code ON clients');
        $this->addSql('DROP INDEX idx_cc_idcontrole ON clients_controles');
        $this->addSql('DROP INDEX idx_cc_idclient ON clients_controles');
        $this->addSql('DROP INDEX idx_cc_idcontrole_idclient ON clients_controles');
        $this->addSql('DROP INDEX idx_cc_agr_centre ON clients_controles');
        $this->addSql('DROP INDEX idx_perf_clients_controles_ctrl_client_centre ON clients_controles');
        $this->addSql('DROP INDEX idx_controles_idcontrole ON controles');
        $this->addSql('DROP INDEX idx_controles_date_ctrl ON controles');
        $this->addSql('DROP INDEX idx_controles_date_type ON controles');
        $this->addSql('DROP INDEX idx_controles_immat ON controles');
        $this->addSql('DROP INDEX idx_controles_type_res ON controles');
        $this->addSql('DROP INDEX idx_perf_controles_date_reseau_type ON controles');
        $this->addSql('DROP INDEX idx_cf_idcontrole ON controles_factures');
        $this->addSql('DROP INDEX idx_cf_idfacture ON controles_factures');
        $this->addSql('DROP INDEX idx_cf_idcontrole_idfacture ON controles_factures');
        $this->addSql('DROP INDEX idx_cf_idfacture_idcontrole ON controles_factures');
        $this->addSql('ALTER TABLE encours_bancaire ADD CONSTRAINT FK_C4CD236BFCF77503 FOREIGN KEY (societe_id) REFERENCES societe (id)');
        $this->addSql('ALTER TABLE encours_montant ADD CONSTRAINT FK_4ADEEF75B085AAD FOREIGN KEY (encours_id) REFERENCES encours_bancaire (id)');
        $this->addSql('DROP INDEX idx_factures_idfacture ON factures');
        $this->addSql('DROP INDEX idx_factures_type_facture ON factures');
        $this->addSql('DROP INDEX idx_factures_date_facture ON factures');
        $this->addSql('DROP INDEX idx_factures_type_date ON factures');
        $this->addSql('DROP INDEX idx_factures_total_ht ON factures');
        $this->addSql('DROP INDEX idx_factures_type_date_total ON factures');
        $this->addSql('DROP INDEX idx_perf_factures_idfacture_type_total ON factures');
        $this->addSql('DROP INDEX idx_fr_idreglement ON factures_reglements');
        $this->addSql('DROP INDEX idx_fr_idfacture ON factures_reglements');
        $this->addSql('DROP INDEX idx_fr_reglement_facture ON factures_reglements');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CA5859934A FOREIGN KEY (salarie_id) REFERENCES salarie (id)');
        $this->addSql('DROP INDEX idx_reglements_idreglement ON reglements');
        $this->addSql('DROP INDEX idx_reglements_date_reglt ON reglements');
        $this->addSql('DROP INDEX idx_reglements_idreglement_export ON reglements');
        $this->addSql('DROP INDEX idx_perf_salarie_agr_controleur ON salarie');
        $this->addSql('DROP INDEX idx_perf_salarie_agr_cl_controleur ON salarie');
        $this->addSql('ALTER TABLE salarie_centre ADD CONSTRAINT FK_105A9AFE5859934A FOREIGN KEY (salarie_id) REFERENCES salarie (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE salarie_centre ADD CONSTRAINT FK_105A9AFE463CD7C3 FOREIGN KEY (centre_id) REFERENCES centre (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_centre ADD CONSTRAINT FK_A3F2F148A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_centre ADD CONSTRAINT FK_A3F2F148463CD7C3 FOREIGN KEY (centre_id) REFERENCES centre (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_notification ADD CONSTRAINT FK_3F980AC8EF1A9D84 FOREIGN KEY (notification_id) REFERENCES notification (id)');
        $this->addSql('ALTER TABLE user_notification ADD CONSTRAINT FK_3F980AC8A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE voiture ADD CONSTRAINT FK_E9E2810FFCF77503 FOREIGN KEY (societe_id) REFERENCES societe (id)');
        $this->addSql('ALTER TABLE voiture ADD CONSTRAINT FK_E9E2810F463CD7C3 FOREIGN KEY (centre_id) REFERENCES centre (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE import_health_check (id INT AUTO_INCREMENT NOT NULL, reseau_id INT DEFAULT NULL, check_date DATE NOT NULL, reseau_name VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, files_imported INT NOT NULL, expected_files INT NOT NULL, controles_files INT NOT NULL, latest_imported_at DATETIME DEFAULT NULL, status VARCHAR(20) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, issues JSON NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX uniq_import_health_reseau_date (reseau_id, check_date), INDEX IDX_D1E22214445D170C (reseau_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE import_health_check ADD CONSTRAINT `FK_D1E22214445D170C` FOREIGN KEY (reseau_id) REFERENCES reseau (id) ON UPDATE NO ACTION ON DELETE SET NULL');
        $this->addSql('ALTER TABLE centre DROP email_orange, DROP mail_orange_password');
        $this->addSql('CREATE INDEX idx_clients_idclient ON clients (idclient)');
        $this->addSql('CREATE INDEX idx_clients_code_client ON clients (code_client)');
        $this->addSql('CREATE INDEX idx_clients_nom_code ON clients (nom_code_client(191))');
        $this->addSql('CREATE INDEX idx_perf_clients_idclient_code ON clients (idclient, code_client, nom_code_client(120))');
        $this->addSql('CREATE INDEX idx_cc_idcontrole ON clients_controles (idcontrole)');
        $this->addSql('CREATE INDEX idx_cc_idclient ON clients_controles (idclient)');
        $this->addSql('CREATE INDEX idx_cc_idcontrole_idclient ON clients_controles (idcontrole, idclient)');
        $this->addSql('CREATE INDEX idx_cc_agr_centre ON clients_controles (agr_centre)');
        $this->addSql('CREATE INDEX idx_perf_clients_controles_ctrl_client_centre ON clients_controles (idcontrole, idclient, agr_centre)');
        $this->addSql('CREATE INDEX idx_controles_idcontrole ON controles (idcontrole)');
        $this->addSql('CREATE INDEX idx_controles_date_ctrl ON controles (date_ctrl)');
        $this->addSql('CREATE INDEX idx_controles_date_type ON controles (date_ctrl, type_ctrl)');
        $this->addSql('CREATE INDEX idx_controles_immat ON controles (immat_vehicule)');
        $this->addSql('CREATE INDEX idx_controles_type_res ON controles (type_ctrl, res_ctrl)');
        $this->addSql('CREATE INDEX idx_perf_controles_date_reseau_type ON controles (date_ctrl, reseau_id, type_ctrl)');
        $this->addSql('CREATE INDEX idx_cf_idcontrole ON controles_factures (idcontrole)');
        $this->addSql('CREATE INDEX idx_cf_idfacture ON controles_factures (idfacture)');
        $this->addSql('CREATE INDEX idx_cf_idcontrole_idfacture ON controles_factures (idcontrole, idfacture)');
        $this->addSql('CREATE INDEX idx_cf_idfacture_idcontrole ON controles_factures (idfacture, idcontrole)');
        $this->addSql('ALTER TABLE encours_bancaire DROP FOREIGN KEY FK_C4CD236BFCF77503');
        $this->addSql('ALTER TABLE encours_montant DROP FOREIGN KEY FK_4ADEEF75B085AAD');
        $this->addSql('CREATE INDEX idx_factures_idfacture ON factures (idfacture)');
        $this->addSql('CREATE INDEX idx_factures_type_facture ON factures (type_facture)');
        $this->addSql('CREATE INDEX idx_factures_date_facture ON factures (date_facture)');
        $this->addSql('CREATE INDEX idx_factures_type_date ON factures (type_facture, date_facture)');
        $this->addSql('CREATE INDEX idx_factures_total_ht ON factures (total_ht)');
        $this->addSql('CREATE INDEX idx_factures_type_date_total ON factures (type_facture, date_facture, total_ht)');
        $this->addSql('CREATE INDEX idx_perf_factures_idfacture_type_total ON factures (idfacture, type_facture, total_ht)');
        $this->addSql('CREATE INDEX idx_fr_idreglement ON factures_reglements (idreglement)');
        $this->addSql('CREATE INDEX idx_fr_idfacture ON factures_reglements (idfacture)');
        $this->addSql('CREATE INDEX idx_fr_reglement_facture ON factures_reglements (idreglement, idfacture)');
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CA5859934A');
        $this->addSql('CREATE INDEX idx_reglements_idreglement ON reglements (idreglement)');
        $this->addSql('CREATE INDEX idx_reglements_date_reglt ON reglements (date_reglt)');
        $this->addSql('CREATE INDEX idx_reglements_idreglement_export ON reglements (idreglement, date_export, id)');
        $this->addSql('CREATE INDEX idx_perf_salarie_agr_controleur ON salarie (agr_controleur)');
        $this->addSql('CREATE INDEX idx_perf_salarie_agr_cl_controleur ON salarie (agr_cl_controleur)');
        $this->addSql('ALTER TABLE salarie_centre DROP FOREIGN KEY FK_105A9AFE5859934A');
        $this->addSql('ALTER TABLE salarie_centre DROP FOREIGN KEY FK_105A9AFE463CD7C3');
        $this->addSql('ALTER TABLE user_centre DROP FOREIGN KEY FK_A3F2F148A76ED395');
        $this->addSql('ALTER TABLE user_centre DROP FOREIGN KEY FK_A3F2F148463CD7C3');
        $this->addSql('ALTER TABLE user_notification DROP FOREIGN KEY FK_3F980AC8EF1A9D84');
        $this->addSql('ALTER TABLE user_notification DROP FOREIGN KEY FK_3F980AC8A76ED395');
        $this->addSql('ALTER TABLE voiture DROP FOREIGN KEY FK_E9E2810FFCF77503');
        $this->addSql('ALTER TABLE voiture DROP FOREIGN KEY FK_E9E2810F463CD7C3');
    }
}
