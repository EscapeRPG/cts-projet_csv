<?php

namespace App\DTO;

use App\Utils\DateParser;

class FacturesDTO
{
    public function __construct(
        public string             $idfacture,
        public \DateTimeImmutable $dateExport,
        public string             $numFacture,
        public string             $typeFacture,
        public \DateTimeImmutable $dateFacture,
        public \DateTimeImmutable $dateEcheance,
        public string             $numTvaIntra,
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
        public ?string            $numeroReleve,
    )
    {
    }

    /**
     * @throws \Exception
     */
    public static function fromArray(array $data): self
    {
        return new self(
            idfacture: $data['idfacture'],
            dateExport: DateParser::parseDate($data['date_export'] ?? null),
            numFacture: $data['num_facture'],
            typeFacture: $data['type_facture'],
            dateFacture: DateParser::parseDate($data['date_facture'] ?? null),
            dateEcheance: DateParser::parseDate($data['date_echeance'] ?? null),
            numTvaIntra: $data['num_tva_intra'],
            devise: $data['devise'],
            otcHt: $data['otc_ht'],
            montantTvaOtcHt: $data['montant_tva_otc'],
            pourcentageTvaOtc: $data['pourcentage_tva_otc'],
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
            numeroReleve: $data['numero_releve'] ?? null,
        );
    }
}
