<?php

namespace App\Service;

use App\DTO\PrestasNonFactureesDTO;
use App\Entity\Controles;
use App\Entity\PrestasNonFacturees;

class ImportPrestasNonFactureesService extends AbstractCsvImportService
{
    private array $controlesCache = [];

    /**
     * @throws \Exception
     */
    protected function handleRow(array $data, int $lineNumber): bool
    {
        $dto = PrestasNonFactureesDTO::fromArray($data);

        $controle = $this->getControle($dto->idcontrole);

        // Déjà existant ?
        $exists = $this->em->getRepository(PrestasNonFacturees::class)
            ->findOneBy([
                'idcontrole' => $controle,
                'dateExport' => $dto->dateExport,
            ]);

        if ($exists) {
            return false;
        }

        $entity = new PrestasNonFacturees()
            ->setIdcontrole($controle)
            ->setDateExport($dto->dateExport)
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
            ->setMontantTva($dto->montantTva);

        $this->em->persist($entity);

        return true;
    }

    protected
    function resetCaches(): void
    {
        $this->controlesCache = [];
    }

    private
    function getControle(string $idcontrole): Controles
    {
        return $this->controlesCache[$idcontrole]
            ??= $this->em->getRepository(Controles::class)
            ->findOneBy(['idcontrole' => $idcontrole])
            ?? throw new \RuntimeException("Contrôle $idcontrole introuvable");
    }
}
