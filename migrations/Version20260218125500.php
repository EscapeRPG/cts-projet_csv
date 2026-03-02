<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260218125500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Passe les colonnes idfacture/idreglement en VARCHAR(50) pour les identifiants alphanumériques.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE factures MODIFY idfacture VARCHAR(50) NOT NULL');
        $this->addSql('ALTER TABLE controles_factures MODIFY idfacture VARCHAR(50) NOT NULL');
        $this->addSql('ALTER TABLE factures_reglements MODIFY idfacture VARCHAR(50) NOT NULL, MODIFY idreglement VARCHAR(50) NOT NULL');
        $this->addSql('ALTER TABLE reglements MODIFY idreglement VARCHAR(50) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE factures MODIFY idfacture BIGINT NOT NULL');
        $this->addSql('ALTER TABLE controles_factures MODIFY idfacture BIGINT NOT NULL');
        $this->addSql('ALTER TABLE factures_reglements MODIFY idfacture BIGINT NOT NULL, MODIFY idreglement BIGINT NOT NULL');
        $this->addSql('ALTER TABLE reglements MODIFY idreglement BIGINT NOT NULL');
    }
}
