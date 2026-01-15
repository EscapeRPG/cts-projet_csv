<?php

namespace App\Entity;

use App\Repository\CentresClientsRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CentresClientsRepository::class)]
class CentresClients
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 8)]
    private ?string $agrCentre = null;

    #[ORM\ManyToOne(inversedBy: 'centresClients')]
    #[ORM\JoinColumn(name: 'idclient', referencedColumnName: 'idclient', nullable: false)]
    private ?Clients $idclient = null;

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

    public function getIdclient(): ?Clients
    {
        return $this->idclient;
    }

    public function setIdclient(?Clients $idclient): static
    {
        $this->idclient = $idclient;

        return $this;
    }
}
