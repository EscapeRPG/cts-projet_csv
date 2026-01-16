<?php

namespace App\Service;

class ImportClientsService extends AbstractCsvImportService
{
    protected static function getTableName(): string
    {
        return 'clients';
    }

    protected static function getColumns(): array
    {
        return [
            'idclient',
            'date_export',
            'date_creation',
            'code_client',
            'nom_code_client',
            'code_sage',
            'nom',
            'prenom',
            'adresse1',
            'adresse2',
            'cp',
            'ville',
            'email',
            'telephone',
            'mobile',
            'num_tva_intra',
        ];
    }

    protected static function getUniqueKeys(): array
    {
        return ['idclient'];
    }

    protected static function getColumnMapping(): array
    {
        return [
            'idclient' => ['idclient'],
            'date_export' => ['date_export'],
            'date_creation' => ['date_creation'],
            'code_client' => ['code_client'],
            'nom_code_client' => ['nom_code_client'],
            'code_sage' => ['code_sage'],
            'nom' => ['nom'],
            'prenom' => ['prenom'],
            'adresse1' => ['adresse1'],
            'adresse2' => ['adresse2'],
            'cp' => ['cp'],
            'ville' => ['ville'],
            'email' => ['email'],
            'telephone' => ['telephone'],
            'mobile' => ['mobile'],
            'num_tva_intra' => ['num_tva_intra']
        ];
    }

    protected static function getDateColumns(): array
    {
        return ['date_export', 'date_creation'];
    }

    protected static function getDecimalColumns(): array
    {
        return [];
    }
}
