<?php

namespace App\Service;

use App\DTO\FacturesDTO;
use App\Entity\Factures;

class ImportFacturesService extends AbstractCsvImportService
{
    /**
     * @throws \Exception
     */
    protected function handleRow(array $data, int $lineNumber): bool
    {
        $dto = FacturesDTO::fromArray($data);

        $exists = $this->em->getRepository(Factures::class)
            ->findOneBy([
                'idfacture' => $dto->idfacture,
                'dateExport' => $dto->dateExport
            ]);

        if ($exists) {
            return false;
        }

        $entity = new Factures()
            ->setIdfacture($dto->idfacture)
            ->setDateExport($dto->dateExport)
            ->setNumFacture($dto->numFacture)
            ->setTypeFacture($dto->typeFacture)
            ->setDateFacture($dto->dateFacture)
            ->setDateEcheance($dto->dateEcheance)
            ->setNumTvaIntra($dto->numTvaIntra)
            ->setDevise($dto->devise)
            ->setOtcHt($dto->otcHt)
            ->setMontantTvaOtcHt($dto->montantTvaOtcHt)
            ->setPourcentageTvaOtc($dto->pourcentageTvaOtc)
            ->setOtcTtc($dto->otcTtc)
            ->setMontantPrestaHt($dto->montantPrestaHt)
            ->setMontantPrestaTtc($dto->montantPrestaTtc)
            ->setPourcentageTvaPresta($dto->pourcentageTvaPresta)
            ->setMontantTvaPresta($dto->montantTvaPresta)
            ->setMontantRemise($dto->montantRemise)
            ->setPourcentageRemise($dto->pourcentageRemise)
            ->setTotalHt($dto->totalHt)
            ->setTotalTtc($dto->totalTtc)
            ->setPourcentageTva($dto->pourcentageTva)
            ->setMontantTva($dto->montantTva)
            ->setNumeroReleve($dto->numeroReleve);

        $this->em->persist($entity);

        return true;
    }
}
