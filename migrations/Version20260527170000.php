<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Previously restored business UNIQUE keys for upsert imports.
 *
 * CSV imports now keep every source row, so this migration is intentionally inert.
 */
final class Version20260527170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'No-op: CSV source tables must keep duplicate business identifiers.';
    }

    public function up(Schema $schema): void
    {
    }

    public function down(Schema $schema): void
    {
    }
}
