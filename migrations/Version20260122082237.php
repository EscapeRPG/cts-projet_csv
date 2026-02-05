<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260122082237 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE affectation ADD CONSTRAINT FK_F4DD61D35859934A FOREIGN KEY (salarie_id) REFERENCES salaries (id)');
        $this->addSql('ALTER TABLE affectation ADD CONSTRAINT FK_F4DD61D3463CD7C3 FOREIGN KEY (centre_id) REFERENCES centre (id)');
        $this->addSql('ALTER TABLE centre ADD CONSTRAINT FK_C6A0EA75445D170C FOREIGN KEY (reseau_id) REFERENCES reseau (id)');
        $this->addSql('ALTER TABLE centre RENAME INDEX agr_centre TO UNIQ_C6A0EA75A927AF30');
        $this->addSql('ALTER TABLE centres_clients ADD CONSTRAINT FK_16BB6A2A445D170C FOREIGN KEY (reseau_id) REFERENCES reseau (id)');
        $this->addSql('ALTER TABLE clients ADD CONSTRAINT FK_C82E74445D170C FOREIGN KEY (reseau_id) REFERENCES reseau (id)');
        $this->addSql('ALTER TABLE clients_controles ADD CONSTRAINT FK_E0443B21445D170C FOREIGN KEY (reseau_id) REFERENCES reseau (id)');
        $this->addSql('ALTER TABLE controles ADD CONSTRAINT FK_B10ABA6D445D170C FOREIGN KEY (reseau_id) REFERENCES reseau (id)');
        $this->addSql('ALTER TABLE controles_factures ADD CONSTRAINT FK_B77B2F69445D170C FOREIGN KEY (reseau_id) REFERENCES reseau (id)');
        $this->addSql('ALTER TABLE controles_non_factures ADD CONSTRAINT FK_76FCF60E445D170C FOREIGN KEY (reseau_id) REFERENCES reseau (id)');
        $this->addSql('ALTER TABLE factures ADD CONSTRAINT FK_647590B445D170C FOREIGN KEY (reseau_id) REFERENCES reseau (id)');
        $this->addSql('ALTER TABLE factures_reglements ADD CONSTRAINT FK_1D789B86445D170C FOREIGN KEY (reseau_id) REFERENCES reseau (id)');
        $this->addSql('ALTER TABLE imported_files ADD CONSTRAINT FK_D8475CB7445D170C FOREIGN KEY (reseau_id) REFERENCES reseau (id)');
        $this->addSql('ALTER TABLE prestas_non_facturees ADD CONSTRAINT FK_6E23438D445D170C FOREIGN KEY (reseau_id) REFERENCES reseau (id)');
        $this->addSql('ALTER TABLE reglements ADD CONSTRAINT FK_648F2671445D170C FOREIGN KEY (reseau_id) REFERENCES reseau (id)');
        $this->addSql('ALTER TABLE salaries RENAME INDEX code TO UNIQ_828E3A1A77153098');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE affectation DROP FOREIGN KEY FK_F4DD61D35859934A');
        $this->addSql('ALTER TABLE affectation DROP FOREIGN KEY FK_F4DD61D3463CD7C3');
        $this->addSql('ALTER TABLE centre DROP FOREIGN KEY FK_C6A0EA75445D170C');
        $this->addSql('ALTER TABLE centre RENAME INDEX uniq_c6a0ea75a927af30 TO agr_centre');
        $this->addSql('ALTER TABLE centres_clients DROP FOREIGN KEY FK_16BB6A2A445D170C');
        $this->addSql('ALTER TABLE clients DROP FOREIGN KEY FK_C82E74445D170C');
        $this->addSql('ALTER TABLE clients_controles DROP FOREIGN KEY FK_E0443B21445D170C');
        $this->addSql('ALTER TABLE controles DROP FOREIGN KEY FK_B10ABA6D445D170C');
        $this->addSql('ALTER TABLE controles_factures DROP FOREIGN KEY FK_B77B2F69445D170C');
        $this->addSql('ALTER TABLE controles_non_factures DROP FOREIGN KEY FK_76FCF60E445D170C');
        $this->addSql('ALTER TABLE factures DROP FOREIGN KEY FK_647590B445D170C');
        $this->addSql('ALTER TABLE factures_reglements DROP FOREIGN KEY FK_1D789B86445D170C');
        $this->addSql('ALTER TABLE imported_files DROP FOREIGN KEY FK_D8475CB7445D170C');
        $this->addSql('ALTER TABLE prestas_non_facturees DROP FOREIGN KEY FK_6E23438D445D170C');
        $this->addSql('ALTER TABLE reglements DROP FOREIGN KEY FK_648F2671445D170C');
        $this->addSql('ALTER TABLE salaries RENAME INDEX uniq_828e3a1a77153098 TO code');
    }
}
