<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260116132323 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX uniq_cntrclt_client ON centres_clients');
        $this->addSql('DROP INDEX uniq_clt_client ON clients');
        $this->addSql('DROP INDEX uniq_cltctrl_client_controle ON clients_controles');
        $this->addSql('DROP INDEX uniq_ctrl_controle ON controles');
        $this->addSql('DROP INDEX uniq_ctrlfact_controle_facture ON controles_factures');
        $this->addSql('DROP INDEX uniq_ctrlnfact_controle_client ON controles_non_factures');
        $this->addSql('DROP INDEX uniq_factures_facture ON factures');
        $this->addSql('DROP INDEX uniq_factreglt_facture_reglement ON factures_reglements');
        $this->addSql('DROP INDEX uniq_presta_controle ON prestas_non_facturees');
        $this->addSql('DROP INDEX uniq_reglt_reglement ON reglements');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE UNIQUE INDEX uniq_cntrclt_client ON centres_clients (idclient)');
        $this->addSql('CREATE UNIQUE INDEX uniq_clt_client ON clients (idclient)');
        $this->addSql('CREATE UNIQUE INDEX uniq_cltctrl_client_controle ON clients_controles (idclient, idcontrole)');
        $this->addSql('CREATE UNIQUE INDEX uniq_ctrl_controle ON controles (idcontrole)');
        $this->addSql('CREATE UNIQUE INDEX uniq_ctrlfact_controle_facture ON controles_factures (idcontrole, idfacture)');
        $this->addSql('CREATE UNIQUE INDEX uniq_ctrlnfact_controle_client ON controles_non_factures (idcontrole, idclient)');
        $this->addSql('CREATE UNIQUE INDEX uniq_factures_facture ON factures (idfacture)');
        $this->addSql('CREATE UNIQUE INDEX uniq_factreglt_facture_reglement ON factures_reglements (idfacture, idreglement)');
        $this->addSql('CREATE UNIQUE INDEX uniq_presta_controle ON prestas_non_facturees (idcontrole)');
        $this->addSql('CREATE UNIQUE INDEX uniq_reglt_reglement ON reglements (idreglement)');
    }
}
