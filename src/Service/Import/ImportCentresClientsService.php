<?php

namespace App\Service\Import;

/**
 * Imports CSV rows into the `centres_clients` table.
 */
class ImportCentresClientsService extends \App\Service\Import\AbstractCsvImportService
{
    /**
     * @return string Target table name.
     */
    protected static function getTableName(): string
    {
        return 'centres_clients';
    }

    /**
     * @return array<int, string> Target table columns.
     */
    protected static function getColumns(): array
    {
        return [
            'agr_centre',
            'idclient',
            'reseau_id',
        ];
    }

    /**
     * @return array<int, string> Unique key columns.
     */
    protected static function getUniqueKeys(): array
    {
        return ['idclient'];
    }

    /**
     * @return array<string, array<int, string>> CSV-to-database mapping.
     */
    protected static function getColumnMapping(): array
    {
        return [
            'agr_centre' => ['agr_centre'],
            'idclient' => ['idclient'],
        ];
    }

    /**
     * @return array<int, string> Date/time columns.
     */
    protected static function getDateColumns(): array
    {
        return [];
    }

    /**
     * @return array<int, string> Decimal columns.
     */
    protected static function getDecimalColumns(): array
    {
        return [];
    }
}
