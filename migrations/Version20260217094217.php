<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260217094217 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE synthese_pros');
        $this->addSql('ALTER TABLE centre ADD CONSTRAINT FK_C6A0EA75445D170C FOREIGN KEY (reseau_id) REFERENCES reseau (id)');
        $this->addSql('ALTER TABLE centre ADD CONSTRAINT FK_C6A0EA75FCF77503 FOREIGN KEY (societe_id) REFERENCES societe (id)');
        $this->addSql('ALTER TABLE centres_clients ADD CONSTRAINT FK_16BB6A2A445D170C FOREIGN KEY (reseau_id) REFERENCES reseau (id)');
        $this->addSql('DROP INDEX idx_clients_nomcodeclient ON clients');
        $this->addSql('ALTER TABLE clients ADD CONSTRAINT FK_C82E74445D170C FOREIGN KEY (reseau_id) REFERENCES reseau (id)');
        $this->addSql('DROP INDEX idx_cc_idclient_idcontrole ON clients_controles');
        $this->addSql('ALTER TABLE clients_controles ADD CONSTRAINT FK_E0443B21445D170C FOREIGN KEY (reseau_id) REFERENCES reseau (id)');
        $this->addSql('DROP INDEX idx_controles_date_type ON controles');
        $this->addSql('DROP INDEX idx_controles_idcontrole ON controles');
        $this->addSql('ALTER TABLE controles ADD CONSTRAINT FK_B10ABA6D445D170C FOREIGN KEY (reseau_id) REFERENCES reseau (id)');
        $this->addSql('DROP INDEX idx_cf_idfacture_idcontrole ON controles_factures');
        $this->addSql('ALTER TABLE controles_factures ADD CONSTRAINT FK_B77B2F69445D170C FOREIGN KEY (reseau_id) REFERENCES reseau (id)');
        $this->addSql('ALTER TABLE controles_non_factures ADD CONSTRAINT FK_76FCF60E445D170C FOREIGN KEY (reseau_id) REFERENCES reseau (id)');
        $this->addSql('DROP INDEX idx_factures_date_type_total ON factures');
        $this->addSql('ALTER TABLE factures ADD CONSTRAINT FK_647590B445D170C FOREIGN KEY (reseau_id) REFERENCES reseau (id)');
        $this->addSql('ALTER TABLE factures_reglements ADD CONSTRAINT FK_1D789B86445D170C FOREIGN KEY (reseau_id) REFERENCES reseau (id)');
        $this->addSql('ALTER TABLE imported_files ADD CONSTRAINT FK_D8475CB7445D170C FOREIGN KEY (reseau_id) REFERENCES reseau (id)');
        $this->addSql('ALTER TABLE prestas_non_facturees ADD CONSTRAINT FK_6E23438D445D170C FOREIGN KEY (reseau_id) REFERENCES reseau (id)');
        $this->addSql('ALTER TABLE reglements ADD CONSTRAINT FK_648F2671445D170C FOREIGN KEY (reseau_id) REFERENCES reseau (id)');
        $this->addSql('ALTER TABLE reset_password_request ADD CONSTRAINT FK_7CE748AA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE salarie ADD CONSTRAINT FK_828E3A1AFCF77503 FOREIGN KEY (societe_id) REFERENCES societe (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE synthese_pros (id INT AUTO_INCREMENT NOT NULL, code_client VARCHAR(50) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, annee INT NOT NULL, mois INT NOT NULL, ca NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL, nb_controles INT DEFAULT 0 NOT NULL, agr_centre VARCHAR(50) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, societe_nom VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, UNIQUE INDEX unique_client_annee_mois (code_client, annee, mois), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE centre DROP FOREIGN KEY FK_C6A0EA75445D170C');
        $this->addSql('ALTER TABLE centre DROP FOREIGN KEY FK_C6A0EA75FCF77503');
        $this->addSql('ALTER TABLE centres_clients DROP FOREIGN KEY FK_16BB6A2A445D170C');
        $this->addSql('ALTER TABLE clients DROP FOREIGN KEY FK_C82E74445D170C');
        $this->addSql('CREATE INDEX idx_clients_nomcodeclient ON clients (nom_code_client(250))');
        $this->addSql('ALTER TABLE clients_controles DROP FOREIGN KEY FK_E0443B21445D170C');
        $this->addSql('CREATE INDEX idx_cc_idclient_idcontrole ON clients_controles (idclient, idcontrole)');
        $this->addSql('ALTER TABLE controles DROP FOREIGN KEY FK_B10ABA6D445D170C');
        $this->addSql('CREATE INDEX idx_controles_date_type ON controles (date_ctrl, type_ctrl)');
        $this->addSql('CREATE INDEX idx_controles_idcontrole ON controles (idcontrole)');
        $this->addSql('ALTER TABLE controles_factures DROP FOREIGN KEY FK_B77B2F69445D170C');
        $this->addSql('CREATE INDEX idx_cf_idfacture_idcontrole ON controles_factures (idfacture, idcontrole)');
        $this->addSql('ALTER TABLE controles_non_factures DROP FOREIGN KEY FK_76FCF60E445D170C');
        $this->addSql('ALTER TABLE factures DROP FOREIGN KEY FK_647590B445D170C');
        $this->addSql('CREATE INDEX idx_factures_date_type_total ON factures (date_facture, type_facture, total_ht)');
        $this->addSql('ALTER TABLE factures_reglements DROP FOREIGN KEY FK_1D789B86445D170C');
        $this->addSql('ALTER TABLE imported_files DROP FOREIGN KEY FK_D8475CB7445D170C');
        $this->addSql('ALTER TABLE prestas_non_facturees DROP FOREIGN KEY FK_6E23438D445D170C');
        $this->addSql('ALTER TABLE reglements DROP FOREIGN KEY FK_648F2671445D170C');
        $this->addSql('ALTER TABLE reset_password_request DROP FOREIGN KEY FK_7CE748AA76ED395');
        $this->addSql('ALTER TABLE salarie DROP FOREIGN KEY FK_828E3A1AFCF77503');
    }
}
