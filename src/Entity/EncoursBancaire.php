<?php

namespace App\Entity;

use App\Repository\EncoursBancaireRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EncoursBancaireRepository::class)]
class EncoursBancaire
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'encoursBancaires')]
    private ?Societe $societe = null;

    #[ORM\Column(length: 255)]
    private ?string $centre = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $banque = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $emprunt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $date = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $garanties = null;

    #[ORM\Column(length: 20)]
    private ?string $type = null;

    /**
     * @var Collection<int, EncoursMontant>
     */
    #[ORM\OneToMany(targetEntity: EncoursMontant::class, mappedBy: 'encours', cascade: ['persist'], orphanRemoval: true)]
    private Collection $montants;

    public function __construct()
    {
        $this->montants = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getSociete(): ?Societe
    {
        return $this->societe;
    }

    public function setSociete(?Societe $societe): static
    {
        $this->societe = $societe;

        return $this;
    }

    public function getCentre(): ?string
    {
        return $this->centre;
    }

    public function setCentre(string $centre): static
    {
        $this->centre = $centre;

        return $this;
    }

    public function getBanque(): ?string
    {
        return $this->banque;
    }

    public function setBanque(?string $banque): static
    {
        $this->banque = $banque;

        return $this;
    }

    public function getEmprunt(): ?string
    {
        return $this->emprunt;
    }

    public function setEmprunt(?string $emprunt): static
    {
        $this->emprunt = $emprunt;

        return $this;
    }

    public function getDate(): ?string
    {
        return $this->date;
    }

    public function setDate(?string $date): static
    {
        $this->date = $date;

        return $this;
    }

    public function getGaranties(): ?string
    {
        return $this->garanties;
    }

    public function setGaranties(?string $garanties): static
    {
        $this->garanties = $garanties;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    /**
     * @return Collection<int, EncoursMontant>
     */
    public function getMontants(): Collection
    {
        return $this->montants;
    }

    // Symfony forms expect addMontant/removeMontant for a "montants" collection.
    public function addMontant(EncoursMontant $montant): static
    {
        return $this->addMontants($montant);
    }

    public function removeMontant(EncoursMontant $montant): static
    {
        return $this->removeMontants($montant);
    }

    public function addMontants(EncoursMontant $montants): static
    {
        if (!$this->montants->contains($montants)) {
            $this->montants->add($montants);
            $montants->setEncours($this);
        }

        return $this;
    }

    public function removeMontants(EncoursMontant $montants): static
    {
        if ($this->montants->removeElement($montants)) {
            // set the owning side to null (unless already changed)
            if ($montants->getEncours() === $this) {
                $montants->setEncours(null);
            }
        }

        return $this;
    }
}
