<?php

namespace App\Service\Import;

class ImportFacturesService extends \App\Service\Import\AbstractCsvImportService
{
    protected static function getTableName(): string
    {
        return 'factures';
    }

    protected static function getColumns(): array
    {
        return [
            'idfacture',
            'date_export',
            'type_facture',
            'date_facture',
            'date_echeance',
            'num_tva_intra',
            'devise',
            'otc_ht',
            'montant_tva_otc_ht',
            'pourcentage_tva_otc',
            'otc_ttc',
            'montant_presta_ht',
            'montant_presta_ttc',
            'pourcentage_tva_presta',
            'montant_tva_presta',
            'montant_remise',
            'pourcentage_remise',
            'total_ht',
            'total_ttc',
            'pourcentage_tva',
            'montant_tva',
            'num_facture',
            'numero_releve',
            'reseau_id',
        ];
    }

    protected static function getUniqueKeys(): array
    {
        return ['idfacture'];
    }

    protected static function getColumnMapping(): array
    {
        return [
            'idfacture' => ['idfacture'],
            'date_export' => ['date_export'],
            'type_facture' => ['type_facture'],
            'date_facture' => ['date_facture'],
            'date_echeance' => ['date_echeance'],
            'num_tva_intra' => ['num_tva_intra'],
            'devise' => ['devise'],
            'otc_ht' => ['otc_ht'],
            'montant_tva_otc_ht' => ['montant_tva_otc_ht', 'montant_tva_otc'],
            'pourcentage_tva_otc' => ['pourcentage_tva_otc', 'pourcentage_tva', '_otc'],
            'otc_ttc' => ['otc_ttc'],
            'montant_presta_ht' => ['montant_presta_ht'],
            'montant_presta_ttc' => ['montant_presta_ttc'],
            'pourcentage_tva_presta' => ['pourcentage_tva_presta'],
            'montant_tva_presta' => ['montant_tva_presta'],
            'montant_remise' => ['montant_remise'],
            'pourcentage_remise' => ['pourcentage_remise'],
            'total_ht' => ['total_ht'],
            'total_ttc' => ['total_ttc'],
            'pourcentage_tva' => ['pourcentage_tva'],
            'montant_tva' => ['montant_tva'],
            'num_facture' => ['num_facture'],
            'numero_releve' => ['numero_releve'],
        ];
    }

    protected static function getDateColumns(): array
    {
        return ['date_export', 'date_facture', 'date_echeance'];
    }

    protected static function getDecimalColumns(): array
    {
        return [
            'otc_ht',
            'montant_tva_otc_ht',
            'pourcentage_tva_otc',
            'otc_ttc',
            'montant_presta_ht',
            'montant_presta_ttc',
            'pourcentage_tva_presta',
            'montant_tva_presta',
            'montant_remise',
            'pourcentage_remise',
            'total_ht',
            'total_ttc',
            'pourcentage_tva',
            'montant_tva'
            ];
    }
}
