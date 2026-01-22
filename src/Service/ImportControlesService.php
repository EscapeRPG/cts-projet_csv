<?php

namespace App\Service;

class ImportControlesService extends AbstractCsvImportService
{
    protected static function getTableName(): string
    {
        return 'controles';
    }

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

    protected static function getUniqueKeys(): array
    {
        return ['idcontrole'];
    }

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
            'annee_circulation' => ['annee_circulation'],
        ];
    }

    protected static function getDateColumns(): array
    {
        return ['date_export', 'date_prise_rdv', 'deb_ctrl', 'fin_ctrl', 'date_ctrl'];
    }

    protected static function getDecimalColumns(): array
    {
        return [];
    }
}
