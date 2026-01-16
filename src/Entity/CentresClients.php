<?php

namespace App\Entity;

use App\Repository\CentresClientsRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CentresClientsRepository::class)]
#[ORM\Table(
    name: 'centres_clients',
    uniqueConstraints: [
        new ORM\UniqueConstraint(
            name: 'uniq_cntrclt_client',
            columns: ['idclient']
        )
    ]
)]
class CentresClients
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 8)]
    private ?string $agrCentre = null;

    #[ORM\Column(type: Types::BIGINT)]
    private ?string $idclient = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAgrCentre(): ?string
    {
        return $this->agrCentre;
    }

    public function setAgrCentre(string $agrCentre): static
    {
        $this->agrCentre = $agrCentre;

        return $this;
    }

    public function getIdclient(): ?string
    {
        return $this->idclient;
    }

    public function setIdclient(string $idclient): static
    {
        $this->idclient = $idclient;

        return $this;
    }
}
