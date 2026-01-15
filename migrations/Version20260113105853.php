<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260113105853 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE centres_clients ADD CONSTRAINT FK_16BB6A2AA3F9A9F9 FOREIGN KEY (idclient) REFERENCES clients (idclient)');
        $this->addSql('ALTER TABLE prestas_non_facturees ADD idcontrole_id INT NOT NULL');
        $this->addSql('ALTER TABLE prestas_non_facturees ADD CONSTRAINT FK_6E23438DA4EC7163B3485609 FOREIGN KEY (idcontrole, idcontrole_id) REFERENCES controles (idcontrole, id)');
        $this->addSql('CREATE INDEX IDX_6E23438DA4EC7163B3485609 ON prestas_non_facturees (idcontrole, idcontrole_id)');
        $this->addSql('ALTER TABLE reglements CHANGE id_reglement idreglement BIGINT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE centres_clients DROP FOREIGN KEY FK_16BB6A2AA3F9A9F9');
        $this->addSql('ALTER TABLE prestas_non_facturees DROP FOREIGN KEY FK_6E23438DA4EC7163B3485609');
        $this->addSql('DROP INDEX IDX_6E23438DA4EC7163B3485609 ON prestas_non_facturees');
        $this->addSql('ALTER TABLE prestas_non_facturees DROP idcontrole_id');
        $this->addSql('ALTER TABLE reglements CHANGE idreglement id_reglement BIGINT NOT NULL');
    }
}
