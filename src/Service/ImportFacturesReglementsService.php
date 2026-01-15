<?php

namespace App\Service;

use App\DTO\FacturesReglementsDTO;
use App\Entity\Clients;
use App\Entity\Factures;
use App\Entity\FacturesReglements;
use App\Entity\Reglements;

class ImportFacturesReglementsService extends AbstractCsvImportService
{
    private array $facturesCache = [];
    private array $reglementsCache = [];
    private array $clientsCache = [];

    /**
     * @throws \Exception
     */
    protected function handleRow(array $data, int $lineNumber): bool
    {
        $dto = FacturesReglementsDTO::fromArray($data);

        $facture = $this->getFacture($dto->idfacture);
        $reglement = $this->getReglement($dto->idreglement);
        $client = $this->getClient($dto->idclient);

        // Déjà existant ?
        $exists = $this->em->getRepository(FacturesReglements::class)
            ->findOneBy([
                'idfacture' => $facture,
                'idreglement' => $reglement,
                'idclient' => $client,
            ]);

        if ($exists) {
            return false;
        }

        $entity = (new FacturesReglements())
            ->setIdfacture($facture)
            ->setIdreglement($reglement)
            ->setIdclient($client)
            ->setAgrCentre($dto->agrCentre);

        $this->em->persist($entity);

        return true;
    }

    protected function resetCaches(): void
    {
        $this->facturesCache = [];
        $this->reglementsCache = [];
        $this->clientsCache = [];
    }

    private function getFacture(string $idfacture): Factures
    {
        return $this->facturesCache[$idfacture]
            ??= $this->em->getRepository(Factures::class)
            ->findOneBy(['idfacture' => $idfacture])
            ?? throw new \RuntimeException("Facture $idfacture introuvable");
    }

    private function getReglement(string $idreglement): Reglements
    {
        return $this->reglementsCache[$idreglement]
            ??= $this->em->getRepository(Reglements::class)
            ->findOneBy(['idreglement' => $idreglement])
            ?? throw new \RuntimeException("Règlement $idreglement introuvable");
    }

    private function getClient(string $idclient): Clients
    {
        return $this->clientsCache[$idclient]
            ??= $this->em->getRepository(Clients::class)
            ->findOneBy(['idclient' => $idclient])
            ?? throw new \RuntimeException("Client $idclient introuvable");
    }
}
