<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260702120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute l historique de suivi quotidien des imports SFTP.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE import_health_check (id INT AUTO_INCREMENT NOT NULL, reseau_id INT DEFAULT NULL, check_date DATE NOT NULL, reseau_name VARCHAR(255) NOT NULL, files_imported INT NOT NULL, expected_files INT NOT NULL, controles_files INT NOT NULL, latest_imported_at DATETIME DEFAULT NULL, status VARCHAR(20) NOT NULL, issues JSON NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_D1E22214445D170C (reseau_id), UNIQUE INDEX uniq_import_health_reseau_date (reseau_id, check_date), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE import_health_check ADD CONSTRAINT FK_D1E22214445D170C FOREIGN KEY (reseau_id) REFERENCES reseau (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE import_health_check DROP FOREIGN KEY FK_D1E22214445D170C');
        $this->addSql('DROP TABLE import_health_check');
    }
}
