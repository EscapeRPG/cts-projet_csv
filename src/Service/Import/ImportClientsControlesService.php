<?php

namespace App\Service\Import;

class ImportClientsControlesService extends \App\Service\Import\AbstractCsvImportService
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
            'reseau_id',
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
