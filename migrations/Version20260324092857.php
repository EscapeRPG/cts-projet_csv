<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260324092857 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE notification (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(50) NOT NULL, message VARCHAR(255) NOT NULL, target_date DATE DEFAULT NULL, created_at DATETIME NOT NULL, expires_at DATETIME NOT NULL, salarie_id INT DEFAULT NULL, INDEX IDX_BF5476CA5859934A (salarie_id), UNIQUE INDEX uniq_notification_type_salarie_target_date (type, salarie_id, target_date), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE user_notification (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, read_at DATETIME DEFAULT NULL, notification_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_3F980AC8EF1A9D84 (notification_id), INDEX IDX_3F980AC8A76ED395 (user_id), UNIQUE INDEX uniq_user_notification_notification_user (notification_id, user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CA5859934A FOREIGN KEY (salarie_id) REFERENCES salarie (id)');
        $this->addSql('ALTER TABLE user_notification ADD CONSTRAINT FK_3F980AC8EF1A9D84 FOREIGN KEY (notification_id) REFERENCES notification (id)');
        $this->addSql('ALTER TABLE user_notification ADD CONSTRAINT FK_3F980AC8A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('DROP TABLE synthese_controles');
        $this->addSql('DROP TABLE synthese_meta');
        $this->addSql('DROP TABLE synthese_pros');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE synthese_controles (id INT AUTO_INCREMENT NOT NULL, societe_nom VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, agr_centre VARCHAR(50) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, centre_ville VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'\' COLLATE `utf8mb4_0900_ai_ci`, reseau_id INT NOT NULL, reseau_nom VARCHAR(50) CHARACTER SET utf8mb4 DEFAULT \'\' COLLATE `utf8mb4_0900_ai_ci`, salarie_id INT NOT NULL, salarie_agr VARCHAR(20) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, salarie_nom VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, salarie_prenom VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, annee INT NOT NULL, mois INT NOT NULL, nb_controles INT DEFAULT 0 NOT NULL, nb_vtp INT DEFAULT 0 NOT NULL, nb_vtp_particuliers INT DEFAULT 0 NOT NULL, nb_vtp_professionnels INT DEFAULT 0 NOT NULL, nb_clvtp INT DEFAULT 0 NOT NULL, nb_clvtp_particuliers INT DEFAULT 0 NOT NULL, nb_clvtp_professionnels INT DEFAULT 0 NOT NULL, nb_cv INT DEFAULT 0 NOT NULL, nb_cv_particuliers INT DEFAULT 0 NOT NULL, nb_cv_professionnels INT DEFAULT 0 NOT NULL, nb_clcv INT DEFAULT 0 NOT NULL, nb_clcv_particuliers INT DEFAULT 0 NOT NULL, nb_clcv_professionnels INT DEFAULT 0 NOT NULL, nb_vtc INT DEFAULT 0 NOT NULL, nb_vtc_particuliers INT DEFAULT 0 NOT NULL, nb_vtc_professionnels INT DEFAULT 0 NOT NULL, nb_vol INT DEFAULT 0 NOT NULL, nb_vol_particuliers INT DEFAULT 0 NOT NULL, nb_vol_professionnels INT DEFAULT 0 NOT NULL, nb_clvol INT DEFAULT 0 NOT NULL, nb_clvol_particuliers INT DEFAULT 0 NOT NULL, nb_clvol_professionnels INT DEFAULT 0 NOT NULL, nb_auto INT DEFAULT 0 NOT NULL, nb_moto INT DEFAULT 0 NOT NULL, total_presta_ht NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL, total_presta_ht_particuliers NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL, total_presta_ht_professionnels NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL, total_ht_vtp NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL, total_ht_vtp_particuliers NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL, total_ht_vtp_professionnels NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL, total_ht_clvtp NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL, total_ht_clvtp_particuliers NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL, total_ht_clvtp_professionnels NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL, total_ht_cv NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL, total_ht_cv_particuliers NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL, total_ht_cv_professionnels NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL, total_ht_clcv NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL, total_ht_clcv_particuliers NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL, total_ht_clcv_professionnels NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL, total_ht_vtc NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL, total_ht_vtc_particuliers NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL, total_ht_vtc_professionnels NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL, total_ht_vol NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL, total_ht_vol_particuliers NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL, total_ht_vol_professionnels NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL, total_ht_clvol NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL, total_ht_clvol_particuliers NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL, total_ht_clvol_professionnels NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL, temps_total INT DEFAULT 0 NOT NULL, temps_total_auto INT DEFAULT 0 NOT NULL, temps_total_moto INT DEFAULT 0 NOT NULL, taux_refus NUMERIC(5, 2) DEFAULT \'0.00\' NOT NULL, refus_auto INT DEFAULT 0 NOT NULL, refus_moto INT DEFAULT 0 NOT NULL, nb_particuliers INT DEFAULT 0 NOT NULL, nb_professionnels INT DEFAULT 0 NOT NULL, nb_particuliers_auto INT DEFAULT 0 NOT NULL, nb_particuliers_moto INT DEFAULT 0 NOT NULL, nb_professionnels_auto INT DEFAULT 0 NOT NULL, nb_professionnels_moto INT DEFAULT 0 NOT NULL, UNIQUE INDEX unique_salarie_mois_annee (salarie_id, salarie_agr, agr_centre, annee, mois), INDEX idx_synthese_periode (annee, mois), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE synthese_meta (meta_key VARCHAR(64) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, last_run_at DATETIME NOT NULL, PRIMARY KEY (meta_key)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE synthese_pros (id INT AUTO_INCREMENT NOT NULL, code_client VARCHAR(50) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, annee INT NOT NULL, mois INT NOT NULL, ca NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL, ca_auto NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL, ca_moto NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL, nb_controles INT DEFAULT 0 NOT NULL, nb_controles_auto INT DEFAULT 0 NOT NULL, nb_controles_moto INT DEFAULT 0 NOT NULL, ca_vtp NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL, ca_clvtp NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL, ca_cv NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL, ca_clcv NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL, ca_vtc NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL, ca_vol NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL, ca_clvol NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL, nb_vtp INT DEFAULT 0 NOT NULL, nb_clvtp INT DEFAULT 0 NOT NULL, nb_cv INT DEFAULT 0 NOT NULL, nb_clcv INT DEFAULT 0 NOT NULL, nb_vtc INT DEFAULT 0 NOT NULL, nb_vol INT DEFAULT 0 NOT NULL, nb_clvol INT DEFAULT 0 NOT NULL, agr_centre VARCHAR(50) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, societe_nom VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, reseau_id INT NOT NULL, reseau_nom VARCHAR(50) CHARACTER SET utf8mb4 DEFAULT \'\' NOT NULL COLLATE `utf8mb4_0900_ai_ci`, UNIQUE INDEX unique_client_annee_mois_centre_societe (code_client, annee, mois, agr_centre, societe_nom), INDEX idx_synthese_pros_periode (annee, mois), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CA5859934A');
        $this->addSql('ALTER TABLE user_notification DROP FOREIGN KEY FK_3F980AC8EF1A9D84');
        $this->addSql('ALTER TABLE user_notification DROP FOREIGN KEY FK_3F980AC8A76ED395');
        $this->addSql('DROP TABLE notification');
        $this->addSql('DROP TABLE user_notification');
    }
}
