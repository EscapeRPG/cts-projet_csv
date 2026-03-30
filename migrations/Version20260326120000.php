<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260326120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Restore critical indexes for suivi raw joins on controles, clients_controles, controles_factures, factures and clients.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX idx_controles_date_type_idcontrole ON controles (date_ctrl, type_ctrl, idcontrole)');
        $this->addSql('CREATE INDEX idx_controles_type_res_idcontrole ON controles (type_ctrl, res_ctrl, idcontrole)');

        $this->addSql('CREATE INDEX idx_cc_idcontrole_client_centre_controleur ON clients_controles (idcontrole, idclient, agr_centre, agr_controleur)');
        $this->addSql('CREATE INDEX idx_clients_idclient_code_client ON clients (idclient, code_client)');

        $this->addSql('CREATE INDEX idx_cf_idcontrole_idfacture ON controles_factures (idcontrole, idfacture)');
        $this->addSql('CREATE INDEX idx_cf_idfacture_idcontrole ON controles_factures (idfacture, idcontrole)');

        $this->addSql('CREATE INDEX idx_factures_idfacture_type_facture ON factures (idfacture, type_facture)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_controles_date_type_idcontrole ON controles');
        $this->addSql('DROP INDEX idx_controles_type_res_idcontrole ON controles');

        $this->addSql('DROP INDEX idx_cc_idcontrole_client_centre_controleur ON clients_controles');
        $this->addSql('DROP INDEX idx_clients_idclient_code_client ON clients');

        $this->addSql('DROP INDEX idx_cf_idcontrole_idfacture ON controles_factures');
        $this->addSql('DROP INDEX idx_cf_idfacture_idcontrole ON controles_factures');

        $this->addSql('DROP INDEX idx_factures_idfacture_type_facture ON factures');
    }
}
