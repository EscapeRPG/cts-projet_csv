<?php

namespace App\DTO;

use App\Utils\DateParser;

class PrestasNonFactureesDTO
{
    public function __construct(
        public string             $idcontrole,
        public ?\DateTimeImmutable $dateExport,
        public string             $devise,
        public string             $otcHt,
        public string             $montantTvaOtcHt,
        public string             $pourcentageTvaOtc,
        public string             $otcTtc,
        public string             $montantPrestaHt,
        public string             $montantPrestaTtc,
        public string             $pourcentageTvaPresta,
        public string             $montantTvaPresta,
        public string             $montantRemise,
        public string             $pourcentageRemise,
        public string             $totalHt,
        public string             $totalTtc,
        public string             $pourcentageTva,
        public string             $montantTva,
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
            devise: $data['devise'],
            otcHt: $data['otc_ht'],
            montantTvaOtcHt: $data['montant_tva_otc'],
            pourcentageTvaOtc: $data['_otc'],
            otcTtc: $data['otc_ttc'],
            montantPrestaHt: $data['montant_presta_ht'],
            montantPrestaTtc: $data['montant_presta_ttc'],
            pourcentageTvaPresta: $data['pourcentage_tva_presta'],
            montantTvaPresta: $data['montant_tva_presta'],
            montantRemise: $data['montant_remise'],
            pourcentageRemise: $data['pourcentage_remise'],
            totalHt: $data['total_ht'],
            totalTtc: $data['total_ttc'],
            pourcentageTva: $data['pourcentage_tva'],
            montantTva: $data['montant_tva'],
        );
    }
}
