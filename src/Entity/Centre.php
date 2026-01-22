<?php

namespace App\Entity;

use App\Repository\CentreRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: CentreRepository::class)]
#[UniqueEntity('agr_centre')]
class Centre
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nom = null;

    #[ORM\Column(length: 50, unique: true, nullable: true)]
    private ?string $agr_centre = null;

    #[ORM\ManyToOne(inversedBy: 'centres')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Reseau $reseau = null;

    /**
     * @var Collection<int, Affectation>
     */
    #[ORM\OneToMany(targetEntity: Affectation::class, mappedBy: 'centre', orphanRemoval: true)]
    private Collection $affectations;

    #[ORM\ManyToOne(inversedBy: 'centre')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Societe $societe = null;

    public function __construct()
    {
        $this->affectations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(?string $nom): static
    {
        $this->nom = $nom;
        return $this;
    }

    public function getAgrCentre(): ?string
    {
        return $this->agr_centre;
    }

    public function setAgrCentre(?string $agr_centre): static
    {
        $this->agr_centre = $agr_centre;
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

    /**
     * @return Collection<int, Affectation>
     */
    public function getAffectations(): Collection
    {
        return $this->affectations;
    }

    public function addAffectation(Affectation $affectation): static
    {
        if (!$this->affectations->contains($affectation)) {
            $this->affectations->add($affectation);
            $affectation->setCentre($this);
        }

        return $this;
    }

    public function removeAffectation(Affectation $affectation): static
    {
        if ($this->affectations->removeElement($affectation)) {
            if ($affectation->getCentre() === $this) {
                $affectation->setCentre(null);
            }
        }

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
}
