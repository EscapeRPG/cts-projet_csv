<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260507121000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align doctrine_migration_versions collation with application tables (utf8mb4_0900_ai_ci).';
    }

    public function up(Schema $schema): void
    {
        // MySQL 8+ can throw "Illegal mix of collations" when comparing this table with other utf8mb4_0900_ai_ci strings.
        $this->addSql('ALTER TABLE doctrine_migration_versions CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE doctrine_migration_versions CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');
    }
}

