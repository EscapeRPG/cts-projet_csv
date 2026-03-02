<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260220132000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute des agrégats auto/moto dans synthese_pros pour filtrer CL/VL sans requêtes raw.';
    }

    public function up(Schema $schema): void
    {
        if (!$this->columnExists('synthese_pros', 'ca_auto')) {
            $this->addSql('ALTER TABLE synthese_pros ADD COLUMN ca_auto DECIMAL(12,2) NOT NULL DEFAULT 0');
        }

        if (!$this->columnExists('synthese_pros', 'ca_moto')) {
            $this->addSql('ALTER TABLE synthese_pros ADD COLUMN ca_moto DECIMAL(12,2) NOT NULL DEFAULT 0');
        }

        if (!$this->columnExists('synthese_pros', 'nb_controles_auto')) {
            $this->addSql('ALTER TABLE synthese_pros ADD COLUMN nb_controles_auto INT NOT NULL DEFAULT 0');
        }

        if (!$this->columnExists('synthese_pros', 'nb_controles_moto')) {
            $this->addSql('ALTER TABLE synthese_pros ADD COLUMN nb_controles_moto INT NOT NULL DEFAULT 0');
        }
    }

    public function down(Schema $schema): void
    {
        if ($this->columnExists('synthese_pros', 'ca_auto')) {
            $this->addSql('ALTER TABLE synthese_pros DROP COLUMN ca_auto');
        }

        if ($this->columnExists('synthese_pros', 'ca_moto')) {
            $this->addSql('ALTER TABLE synthese_pros DROP COLUMN ca_moto');
        }

        if ($this->columnExists('synthese_pros', 'nb_controles_auto')) {
            $this->addSql('ALTER TABLE synthese_pros DROP COLUMN nb_controles_auto');
        }

        if ($this->columnExists('synthese_pros', 'nb_controles_moto')) {
            $this->addSql('ALTER TABLE synthese_pros DROP COLUMN nb_controles_moto');
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        $sql = <<<'SQL'
            SELECT COUNT(*)
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table_name
              AND COLUMN_NAME = :column_name
        SQL;

        return (int)$this->connection->fetchOne($sql, [
            'table_name' => $table,
            'column_name' => $column,
        ]) > 0;
    }
}
