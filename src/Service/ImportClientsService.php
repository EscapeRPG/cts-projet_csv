<?php

namespace App\Service;

use App\DTO\ClientsDTO;
use App\Entity\Clients;

class ImportClientsService extends AbstractCsvImportService
{
    /**
     * @throws \Exception
     */
    protected function handleRow(array $data, int $lineNumber): bool
    {
        $dto = ClientsDTO::fromArray($data);

        $exists = $this->em->getRepository(Clients::class)
            ->findOneBy([
                'idclient' => $dto->idclient,
                'dateExport' => $dto->dateExport
            ]);

        if ($exists) {
            return false;
        }

        $entity = (new Clients())
            ->setIdclient($dto->idclient)
            ->setDateExport($dto->dateExport)
            ->setDateCreation($dto->dateCreation)
            ->setCodeClient($dto->codeClient)
            ->setNomCodeClient($dto->nomCodeClient)
            ->setCodeSage($dto->codeSage)
            ->setNom($dto->nom)
            ->setPrenom($dto->prenom)
            ->setAdresse1($dto->adresse1)
            ->setAdresse2($dto->adresse2)
            ->setCp($dto->cp)
            ->setVille($dto->ville)
            ->setEmail($dto->email)
            ->setTelephone($dto->telephone)
            ->setMobile($dto->mobile)
            ->setNumTvaIntra($dto->numTvaIntra);

        $this->em->persist($entity);

        return true;
    }
}
