<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260113122422 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE clients_controles (id INT AUTO_INCREMENT NOT NULL, agr_centre VARCHAR(8) NOT NULL, agr_controleur VARCHAR(8) NOT NULL, idclient BIGINT NOT NULL, idcontrole BIGINT NOT NULL, INDEX IDX_E0443B21A3F9A9F9 (idclient), INDEX IDX_E0443B21A4EC7163 (idcontrole), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE controles_factures (id INT AUTO_INCREMENT NOT NULL, agr_centre VARCHAR(8) NOT NULL, agr_controleur VARCHAR(8) NOT NULL, idcontrole BIGINT NOT NULL, idfacture BIGINT NOT NULL, idclient BIGINT NOT NULL, INDEX IDX_B77B2F69A4EC7163 (idcontrole), INDEX IDX_B77B2F692182367E (idfacture), INDEX IDX_B77B2F69A3F9A9F9 (idclient), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE factures_reglements (id INT AUTO_INCREMENT NOT NULL, agr_centre VARCHAR(8) NOT NULL, idfacture BIGINT NOT NULL, idreglement BIGINT NOT NULL, idclient BIGINT NOT NULL, INDEX IDX_1D789B862182367E (idfacture), INDEX IDX_1D789B8695FF68B9 (idreglement), INDEX IDX_1D789B86A3F9A9F9 (idclient), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE clients_controles ADD CONSTRAINT FK_E0443B21A3F9A9F9 FOREIGN KEY (idclient) REFERENCES clients (idclient)');
        $this->addSql('ALTER TABLE clients_controles ADD CONSTRAINT FK_E0443B21A4EC7163 FOREIGN KEY (idcontrole) REFERENCES controles (idcontrole)');
        $this->addSql('ALTER TABLE controles_factures ADD CONSTRAINT FK_B77B2F69A4EC7163 FOREIGN KEY (idcontrole) REFERENCES controles (idcontrole)');
        $this->addSql('ALTER TABLE controles_factures ADD CONSTRAINT FK_B77B2F692182367E FOREIGN KEY (idfacture) REFERENCES factures (idfacture)');
        $this->addSql('ALTER TABLE controles_factures ADD CONSTRAINT FK_B77B2F69A3F9A9F9 FOREIGN KEY (idclient) REFERENCES clients (idclient)');
        $this->addSql('ALTER TABLE factures_reglements ADD CONSTRAINT FK_1D789B862182367E FOREIGN KEY (idfacture) REFERENCES factures (idfacture)');
        $this->addSql('ALTER TABLE factures_reglements ADD CONSTRAINT FK_1D789B8695FF68B9 FOREIGN KEY (idreglement) REFERENCES reglements (idreglement)');
        $this->addSql('ALTER TABLE factures_reglements ADD CONSTRAINT FK_1D789B86A3F9A9F9 FOREIGN KEY (idclient) REFERENCES clients (idclient)');
        $this->addSql('ALTER TABLE centres_clients ADD CONSTRAINT FK_16BB6A2AA3F9A9F9 FOREIGN KEY (idclient) REFERENCES clients (idclient)');
        $this->addSql('ALTER TABLE controles_non_factures ADD CONSTRAINT FK_76FCF60EA4EC7163 FOREIGN KEY (idcontrole) REFERENCES controles (idcontrole)');
        $this->addSql('ALTER TABLE controles_non_factures ADD CONSTRAINT FK_76FCF60EA3F9A9F9 FOREIGN KEY (idclient) REFERENCES clients (idclient)');
        $this->addSql('ALTER TABLE prestas_non_facturees ADD CONSTRAINT FK_6E23438DA4EC7163 FOREIGN KEY (idcontrole) REFERENCES controles (idcontrole)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE clients_controles DROP FOREIGN KEY FK_E0443B21A3F9A9F9');
        $this->addSql('ALTER TABLE clients_controles DROP FOREIGN KEY FK_E0443B21A4EC7163');
        $this->addSql('ALTER TABLE controles_factures DROP FOREIGN KEY FK_B77B2F69A4EC7163');
        $this->addSql('ALTER TABLE controles_factures DROP FOREIGN KEY FK_B77B2F692182367E');
        $this->addSql('ALTER TABLE controles_factures DROP FOREIGN KEY FK_B77B2F69A3F9A9F9');
        $this->addSql('ALTER TABLE factures_reglements DROP FOREIGN KEY FK_1D789B862182367E');
        $this->addSql('ALTER TABLE factures_reglements DROP FOREIGN KEY FK_1D789B8695FF68B9');
        $this->addSql('ALTER TABLE factures_reglements DROP FOREIGN KEY FK_1D789B86A3F9A9F9');
        $this->addSql('DROP TABLE clients_controles');
        $this->addSql('DROP TABLE controles_factures');
        $this->addSql('DROP TABLE factures_reglements');
        $this->addSql('ALTER TABLE centres_clients DROP FOREIGN KEY FK_16BB6A2AA3F9A9F9');
        $this->addSql('ALTER TABLE controles_non_factures DROP FOREIGN KEY FK_76FCF60EA4EC7163');
        $this->addSql('ALTER TABLE controles_non_factures DROP FOREIGN KEY FK_76FCF60EA3F9A9F9');
        $this->addSql('ALTER TABLE prestas_non_facturees DROP FOREIGN KEY FK_6E23438DA4EC7163');
    }
}
