<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260112155952 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE reglements (id INT AUTO_INCREMENT NOT NULL, id_reglement BIGINT NOT NULL, date_export DATETIME NOT NULL, mode_reglt VARCHAR(3) NOT NULL, date_reglt DATETIME NOT NULL, montant_reglt NUMERIC(8, 2) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE clients ADD code_sage VARCHAR(25) DEFAULT NULL, ADD nom VARCHAR(255) NOT NULL, ADD prenom VARCHAR(255) DEFAULT NULL, ADD adresse1 VARCHAR(255) DEFAULT NULL, ADD adresse2 VARCHAR(255) DEFAULT NULL, ADD cp VARCHAR(5) NOT NULL, ADD ville VARCHAR(255) NOT NULL, ADD email VARCHAR(255) DEFAULT NULL, ADD telephone VARCHAR(12) DEFAULT NULL, ADD mobile VARCHAR(12) DEFAULT NULL, ADD num_tva_intra VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE reglements');
        $this->addSql('ALTER TABLE clients DROP code_sage, DROP nom, DROP prenom, DROP adresse1, DROP adresse2, DROP cp, DROP ville, DROP email, DROP telephone, DROP mobile, DROP num_tva_intra');
    }
}
