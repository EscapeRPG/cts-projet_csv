<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260114111546 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE imported_files');
        $this->addSql('ALTER TABLE centres_clients ADD CONSTRAINT FK_16BB6A2AA3F9A9F9 FOREIGN KEY (idclient) REFERENCES clients (idclient)');
        $this->addSql('ALTER TABLE clients_controles ADD CONSTRAINT FK_E0443B21A3F9A9F9 FOREIGN KEY (idclient) REFERENCES clients (idclient)');
        $this->addSql('ALTER TABLE clients_controles ADD CONSTRAINT FK_E0443B21A4EC7163 FOREIGN KEY (idcontrole) REFERENCES controles (idcontrole)');
        $this->addSql('ALTER TABLE controles_factures ADD CONSTRAINT FK_B77B2F69A4EC7163 FOREIGN KEY (idcontrole) REFERENCES controles (idcontrole)');
        $this->addSql('ALTER TABLE controles_factures ADD CONSTRAINT FK_B77B2F692182367E FOREIGN KEY (idfacture) REFERENCES factures (idfacture)');
        $this->addSql('ALTER TABLE controles_factures ADD CONSTRAINT FK_B77B2F69A3F9A9F9 FOREIGN KEY (idclient) REFERENCES clients (idclient)');
        $this->addSql('ALTER TABLE controles_non_factures ADD CONSTRAINT FK_76FCF60EA4EC7163 FOREIGN KEY (idcontrole) REFERENCES controles (idcontrole)');
        $this->addSql('ALTER TABLE controles_non_factures ADD CONSTRAINT FK_76FCF60EA3F9A9F9 FOREIGN KEY (idclient) REFERENCES clients (idclient)');
        $this->addSql('ALTER TABLE factures_reglements ADD CONSTRAINT FK_1D789B862182367E FOREIGN KEY (idfacture) REFERENCES factures (idfacture)');
        $this->addSql('ALTER TABLE factures_reglements ADD CONSTRAINT FK_1D789B8695FF68B9 FOREIGN KEY (idreglement) REFERENCES reglements (idreglement)');
        $this->addSql('ALTER TABLE factures_reglements ADD CONSTRAINT FK_1D789B86A3F9A9F9 FOREIGN KEY (idclient) REFERENCES clients (idclient)');
        $this->addSql('ALTER TABLE prestas_non_facturees ADD CONSTRAINT FK_6E23438DA4EC7163 FOREIGN KEY (idcontrole) REFERENCES controles (idcontrole)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE imported_files (id INT AUTO_INCREMENT NOT NULL, filename VARCHAR(150) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, imported_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_D8475CB73C0BE965 (filename), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = MyISAM COMMENT = \'\' ');
        $this->addSql('ALTER TABLE centres_clients DROP FOREIGN KEY FK_16BB6A2AA3F9A9F9');
        $this->addSql('ALTER TABLE clients_controles DROP FOREIGN KEY FK_E0443B21A3F9A9F9');
        $this->addSql('ALTER TABLE clients_controles DROP FOREIGN KEY FK_E0443B21A4EC7163');
        $this->addSql('ALTER TABLE controles_factures DROP FOREIGN KEY FK_B77B2F69A4EC7163');
        $this->addSql('ALTER TABLE controles_factures DROP FOREIGN KEY FK_B77B2F692182367E');
        $this->addSql('ALTER TABLE controles_factures DROP FOREIGN KEY FK_B77B2F69A3F9A9F9');
        $this->addSql('ALTER TABLE controles_non_factures DROP FOREIGN KEY FK_76FCF60EA4EC7163');
        $this->addSql('ALTER TABLE controles_non_factures DROP FOREIGN KEY FK_76FCF60EA3F9A9F9');
        $this->addSql('ALTER TABLE factures_reglements DROP FOREIGN KEY FK_1D789B862182367E');
        $this->addSql('ALTER TABLE factures_reglements DROP FOREIGN KEY FK_1D789B8695FF68B9');
        $this->addSql('ALTER TABLE factures_reglements DROP FOREIGN KEY FK_1D789B86A3F9A9F9');
        $this->addSql('ALTER TABLE prestas_non_facturees DROP FOREIGN KEY FK_6E23438DA4EC7163');
    }
}
