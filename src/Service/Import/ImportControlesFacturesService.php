<?php

namespace App\Service\Import;

/**
 * Imports CSV rows into the `controles_factures` table.
 */
class ImportControlesFacturesService extends \App\Service\Import\AbstractCsvImportService
{
    /**
     * @return string Target table name.
     */
    protected static function getTableName(): string
    {
        return 'controles_factures';
    }

    /**
     * @return array<int, string> Target table columns.
     */
    protected static function getColumns(): array
    {
        return [
            'agr_centre',
            'agr_controleur',
            'idcontrole',
            'idfacture',
            'idclient',
            'reseau_id',
        ];
    }

    /**
     * @return array<int, string> Unique key columns.
     */
    protected static function getUniqueKeys(): array
    {
        return ['idcontrole', 'idfacture'];
    }

    /**
     * @return array<string, array<int, string>> CSV-to-database mapping.
     */
    protected static function getColumnMapping(): array
    {
        return [
            'agr_centre' => ['agr_centre'],
            'agr_controleur' => ['agr_controleur'],
            'idcontrole' => ['idcontrole'],
            'idfacture' => ['idfacture'],
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
