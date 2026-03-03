<?php

namespace App\Service\Import;

/**
 * Imports CSV rows into the `factures` table.
 */
class ImportFacturesService extends \App\Service\Import\AbstractCsvImportService
{
    /**
     * @return string Target table name.
     */
    protected static function getTableName(): string
    {
        return 'factures';
    }

    /**
     * @return array<int, string> Target table columns.
     */
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

    /**
     * @return array<int, string> Unique key columns.
     */
    protected static function getUniqueKeys(): array
    {
        return ['idfacture'];
    }

    /**
     * @return array<string, array<int, string>> CSV-to-database mapping.
     */
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
            'montant_presta_ht' => ['montant_presta_ht', 'montant_prestation_ht'],
            'montant_presta_ttc' => ['montant_presta_ttc', 'montant_prestation_ttc'],
            'pourcentage_tva_presta' => ['pourcentage_tva_presta', 'pourcentage_tva_prestation'],
            'montant_tva_presta' => ['montant_tva_presta', 'montant_tva_prestation'],
            'montant_remise' => ['montant_remise'],
            'pourcentage_remise' => ['pourcentage_remise'],
            'total_ht' => ['total_ht'],
            'total_ttc' => ['total_ttc'],
            'pourcentage_tva' => ['pourcentage_tva', 'pourcentage_tva_total'],
            'montant_tva' => ['montant_tva'],
            'num_facture' => ['num_facture'],
            'numero_releve' => ['numero_releve'],
        ];
    }

    /**
     * @return array<int, string> Date/time columns.
     */
    protected static function getDateColumns(): array
    {
        return ['date_export', 'date_facture', 'date_echeance'];
    }

    /**
     * @return array<int, string> Decimal columns.
     */
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
