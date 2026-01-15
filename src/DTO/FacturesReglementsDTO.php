<?php

namespace App\DTO;

class FacturesReglementsDTO
{
    public function __construct(
        public string $agrCentre,
        public string $idreglement,
        public string $idclient,
        public string $idfacture,
    )
    {
    }

    /**
     * @throws \Exception
     */
    public static function fromArray(array $data): self
    {
        return new self(
            agrCentre: $data['agr_centre'],
            idreglement: $data['idreglement'],
            idclient: $data['idclient'],
            idfacture: $data['idfacture'],
        );
    }
}
