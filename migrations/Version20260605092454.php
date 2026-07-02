<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260605092454 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX idx_clients_idclient ON clients');
        $this->addSql('DROP INDEX idx_clients_code_client ON clients');
        $this->addSql('DROP INDEX idx_clients_nom_code ON clients');
        $this->addSql('DROP INDEX idx_perf_clients_idclient_code ON clients');
        $this->addSql('ALTER TABLE clients CHANGE idclient idclient VARCHAR(50) DEFAULT NULL, CHANGE date_export date_export DATETIME DEFAULT NULL, CHANGE nom nom VARCHAR(255) DEFAULT NULL, CHANGE cp cp VARCHAR(5) DEFAULT NULL, CHANGE ville ville VARCHAR(255) DEFAULT NULL, CHANGE telephone telephone VARCHAR(20) DEFAULT NULL, CHANGE mobile mobile VARCHAR(20) DEFAULT NULL');
        $this->addSql('DROP INDEX idx_cc_idcontrole ON clients_controles');
        $this->addSql('DROP INDEX idx_cc_idclient ON clients_controles');
        $this->addSql('DROP INDEX idx_cc_idcontrole_idclient ON clients_controles');
        $this->addSql('DROP INDEX idx_cc_agr_centre ON clients_controles');
        $this->addSql('DROP INDEX idx_perf_clients_controles_ctrl_client_centre ON clients_controles');
        $this->addSql('ALTER TABLE clients_controles CHANGE idclient idclient VARCHAR(50) DEFAULT NULL, CHANGE idcontrole idcontrole VARCHAR(50) DEFAULT NULL, CHANGE agr_centre agr_centre VARCHAR(8) DEFAULT NULL, CHANGE agr_controleur agr_controleur VARCHAR(8) DEFAULT NULL');
        $this->addSql('DROP INDEX idx_controles_idcontrole ON controles');
        $this->addSql('DROP INDEX idx_controles_date_ctrl ON controles');
        $this->addSql('DROP INDEX idx_controles_date_type ON controles');
        $this->addSql('DROP INDEX idx_controles_immat ON controles');
        $this->addSql('DROP INDEX idx_controles_type_res ON controles');
        $this->addSql('DROP INDEX idx_perf_controles_date_reseau_type ON controles');
        $this->addSql('ALTER TABLE controles CHANGE idcontrole idcontrole VARCHAR(50) DEFAULT NULL, CHANGE date_export date_export DATETIME DEFAULT NULL, CHANGE num_pv_ctrl num_pv_ctrl VARCHAR(20) DEFAULT NULL, CHANGE num_lia_ctrl num_lia_ctrl VARCHAR(20) DEFAULT NULL, CHANGE immat_vehicule immat_vehicule VARCHAR(12) DEFAULT NULL, CHANGE num_serie_vehicule num_serie_vehicule VARCHAR(12) DEFAULT NULL, CHANGE type_rdv type_rdv VARCHAR(1) DEFAULT NULL, CHANGE deb_ctrl deb_ctrl TIME DEFAULT NULL, CHANGE fin_ctrl fin_ctrl TIME DEFAULT NULL, CHANGE date_ctrl date_ctrl DATE DEFAULT NULL, CHANGE temps_ctrl temps_ctrl SMALLINT DEFAULT NULL, CHANGE ref_temps ref_temps SMALLINT DEFAULT NULL, CHANGE res_ctrl res_ctrl VARCHAR(2) DEFAULT NULL, CHANGE type_ctrl type_ctrl VARCHAR(5) DEFAULT NULL, CHANGE modele_vehicule modele_vehicule VARCHAR(255) DEFAULT NULL, CHANGE annee_circulation annee_circulation INT DEFAULT NULL');
        $this->addSql('DROP INDEX idx_cf_idcontrole ON controles_factures');
        $this->addSql('DROP INDEX idx_cf_idfacture ON controles_factures');
        $this->addSql('DROP INDEX idx_cf_idcontrole_idfacture ON controles_factures');
        $this->addSql('DROP INDEX idx_cf_idfacture_idcontrole ON controles_factures');
        $this->addSql('ALTER TABLE controles_factures CHANGE idcontrole idcontrole VARCHAR(50) DEFAULT NULL, CHANGE idfacture idfacture VARCHAR(50) DEFAULT NULL, CHANGE agr_centre agr_centre VARCHAR(8) DEFAULT NULL, CHANGE agr_controleur agr_controleur VARCHAR(8) DEFAULT NULL, CHANGE idclient idclient VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE controles_non_factures CHANGE idcontrole idcontrole VARCHAR(50) DEFAULT NULL, CHANGE agr_centre agr_centre VARCHAR(8) DEFAULT NULL, CHANGE agr_controleur agr_controleur VARCHAR(8) DEFAULT NULL, CHANGE idclient idclient VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE encours_bancaire ADD CONSTRAINT FK_C4CD236BFCF77503 FOREIGN KEY (societe_id) REFERENCES societe (id)');
        $this->addSql('ALTER TABLE encours_montant ADD CONSTRAINT FK_4ADEEF75B085AAD FOREIGN KEY (encours_id) REFERENCES encours_bancaire (id)');
        $this->addSql('DROP INDEX idx_factures_idfacture ON factures');
        $this->addSql('DROP INDEX idx_factures_type_facture ON factures');
        $this->addSql('DROP INDEX idx_factures_date_facture ON factures');
        $this->addSql('DROP INDEX idx_factures_type_date ON factures');
        $this->addSql('DROP INDEX idx_factures_total_ht ON factures');
        $this->addSql('DROP INDEX idx_factures_type_date_total ON factures');
        $this->addSql('DROP INDEX idx_perf_factures_idfacture_type_total ON factures');
        $this->addSql('ALTER TABLE factures CHANGE idfacture idfacture VARCHAR(50) DEFAULT NULL, CHANGE date_export date_export DATETIME DEFAULT NULL, CHANGE num_facture num_facture VARCHAR(50) DEFAULT NULL, CHANGE type_facture type_facture VARCHAR(1) DEFAULT NULL, CHANGE date_facture date_facture DATETIME DEFAULT NULL, CHANGE num_tva_intra num_tva_intra VARCHAR(255) DEFAULT NULL, CHANGE devise devise VARCHAR(3) DEFAULT NULL, CHANGE otc_ht otc_ht NUMERIC(8, 2) DEFAULT NULL, CHANGE montant_tva_otc_ht montant_tva_otc_ht NUMERIC(8, 2) DEFAULT NULL, CHANGE pourcentage_tva_otc pourcentage_tva_otc NUMERIC(8, 2) DEFAULT NULL, CHANGE otc_ttc otc_ttc NUMERIC(8, 2) DEFAULT NULL, CHANGE montant_presta_ht montant_presta_ht NUMERIC(8, 2) DEFAULT NULL, CHANGE montant_presta_ttc montant_presta_ttc NUMERIC(8, 2) DEFAULT NULL, CHANGE pourcentage_tva_presta pourcentage_tva_presta NUMERIC(8, 2) DEFAULT NULL, CHANGE montant_tva_presta montant_tva_presta NUMERIC(8, 2) DEFAULT NULL, CHANGE montant_remise montant_remise NUMERIC(8, 2) DEFAULT NULL, CHANGE pourcentage_remise pourcentage_remise NUMERIC(8, 2) DEFAULT NULL, CHANGE total_ht total_ht NUMERIC(8, 2) DEFAULT NULL, CHANGE total_ttc total_ttc NUMERIC(8, 2) DEFAULT NULL, CHANGE pourcentage_tva pourcentage_tva NUMERIC(8, 2) DEFAULT NULL, CHANGE montant_tva montant_tva NUMERIC(8, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE factures_reglements CHANGE idfacture idfacture VARCHAR(50) DEFAULT NULL, CHANGE idreglement idreglement VARCHAR(50) DEFAULT NULL, CHANGE agr_centre agr_centre VARCHAR(8) DEFAULT NULL, CHANGE idclient idclient VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CA5859934A FOREIGN KEY (salarie_id) REFERENCES salarie (id)');
        $this->addSql('ALTER TABLE prestas_non_facturees CHANGE idcontrole idcontrole VARCHAR(50) DEFAULT NULL, CHANGE date_export date_export DATETIME DEFAULT NULL, CHANGE devise devise VARCHAR(3) DEFAULT NULL, CHANGE otc_ht otc_ht NUMERIC(8, 2) DEFAULT NULL, CHANGE montant_tva_otc_ht montant_tva_otc_ht NUMERIC(8, 2) DEFAULT NULL, CHANGE pourcentage_tva_otc pourcentage_tva_otc NUMERIC(8, 2) DEFAULT NULL, CHANGE otc_ttc otc_ttc NUMERIC(8, 2) DEFAULT NULL, CHANGE montant_presta_ht montant_presta_ht NUMERIC(8, 2) DEFAULT NULL, CHANGE montant_presta_ttc montant_presta_ttc NUMERIC(8, 2) DEFAULT NULL, CHANGE pourcentage_tva_presta pourcentage_tva_presta NUMERIC(8, 2) DEFAULT NULL, CHANGE montant_tva_presta montant_tva_presta NUMERIC(8, 2) DEFAULT NULL, CHANGE montant_remise montant_remise NUMERIC(8, 2) DEFAULT NULL, CHANGE pourcentage_remise pourcentage_remise NUMERIC(8, 2) DEFAULT NULL, CHANGE total_ht total_ht NUMERIC(8, 2) DEFAULT NULL, CHANGE total_ttc total_ttc NUMERIC(8, 2) DEFAULT NULL, CHANGE pourcentage_tva pourcentage_tva NUMERIC(8, 2) DEFAULT NULL, CHANGE montant_tva montant_tva NUMERIC(8, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE reglements CHANGE idreglement idreglement VARCHAR(50) DEFAULT NULL, CHANGE date_export date_export DATETIME DEFAULT NULL, CHANGE mode_reglt mode_reglt VARCHAR(3) DEFAULT NULL, CHANGE date_reglt date_reglt DATETIME DEFAULT NULL, CHANGE montant_reglt montant_reglt NUMERIC(8, 2) DEFAULT NULL');
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
        $this->addSql('ALTER TABLE clients CHANGE idclient idclient VARCHAR(50) NOT NULL, CHANGE date_export date_export DATETIME NOT NULL, CHANGE nom nom VARCHAR(255) NOT NULL, CHANGE cp cp VARCHAR(5) NOT NULL, CHANGE ville ville VARCHAR(255) NOT NULL, CHANGE telephone telephone VARCHAR(12) DEFAULT NULL, CHANGE mobile mobile VARCHAR(12) DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_clients_idclient ON clients (idclient)');
        $this->addSql('CREATE INDEX idx_clients_code_client ON clients (code_client)');
        $this->addSql('CREATE INDEX idx_clients_nom_code ON clients (nom_code_client(191))');
        $this->addSql('CREATE INDEX idx_perf_clients_idclient_code ON clients (idclient, code_client, nom_code_client(120))');
        $this->addSql('ALTER TABLE clients_controles CHANGE idclient idclient VARCHAR(50) NOT NULL, CHANGE idcontrole idcontrole VARCHAR(50) NOT NULL, CHANGE agr_centre agr_centre VARCHAR(8) NOT NULL, CHANGE agr_controleur agr_controleur VARCHAR(8) NOT NULL');
        $this->addSql('CREATE INDEX idx_cc_idcontrole ON clients_controles (idcontrole)');
        $this->addSql('CREATE INDEX idx_cc_idclient ON clients_controles (idclient)');
        $this->addSql('CREATE INDEX idx_cc_idcontrole_idclient ON clients_controles (idcontrole, idclient)');
        $this->addSql('CREATE INDEX idx_cc_agr_centre ON clients_controles (agr_centre)');
        $this->addSql('CREATE INDEX idx_perf_clients_controles_ctrl_client_centre ON clients_controles (idcontrole, idclient, agr_centre)');
        $this->addSql('ALTER TABLE controles CHANGE idcontrole idcontrole VARCHAR(50) NOT NULL, CHANGE date_export date_export DATETIME NOT NULL, CHANGE num_pv_ctrl num_pv_ctrl VARCHAR(20) NOT NULL, CHANGE num_lia_ctrl num_lia_ctrl VARCHAR(20) NOT NULL, CHANGE immat_vehicule immat_vehicule VARCHAR(12) NOT NULL, CHANGE num_serie_vehicule num_serie_vehicule VARCHAR(12) NOT NULL, CHANGE type_rdv type_rdv VARCHAR(1) NOT NULL, CHANGE deb_ctrl deb_ctrl TIME NOT NULL, CHANGE fin_ctrl fin_ctrl TIME NOT NULL, CHANGE date_ctrl date_ctrl DATE NOT NULL, CHANGE temps_ctrl temps_ctrl SMALLINT NOT NULL, CHANGE ref_temps ref_temps SMALLINT NOT NULL, CHANGE res_ctrl res_ctrl VARCHAR(2) NOT NULL, CHANGE type_ctrl type_ctrl VARCHAR(5) NOT NULL, CHANGE modele_vehicule modele_vehicule VARCHAR(255) NOT NULL, CHANGE annee_circulation annee_circulation INT NOT NULL');
        $this->addSql('CREATE INDEX idx_controles_idcontrole ON controles (idcontrole)');
        $this->addSql('CREATE INDEX idx_controles_date_ctrl ON controles (date_ctrl)');
        $this->addSql('CREATE INDEX idx_controles_date_type ON controles (date_ctrl, type_ctrl)');
        $this->addSql('CREATE INDEX idx_controles_immat ON controles (immat_vehicule)');
        $this->addSql('CREATE INDEX idx_controles_type_res ON controles (type_ctrl, res_ctrl)');
        $this->addSql('CREATE INDEX idx_perf_controles_date_reseau_type ON controles (date_ctrl, reseau_id, type_ctrl)');
        $this->addSql('ALTER TABLE controles_factures CHANGE idcontrole idcontrole VARCHAR(50) NOT NULL, CHANGE idfacture idfacture VARCHAR(50) NOT NULL, CHANGE agr_centre agr_centre VARCHAR(8) NOT NULL, CHANGE agr_controleur agr_controleur VARCHAR(8) NOT NULL, CHANGE idclient idclient VARCHAR(50) NOT NULL');
        $this->addSql('CREATE INDEX idx_cf_idcontrole ON controles_factures (idcontrole)');
        $this->addSql('CREATE INDEX idx_cf_idfacture ON controles_factures (idfacture)');
        $this->addSql('CREATE INDEX idx_cf_idcontrole_idfacture ON controles_factures (idcontrole, idfacture)');
        $this->addSql('CREATE INDEX idx_cf_idfacture_idcontrole ON controles_factures (idfacture, idcontrole)');
        $this->addSql('ALTER TABLE controles_non_factures CHANGE idcontrole idcontrole VARCHAR(50) NOT NULL, CHANGE agr_centre agr_centre VARCHAR(8) NOT NULL, CHANGE agr_controleur agr_controleur VARCHAR(8) NOT NULL, CHANGE idclient idclient VARCHAR(50) NOT NULL');
        $this->addSql('ALTER TABLE encours_bancaire DROP FOREIGN KEY FK_C4CD236BFCF77503');
        $this->addSql('ALTER TABLE encours_montant DROP FOREIGN KEY FK_4ADEEF75B085AAD');
        $this->addSql('ALTER TABLE factures CHANGE idfacture idfacture VARCHAR(50) NOT NULL, CHANGE date_export date_export DATETIME NOT NULL, CHANGE num_facture num_facture VARCHAR(50) NOT NULL, CHANGE type_facture type_facture VARCHAR(1) NOT NULL, CHANGE date_facture date_facture DATETIME NOT NULL, CHANGE num_tva_intra num_tva_intra VARCHAR(255) NOT NULL, CHANGE devise devise VARCHAR(3) NOT NULL, CHANGE otc_ht otc_ht NUMERIC(8, 2) NOT NULL, CHANGE montant_tva_otc_ht montant_tva_otc_ht NUMERIC(8, 2) NOT NULL, CHANGE pourcentage_tva_otc pourcentage_tva_otc NUMERIC(8, 2) NOT NULL, CHANGE otc_ttc otc_ttc NUMERIC(8, 2) NOT NULL, CHANGE montant_presta_ht montant_presta_ht NUMERIC(8, 2) NOT NULL, CHANGE montant_presta_ttc montant_presta_ttc NUMERIC(8, 2) NOT NULL, CHANGE pourcentage_tva_presta pourcentage_tva_presta NUMERIC(8, 2) NOT NULL, CHANGE montant_tva_presta montant_tva_presta NUMERIC(8, 2) NOT NULL, CHANGE montant_remise montant_remise NUMERIC(8, 2) NOT NULL, CHANGE pourcentage_remise pourcentage_remise NUMERIC(8, 2) NOT NULL, CHANGE total_ht total_ht NUMERIC(8, 2) NOT NULL, CHANGE total_ttc total_ttc NUMERIC(8, 2) NOT NULL, CHANGE pourcentage_tva pourcentage_tva NUMERIC(8, 2) NOT NULL, CHANGE montant_tva montant_tva NUMERIC(8, 2) NOT NULL');
        $this->addSql('CREATE INDEX idx_factures_idfacture ON factures (idfacture)');
        $this->addSql('CREATE INDEX idx_factures_type_facture ON factures (type_facture)');
        $this->addSql('CREATE INDEX idx_factures_date_facture ON factures (date_facture)');
        $this->addSql('CREATE INDEX idx_factures_type_date ON factures (type_facture, date_facture)');
        $this->addSql('CREATE INDEX idx_factures_total_ht ON factures (total_ht)');
        $this->addSql('CREATE INDEX idx_factures_type_date_total ON factures (type_facture, date_facture, total_ht)');
        $this->addSql('CREATE INDEX idx_perf_factures_idfacture_type_total ON factures (idfacture, type_facture, total_ht)');
        $this->addSql('ALTER TABLE factures_reglements CHANGE idfacture idfacture VARCHAR(50) NOT NULL, CHANGE idreglement idreglement VARCHAR(50) NOT NULL, CHANGE agr_centre agr_centre VARCHAR(8) NOT NULL, CHANGE idclient idclient VARCHAR(50) NOT NULL');
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CA5859934A');
        $this->addSql('ALTER TABLE prestas_non_facturees CHANGE idcontrole idcontrole VARCHAR(50) NOT NULL, CHANGE date_export date_export DATETIME NOT NULL, CHANGE devise devise VARCHAR(3) NOT NULL, CHANGE otc_ht otc_ht NUMERIC(8, 2) NOT NULL, CHANGE montant_tva_otc_ht montant_tva_otc_ht NUMERIC(8, 2) NOT NULL, CHANGE pourcentage_tva_otc pourcentage_tva_otc NUMERIC(8, 2) NOT NULL, CHANGE otc_ttc otc_ttc NUMERIC(8, 2) NOT NULL, CHANGE montant_presta_ht montant_presta_ht NUMERIC(8, 2) NOT NULL, CHANGE montant_presta_ttc montant_presta_ttc NUMERIC(8, 2) NOT NULL, CHANGE pourcentage_tva_presta pourcentage_tva_presta NUMERIC(8, 2) NOT NULL, CHANGE montant_tva_presta montant_tva_presta NUMERIC(8, 2) NOT NULL, CHANGE montant_remise montant_remise NUMERIC(8, 2) NOT NULL, CHANGE pourcentage_remise pourcentage_remise NUMERIC(8, 2) NOT NULL, CHANGE total_ht total_ht NUMERIC(8, 2) NOT NULL, CHANGE total_ttc total_ttc NUMERIC(8, 2) NOT NULL, CHANGE pourcentage_tva pourcentage_tva NUMERIC(8, 2) NOT NULL, CHANGE montant_tva montant_tva NUMERIC(8, 2) NOT NULL');
        $this->addSql('ALTER TABLE reglements CHANGE idreglement idreglement VARCHAR(50) NOT NULL, CHANGE date_export date_export DATETIME NOT NULL, CHANGE mode_reglt mode_reglt VARCHAR(3) NOT NULL, CHANGE date_reglt date_reglt DATETIME NOT NULL, CHANGE montant_reglt montant_reglt NUMERIC(8, 2) NOT NULL');
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
