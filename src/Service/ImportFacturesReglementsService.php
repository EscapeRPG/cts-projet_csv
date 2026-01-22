<?php

namespace App\Service;

class ImportFacturesReglementsService extends AbstractCsvImportService
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
