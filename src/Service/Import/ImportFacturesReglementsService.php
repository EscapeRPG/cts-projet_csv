<?php

namespace App\Service\Import;

class ImportFacturesReglementsService extends \App\Service\Import\AbstractCsvImportService
{
    protected static function getTableName(): string
    {
        return 'factures_reglements';
    }

    protected static function getColumns(): array
    {
        return [
            'agr_centre',
            'idfacture',
            'idreglement',
            'idclient',
            'reseau_id',
            'data_date',
        ];
    }

    protected static function getUniqueKeys(): array
    {
        return ['idfacture', 'idreglement'];
    }

    protected static function getColumnMapping(): array
    {
        return [
            'agr_centre' => ['agr_centre'],
            'idfacture' => ['idfacture'],
            'idreglement' => ['idreglement'],
            'idclient' => ['idclient'],
        ];
    }

    protected static function getDateColumns(): array
    {
        return [];
    }

    protected static function getDecimalColumns(): array
    {
        return [];
    }
}
