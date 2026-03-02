<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260220134000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les compteurs particuliers/professionnels auto/moto dans synthese_controles.';
    }

    public function up(Schema $schema): void
    {
        if (!$this->columnExists('synthese_controles', 'nb_particuliers_auto')) {
            $this->addSql('ALTER TABLE synthese_controles ADD COLUMN nb_particuliers_auto INT NOT NULL DEFAULT 0');
        }

        if (!$this->columnExists('synthese_controles', 'nb_particuliers_moto')) {
            $this->addSql('ALTER TABLE synthese_controles ADD COLUMN nb_particuliers_moto INT NOT NULL DEFAULT 0');
        }

        if (!$this->columnExists('synthese_controles', 'nb_professionnels_auto')) {
            $this->addSql('ALTER TABLE synthese_controles ADD COLUMN nb_professionnels_auto INT NOT NULL DEFAULT 0');
        }

        if (!$this->columnExists('synthese_controles', 'nb_professionnels_moto')) {
            $this->addSql('ALTER TABLE synthese_controles ADD COLUMN nb_professionnels_moto INT NOT NULL DEFAULT 0');
        }
    }

    public function down(Schema $schema): void
    {
        if ($this->columnExists('synthese_controles', 'nb_particuliers_auto')) {
            $this->addSql('ALTER TABLE synthese_controles DROP COLUMN nb_particuliers_auto');
        }

        if ($this->columnExists('synthese_controles', 'nb_particuliers_moto')) {
            $this->addSql('ALTER TABLE synthese_controles DROP COLUMN nb_particuliers_moto');
        }

        if ($this->columnExists('synthese_controles', 'nb_professionnels_auto')) {
            $this->addSql('ALTER TABLE synthese_controles DROP COLUMN nb_professionnels_auto');
        }

        if ($this->columnExists('synthese_controles', 'nb_professionnels_moto')) {
            $this->addSql('ALTER TABLE synthese_controles DROP COLUMN nb_professionnels_moto');
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

