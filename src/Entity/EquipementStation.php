<?php

namespace App\Entity;

use App\Enum\CategorieEquipement;
use App\Repository\EquipementStationRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: EquipementStationRepository::class)]
#[ORM\UniqueConstraint(
    name: 'UNIQ_EQUIPEMENT_STATION_CENTRE_CODE',
    columns: ['centre_id', 'code'],
)]
#[ORM\UniqueConstraint(
    name: 'UNIQ_EQUIPEMENT_STATION_PORTIQUE_IMPORT',
    columns: ['centre_id', 'numero_portique_import'],
)]
#[UniqueEntity(
    fields: ['centre', 'code'],
    message: 'Un équipement portant ce code existe déjà pour cette station.',
)]
#[UniqueEntity(
    fields: ['centre', 'numeroPortiqueImport'],
    message: 'Ce numéro de portique importé est déjà associé à un équipement de cette station.',
)]
class EquipementStation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'equipementsStation')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Centre $centre = null;

    #[ORM\Column(length: 50, nullable: false)]
    private ?string $code = null;

    #[ORM\Column(length: 100, nullable: false)]
    private ?string $libelle = null;

    #[ORM\Column(length: 30, enumType: CategorieEquipement::class)]
    private CategorieEquipement $categorie;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?EquipementStation $portiqueAssocie = null;

    #[ORM\Column(nullable: true)]
    private ?int $numeroPortiqueImport = null;

    #[ORM\Column(nullable: false, options: ['default' => 0])]
    private int $ordreAffichage = 0;

    #[ORM\Column(nullable: false, options: ['default' => true])]
    private bool $isActive = true;

    public function __construct(
        Centre $centre,
        string $code,
        string $libelle,
        CategorieEquipement $categorie,
    ) {
        $this->centre = $centre;
        $this->code = $code;
        $this->libelle = $libelle;
        $this->categorie = $categorie;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCentre(): ?Centre
    {
        return $this->centre;
    }

    public function setCentre(?Centre $centre): static
    {
        $this->centre = $centre;

        return $this;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getLibelle(): ?string
    {
        return $this->libelle;
    }

    public function setLibelle(string $libelle): static
    {
        $this->libelle = $libelle;

        return $this;
    }

    public function getCategorie(): CategorieEquipement
    {
        return $this->categorie;
    }

    public function setCategorie(CategorieEquipement $categorie): static
    {
        if ($categorie !== CategorieEquipement::BORNE && $this->portiqueAssocie !== null) {
            throw new \DomainException('Un équipement associé à un portique doit rester une borne.');
        }

        if ($categorie !== CategorieEquipement::PORTIQUE && $this->numeroPortiqueImport !== null) {
            throw new \DomainException('Un numéro de portique importé ne peut être associé qu’à un portique.');
        }

        $this->categorie = $categorie;

        return $this;
    }

    public function getPortiqueAssocie(): ?EquipementStation
    {
        return $this->portiqueAssocie;
    }

    public function getNumeroPortiqueImport(): ?int
    {
        return $this->numeroPortiqueImport;
    }

    public function setNumeroPortiqueImport(?int $numeroPortiqueImport): static
    {
        if ($numeroPortiqueImport !== null && $numeroPortiqueImport <= 0) {
            throw new \DomainException('Le numéro de portique importé doit être strictement positif.');
        }

        if ($numeroPortiqueImport !== null && $this->categorie !== CategorieEquipement::PORTIQUE) {
            throw new \DomainException('Un numéro de portique importé ne peut être associé qu’à un portique.');
        }

        $this->numeroPortiqueImport = $numeroPortiqueImport;

        return $this;
    }

    public function getOrdreAffichage(): int
    {
        return $this->ordreAffichage;
    }

    public function setOrdreAffichage(int $ordreAffichage): static
    {
        $this->ordreAffichage = $ordreAffichage;

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

    public function associerPortique(EquipementStation $portique): void
    {
        if ($this->categorie !== CategorieEquipement::BORNE) {
            throw new \DomainException('Seule une borne peut être associée à un portique.');
        }

        if ($portique->getCategorie() !== CategorieEquipement::PORTIQUE) {
            throw new \DomainException('L\'équipement associé doit être un portique.');
        }

        if ($portique->getCentre() !== $this->centre) {
            throw new \DomainException('La borne et le portique doivent appartenir au même centre.');
        }

        $this->portiqueAssocie = $portique;
    }
}
