<?php

namespace App\Entity;

use App\Repository\SocieteRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SocieteRepository::class)]
class Societe
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100, unique: true)]
    private ?string $nom = null;

    /**
     * @var Collection<int, Centre>
     */
    #[ORM\OneToMany(targetEntity: Centre::class, mappedBy: 'societe')]
    private Collection $centre;

    public function __construct()
    {
        $this->centre = new ArrayCollection();
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

    /**
     * @return Collection<int, Centre>
     */
    public function getCentre(): Collection
    {
        return $this->centre;
    }

    public function addCentre(Centre $centre): static
    {
        if (!$this->centre->contains($centre)) {
            $this->centre->add($centre);
            $centre->setSociete($this);
        }

        return $this;
    }

    public function removeCentre(Centre $centre): static
    {
        if ($this->centre->removeElement($centre)) {
            // set the owning side to null (unless already changed)
            if ($centre->getSociete() === $this) {
                $centre->setSociete(null);
            }
        }

        return $this;
    }
}
