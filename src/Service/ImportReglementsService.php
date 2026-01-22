<?php

namespace App\Service;

class ImportReglementsService extends AbstractCsvImportService
{
    protected static function getTableName(): string
    {
        return 'reglements';
    }

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

    protected static function getUniqueKeys(): array
    {
        return ['idreglement'];
    }

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

    protected static function getDateColumns(): array
    {
        return ['date_export', 'date_reglt'];
    }

    protected static function getDecimalColumns(): array
    {
        return ['montant_reglt'];
    }
}
