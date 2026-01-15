<?php

namespace App\Entity;

use App\Repository\ControlesFacturesRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ControlesFacturesRepository::class)]
class ControlesFactures
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'controlesFactures')]
    #[ORM\JoinColumn(
        name: 'idcontrole',
        referencedColumnName: 'idcontrole',
        nullable: false
    )]
    private ?Controles $idcontrole = null;

    #[ORM\ManyToOne(inversedBy: 'controlesFactures')]
    #[ORM\JoinColumn(
        name: 'idfacture',
        referencedColumnName: 'idfacture',
        nullable: false
    )]
    private ?Factures $idfacture = null;

    #[ORM\Column(length: 8)]
    private ?string $agrCentre = null;

    #[ORM\Column(length: 8)]
    private ?string $agrControleur = null;

    #[ORM\ManyToOne(inversedBy: 'controlesFactures')]
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

    public function getIdcontrole(): ?Controles
    {
        return $this->idcontrole;
    }

    public function setIdcontrole(?Controles $idcontrole): static
    {
        $this->idcontrole = $idcontrole;

        return $this;
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

    public function getAgrCentre(): ?string
    {
        return $this->agrCentre;
    }

    public function setAgrCentre(string $agrCentre): static
    {
        $this->agrCentre = $agrCentre;

        return $this;
    }

    public function getAgrControleur(): ?string
    {
        return $this->agrControleur;
    }

    public function setAgrControleur(string $agrControleur): static
    {
        $this->agrControleur = $agrControleur;

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
