<?php

namespace App\Entity;

use App\Repository\FacturesReglementsRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FacturesReglementsRepository::class)]
class FacturesReglements
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'facturesReglements')]
    #[ORM\JoinColumn(
        name: 'idfacture',
        referencedColumnName: 'idfacture',
        nullable: false
    )]
    private ?Factures $idfacture = null;

    #[ORM\ManyToOne(inversedBy: 'facturesReglements')]
    #[ORM\JoinColumn(
        name: 'idreglement',
        referencedColumnName: 'idreglement',
        nullable: false
    )]
    private ?Reglements $idreglement = null;

    #[ORM\Column(length: 8)]
    private ?string $agrCentre = null;

    #[ORM\ManyToOne(inversedBy: 'facturesReglements')]
    #[ORM\JoinColumn(
        name: 'idclient',
        referencedColumnName: 'idclient',
        nullable: false
    )]
    private ?Clients $idclient = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIdfacture(): ?Factures
    {
        return $this->idfacture;
    }

    public function setIdfacture(?Factures $idfacture): static
    {
        $this->idfacture = $idfacture;

        return $this;
    }

    public function getIdreglement(): ?Reglements
    {
        return $this->idreglement;
    }

    public function setIdreglement(?Reglements $idreglement): static
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
