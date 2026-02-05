<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260204143538 extends AbstractMigration
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
        $this->addSql('ALTER TABLE centre ADD CONSTRAINT FK_C6A0EA75FCF77503 FOREIGN KEY (societe_id) REFERENCES societe (id)');
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
        $this->addSql('ALTER TABLE reset_password_request ADD CONSTRAINT FK_7CE748AA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('DROP INDEX UNIQ_828E3A1A7AB5DA25 ON salaries');
        $this->addSql('ALTER TABLE salaries ADD societe VARCHAR(255) NOT NULL, ADD agr_cl_controleur VARCHAR(20) DEFAULT NULL, ADD date_naissance DATE DEFAULT NULL, ADD email VARCHAR(150) DEFAULT NULL, ADD telephone VARCHAR(20) DEFAULT NULL, ADD echelons INT DEFAULT NULL, ADD salaire_brut NUMERIC(10, 2) DEFAULT NULL, ADD nb_heures INT DEFAULT NULL, ADD veste_manche_amovible VARCHAR(5) DEFAULT NULL, ADD polaire VARCHAR(5) DEFAULT NULL, ADD pantalon VARCHAR(5) DEFAULT NULL, ADD tee_shirts VARCHAR(5) DEFAULT NULL, ADD polo VARCHAR(5) DEFAULT NULL, ADD chaussures INT DEFAULT NULL, CHANGE nom nom VARCHAR(50) NOT NULL, CHANGE prenom prenom VARCHAR(50) NOT NULL, CHANGE agr_controleur agr_controleur VARCHAR(20) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE affectation DROP FOREIGN KEY FK_F4DD61D35859934A');
        $this->addSql('ALTER TABLE affectation DROP FOREIGN KEY FK_F4DD61D3463CD7C3');
        $this->addSql('ALTER TABLE centre DROP FOREIGN KEY FK_C6A0EA75445D170C');
        $this->addSql('ALTER TABLE centre DROP FOREIGN KEY FK_C6A0EA75FCF77503');
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
        $this->addSql('ALTER TABLE reset_password_request DROP FOREIGN KEY FK_7CE748AA76ED395');
        $this->addSql('ALTER TABLE salaries DROP societe, DROP agr_cl_controleur, DROP date_naissance, DROP email, DROP telephone, DROP echelons, DROP salaire_brut, DROP nb_heures, DROP veste_manche_amovible, DROP polaire, DROP pantalon, DROP tee_shirts, DROP polo, DROP chaussures, CHANGE agr_controleur agr_controleur VARCHAR(50) DEFAULT NULL, CHANGE nom nom VARCHAR(100) NOT NULL, CHANGE prenom prenom VARCHAR(100) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_828E3A1A7AB5DA25 ON salaries (agr_controleur)');
    }
}
