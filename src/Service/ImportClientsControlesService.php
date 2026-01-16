<?php

namespace App\Service;

class ImportClientsControlesService extends AbstractCsvImportService
{
    protected static function getTableName(): string
    {
        return 'clients_controles';
    }

    protected static function getColumns(): array
    {
        return [
            'agr_centre',
            'agr_controleur',
            'idclient',
            'idcontrole',
        ];
    }

    protected static function getUniqueKeys(): array
    {
        return ['idclient', 'idcontrole'];
    }

    protected static function getColumnMapping(): array
    {
        return [
            'idclient' => ['idclient'],
            'idcontrole' => ['idcontrole'],
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
