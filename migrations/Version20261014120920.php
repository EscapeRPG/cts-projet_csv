<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261014120920 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE outils_reseaux (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(18) NOT NULL, logo VARCHAR(255) DEFAULT NULL, lien VARCHAR(255) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
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
        $this->addSql('DROP INDEX idx_reglements_idreglement ON reglements');
        $this->addSql('DROP INDEX idx_reglements_date_reglt ON reglements');
        $this->addSql('DROP INDEX idx_reglements_idreglement_export ON reglements');
        $this->addSql('DROP INDEX idx_perf_salarie_agr_controleur ON salarie');
        $this->addSql('DROP INDEX idx_perf_salarie_agr_cl_controleur ON salarie');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE outils_reseaux');
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
        $this->addSql('CREATE INDEX idx_reglements_idreglement ON reglements (idreglement)');
        $this->addSql('CREATE INDEX idx_reglements_date_reglt ON reglements (date_reglt)');
        $this->addSql('CREATE INDEX idx_reglements_idreglement_export ON reglements (idreglement, date_export, id)');
        $this->addSql('CREATE INDEX idx_perf_salarie_agr_controleur ON salarie (agr_controleur)');
        $this->addSql('CREATE INDEX idx_perf_salarie_agr_cl_controleur ON salarie (agr_cl_controleur)');
    }
}
