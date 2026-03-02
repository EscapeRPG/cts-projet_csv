<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260218124500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Passe les colonnes idclient/idcontrole en VARCHAR(50) pour accepter les identifiants alphanumériques.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE centres_clients MODIFY idclient VARCHAR(50) NOT NULL');
        $this->addSql('ALTER TABLE clients MODIFY idclient VARCHAR(50) NOT NULL');
        $this->addSql('ALTER TABLE clients_controles MODIFY idclient VARCHAR(50) NOT NULL, MODIFY idcontrole VARCHAR(50) NOT NULL');
        $this->addSql('ALTER TABLE controles MODIFY idcontrole VARCHAR(50) NOT NULL');
        $this->addSql('ALTER TABLE controles_factures MODIFY idcontrole VARCHAR(50) NOT NULL, MODIFY idclient VARCHAR(50) NOT NULL');
        $this->addSql('ALTER TABLE controles_non_factures MODIFY idcontrole VARCHAR(50) NOT NULL, MODIFY idclient VARCHAR(50) NOT NULL');
        $this->addSql('ALTER TABLE factures_reglements MODIFY idclient VARCHAR(50) NOT NULL');
        $this->addSql('ALTER TABLE prestas_non_facturees MODIFY idcontrole VARCHAR(50) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE centres_clients MODIFY idclient BIGINT NOT NULL');
        $this->addSql('ALTER TABLE clients MODIFY idclient BIGINT NOT NULL');
        $this->addSql('ALTER TABLE clients_controles MODIFY idclient BIGINT NOT NULL, MODIFY idcontrole BIGINT NOT NULL');
        $this->addSql('ALTER TABLE controles MODIFY idcontrole BIGINT NOT NULL');
        $this->addSql('ALTER TABLE controles_factures MODIFY idcontrole BIGINT NOT NULL, MODIFY idclient BIGINT NOT NULL');
        $this->addSql('ALTER TABLE controles_non_factures MODIFY idcontrole BIGINT NOT NULL, MODIFY idclient BIGINT NOT NULL');
        $this->addSql('ALTER TABLE factures_reglements MODIFY idclient BIGINT NOT NULL');
        $this->addSql('ALTER TABLE prestas_non_facturees MODIFY idcontrole BIGINT NOT NULL');
    }
}
