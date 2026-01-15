<?php

namespace App\DTO;

use App\Utils\DateParser;
use DateTimeImmutable;

class ClientsDTO
{
    public function __construct(
        public string              $idclient,
        public ?\DateTimeImmutable $dateExport,
        public ?\DateTimeImmutable $dateCreation,
        public ?string             $codeClient,
        public ?string             $nomCodeClient,
        public ?string             $codeSage,
        public string              $nom,
        public ?string             $prenom,
        public ?string             $adresse1,
        public ?string             $adresse2,
        public string              $cp,
        public string              $ville,
        public ?string             $email,
        public ?string             $telephone,
        public ?string             $mobile,
        public ?string             $numTvaIntra,
    )
    {
    }

    /**
     * @throws \Exception
     */
    public static function fromArray(array $data): self
    {
        return new self(
            idclient: $data['idclient'],
            dateExport: DateParser::parseDate($data['date_export'] ?? null),
            dateCreation: DateParser::parseDate($data['date_creation'] ?? null),
            codeClient: $data['code_client'] ?? null,
            nomCodeClient: $data['nom_code_client'] ?? null,
            codeSage: $data['code_sage'] ?? null,
            nom: $data['nom'],
            prenom: $data['prenom'] ?? null,
            adresse1: $data['adresse1'] ?? null,
            adresse2: $data['adresse2'] ?? null,
            cp: $data['cp'],
            ville: $data['ville'],
            email: $data['email'] ?? null,
            telephone: $data['telephone'] ?? null,
            mobile: $data['mobile'] ?? null,
            numTvaIntra: $data['num_tva_intra'] ?? null,
        );
    }
}
