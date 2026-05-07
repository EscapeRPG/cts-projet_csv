<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260505114244 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE encours_bancaire ADD annee INT NOT NULL, DROP solde2015, DROP solde2016, DROP solde2017, DROP solde2018, DROP solde2019, DROP solde2020, DROP solde2021, DROP solde2022, DROP solde2023, DROP solde2024, DROP solde2025, DROP solde2026, DROP solde2027, DROP solde2028, DROP solde2029, DROP solde2030, DROP solde2031, DROP solde2032, DROP solde2033, DROP solde2034, DROP solde2035, DROP solde2036, DROP solde2037, DROP solde2038, DROP solde2039, DROP solde2040');
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
        $this->addSql('ALTER TABLE encours_bancaire ADD solde2015 NUMERIC(10, 2) DEFAULT NULL, ADD solde2016 NUMERIC(10, 2) DEFAULT NULL, ADD solde2017 NUMERIC(10, 2) DEFAULT NULL, ADD solde2018 NUMERIC(10, 2) DEFAULT NULL, ADD solde2019 NUMERIC(10, 2) DEFAULT NULL, ADD solde2020 NUMERIC(10, 2) DEFAULT NULL, ADD solde2021 NUMERIC(10, 2) DEFAULT NULL, ADD solde2022 NUMERIC(10, 2) DEFAULT NULL, ADD solde2023 NUMERIC(10, 2) DEFAULT NULL, ADD solde2024 NUMERIC(10, 2) DEFAULT NULL, ADD solde2025 NUMERIC(10, 2) DEFAULT NULL, ADD solde2026 NUMERIC(10, 2) DEFAULT NULL, ADD solde2027 NUMERIC(10, 2) DEFAULT NULL, ADD solde2028 NUMERIC(10, 2) DEFAULT NULL, ADD solde2029 NUMERIC(10, 2) DEFAULT NULL, ADD solde2030 NUMERIC(10, 2) DEFAULT NULL, ADD solde2031 NUMERIC(10, 2) DEFAULT NULL, ADD solde2032 NUMERIC(10, 2) DEFAULT NULL, ADD solde2033 NUMERIC(10, 2) DEFAULT NULL, ADD solde2034 NUMERIC(10, 2) DEFAULT NULL, ADD solde2035 NUMERIC(10, 2) DEFAULT NULL, ADD solde2036 NUMERIC(10, 2) DEFAULT NULL, ADD solde2037 NUMERIC(10, 2) DEFAULT NULL, ADD solde2038 NUMERIC(10, 2) DEFAULT NULL, ADD solde2039 NUMERIC(10, 2) DEFAULT NULL, ADD solde2040 NUMERIC(10, 2) DEFAULT NULL, DROP annee');
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
