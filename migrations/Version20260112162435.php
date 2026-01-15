<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260112162435 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE factures (id INT AUTO_INCREMENT NOT NULL, idfacture BIGINT NOT NULL, date_export DATETIME NOT NULL, type_facture VARCHAR(1) NOT NULL, date_facture DATETIME NOT NULL, date_echeance DATETIME NOT NULL, num_tva_intra VARCHAR(255) NOT NULL, devise VARCHAR(3) NOT NULL, otc_ht NUMERIC(8, 2) NOT NULL, montant_tva_otc NUMERIC(8, 2) NOT NULL, pourcentage_tva_otc NUMERIC(8, 2) NOT NULL, otc_ttc NUMERIC(8, 2) NOT NULL, montant_presta_ht NUMERIC(8, 2) NOT NULL, montant_presta_ttc NUMERIC(8, 2) NOT NULL, pourcentage_tva_presta NUMERIC(8, 2) NOT NULL, montant_tva_presta NUMERIC(8, 2) NOT NULL, montant_remise NUMERIC(8, 2) NOT NULL, pourcentage_remise NUMERIC(8, 2) NOT NULL, total_ht NUMERIC(8, 2) NOT NULL, total_ttc NUMERIC(8, 2) NOT NULL, pourcentage_tva NUMERIC(8, 2) NOT NULL, montant_tva NUMERIC(8, 2) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE reglements ADD banque VARCHAR(255) DEFAULT NULL, ADD numero_cheque VARCHAR(50) DEFAULT NULL, ADD numero_releve VARCHAR(50) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE factures');
        $this->addSql('ALTER TABLE reglements DROP banque, DROP numero_cheque, DROP numero_releve');
    }
}
