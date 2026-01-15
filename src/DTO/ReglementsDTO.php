<?php

namespace App\DTO;

use App\Utils\DateParser;

class ReglementsDTO
{
    public function __construct(
        public string             $idreglement,
        public \DateTimeImmutable $dateExport,
        public string             $modeReglt,
        public \DateTimeImmutable $dateReglt,
        public string             $montantReglt,
        public ?string             $banque,
        public ?string             $numeroCheque,
        public ?string             $numeroReleve,
    )
    {
    }

    /**
     * @throws \Exception
     */
    public static function fromArray(array $data): self
    {
        return new self(
            idreglement: $data['idreglement'],
            dateExport: DateParser::parseDate($data['date_export'] ?? null),
            modeReglt: $data['mode_reglt'],
            dateReglt: DateParser::parseDate($data['date_reglt'] ?? null),
            montantReglt: $data['montant_reglt'],
            banque: $data['banque'],
            numeroCheque: $data['numero_cheque'],
            numeroReleve: $data['numero_releve'],
        );
    }
}
