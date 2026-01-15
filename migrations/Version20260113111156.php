<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260113111156 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE controles_non_factures (id INT AUTO_INCREMENT NOT NULL, agr_centre VARCHAR(8) NOT NULL, agr_controleur VARCHAR(8) NOT NULL, idcontrole BIGINT NOT NULL, idclient BIGINT NOT NULL, INDEX IDX_76FCF60EA4EC7163 (idcontrole), INDEX IDX_76FCF60EA3F9A9F9 (idclient), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE controles_non_factures ADD CONSTRAINT FK_76FCF60EA4EC7163 FOREIGN KEY (idcontrole) REFERENCES controles (idcontrole)');
        $this->addSql('ALTER TABLE controles_non_factures ADD CONSTRAINT FK_76FCF60EA3F9A9F9 FOREIGN KEY (idclient) REFERENCES clients (idclient)');
        $this->addSql('ALTER TABLE centres_clients ADD CONSTRAINT FK_16BB6A2AA3F9A9F9 FOREIGN KEY (idclient) REFERENCES clients (idclient)');
        $this->addSql('DROP INDEX IDX_6E23438DA4EC7163B3485609 ON prestas_non_facturees');
        $this->addSql('ALTER TABLE prestas_non_facturees DROP idcontrole_id');
        $this->addSql('ALTER TABLE prestas_non_facturees ADD CONSTRAINT FK_6E23438DA4EC7163 FOREIGN KEY (idcontrole) REFERENCES controles (idcontrole)');
        $this->addSql('CREATE INDEX IDX_6E23438DA4EC7163 ON prestas_non_facturees (idcontrole)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE controles_non_factures DROP FOREIGN KEY FK_76FCF60EA4EC7163');
        $this->addSql('ALTER TABLE controles_non_factures DROP FOREIGN KEY FK_76FCF60EA3F9A9F9');
        $this->addSql('DROP TABLE controles_non_factures');
        $this->addSql('ALTER TABLE centres_clients DROP FOREIGN KEY FK_16BB6A2AA3F9A9F9');
        $this->addSql('ALTER TABLE prestas_non_facturees DROP FOREIGN KEY FK_6E23438DA4EC7163');
        $this->addSql('DROP INDEX IDX_6E23438DA4EC7163 ON prestas_non_facturees');
        $this->addSql('ALTER TABLE prestas_non_facturees ADD idcontrole_id INT NOT NULL');
        $this->addSql('CREATE INDEX IDX_6E23438DA4EC7163B3485609 ON prestas_non_facturees (idcontrole, idcontrole_id)');
    }
}
