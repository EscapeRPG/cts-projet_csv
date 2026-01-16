<?php

namespace App\Entity;

use App\Repository\ControlesNonFacturesRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ControlesNonFacturesRepository::class)]
#[ORM\Table(
    name: 'controles_non_factures',
    uniqueConstraints: [
        new ORM\UniqueConstraint(
            name: 'uniq_ctrlnfact_controle_client',
            columns: ['idcontrole', 'idclient']
        )
    ]
)]
class ControlesNonFactures
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::BIGINT)]
    private ?string $idcontrole = null;

    #[ORM\Column(length: 8)]
    private ?string $agrCentre = null;

    #[ORM\Column(length: 8)]
    private ?string $agrControleur = null;

    #[ORM\Column(type: Types::BIGINT)]
    private ?string $idclient = null;

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
}
