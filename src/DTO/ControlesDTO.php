<?php

namespace App\DTO;

use App\Utils\DateParser;

class ControlesDTO
{
    public function __construct(
        public string             $idcontrole,
        public \DateTimeImmutable $dateExport,
        public string             $numPvControle,
        public string             $numLiaControle,
        public string             $immatVehicule,
        public string             $numSerieVehicule,
        public ?\DateTimeImmutable $datePriseRdv,
        public string             $typeRdv,
        public \DateTimeImmutable $debCtrl,
        public \DateTimeImmutable $finCtrl,
        public \DateTimeImmutable $dateCtrl,
        public int                $tempsCtrl,
        public int                $refTemps,
        public string             $resCtrl,
        public string             $typeCtrl,
        public string             $modeleVehicule,
        public int                $anneeCirculation,
    )
    {
    }

    /**
     * @throws \Exception
     */
    public static function fromArray(array $data): self
    {
        return new self(
            idcontrole: $data['idcontrole'],
            dateExport: DateParser::parseDate($data['date_export'] ?? null),
            numPvControle: $data['num_pv_ctrl'],
            numLiaControle: $data['num_lia_ctrl'],
            immatVehicule: $data['immat_vehicule'],
            numSerieVehicule: $data['num_serie_vehicule'],
            datePriseRdv: DateParser::parseDate($data['date_prise_rdv'] ?? null),
            typeRdv: $data['type_rdv'],
            debCtrl: DateParser::parseDate($data['deb_ctrl'] ?? null),
            finCtrl: DateParser::parseDate($data['fin_ctrl'] ?? null),
            dateCtrl: DateParser::parseDate($data['date_ctrl'] ?? null),
            tempsCtrl: $data['temps_ctrl'],
            refTemps: $data['ref_temps'],
            resCtrl: $data['res_ctrl'],
            typeCtrl: $data['type_ctrl'],
            modeleVehicule: $data['modele_vehicule'],
            anneeCirculation: $data['annee_circulation'],
        );
    }
}
