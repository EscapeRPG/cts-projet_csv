<?php

namespace App\Entity;

use App\Repository\ClientsRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ClientsRepository::class)]
#[ORM\Table(
    name: 'clients',
    uniqueConstraints: [
        new ORM\UniqueConstraint(
            name: 'uniq_clt_client',
            columns: ['idclient']
        )
    ]
)]
class Clients
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::BIGINT)]
    private ?string $idclient = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $dateExport = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $dateCreation = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $codeClient = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nomCodeClient = null;

    #[ORM\Column(length: 25, nullable: true)]
    private ?string $codeSage = null;

    #[ORM\Column(length: 255)]
    private ?string $nom = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $prenom = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $adresse1 = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $adresse2 = null;

    #[ORM\Column(length: 5)]
    private ?string $cp = null;

    #[ORM\Column(length: 255)]
    private ?string $ville = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 12, nullable: true)]
    private ?string $telephone = null;

    #[ORM\Column(length: 12, nullable: true)]
    private ?string $mobile = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $numTvaIntra = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getDateExport(): ?\DateTimeImmutable
    {
        return $this->dateExport;
    }

    public function setDateExport(\DateTimeImmutable $dateExport): static
    {
        $this->dateExport = $dateExport;

        return $this;
    }

    public function getDateCreation(): ?\DateTimeImmutable
    {
        return $this->dateCreation;
    }

    public function setDateCreation(\DateTimeImmutable $dateCreation): static
    {
        $this->dateCreation = $dateCreation;

        return $this;
    }

    public function getCodeClient(): ?string
    {
        return $this->codeClient;
    }

    public function setCodeClient(string $codeClient): static
    {
        $this->codeClient = $codeClient;

        return $this;
    }

    public function getNomCodeClient(): ?string
    {
        return $this->nomCodeClient;
    }

    public function setNomCodeClient(?string $nomCodeClient): static
    {
        $this->nomCodeClient = $nomCodeClient;

        return $this;
    }

    public function getCodeSage(): ?string
    {
        return $this->codeSage;
    }

    public function setCodeSage(?string $codeSage): static
    {
        $this->codeSage = $codeSage;

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

    public function setPrenom(?string $prenom): static
    {
        $this->prenom = $prenom;

        return $this;
    }

    public function getAdresse1(): ?string
    {
        return $this->adresse1;
    }

    public function setAdresse1(?string $adresse1): static
    {
        $this->adresse1 = $adresse1;

        return $this;
    }

    public function getAdresse2(): ?string
    {
        return $this->adresse2;
    }

    public function setAdresse2(?string $adresse2): static
    {
        $this->adresse2 = $adresse2;

        return $this;
    }

    public function getCp(): ?string
    {
        return $this->cp;
    }

    public function setCp(string $cp): static
    {
        $this->cp = $cp;

        return $this;
    }

    public function getVille(): ?string
    {
        return $this->ville;
    }

    public function setVille(string $ville): static
    {
        $this->ville = $ville;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
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

    public function getMobile(): ?string
    {
        return $this->mobile;
    }

    public function setMobile(?string $mobile): static
    {
        $this->mobile = $mobile;

        return $this;
    }

    public function getNumTvaIntra(): ?string
    {
        return $this->numTvaIntra;
    }

    public function setNumTvaIntra(?string $numTvaIntra): static
    {
        $this->numTvaIntra = $numTvaIntra;

        return $this;
    }
}
