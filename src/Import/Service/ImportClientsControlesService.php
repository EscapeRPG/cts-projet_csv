<?php

namespace App\Import\Service;

/**
 * Imports CSV rows into the `clients_controles` table.
 */
class ImportClientsControlesService extends \App\Import\Service\AbstractCsvImportService
{
    /**
     * @return string Target table name.
     */
    protected static function getTableName(): string
    {
        return 'clients_controles';
    }

    /**
     * @return array<int, string> Target table columns.
     */
    protected static function getColumns(): array
    {
        return [
            'agr_centre',
            'agr_controleur',
            'idclient',
            'idcontrole',
            'reseau_id',
        ];
    }

    /**
     * @return array<string, array<int, string>> CSV-to-database mapping.
     */
    protected static function getColumnMapping(): array
    {
        return [
            'idclient' => ['idclient'],
            'idcontrole' => ['idcontrole'],
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
