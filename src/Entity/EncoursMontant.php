<?php

namespace App\Entity;

use App\Repository\EncoursMontantRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EncoursMontantRepository::class)]
class EncoursMontant
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'montants')]
    #[ORM\JoinColumn(nullable: false)]
    private ?EncoursBancaire $encours = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $montant = null;

    #[ORM\Column]
    private ?int $annee = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getEncours(): ?EncoursBancaire
    {
        return $this->encours;
    }

    public function setEncours(?EncoursBancaire $encours): static
    {
        $this->encours = $encours;

        return $this;
    }

    public function getMontant(): ?string
    {
        return $this->montant;
    }

    public function setMontant(string $montant): static
    {
        $this->montant = $montant;

        return $this;
    }

    public function getAnnee(): ?int
    {
        return $this->annee;
    }

    public function setAnnee(int $annee): static
    {
        $this->annee = $annee;

        return $this;
    }
}
