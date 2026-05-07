<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260505113312 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE encours_bancaire (id INT AUTO_INCREMENT NOT NULL, centre VARCHAR(255) NOT NULL, banque VARCHAR(255) DEFAULT NULL, emprunt NUMERIC(10, 2) DEFAULT NULL, date VARCHAR(255) DEFAULT NULL, garanties VARCHAR(255) DEFAULT NULL, type VARCHAR(20) NOT NULL, solde2015 NUMERIC(10, 2) DEFAULT NULL, solde2016 NUMERIC(10, 2) DEFAULT NULL, solde2017 NUMERIC(10, 2) DEFAULT NULL, solde2018 NUMERIC(10, 2) DEFAULT NULL, solde2019 NUMERIC(10, 2) DEFAULT NULL, solde2020 NUMERIC(10, 2) DEFAULT NULL, solde2021 NUMERIC(10, 2) DEFAULT NULL, solde2022 NUMERIC(10, 2) DEFAULT NULL, solde2023 NUMERIC(10, 2) DEFAULT NULL, solde2024 NUMERIC(10, 2) DEFAULT NULL, solde2025 NUMERIC(10, 2) DEFAULT NULL, solde2026 NUMERIC(10, 2) DEFAULT NULL, solde2027 NUMERIC(10, 2) DEFAULT NULL, solde2028 NUMERIC(10, 2) DEFAULT NULL, solde2029 NUMERIC(10, 2) DEFAULT NULL, solde2030 NUMERIC(10, 2) DEFAULT NULL, solde2031 NUMERIC(10, 2) DEFAULT NULL, solde2032 NUMERIC(10, 2) DEFAULT NULL, solde2033 NUMERIC(10, 2) DEFAULT NULL, solde2034 NUMERIC(10, 2) DEFAULT NULL, solde2035 NUMERIC(10, 2) DEFAULT NULL, solde2036 NUMERIC(10, 2) DEFAULT NULL, solde2037 NUMERIC(10, 2) DEFAULT NULL, solde2038 NUMERIC(10, 2) DEFAULT NULL, solde2039 NUMERIC(10, 2) DEFAULT NULL, solde2040 NUMERIC(10, 2) DEFAULT NULL, societe_id INT DEFAULT NULL, INDEX IDX_C4CD236BFCF77503 (societe_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE encours_bancaire ADD CONSTRAINT FK_C4CD236BFCF77503 FOREIGN KEY (societe_id) REFERENCES societe (id)');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CA5859934A FOREIGN KEY (salarie_id) REFERENCES salarie (id)');
        $this->addSql('ALTER TABLE salarie_centre ADD CONSTRAINT FK_105A9AFE5859934A FOREIGN KEY (salarie_id) REFERENCES salarie (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE salarie_centre ADD CONSTRAINT FK_105A9AFE463CD7C3 FOREIGN KEY (centre_id) REFERENCES centre (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_centre ADD CONSTRAINT FK_A3F2F148A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_centre ADD CONSTRAINT FK_A3F2F148463CD7C3 FOREIGN KEY (centre_id) REFERENCES centre (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_notification ADD CONSTRAINT FK_3F980AC8EF1A9D84 FOREIGN KEY (notification_id) REFERENCES notification (id)');
        $this->addSql('ALTER TABLE user_notification ADD CONSTRAINT FK_3F980AC8A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE voiture ADD CONSTRAINT FK_E9E2810FFCF77503 FOREIGN KEY (societe_id) REFERENCES societe (id)');
        $this->addSql('ALTER TABLE voiture ADD CONSTRAINT FK_E9E2810F463CD7C3 FOREIGN KEY (centre_id) REFERENCES centre (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE encours_bancaire DROP FOREIGN KEY FK_C4CD236BFCF77503');
        $this->addSql('DROP TABLE encours_bancaire');
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CA5859934A');
        $this->addSql('ALTER TABLE salarie_centre DROP FOREIGN KEY FK_105A9AFE5859934A');
        $this->addSql('ALTER TABLE salarie_centre DROP FOREIGN KEY FK_105A9AFE463CD7C3');
        $this->addSql('ALTER TABLE user_centre DROP FOREIGN KEY FK_A3F2F148A76ED395');
        $this->addSql('ALTER TABLE user_centre DROP FOREIGN KEY FK_A3F2F148463CD7C3');
        $this->addSql('ALTER TABLE user_notification DROP FOREIGN KEY FK_3F980AC8EF1A9D84');
        $this->addSql('ALTER TABLE user_notification DROP FOREIGN KEY FK_3F980AC8A76ED395');
        $this->addSql('ALTER TABLE voiture DROP FOREIGN KEY FK_E9E2810FFCF77503');
        $this->addSql('ALTER TABLE voiture DROP FOREIGN KEY FK_E9E2810F463CD7C3');
    }
}
