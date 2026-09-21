<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260901120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la correspondance entre un équipement et le numéro de portique des imports Astikoto.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE equipement_station ADD numero_portique_import INT DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_EQUIPEMENT_STATION_PORTIQUE_IMPORT ON equipement_station (centre_id, numero_portique_import)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_EQUIPEMENT_STATION_PORTIQUE_IMPORT ON equipement_station');
        $this->addSql('ALTER TABLE equipement_station DROP numero_portique_import');
    }
}
