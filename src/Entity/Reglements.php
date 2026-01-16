<?php

namespace App\Entity;

use App\Repository\ReglementsRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReglementsRepository::class)]
#[ORM\Table(
    name: 'reglements',
    uniqueConstraints: [
        new ORM\UniqueConstraint(
            name: 'uniq_reglt_reglement',
            columns: ['idreglement']
        )
    ]
)]
class Reglements
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::BIGINT)]
    private ?string $idreglement = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $dateExport = null;

    #[ORM\Column(length: 3)]
    private ?string $modeReglt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $dateReglt = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2)]
    private ?string $montantReglt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $banque = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $numeroCheque = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $numeroReleve = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIdreglement(): ?string
    {
        return $this->idreglement;
    }

    public function setIdreglement(string $idreglement): static
    {
        $this->idreglement = $idreglement;

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

    public function getModeReglt(): ?string
    {
        return $this->modeReglt;
    }

    public function setModeReglt(string $modeReglt): static
    {
        $this->modeReglt = $modeReglt;

        return $this;
    }

    public function getDateReglt(): ?\DateTimeImmutable
    {
        return $this->dateReglt;
    }

    public function setDateReglt(\DateTimeImmutable $dateReglt): static
    {
        $this->dateReglt = $dateReglt;

        return $this;
    }

    public function getMontantReglt(): ?string
    {
        return $this->montantReglt;
    }

    public function setMontantReglt(?string $montantReglt): static
    {
        $this->montantReglt = $montantReglt;

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

    public function getNumeroCheque(): ?string
    {
        return $this->numeroCheque;
    }

    public function setNumeroCheque(?string $numeroCheque): static
    {
        $this->numeroCheque = $numeroCheque;

        return $this;
    }

    public function getNumeroReleve(): ?string
    {
        return $this->numeroReleve;
    }

    public function setNumeroReleve(?string $numeroReleve): static
    {
        $this->numeroReleve = $numeroReleve;

        return $this;
    }
}
