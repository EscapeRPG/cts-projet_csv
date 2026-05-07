<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260507120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add user_societe join table for encours/company scoping.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE user_societe (user_id INT NOT NULL, societe_id INT NOT NULL, INDEX IDX_6D6C6720A76ED395 (user_id), INDEX IDX_6D6C6720A188FE64 (societe_id), PRIMARY KEY(user_id, societe_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE user_societe ADD CONSTRAINT FK_6D6C6720A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_societe ADD CONSTRAINT FK_6D6C6720A188FE64 FOREIGN KEY (societe_id) REFERENCES societe (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE user_societe');
    }
}

