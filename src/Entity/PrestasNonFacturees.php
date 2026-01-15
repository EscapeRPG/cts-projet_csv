<?php

namespace App\Entity;

use App\Repository\PrestasNonFactureesRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PrestasNonFactureesRepository::class)]
class PrestasNonFacturees
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'prestasNonFacturees')]
    #[ORM\JoinColumn(
        name: 'idcontrole',
        referencedColumnName: 'idcontrole',
        nullable: false
    )]
    private ?Controles $idcontrole = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $dateExport = null;

    #[ORM\Column(length: 3)]
    private ?string $devise = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2)]
    private ?string $otcHt = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2)]
    private ?string $montantTvaOtcHt = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2)]
    private ?string $pourcentageTvaOtc = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2)]
    private ?string $otcTtc = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2)]
    private ?string $montantPrestaHt = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2)]
    private ?string $montantPrestaTtc = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2)]
    private ?string $pourcentageTvaPresta = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2)]
    private ?string $MontantTvaPresta = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2)]
    private ?string $montantRemise = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2)]
    private ?string $pourcentageRemise = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2)]
    private ?string $totalHt = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2)]
    private ?string $totalTtc = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2)]
    private ?string $pourcentageTva = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2)]
    private ?string $montantTva = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIdcontrole(): ?Controles
    {
        return $this->idcontrole;
    }

    public function setIdcontrole(?Controles $idcontrole): static
    {
        $this->idcontrole = $idcontrole;

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

    public function getDevise(): ?string
    {
        return $this->devise;
    }

    public function setDevise(string $devise): static
    {
        $this->devise = $devise;

        return $this;
    }

    public function getOtcHt(): ?string
    {
        return $this->otcHt;
    }

    public function setOtcHt(string $otcHt): static
    {
        $this->otcHt = $otcHt;

        return $this;
    }

    public function getMontantTvaOtcHt(): ?string
    {
        return $this->montantTvaOtcHt;
    }

    public function setMontantTvaOtcHt(string $montantTvaOtcHt): static
    {
        $this->montantTvaOtcHt = $montantTvaOtcHt;

        return $this;
    }

    public function getPourcentageTvaOtc(): ?string
    {
        return $this->pourcentageTvaOtc;
    }

    public function setPourcentageTvaOtc(string $pourcentageTvaOtc): static
    {
        $this->pourcentageTvaOtc = $pourcentageTvaOtc;

        return $this;
    }

    public function getOtcTtc(): ?string
    {
        return $this->otcTtc;
    }

    public function setOtcTtc(string $otcTtc): static
    {
        $this->otcTtc = $otcTtc;

        return $this;
    }

    public function getMontantPrestaHt(): ?string
    {
        return $this->montantPrestaHt;
    }

    public function setMontantPrestaHt(string $montantPrestaHt): static
    {
        $this->montantPrestaHt = $montantPrestaHt;

        return $this;
    }

    public function getMontantPrestaTtc(): ?string
    {
        return $this->montantPrestaTtc;
    }

    public function setMontantPrestaTtc(string $montantPrestaTtc): static
    {
        $this->montantPrestaTtc = $montantPrestaTtc;

        return $this;
    }

    public function getPourcentageTvaPresta(): ?string
    {
        return $this->pourcentageTvaPresta;
    }

    public function setPourcentageTvaPresta(string $pourcentageTvaPresta): static
    {
        $this->pourcentageTvaPresta = $pourcentageTvaPresta;

        return $this;
    }

    public function getMontantTvaPresta(): ?string
    {
        return $this->MontantTvaPresta;
    }

    public function setMontantTvaPresta(string $MontantTvaPresta): static
    {
        $this->MontantTvaPresta = $MontantTvaPresta;

        return $this;
    }

    public function getMontantRemise(): ?string
    {
        return $this->montantRemise;
    }

    public function setMontantRemise(string $montantRemise): static
    {
        $this->montantRemise = $montantRemise;

        return $this;
    }

    public function getPourcentageRemise(): ?string
    {
        return $this->pourcentageRemise;
    }

    public function setPourcentageRemise(string $pourcentageRemise): static
    {
        $this->pourcentageRemise = $pourcentageRemise;

        return $this;
    }

    public function getTotalHt(): ?string
    {
        return $this->totalHt;
    }

    public function setTotalHt(string $totalHt): static
    {
        $this->totalHt = $totalHt;

        return $this;
    }

    public function getTotalTtc(): ?string
    {
        return $this->totalTtc;
    }

    public function setTotalTtc(string $totalTtc): static
    {
        $this->totalTtc = $totalTtc;

        return $this;
    }

    public function getPourcentageTva(): ?string
    {
        return $this->pourcentageTva;
    }

    public function setPourcentageTva(string $pourcentageTva): static
    {
        $this->pourcentageTva = $pourcentageTva;

        return $this;
    }

    public function getMontantTva(): ?string
    {
        return $this->montantTva;
    }

    public function setMontantTva(string $montantTva): static
    {
        $this->montantTva = $montantTva;

        return $this;
    }
}
