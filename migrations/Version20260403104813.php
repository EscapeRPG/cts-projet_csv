<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260403104813 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE salarie_centre (salarie_id INT NOT NULL, centre_id INT NOT NULL, INDEX IDX_105A9AFE5859934A (salarie_id), INDEX IDX_105A9AFE463CD7C3 (centre_id), PRIMARY KEY (salarie_id, centre_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE salarie_centre ADD CONSTRAINT FK_105A9AFE5859934A FOREIGN KEY (salarie_id) REFERENCES salarie (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE salarie_centre ADD CONSTRAINT FK_105A9AFE463CD7C3 FOREIGN KEY (centre_id) REFERENCES centre (id) ON DELETE CASCADE');

        $this->addSql('ALTER TABLE user ADD salarie_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT FK_8D93D6495859934A FOREIGN KEY (salarie_id) REFERENCES salarie (id) ON DELETE SET NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D6495859934A ON user (salarie_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE salarie_centre DROP FOREIGN KEY FK_105A9AFE5859934A');
        $this->addSql('ALTER TABLE salarie_centre DROP FOREIGN KEY FK_105A9AFE463CD7C3');
        $this->addSql('DROP TABLE salarie_centre');

        $this->addSql('ALTER TABLE user DROP FOREIGN KEY FK_8D93D6495859934A');
        $this->addSql('DROP INDEX UNIQ_8D93D6495859934A ON user');
        $this->addSql('ALTER TABLE user DROP salarie_id');
    }
}
