<?php

namespace App\Service;

class ImportCentresClientsService extends AbstractCsvImportService
{
    protected static function getTableName(): string
    {
        return 'centres_clients';
    }

    protected static function getColumns(): array
    {
        return [
            'agr_centre',
            'idclient',
            'reseau_id',
        ];
    }

    protected static function getUniqueKeys(): array
    {
        return ['idclient'];
    }

    protected static function getColumnMapping(): array
    {
        return [
            'agr_centre' => ['agr_centre'],
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
