<?php

namespace App\DTO;

class ControlesFacturesDTO
{
    public function __construct(
        public string $agrCentre,
        public string $agrControleur,
        public string $idcontrole,
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
            agrControleur: $data['agr_controleur'],
            idcontrole: $data['idcontrole'],
            idclient: $data['idclient'],
            idfacture: $data['idfacture'],
        );
    }
}
