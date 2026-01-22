<?php

namespace App\Entity;

use App\Repository\ReseauRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReseauRepository::class)]
class Reseau
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nom = null;

    #[ORM\Column]
    private bool $isActive = true;

    /**
     * @var Collection<int, Centre>
     */
    #[ORM\OneToMany(targetEntity: Centre::class, mappedBy: 'reseau', orphanRemoval: true)]
    private Collection $centres;

    public function __construct()
    {
        $this->centres = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }

    /**
     * @return Collection<int, Centre>
     */
    public function getCentres(): Collection
    {
        return $this->centres;
    }

    public function addCentre(Centre $centre): static
    {
        if (!$this->centres->contains($centre)) {
            $this->centres->add($centre);
            $centre->setReseau($this);
        }

        return $this;
    }

    public function removeCentre(Centre $centre): static
    {
        if ($this->centres->removeElement($centre)) {
            if ($centre->getReseau() === $this) {
                $centre->setReseau(null);
            }
        }

        return $this;
    }
}
