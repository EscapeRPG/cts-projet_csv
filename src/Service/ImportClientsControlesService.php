<?php

namespace App\Service;

use App\DTO\ClientsControlesDTO;
use App\Entity\Clients;
use App\Entity\ClientsControles;
use App\Entity\Controles;

class ImportClientsControlesService extends AbstractCsvImportService
{
    private array $controlesCache = [];
    private array $clientsCache = [];

    /**
     * @throws \Exception
     */
    protected function handleRow(array $data, int $lineNumber): bool
    {
        $dto = ClientsControlesDTO::fromArray($data);

        $controle = $this->getControle($dto->idcontrole);
        $client = $this->getClient($dto->idclient);

        // Déjà existant ?
        $exists = $this->em->getRepository(ClientsControles::class)
            ->findOneBy([
                'idcontrole' => $controle,
                'idclient' => $client,
            ]);

        if ($exists) {
            return false;
        }

        $entity = (new ClientsControles())
            ->setIdcontrole($controle)
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

    private function getClient(string $idclient): Clients
    {
        return $this->clientsCache[$idclient]
            ??= $this->em->getRepository(Clients::class)
            ->findOneBy(['idclient' => $idclient])
            ?? throw new \RuntimeException("Client $idclient introuvable");
    }
}
