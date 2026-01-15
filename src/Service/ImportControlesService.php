<?php

namespace App\Service;

use App\DTO\ControlesDTO;
use App\Entity\Controles;

class ImportControlesService extends AbstractCsvImportService
{
    /**
     * @throws \Exception
     */
    protected function handleRow(array $data, int $lineNumber): bool
    {
        $dto = ControlesDTO::fromArray($data);

        $exists = $this->em->getRepository(Controles::class)
            ->findOneBy([
                'idcontrole' => $dto->idcontrole,
                'dateExport' => $dto->dateExport
            ]);

        if ($exists) {
            return false;
        }

        $client = (new Controles())
            ->setIdcontrole($dto->idcontrole)
            ->setDateExport($dto->dateExport)
            ->setNumPvCtrl($dto->numPvControle)
            ->setNumLiaCtrl($dto->numLiaControle)
            ->setImmatVehicule($dto->immatVehicule)
            ->setNumSerieVehicule($dto->numSerieVehicule)
            ->setDatePriseRdv($dto->datePriseRdv)
            ->setTypeRdv($dto->typeRdv)
            ->setDebCtrl($dto->debCtrl)
            ->setFinCtrl($dto->finCtrl)
            ->setDateCtrl($dto->dateCtrl)
            ->setTempsCtrl($dto->tempsCtrl)
            ->setRefTemps($dto->refTemps)
            ->setResCtrl($dto->resCtrl)
            ->setTypeCtrl($dto->typeCtrl)
            ->setModeleVehicule($dto->modeleVehicule)
            ->setAnneeCirculation($dto->anneeCirculation);

        $this->em->persist($client);

        return true;
    }
}
