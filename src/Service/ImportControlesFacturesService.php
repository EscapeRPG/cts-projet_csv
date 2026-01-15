<?php

namespace App\Service;

use App\DTO\ControlesFacturesDTO;
use App\Entity\Clients;
use App\Entity\Controles;
use App\Entity\ControlesFactures;
use App\Entity\Factures;

class ImportControlesFacturesService extends AbstractCsvImportService
{
    private array $controlesCache = [];
    private array $facturesCache = [];
    private array $clientsCache = [];

    /**
     * @throws \Exception
     */
    protected function handleRow(array $data, int $lineNumber): bool
    {
        $dto = ControlesFacturesDTO::fromArray($data);

        $controle = $this->getControle($dto->idcontrole);
        $facture = $this->getFacture($dto->idfacture);
        $client = $this->getClient($dto->idclient);

        // Déjà existant ?
        $exists = $this->em->getRepository(ControlesFactures::class)
            ->findOneBy([
                'idcontrole' => $controle,
                'idfacture' => $facture,
                'idclient' => $client,
            ]);

        if ($exists) {
            return false;
        }

        $entity = (new ControlesFactures())
            ->setIdcontrole($controle)
            ->setIdfacture($facture)
            ->setIdclient($client)
            ->setAgrCentre($dto->agrCentre)
            ->setAgrControleur($dto->agrControleur);

        $this->em->persist($entity);

        return true;
    }

    protected function resetCaches(): void
    {
        $this->controlesCache = [];
        $this->clientsCache = [];
    }

    private function getControle(string $idcontrole): Controles
    {
        return $this->controlesCache[$idcontrole]
            ??= $this->em->getRepository(Controles::class)
            ->findOneBy(['idcontrole' => $idcontrole])
            ?? throw new \RuntimeException("Contrôle $idcontrole introuvable");
    }

    private function getFacture(string $idfacture): Factures
    {
        return $this->facturesCache[$idfacture]
            ??= $this->em->getRepository(Factures::class)
            ->findOneBy(['idcontrole' => $idcontrole])
            ?? throw new \RuntimeException("Facture $idfacture introuvable");
    }

    private function getClient(string $idclient): Clients
    {
        return $this->clientsCache[$idclient]
            ??= $this->em->getRepository(Clients::class)
            ->findOneBy(['idclient' => $idclient])
            ?? throw new \RuntimeException("Client $idclient introuvable");
    }
}
