<?php

namespace App\Service;

use App\DTO\CentresClientsDTO;
use App\Entity\CentresClients;
use App\Entity\Clients;

class ImportCentresClientsService extends AbstractCsvImportService
{
    private array $clientsCache = [];

    /**
     * @throws \Exception
     */
    protected function handleRow(array $data, int $lineNumber): bool
    {
        $dto = CentresClientsDTO::fromArray($data);

        $client = $this->getClient($dto->idclient);

        // Déjà existant ?
        $exists = $this->em->getRepository(CentresClients::class)
            ->findOneBy([
                'idclient' => $client,
            ]);

        if ($exists) {
            return false;
        }

        $entity = new CentresClients()
            ->setIdclient($client)
            ->setAgrCentre($dto->agrCentre);

        $this->em->persist($entity);

        return true;
    }

    protected function resetCaches(): void
    {
        $this->clientsCache = [];
    }

    private function getClient(string $idclient): Clients
    {
        return $this->clientsCache[$idclient]
            ??= $this->em->getRepository(Clients::class)
            ->findOneBy(['idclient' => $idclient])
            ?? throw new \RuntimeException("Client $idclient introuvable");
    }
}
