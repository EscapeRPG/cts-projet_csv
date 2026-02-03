<?php

namespace App\Service\Import;

class ImportControlesFacturesService extends \App\Service\Import\AbstractCsvImportService
{
    protected static function getTableName(): string
    {
        return 'controles_factures';
    }

    protected static function getColumns(): array
    {
        return [
            'agr_centre',
            'agr_controleur',
            'idcontrole',
            'idfacture',
            'idclient',
            'reseau_id',
            'data_date',
        ];
    }

    protected static function getUniqueKeys(): array
    {
        return ['idcontrole', 'idfacture'];
    }

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

    protected static function getDateColumns(): array
    {
        return [];
    }

    protected static function getDecimalColumns(): array
    {
        return [];
    }
}
