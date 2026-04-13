<?php

namespace App\Service\Import;

/**
 * Imports CSV rows into the `controles` table.
 */
class ImportControlesService extends \App\Service\Import\AbstractCsvImportService
{
    /**
     * @return string Target table name.
     */
    protected static function getTableName(): string
    {
        return 'controles';
    }

    /**
     * @return array<int, string> Target table columns.
     */
    protected static function getColumns(): array
    {
        return [
            'idcontrole',
            'date_export',
            'num_pv_ctrl',
            'num_lia_ctrl',
            'immat_vehicule',
            'num_serie_vehicule',
            'date_prise_rdv',
            'type_rdv',
            'deb_ctrl',
            'fin_ctrl',
            'date_ctrl',
            'temps_ctrl',
            'ref_temps',
            'res_ctrl',
            'type_ctrl',
            'modele_vehicule',
            'annee_circulation',
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
            'num_pv_ctrl' => ['num_pv_ctrl'],
            'num_lia_ctrl' => ['num_lia_ctrl'],
            'immat_vehicule' => ['immat_vehicule'],
            'num_serie_vehicule' => ['num_serie_vehicule'],
            'date_prise_rdv' => ['date_prise_rdv'],
            'type_rdv' => ['type_rdv'],
            'deb_ctrl' => ['deb_ctrl'],
            'fin_ctrl' => ['fin_ctrl'],
            'date_ctrl' => ['date_ctrl'],
            'temps_ctrl' => ['temps_ctrl'],
            'ref_temps' => ['ref_temps'],
            'res_ctrl' => ['res_ctrl'],
            'type_ctrl' => ['type_ctrl'],
            'modele_vehicule' => ['modele_vehicule'],
            'annee_circulation' => ['annee_circulation', 'datemise_en_service_vehicule', 'date_mise_en_service_vehicule'],
        ];
    }

    /**
     * @return array<int, string> Date/time columns.
     */
    protected static function getDateColumns(): array
    {
        return ['date_export', 'date_prise_rdv', 'deb_ctrl', 'fin_ctrl', 'date_ctrl'];
    }

    /**
     * @return array<int, string> Decimal columns.
     */
    protected static function getDecimalColumns(): array
    {
        return [];
    }
}
