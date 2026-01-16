<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260116114046 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE centres_clients (id INT AUTO_INCREMENT NOT NULL, agr_centre VARCHAR(8) NOT NULL, idclient BIGINT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE clients (id INT AUTO_INCREMENT NOT NULL, idclient BIGINT NOT NULL, date_export DATETIME NOT NULL, date_creation DATETIME NOT NULL, code_client VARCHAR(20) DEFAULT NULL, nom_code_client VARCHAR(255) DEFAULT NULL, code_sage VARCHAR(25) DEFAULT NULL, nom VARCHAR(255) NOT NULL, prenom VARCHAR(255) DEFAULT NULL, adresse1 VARCHAR(255) DEFAULT NULL, adresse2 VARCHAR(255) DEFAULT NULL, cp VARCHAR(5) NOT NULL, ville VARCHAR(255) NOT NULL, email VARCHAR(255) DEFAULT NULL, telephone VARCHAR(12) DEFAULT NULL, mobile VARCHAR(12) DEFAULT NULL, num_tva_intra VARCHAR(255) DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE clients_controles (id INT AUTO_INCREMENT NOT NULL, idclient BIGINT NOT NULL, idcontrole BIGINT NOT NULL, agr_centre VARCHAR(8) NOT NULL, agr_controleur VARCHAR(8) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE controles (id INT AUTO_INCREMENT NOT NULL, idcontrole BIGINT NOT NULL, date_export DATETIME NOT NULL, num_pv_ctrl VARCHAR(20) NOT NULL, num_lia_ctrl VARCHAR(20) NOT NULL, immat_vehicule VARCHAR(12) NOT NULL, num_serie_vehicule VARCHAR(12) NOT NULL, date_prise_rdv DATETIME DEFAULT NULL, type_rdv VARCHAR(1) NOT NULL, deb_ctrl DATETIME NOT NULL, fin_ctrl DATETIME NOT NULL, date_ctrl DATETIME NOT NULL, temps_ctrl SMALLINT NOT NULL, ref_temps SMALLINT NOT NULL, res_ctrl VARCHAR(2) NOT NULL, type_ctrl VARCHAR(5) NOT NULL, modele_vehicule VARCHAR(255) NOT NULL, annee_circulation INT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE controles_factures (id INT AUTO_INCREMENT NOT NULL, idcontrole BIGINT NOT NULL, idfacture BIGINT NOT NULL, agr_centre VARCHAR(8) NOT NULL, agr_controleur VARCHAR(8) NOT NULL, idclient BIGINT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE controles_non_factures (id INT AUTO_INCREMENT NOT NULL, idcontrole BIGINT NOT NULL, agr_centre VARCHAR(8) NOT NULL, agr_controleur VARCHAR(8) NOT NULL, idclient BIGINT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE factures (id INT AUTO_INCREMENT NOT NULL, idfacture BIGINT NOT NULL, date_export DATETIME NOT NULL, num_facture BIGINT NOT NULL, type_facture VARCHAR(1) NOT NULL, date_facture DATETIME NOT NULL, date_echeance DATETIME NOT NULL, num_tva_intra VARCHAR(255) NOT NULL, devise VARCHAR(3) NOT NULL, otc_ht NUMERIC(8, 2) NOT NULL, montant_tva_otc_ht NUMERIC(8, 2) NOT NULL, pourcentage_tva_otc NUMERIC(8, 2) NOT NULL, otc_ttc NUMERIC(8, 2) NOT NULL, montant_presta_ht NUMERIC(8, 2) NOT NULL, montant_presta_ttc NUMERIC(8, 2) NOT NULL, pourcentage_tva_presta NUMERIC(8, 2) NOT NULL, montant_tva_presta NUMERIC(8, 2) NOT NULL, montant_remise NUMERIC(8, 2) NOT NULL, pourcentage_remise NUMERIC(8, 2) NOT NULL, total_ht NUMERIC(8, 2) NOT NULL, total_ttc NUMERIC(8, 2) NOT NULL, pourcentage_tva NUMERIC(8, 2) NOT NULL, montant_tva NUMERIC(8, 2) NOT NULL, numero_releve VARCHAR(50) DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE factures_reglements (id INT AUTO_INCREMENT NOT NULL, idfacture BIGINT NOT NULL, idreglement BIGINT NOT NULL, agr_centre VARCHAR(8) NOT NULL, idclient BIGINT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE prestas_non_facturees (id INT AUTO_INCREMENT NOT NULL, idcontrole BIGINT NOT NULL, date_export DATETIME NOT NULL, devise VARCHAR(3) NOT NULL, otc_ht NUMERIC(8, 2) NOT NULL, montant_tva_otc_ht NUMERIC(8, 2) NOT NULL, pourcentage_tva_otc NUMERIC(8, 2) NOT NULL, otc_ttc NUMERIC(8, 2) NOT NULL, montant_presta_ht NUMERIC(8, 2) NOT NULL, montant_presta_ttc NUMERIC(8, 2) NOT NULL, pourcentage_tva_presta NUMERIC(8, 2) NOT NULL, montant_tva_presta NUMERIC(8, 2) NOT NULL, montant_remise NUMERIC(8, 2) NOT NULL, pourcentage_remise NUMERIC(8, 2) NOT NULL, total_ht NUMERIC(8, 2) NOT NULL, total_ttc NUMERIC(8, 2) NOT NULL, pourcentage_tva NUMERIC(8, 2) NOT NULL, montant_tva NUMERIC(8, 2) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE reglements (id INT AUTO_INCREMENT NOT NULL, idreglement BIGINT NOT NULL, date_export DATETIME NOT NULL, mode_reglt VARCHAR(3) NOT NULL, date_reglt DATETIME NOT NULL, montant_reglt NUMERIC(8, 2) NOT NULL, banque VARCHAR(255) DEFAULT NULL, numero_cheque VARCHAR(50) DEFAULT NULL, numero_releve VARCHAR(50) DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE centres_clients');
        $this->addSql('DROP TABLE clients');
        $this->addSql('DROP TABLE clients_controles');
        $this->addSql('DROP TABLE controles');
        $this->addSql('DROP TABLE controles_factures');
        $this->addSql('DROP TABLE controles_non_factures');
        $this->addSql('DROP TABLE factures');
        $this->addSql('DROP TABLE factures_reglements');
        $this->addSql('DROP TABLE prestas_non_facturees');
        $this->addSql('DROP TABLE reglements');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
