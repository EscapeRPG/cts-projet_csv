<?php

namespace App\Service;

class ImportControlesNonFacturesService extends AbstractCsvImportService
{
    protected static function getTableName(): string
    {
        return 'controles_non_factures';
    }

    protected static function getColumns(): array
    {
        return [
            'agr_centre',
            'agr_controleur',
            'idcontrole',
            'idclient',
            'reseau_id',
        ];
    }

    protected static function getUniqueKeys(): array
    {
        return ['idcontrole', 'idclient'];
    }

    protected static function getColumnMapping(): array
    {
        return [
            'agr_centre' => ['agr_centre'],
            'agr_controleur' => ['agr_controleur'],
            'idcontrole' => ['idcontrole'],
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
