<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260220183000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Convertit les tables MyISAM en InnoDB et ajoute les clés étrangères manquantes.';
    }

    public function up(Schema $schema): void
    {
        // Nettoyage de données orphelines avant création des contraintes FK.
        // Les demandes de reset orphelines sont obsolètes et peuvent être supprimées sans impact.
        $this->addSql('
            DELETE r
            FROM reset_password_request r
            LEFT JOIN user u ON u.id = r.user_id
            WHERE r.user_id IS NOT NULL
              AND u.id IS NULL
        ');

        $this->convertAllMyIsamTablesToInnoDb();

        $this->addForeignKeyIfMissing('centres_clients', 'reseau_id', 'reseau', 'id', 'FK_16BB6A2A445D170C');
        $this->addForeignKeyIfMissing('clients', 'reseau_id', 'reseau', 'id', 'FK_C82E74445D170C');
        $this->addForeignKeyIfMissing('clients_controles', 'reseau_id', 'reseau', 'id', 'FK_E0443B21445D170C');
        $this->addForeignKeyIfMissing('controles', 'reseau_id', 'reseau', 'id', 'FK_B10ABA6D445D170C');
        $this->addForeignKeyIfMissing('controles_factures', 'reseau_id', 'reseau', 'id', 'FK_B77B2F69445D170C');
        $this->addForeignKeyIfMissing('controles_non_factures', 'reseau_id', 'reseau', 'id', 'FK_76FCF60E445D170C');
        $this->addForeignKeyIfMissing('factures', 'reseau_id', 'reseau', 'id', 'FK_647590B445D170C');
        $this->addForeignKeyIfMissing('factures_reglements', 'reseau_id', 'reseau', 'id', 'FK_1D789B86445D170C');
        $this->addForeignKeyIfMissing('imported_files', 'reseau_id', 'reseau', 'id', 'FK_D8475CB7445D170C');
        $this->addForeignKeyIfMissing('prestas_non_facturees', 'reseau_id', 'reseau', 'id', 'FK_6E23438D445D170C');
        $this->addForeignKeyIfMissing('reglements', 'reseau_id', 'reseau', 'id', 'FK_648F2671445D170C');
        $this->addForeignKeyIfMissing('centre', 'reseau_id', 'reseau', 'id', 'FK_C6A0EA75445D170C');
        $this->addForeignKeyIfMissing('centre', 'societe_id', 'societe', 'id', 'FK_C6A0EA75FCF77503');
        $this->addForeignKeyIfMissing('reset_password_request', 'user_id', 'user', 'id', 'FK_7CE748AA76ED395');
        $this->addForeignKeyIfMissing('salarie', 'societe_id', 'societe', 'id', 'FK_828E3A1AFCF77503');
    }

    public function down(Schema $schema): void
    {
        // Les conversions InnoDB -> MyISAM ne sont pas appliquées en rollback.
        $this->dropForeignKeyIfExists('centres_clients', 'FK_16BB6A2A445D170C');
        $this->dropForeignKeyIfExists('clients', 'FK_C82E74445D170C');
        $this->dropForeignKeyIfExists('clients_controles', 'FK_E0443B21445D170C');
        $this->dropForeignKeyIfExists('controles', 'FK_B10ABA6D445D170C');
        $this->dropForeignKeyIfExists('controles_factures', 'FK_B77B2F69445D170C');
        $this->dropForeignKeyIfExists('controles_non_factures', 'FK_76FCF60E445D170C');
        $this->dropForeignKeyIfExists('factures', 'FK_647590B445D170C');
        $this->dropForeignKeyIfExists('factures_reglements', 'FK_1D789B86445D170C');
        $this->dropForeignKeyIfExists('imported_files', 'FK_D8475CB7445D170C');
        $this->dropForeignKeyIfExists('prestas_non_facturees', 'FK_6E23438D445D170C');
        $this->dropForeignKeyIfExists('reglements', 'FK_648F2671445D170C');
        $this->dropForeignKeyIfExists('centre', 'FK_C6A0EA75445D170C');
        $this->dropForeignKeyIfExists('centre', 'FK_C6A0EA75FCF77503');
        $this->dropForeignKeyIfExists('reset_password_request', 'FK_7CE748AA76ED395');
        $this->dropForeignKeyIfExists('salarie', 'FK_828E3A1AFCF77503');
    }

    private function convertAllMyIsamTablesToInnoDb(): void
    {
        $tables = $this->connection->fetchFirstColumn(
            "
                SELECT TABLE_NAME
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_TYPE = 'BASE TABLE'
                  AND ENGINE = 'MyISAM'
                ORDER BY TABLE_NAME
            "
        );

        foreach ($tables as $table) {
            $this->addSql(sprintf('ALTER TABLE `%s` ENGINE=InnoDB', $table));
        }
    }

    private function addForeignKeyIfMissing(
        string $table,
        string $column,
        string $referencedTable,
        string $referencedColumn,
        string $constraintName
    ): void {
        $exists = (int)$this->connection->fetchOne(
            '
                SELECT COUNT(*)
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = :table_name
                  AND COLUMN_NAME = :column_name
                  AND REFERENCED_TABLE_NAME = :ref_table
                  AND REFERENCED_COLUMN_NAME = :ref_column
            ',
            [
                'table_name' => $table,
                'column_name' => $column,
                'ref_table' => $referencedTable,
                'ref_column' => $referencedColumn,
            ]
        );

        if ($exists > 0) {
            return;
        }

        $this->addSql(sprintf(
            'ALTER TABLE `%s` ADD CONSTRAINT `%s` FOREIGN KEY (`%s`) REFERENCES `%s` (`%s`)',
            $table,
            $constraintName,
            $column,
            $referencedTable,
            $referencedColumn
        ));
    }

    private function dropForeignKeyIfExists(string $table, string $constraintName): void
    {
        $exists = (int)$this->connection->fetchOne(
            '
                SELECT COUNT(*)
                FROM information_schema.TABLE_CONSTRAINTS
                WHERE CONSTRAINT_SCHEMA = DATABASE()
                  AND TABLE_NAME = :table_name
                  AND CONSTRAINT_NAME = :constraint_name
                  AND CONSTRAINT_TYPE = \'FOREIGN KEY\'
            ',
            [
                'table_name' => $table,
                'constraint_name' => $constraintName,
            ]
        );

        if ($exists === 0) {
            return;
        }

        $this->addSql(sprintf(
            'ALTER TABLE `%s` DROP FOREIGN KEY `%s`',
            $table,
            $constraintName
        ));
    }
}
