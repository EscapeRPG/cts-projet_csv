<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260220123000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute des index pour accélérer les filtres de suivi sur synthese_controles et synthese_pros.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX idx_sc_filter_dimensions ON synthese_controles (annee, mois, reseau_id, societe_nom, agr_centre, salarie_id)');
        $this->addSql('CREATE INDEX idx_sc_societe_centre_salarie ON synthese_controles (societe_nom, agr_centre, salarie_id)');
        $this->addSql('CREATE INDEX idx_sp_filter_dimensions ON synthese_pros (annee, mois, reseau_id, societe_nom, agr_centre)');
        $this->addSql('CREATE INDEX idx_sp_societe_centre ON synthese_pros (societe_nom, agr_centre)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_sc_filter_dimensions ON synthese_controles');
        $this->addSql('DROP INDEX idx_sc_societe_centre_salarie ON synthese_controles');
        $this->addSql('DROP INDEX idx_sp_filter_dimensions ON synthese_pros');
        $this->addSql('DROP INDEX idx_sp_societe_centre ON synthese_pros');
    }
}

