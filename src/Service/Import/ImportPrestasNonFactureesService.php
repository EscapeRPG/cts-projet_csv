<?php

namespace App\Service\Import;

/**
 * Imports CSV rows into the `prestas_non_facturees` table.
 */
class ImportPrestasNonFactureesService extends \App\Service\Import\AbstractCsvImportService
{
    /**
     * @return string Target table name.
     */
    protected static function getTableName(): string
    {
        return 'prestas_non_facturees';
    }

    /**
     * @return array<int, string> Target table columns.
     */
    protected static function getColumns(): array
    {
        return [
            'idcontrole',
            'date_export',
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
            'reseau_id',
        ];
    }

    /**
     * @return array<int, string> Unique key columns.
     */
    protected static function getUniqueKeys(): array
    {
        return ['idcontrole'];
    }

    /**
     * @return array<string, array<int, string>> CSV-to-database mapping.
     */
    protected static function getColumnMapping(): array
    {
        return [
            'idcontrole' => ['idcontrole'],
            'date_export' => ['date_export'],
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
        ];
    }

    /**
     * @return array<int, string> Date/time columns.
     */
    protected static function getDateColumns(): array
    {
        return ['date_export'];
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
