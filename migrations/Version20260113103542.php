<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260113103542 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE centres_clients (id INT AUTO_INCREMENT NOT NULL, agr_centre VARCHAR(8) NOT NULL, idclient BIGINT NOT NULL, INDEX IDX_16BB6A2AA3F9A9F9 (idclient), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE prestas_non_facturees (id INT AUTO_INCREMENT NOT NULL, idcontrole BIGINT NOT NULL, date_export DATETIME NOT NULL, devise VARCHAR(3) NOT NULL, otc_ht NUMERIC(8, 2) NOT NULL, montant_tva_otc_ht NUMERIC(8, 2) NOT NULL, pourcentage_tva_otc NUMERIC(8, 2) NOT NULL, otc_ttc NUMERIC(8, 2) NOT NULL, montant_presta_ht NUMERIC(8, 2) NOT NULL, montant_presta_ttc NUMERIC(8, 2) NOT NULL, pourcentage_tva_presta NUMERIC(8, 2) NOT NULL, montant_tva_presta NUMERIC(8, 2) NOT NULL, montant_remise NUMERIC(8, 2) NOT NULL, pourcentage_remise NUMERIC(8, 2) NOT NULL, total_ht NUMERIC(8, 2) NOT NULL, total_ttc NUMERIC(8, 2) NOT NULL, pourcentage_tva NUMERIC(8, 2) NOT NULL, montant_tva NUMERIC(8, 2) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE centres_clients ADD CONSTRAINT FK_16BB6A2AA3F9A9F9 FOREIGN KEY (idclient) REFERENCES clients (idclient)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE centres_clients DROP FOREIGN KEY FK_16BB6A2AA3F9A9F9');
        $this->addSql('DROP TABLE centres_clients');
        $this->addSql('DROP TABLE prestas_non_facturees');
    }
}
