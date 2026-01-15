<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260114150812 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE centres_clients ADD CONSTRAINT FK_16BB6A2AA3F9A9F9 FOREIGN KEY (idclient) REFERENCES clients (idclient)');
        $this->addSql('ALTER TABLE clients_controles ADD CONSTRAINT FK_E0443B21A3F9A9F9 FOREIGN KEY (idclient) REFERENCES clients (idclient)');
        $this->addSql('ALTER TABLE clients_controles ADD CONSTRAINT FK_E0443B21A4EC7163 FOREIGN KEY (idcontrole) REFERENCES controles (idcontrole)');
        $this->addSql('ALTER TABLE controles_factures ADD CONSTRAINT FK_B77B2F69A4EC7163 FOREIGN KEY (idcontrole) REFERENCES controles (idcontrole)');
        $this->addSql('ALTER TABLE controles_factures ADD CONSTRAINT FK_B77B2F692182367E FOREIGN KEY (idfacture) REFERENCES factures (idfacture)');
        $this->addSql('ALTER TABLE controles_factures ADD CONSTRAINT FK_B77B2F69A3F9A9F9 FOREIGN KEY (idclient) REFERENCES clients (idclient)');
        $this->addSql('ALTER TABLE controles_non_factures ADD CONSTRAINT FK_76FCF60EA4EC7163 FOREIGN KEY (idcontrole) REFERENCES controles (idcontrole)');
        $this->addSql('ALTER TABLE controles_non_factures ADD CONSTRAINT FK_76FCF60EA3F9A9F9 FOREIGN KEY (idclient) REFERENCES clients (idclient)');
        $this->addSql('ALTER TABLE factures CHANGE montant_tva_otc montant_tva_otc_ht NUMERIC(8, 2) NOT NULL');
        $this->addSql('ALTER TABLE factures_reglements ADD CONSTRAINT FK_1D789B862182367E FOREIGN KEY (idfacture) REFERENCES factures (idfacture)');
        $this->addSql('ALTER TABLE factures_reglements ADD CONSTRAINT FK_1D789B8695FF68B9 FOREIGN KEY (idreglement) REFERENCES reglements (idreglement)');
        $this->addSql('ALTER TABLE factures_reglements ADD CONSTRAINT FK_1D789B86A3F9A9F9 FOREIGN KEY (idclient) REFERENCES clients (idclient)');
        $this->addSql('ALTER TABLE prestas_non_facturees ADD CONSTRAINT FK_6E23438DA4EC7163 FOREIGN KEY (idcontrole) REFERENCES controles (idcontrole)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE centres_clients DROP FOREIGN KEY FK_16BB6A2AA3F9A9F9');
        $this->addSql('ALTER TABLE clients_controles DROP FOREIGN KEY FK_E0443B21A3F9A9F9');
        $this->addSql('ALTER TABLE clients_controles DROP FOREIGN KEY FK_E0443B21A4EC7163');
        $this->addSql('ALTER TABLE controles_factures DROP FOREIGN KEY FK_B77B2F69A4EC7163');
        $this->addSql('ALTER TABLE controles_factures DROP FOREIGN KEY FK_B77B2F692182367E');
        $this->addSql('ALTER TABLE controles_factures DROP FOREIGN KEY FK_B77B2F69A3F9A9F9');
        $this->addSql('ALTER TABLE controles_non_factures DROP FOREIGN KEY FK_76FCF60EA4EC7163');
        $this->addSql('ALTER TABLE controles_non_factures DROP FOREIGN KEY FK_76FCF60EA3F9A9F9');
        $this->addSql('ALTER TABLE factures CHANGE montant_tva_otc_ht montant_tva_otc NUMERIC(8, 2) NOT NULL');
        $this->addSql('ALTER TABLE factures_reglements DROP FOREIGN KEY FK_1D789B862182367E');
        $this->addSql('ALTER TABLE factures_reglements DROP FOREIGN KEY FK_1D789B8695FF68B9');
        $this->addSql('ALTER TABLE factures_reglements DROP FOREIGN KEY FK_1D789B86A3F9A9F9');
        $this->addSql('ALTER TABLE prestas_non_facturees DROP FOREIGN KEY FK_6E23438DA4EC7163');
    }
}
