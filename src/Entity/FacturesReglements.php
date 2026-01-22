<?php

namespace App\Entity;

use App\Repository\FacturesReglementsRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FacturesReglementsRepository::class)]
class FacturesReglements
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::BIGINT)]
    private ?string $idfacture = null;

    #[ORM\Column(type: Types::BIGINT)]
    private ?string $idreglement = null;

    #[ORM\Column(length: 8)]
    private ?string $agrCentre = null;

    #[ORM\Column(type: Types::BIGINT)]
    private ?string $idclient = null;

    #[ORM\ManyToOne(inversedBy: 'facturesReglements')]
    private ?Reseau $reseau = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIdfacture(): ?string
    {
        return $this->idfacture;
    }

    public function setIdfacture(string $idfacture): static
    {
        $this->idfacture = $idfacture;

        return $this;
    }

    public function getIdreglement(): ?string
    {
        return $this->idreglement;
    }

    public function setIdreglement(string $idreglement): static
    {
        $this->idreglement = $idreglement;

        return $this;
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

    public function getReseau(): ?Reseau
    {
        return $this->reseau;
    }

    public function setReseau(?Reseau $reseau): static
    {
        $this->reseau = $reseau;

        return $this;
    }
}
