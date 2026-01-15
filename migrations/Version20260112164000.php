<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260112164000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE controles (id INT AUTO_INCREMENT NOT NULL, idcontrole BIGINT NOT NULL, date_export DATETIME NOT NULL, num_pv_ctrl VARCHAR(20) NOT NULL, num_lia_ctrl VARCHAR(20) NOT NULL, immat_vehicule VARCHAR(12) NOT NULL, num_serie_vehicule VARCHAR(12) NOT NULL, date_prise_rdv DATETIME DEFAULT NULL, type_rdv VARCHAR(1) NOT NULL, deb_ctrl TIME NOT NULL, fin_ctrl TIME NOT NULL, date_ctrl DATE NOT NULL, temps_ctrl SMALLINT NOT NULL, ref_temps SMALLINT NOT NULL, res_ctrl VARCHAR(2) NOT NULL, type_ctrl VARCHAR(5) NOT NULL, modele_vehicule VARCHAR(255) NOT NULL, annee_circulation INT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE factures ADD numero_releve VARCHAR(50) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE controles');
        $this->addSql('ALTER TABLE factures DROP numero_releve');
    }
}
