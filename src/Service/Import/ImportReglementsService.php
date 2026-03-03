<?php

namespace App\Service\Import;

/**
 * Imports CSV rows into the `reglements` table.
 */
class ImportReglementsService extends AbstractCsvImportService
{
    /**
     * @return string Target table name.
     */
    protected static function getTableName(): string
    {
        return 'reglements';
    }

    /**
     * @return array<int, string> Target table columns.
     */
    protected static function getColumns(): array
    {
        return [
            'idreglement',
            'date_export',
            'mode_reglt',
            'date_reglt',
            'montant_reglt',
            'banque',
            'numero_cheque',
            'numero_releve',
            'reseau_id',
        ];
    }

    /**
     * @return array<int, string> Unique key columns.
     */
    protected static function getUniqueKeys(): array
    {
        return ['idreglement'];
    }

    /**
     * @return array<string, array<int, string>> CSV-to-database mapping.
     */
    protected static function getColumnMapping(): array
    {
        return [
            'idreglement' => ['idreglement'],
            'date_export' => ['date_export'],
            'mode_reglt' => ['mode_reglt'],
            'date_reglt' => ['date_reglt'],
            'montant_reglt' => ['montant_reglt'],
            'banque' => ['banque'],
            'numero_cheque' => ['numero_cheque'],
            'numero_releve' => ['numero_releve'],
        ];
    }

    /**
     * @return array<int, string> Date/time columns.
     */
    protected static function getDateColumns(): array
    {
        return ['date_export', 'date_reglt'];
    }

    /**
     * @return array<int, string> Decimal columns.
     */
    protected static function getDecimalColumns(): array
    {
        return ['montant_reglt'];
    }
}
