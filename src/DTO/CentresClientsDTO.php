<?php

namespace App\DTO;

class CentresClientsDTO
{
    public function __construct(
        public string $agrCentre,
        public string $idclient,
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
            idclient: $data['idclient'],
        );
    }
}
