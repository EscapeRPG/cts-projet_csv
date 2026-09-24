<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260924150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Conserve les totaux saisis des bornes et distingue les champs non renseignés.';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE releve_equipement ADD total_cb NUMERIC(12, 2) DEFAULT NULL, ADD total_especes NUMERIC(12, 2) DEFAULT NULL, ADD total_cheque NUMERIC(12, 2) DEFAULT NULL, ADD total_jetons INT DEFAULT NULL, ADD total_bl NUMERIC(12, 2) DEFAULT NULL');
        $this->addSql("UPDATE releve_equipement b JOIN equipement_station e ON e.id = b.equipement_id LEFT JOIN releve_equipement p ON p.releve_journalier_id = b.releve_journalier_id AND p.equipement_id = e.portique_associe_id SET b.total_cb = b.cb + COALESCE(p.cb, 0), b.total_especes = b.especes + COALESCE(p.especes, 0), b.total_cheque = b.cheque + COALESCE(p.cheque, 0), b.total_jetons = b.jetons + COALESCE(p.jetons, 0), b.total_bl = b.bl + COALESCE(p.bl, 0) WHERE e.categorie = 'borne'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE releve_equipement DROP total_cb, DROP total_especes, DROP total_cheque, DROP total_jetons, DROP total_bl');
    }
}
