<?php

namespace App\Entity;

use App\Repository\FacturesRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FacturesRepository::class)]
#[ORM\Table(name: 'factures', indexes: [
    new ORM\Index(name: 'idx_factures_idfacture', columns: ['idfacture']),
    new ORM\Index(name: 'idx_factures_type_facture', columns: ['type_facture']),
    new ORM\Index(name: 'idx_factures_type_date_facture', columns: ['type_facture', 'date_facture']),
    new ORM\Index(name: 'idx_factures_type_total', columns: ['type_facture', 'total_ht']),
])]
class Factures
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::BIGINT)]
    private ?string $idfacture = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $dateExport = null;

    #[ORM\Column(type: Types::BIGINT)]
    private ?string $numFacture = null;

    #[ORM\Column(length: 1)]
    private ?string $typeFacture = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $dateFacture = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $dateEcheance = null;

    #[ORM\Column(length: 255)]
    private ?string $numTvaIntra = null;

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
    private ?string $montantTvaPresta = null;

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

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $numeroReleve = null;

    #[ORM\ManyToOne(inversedBy: 'factures')]
    private ?Reseau $reseau = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIdfacture(): ?string
    {
        return $this->idfacture;
    }

    public function setIdfacture(string $idfacture): static
    {
        $this->idfacture = $idfacture;

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

    public function getNumFacture(): ?string
    {
        return $this->numFacture;
    }

    public function setNumFacture(string $numFacture): static
    {
        $this->numFacture = $numFacture;

        return $this;
    }

    public function getTypeFacture(): ?string
    {
        return $this->typeFacture;
    }

    public function setTypeFacture(string $typeFacture): static
    {
        $this->typeFacture = $typeFacture;

        return $this;
    }

    public function getDateFacture(): ?\DateTimeImmutable
    {
        return $this->dateFacture;
    }

    public function setDateFacture(\DateTimeImmutable $dateFacture): static
    {
        $this->dateFacture = $dateFacture;

        return $this;
    }

    public function getDateEcheance(): ?\DateTimeImmutable
    {
        return $this->dateEcheance;
    }

    public function setDateEcheance(\DateTimeImmutable $dateEcheance): static
    {
        $this->dateEcheance = $dateEcheance;

        return $this;
    }

    public function getNumTvaIntra(): ?string
    {
        return $this->numTvaIntra;
    }

    public function setNumTvaIntra(string $numTvaIntra): static
    {
        $this->numTvaIntra = $numTvaIntra;

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

    public function getPourcentageTvaOtc(): ?int
    {
        return $this->pourcentageTvaOtc;
    }

    public function setPourcentageTvaOtc(int $pourcentageTvaOtc): static
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

    public function getPourcentageTvaPresta(): ?int
    {
        return $this->pourcentageTvaPresta;
    }

    public function setPourcentageTvaPresta(int $pourcentageTvaPresta): static
    {
        $this->pourcentageTvaPresta = $pourcentageTvaPresta;

        return $this;
    }

    public function getMontantTvaPresta(): ?string
    {
        return $this->montantTvaPresta;
    }

    public function setMontantTvaPresta(string $montantTvaPresta): static
    {
        $this->montantTvaPresta = $montantTvaPresta;

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

    public function getNumeroReleve(): ?string
    {
        return $this->numeroReleve;
    }

    public function setNumeroReleve(?string $numeroReleve): static
    {
        $this->numeroReleve = $numeroReleve;

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
}
