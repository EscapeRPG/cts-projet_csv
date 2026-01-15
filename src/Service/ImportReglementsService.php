<?php

namespace App\Service;

use App\DTO\ReglementsDTO;
use App\Entity\Reglements;

class ImportReglementsService extends AbstractCsvImportService
{
    /**
     * @throws \Exception
     */
    protected function handleRow(array $data, int $lineNumber): bool
    {
        $dto = ReglementsDTO::fromArray($data);

        $exists = $this->em->getRepository(Reglements::class)
            ->findOneBy([
                'idreglement' => $dto->idreglement,
                'dateExport' => $dto->dateExport
            ]);

        if ($exists) {
            return false;
        }

        $entity = new Reglements()
            ->setIdreglement($dto->idreglement)
            ->setDateExport($dto->dateExport)
            ->setModeReglt($dto->modeReglt)
            ->setDateReglt($dto->dateReglt)
            ->setMontantReglt($dto->montantReglt)
            ->setBanque($dto->banque)
            ->setNumeroCheque($dto->numeroCheque)
            ->setNumeroReleve($dto->numeroReleve);

        $this->em->persist($entity);

        return true;
    }
}
