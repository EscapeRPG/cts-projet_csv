<?php

namespace App\Entity;

use App\Repository\SalarieRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: SalarieRepository::class)]
class Salarie
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'salaries')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Societe $societe = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $agrControleur = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $agrClControleur = null;

    #[ORM\Column(length: 50)]
    private ?string $nom = null;

    #[ORM\Column(length: 50)]
    private ?string $prenom = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dateNaissance = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $telephone = null;

    #[ORM\Column(nullable: true)]
    private ?int $echelons = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $salaireBrut = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    private ?string $nbHeures = null;

    #[ORM\Column(length: 5, nullable: true)]
    private ?string $vesteMancheAmovible = null;

    #[ORM\Column(length: 5, nullable: true)]
    private ?string $polaire = null;

    #[ORM\Column(length: 5, nullable: true)]
    private ?string $pantalon = null;

    #[ORM\Column(length: 5, nullable: true)]
    private ?string $teeShirts = null;

    #[ORM\Column(length: 5, nullable: true)]
    private ?string $polo = null;

    #[ORM\Column(nullable: true)]
    private ?int $chaussures = null;

    #[ORM\Column(nullable: true)]
    private ?bool $isActive = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSociete(): ?Societe
    {
        return $this->societe;
    }

    public function setSociete(?Societe $societe): self
    {
        $this->societe = $societe;

        return $this;
    }

    public function getAgrControleur(): ?string
    {
        return $this->agrControleur;
    }

    public function setAgrControleur(?string $agrControleur): static
    {
        $this->agrControleur = $agrControleur;

        return $this;
    }

    public function getAgrClControleur(): ?string
    {
        return $this->agrClControleur;
    }

    public function setAgrClControleur(?string $agrClControleur): static
    {
        $this->agrClControleur = $agrClControleur;

        return $this;
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

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): static
    {
        $this->prenom = $prenom;

        return $this;
    }

    public function getDateNaissance(): \DateTimeImmutable
    {
        return $this->dateNaissance;
    }

    public function setDateNaissance(\DateTimeImmutable $dateNaissance): static
    {
        $this->dateNaissance = $dateNaissance;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(?string $telephone): static
    {
        $this->telephone = $telephone;

        return $this;
    }

    public function getEchelons(): ?int
    {
        return $this->echelons;
    }

    public function setEchelons(?int $echelons): static
    {
        $this->echelons = $echelons;

        return $this;
    }

    public function getSalaireBrut(): ?string
    {
        return $this->salaireBrut;
    }

    public function setSalaireBrut(?string $salaireBrut): static
    {
        $this->salaireBrut = $salaireBrut;

        return $this;
    }

    public function getNbHeures(): ?string
    {
        return $this->nbHeures;
    }

    public function setNbHeures(?string $nbHeures): static
    {
        $this->nbHeures = $nbHeures;

        return $this;
    }

    public function getVesteMancheAmovible(): ?string
    {
        return $this->vesteMancheAmovible;
    }

    public function setVesteMancheAmovible(?string $vesteMancheAmovible): static
    {
        $this->vesteMancheAmovible = $vesteMancheAmovible;

        return $this;
    }

    public function getPolaire(): ?string
    {
        return $this->polaire;
    }

    public function setPolaire(?string $polaire): static
    {
        $this->polaire = $polaire;

        return $this;
    }

    public function getPantalon(): ?string
    {
        return $this->pantalon;
    }

    public function setPantalon(?string $pantalon): static
    {
        $this->pantalon = $pantalon;

        return $this;
    }

    public function getTeeShirts(): ?string
    {
        return $this->teeShirts;
    }

    public function setTeeShirts(?string $teeShirts): static
    {
        $this->teeShirts = $teeShirts;

        return $this;
    }

    public function getPolo(): ?string
    {
        return $this->polo;
    }

    public function setPolo(?string $polo): static
    {
        $this->polo = $polo;

        return $this;
    }

    public function getChaussures(): ?int
    {
        return $this->chaussures;
    }

    public function setChaussures(?int $chaussures): static
    {
        $this->chaussures = $chaussures;

        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }
}
