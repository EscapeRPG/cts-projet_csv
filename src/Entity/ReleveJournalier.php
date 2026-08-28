<?php

namespace App\Entity;

use App\Enum\StatutReleve;
use App\Repository\ReleveJournalierRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: ReleveJournalierRepository::class)]
#[ORM\UniqueConstraint(
    name: 'UNIQ_RELEVE_JOURNALIER_CENTRE_DATE_RELEVE',
    columns: ['centre_id', 'date_releve'],
)]
#[UniqueEntity(
    fields: ['centre', 'dateReleve'],
    message: 'Un relevé existe déjà pour cette station à cette date.',
)]
class ReleveJournalier
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'relevesJournaliers')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Centre $centre = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $dateReleve = null;

    #[ORM\Column(length: 20, enumType: StatutReleve::class, options: ['default' => 'brouillon'])]
    private StatutReleve $statut = StatutReleve::BROUILLON;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $commentaire = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $validatedAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $createdBy = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $updatedBy = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $validatedBy = null;

    /**
     * @var Collection<int, ReleveEquipement>
     */
    #[ORM\OneToMany(targetEntity: ReleveEquipement::class, mappedBy: 'releveJournalier', cascade: ['persist'], orphanRemoval: true)]
    private Collection $relevesEquipements;

    /**
     * @var Collection<int, ReleveProduit>
     */
    #[ORM\OneToMany(targetEntity: ReleveProduit::class, mappedBy: 'releveJournalier', cascade: ['persist'], orphanRemoval: true)]
    private Collection $relevesProduits;

    /**
     * @var Collection<int, RelevePrestation>
     */
    #[ORM\OneToMany(targetEntity: RelevePrestation::class, mappedBy: 'releveJournalier', cascade: ['persist'], orphanRemoval: true)]
    private Collection $relevesPrestations;

    public function __construct()
    {
        $this->relevesEquipements = new ArrayCollection();
        $this->relevesProduits = new ArrayCollection();
        $this->relevesPrestations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCentre(): ?Centre
    {
        return $this->centre;
    }

    public function getDateReleve(): ?\DateTimeImmutable
    {
        return $this->dateReleve;
    }

    public function getStatut(): StatutReleve
    {
        return $this->statut;
    }

    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    public function setCommentaire(?string $commentaire): static
    {
        $this->commentaire = $commentaire;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getValidatedAt(): ?\DateTimeImmutable
    {
        return $this->validatedAt;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function getUpdatedBy(): ?User
    {
        return $this->updatedBy;
    }

    public function getValidatedBy(): ?User
    {
        return $this->validatedBy;
    }

    /**
     * @return Collection<int, ReleveEquipement>
     */
    public function getRelevesEquipements(): Collection
    {
        return $this->relevesEquipements;
    }

    public function addReleveEquipement(ReleveEquipement $ligne): static
    {
        if ($this->relevesEquipements->contains($ligne)) {
            return $this;
        }

        $equipement = $ligne->getEquipement();

        if ($equipement === null) {
            throw new \DomainException(
                'La ligne doit être associée à un équipement.'
            );
        }

        if ($equipement->getCentre() !== $this->centre) {
            throw new \DomainException(
                'Cet équipement n’appartient pas au centre du relevé.'
            );
        }

        foreach ($this->relevesEquipements as $existante) {
            if ($existante->getEquipement() === $equipement) {
                throw new \DomainException(
                    'Cet équipement est déjà présent dans le relevé.'
                );
            }
        }

        $this->relevesEquipements->add($ligne);
        $ligne->setReleveJournalier($this);

        return $this;
    }

    public function removeReleveEquipement(ReleveEquipement $relevesEquipement): static
    {
        if ($this->relevesEquipements->removeElement($relevesEquipement)) {
            // set the owning side to null (unless already changed)
            if ($relevesEquipement->getReleveJournalier() === $this) {
                $relevesEquipement->setReleveJournalier(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ReleveProduit>
     */
    public function getRelevesProduits(): Collection
    {
        return $this->relevesProduits;
    }

    public function addReleveProduit(ReleveProduit $releveProduit): static
    {
        if (!$this->relevesProduits->contains($releveProduit)) {
            $this->relevesProduits->add($releveProduit);
            $releveProduit->setReleveJournalier($this);
        }

        return $this;
    }

    public function removeReleveProduit(ReleveProduit $releveProduit): static
    {
        if ($this->relevesProduits->removeElement($releveProduit)) {
            // set the owning side to null (unless already changed)
            if ($releveProduit->getReleveJournalier() === $this) {
                $releveProduit->setReleveJournalier(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, RelevePrestation>
     */
    public function getRelevesPrestations(): Collection
    {
        return $this->relevesPrestations;
    }

    public function addRelevePrestation(RelevePrestation $relevesPrestation): static
    {
        if (!$this->relevesPrestations->contains($relevesPrestation)) {
            $this->relevesPrestations->add($relevesPrestation);
            $relevesPrestation->setReleveJournalier($this);
        }

        return $this;
    }

    public function removeRelevePrestation(RelevePrestation $relevesPrestation): static
    {
        if ($this->relevesPrestations->removeElement($relevesPrestation)) {
            // set the owning side to null (unless already changed)
            if ($relevesPrestation->getReleveJournalier() === $this) {
                $relevesPrestation->setReleveJournalier(null);
            }
        }

        return $this;
    }

    public static function create(Centre $centre, \DateTimeImmutable $dateReleve, User $auteur, \DateTimeImmutable $at): self
    {
        $releve = new self();

        $releve->centre = $centre;
        $releve->dateReleve = $dateReleve;
        $releve->createdBy = $auteur;
        $releve->createdAt = $at;
        $releve->updatedBy = $auteur;
        $releve->updatedAt = $at;
        $releve->statut = StatutReleve::BROUILLON;

        return $releve;
    }

    public function markAsModified(User $user, \DateTimeImmutable $at): void
    {
        $this->updatedBy = $user;
        $this->updatedAt = $at;

        if ($this->isValidated()) {
            $this->cancelValidation();
        }
    }

    public function markAsValidated(User $user, \DateTimeImmutable $at): void
    {
        $this->validatedBy = $user;
        $this->validatedAt = $at;
        $this->statut = StatutReleve::VALIDE;
    }

    public function isDraft(): bool
    {
        return $this->statut === StatutReleve::BROUILLON;
    }

    public function isValidated(): bool
    {
        return $this->statut === StatutReleve::VALIDE;
    }

    public function isReleveEmpty(): bool
    {
        foreach ($this->relevesEquipements as $ligne) {
            if (!$ligne->isEmpty()) {
                return false;
            }
        }

        foreach ($this->relevesProduits as $ligne) {
            if (!$ligne->isEmpty()) {
                return false;
            }
        }

        foreach ($this->relevesPrestations as $ligne) {
            if (!$ligne->isEmpty()) {
                return false;
            }
        }

        return true;
    }

    private function cancelValidation(): void
    {
        $this->statut = StatutReleve::BROUILLON;
        $this->validatedBy = null;
        $this->validatedAt = null;
    }
}
