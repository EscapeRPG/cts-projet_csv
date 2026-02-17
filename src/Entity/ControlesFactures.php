<?php

namespace App\Entity;

use App\Repository\ControlesFacturesRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ControlesFacturesRepository::class)]
#[ORM\Table(name: 'controles_factures', indexes: [
    new ORM\Index(name: 'idx_cf_idcontrole', columns: ['idcontrole']),
    new ORM\Index(name: 'idx_cf_idfacture', columns: ['idfacture']),
    new ORM\Index(name: 'idx_cf_facture_controle', columns: ['idfacture', 'idcontrole']),
])]
class ControlesFactures
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::BIGINT)]
    private ?string $idcontrole = null;

    #[ORM\Column(type: Types::BIGINT)]
    private ?string $idfacture = null;

    #[ORM\Column(length: 8)]
    private ?string $agrCentre = null;

    #[ORM\Column(length: 8)]
    private ?string $agrControleur = null;

    #[ORM\Column(type: Types::BIGINT)]
    private ?string $idclient = null;

    #[ORM\ManyToOne(inversedBy: 'controlesFactures')]
    private ?Reseau $reseau = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIdcontrole(): ?string
    {
        return $this->idcontrole;
    }

    public function setIdcontrole(string $idcontrole): static
    {
        $this->idcontrole = $idcontrole;

        return $this;
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
