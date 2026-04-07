<?php

namespace App\Entity;

use App\Repository\VoitureRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VoitureRepository::class)]
class Voiture
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'voitures')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Societe $societe = null;

    #[ORM\ManyToOne(inversedBy: 'voitures')]
    private ?Centre $centre = null;

    #[ORM\Column(length: 20)]
    private ?string $immatriculation = null;

    #[ORM\Column(length: 20)]
    private ?string $marque = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $couleur = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $modele = null;

    #[ORM\Column(nullable: true)]
    private ?bool $flocable = null;

    #[ORM\Column(length: 4, nullable: true)]
    private ?string $annee = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $controleTechnique = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $km = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $prix = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $carteGrise = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $lieu = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $utilisateur = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $remarques = null;

    #[ORM\Column(nullable: true)]
    private ?bool $active = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $certificatCessionPath = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $certificatCessionOriginalName = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $certificatCessionMime = null;

    #[ORM\Column(nullable: true)]
    private ?int $certificatCessionSize = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $certificatCessionUploadedAt = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getCentre(): ?Centre
    {
        return $this->centre;
    }

    public function setCentre(?Centre $centre): static
    {
        $this->centre = $centre;

        return $this;
    }

    public function getImmatriculation(): ?string
    {
        return $this->immatriculation;
    }

    public function setImmatriculation(string $immatriculation): static
    {
        $this->immatriculation = $immatriculation;

        return $this;
    }

    public function getMarque(): ?string
    {
        return $this->marque;
    }

    public function setMarque(string $marque): static
    {
        $this->marque = $marque;

        return $this;
    }

    public function getCouleur(): ?string
    {
        return $this->couleur;
    }

    public function setCouleur(?string $couleur): static
    {
        $this->couleur = $couleur;

        return $this;
    }

    public function getModele(): ?string
    {
        return $this->modele;
    }

    public function setModele(?string $modele): static
    {
        $this->modele = $modele;

        return $this;
    }

    public function isFlocable(): ?bool
    {
        return $this->flocable;
    }

    public function setFlocable(?bool $flocable): static
    {
        $this->flocable = $flocable;

        return $this;
    }

    public function getAnnee(): ?string
    {
        return $this->annee;
    }

    public function setAnnee(?string $annee): static
    {
        $this->annee = $annee;

        return $this;
    }

    public function getControleTechnique(): ?\DateTimeImmutable
    {
        return $this->controleTechnique;
    }

    public function setControleTechnique(?\DateTimeImmutable $controleTechnique): static
    {
        $this->controleTechnique = $controleTechnique;

        return $this;
    }

    public function getKm(): ?string
    {
        return $this->km;
    }

    public function setKm(?string $km): static
    {
        $this->km = $km;

        return $this;
    }

    public function getPrix(): ?string
    {
        return $this->prix;
    }

    public function setPrix(?string $prix): static
    {
        $this->prix = $prix;

        return $this;
    }

    public function getCarteGrise(): ?string
    {
        return $this->carteGrise;
    }

    public function setCarteGrise(?string $carteGrise): static
    {
        $this->carteGrise = $carteGrise;

        return $this;
    }

    public function getLieu(): ?string
    {
        return $this->lieu;
    }

    public function setLieu(?string $lieu): static
    {
        $this->lieu = $lieu;

        return $this;
    }

    public function getUtilisateur(): ?string
    {
        return $this->utilisateur;
    }

    public function setUtilisateur(?string $utilisateur): static
    {
        $this->utilisateur = $utilisateur;

        return $this;
    }

    public function getRemarques(): ?string
    {
        return $this->remarques;
    }

    public function setRemarques(?string $remarques): static
    {
        $this->remarques = $remarques;

        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->active;
    }

    public function setActive(?bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    public function getCertificatCessionPath(): ?string
    {
        return $this->certificatCessionPath;
    }

    public function setCertificatCessionPath(?string $certificatCessionPath): static
    {
        $this->certificatCessionPath = $certificatCessionPath;

        return $this;
    }

    public function getCertificatCessionOriginalName(): ?string
    {
        return $this->certificatCessionOriginalName;
    }

    public function setCertificatCessionOriginalName(?string $certificatCessionOriginalName): static
    {
        $this->certificatCessionOriginalName = $certificatCessionOriginalName;

        return $this;
    }

    public function getCertificatCessionMime(): ?string
    {
        return $this->certificatCessionMime;
    }

    public function setCertificatCessionMime(?string $certificatCessionMime): static
    {
        $this->certificatCessionMime = $certificatCessionMime;

        return $this;
    }

    public function getCertificatCessionSize(): ?int
    {
        return $this->certificatCessionSize;
    }

    public function setCertificatCessionSize(?int $certificatCessionSize): static
    {
        $this->certificatCessionSize = $certificatCessionSize;

        return $this;
    }

    public function getCertificatCessionUploadedAt(): ?\DateTimeImmutable
    {
        return $this->certificatCessionUploadedAt;
    }

    public function setCertificatCessionUploadedAt(?\DateTimeImmutable $certificatCessionUploadedAt): static
    {
        $this->certificatCessionUploadedAt = $certificatCessionUploadedAt;

        return $this;
    }
}
