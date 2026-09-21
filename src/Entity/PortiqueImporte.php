<?php

namespace App\Entity;

use App\Repository\PortiqueImporteRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PortiqueImporteRepository::class)]
class PortiqueImporte
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?Centre $centre = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTime $date = null;

    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    private ?\DateTime $heure = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $serviceVendu = null;

    #[ORM\Column(nullable: true)]
    private ?int $jetons = null;

    #[ORM\Column(nullable: true)]
    private ?int $jetonsGratuits = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    private ?string $rechargeCle = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    private ?string $rechargeCleOfferte = null;

    #[ORM\Column(nullable: true)]
    private ?int $cles = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    private ?string $prixTotal = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    private ?string $supplement = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    private ?string $ticketPromo = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $codeRemise = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    private ?string $ticketRembt = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    private ?string $payeCle = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    private ?string $payeJetons1 = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    private ?string $payeJetons2 = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    private ?string $payePieces = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    private ?string $payeBillets = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    private ?string $payeCB = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private ?string $payeTotal = null;

    #[ORM\Column(length: 10)]
    private ?string $mode = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    private ?string $rendu = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    private ?string $tropPercu = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    private ?string $nonDistribue = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    private ?string $creditCle = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $numCle = null;

    #[ORM\Column]
    private ?int $numPortique = null;

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

    public function getDate(): ?\DateTime
    {
        return $this->date;
    }

    public function setDate(\DateTime $date): static
    {
        $this->date = $date;

        return $this;
    }

    public function getHeure(): ?\DateTime
    {
        return $this->heure;
    }

    public function setHeure(?\DateTime $heure): static
    {
        $this->heure = $heure;

        return $this;
    }

    public function getServiceVendu(): ?string
    {
        return $this->serviceVendu;
    }

    public function setServiceVendu(?string $serviceVendu): static
    {
        $this->serviceVendu = $serviceVendu;

        return $this;
    }

    public function getJetons(): ?int
    {
        return $this->jetons;
    }

    public function setJetons(?int $jetons): static
    {
        $this->jetons = $jetons;

        return $this;
    }

    public function getJetonsGratuits(): ?int
    {
        return $this->jetonsGratuits;
    }

    public function setJetonsGratuits(?int $jetonsGratuits): static
    {
        $this->jetonsGratuits = $jetonsGratuits;

        return $this;
    }

    public function getRechargeCle(): ?string
    {
        return $this->rechargeCle;
    }

    public function setRechargeCle(?string $rechargeCle): static
    {
        $this->rechargeCle = $rechargeCle;

        return $this;
    }

    public function getRechargeCleOfferte(): ?string
    {
        return $this->rechargeCleOfferte;
    }

    public function setRechargeCleOfferte(?string $rechargeCleOfferte): static
    {
        $this->rechargeCleOfferte = $rechargeCleOfferte;

        return $this;
    }

    public function getCles(): ?int
    {
        return $this->cles;
    }

    public function setCles(?int $cles): static
    {
        $this->cles = $cles;

        return $this;
    }

    public function getPrixTotal(): ?string
    {
        return $this->prixTotal;
    }

    public function setPrixTotal(?string $prixTotal): static
    {
        $this->prixTotal = $prixTotal;

        return $this;
    }

    public function getSupplement(): ?string
    {
        return $this->supplement;
    }

    public function setSupplement(?string $supplement): static
    {
        $this->supplement = $supplement;

        return $this;
    }

    public function getTicketPromo(): ?string
    {
        return $this->ticketPromo;
    }

    public function setTicketPromo(?string $ticketPromo): static
    {
        $this->ticketPromo = $ticketPromo;

        return $this;
    }

    public function getCodeRemise(): ?string
    {
        return $this->codeRemise;
    }

    public function setCodeRemise(?string $codeRemise): static
    {
        $this->codeRemise = $codeRemise;

        return $this;
    }

    public function getTicketRembt(): ?string
    {
        return $this->ticketRembt;
    }

    public function setTicketRembt(?string $ticketRembt): static
    {
        $this->ticketRembt = $ticketRembt;

        return $this;
    }

    public function getPayeCle(): ?string
    {
        return $this->payeCle;
    }

    public function setPayeCle(?string $payeCle): static
    {
        $this->payeCle = $payeCle;

        return $this;
    }

    public function getPayeJetons1(): ?string
    {
        return $this->payeJetons1;
    }

    public function setPayeJetons1(?string $payeJetons1): static
    {
        $this->payeJetons1 = $payeJetons1;

        return $this;
    }

    public function getPayeJetons2(): ?string
    {
        return $this->payeJetons2;
    }

    public function setPayeJetons2(?string $payeJetons2): static
    {
        $this->payeJetons2 = $payeJetons2;

        return $this;
    }

    public function getPayePieces(): ?string
    {
        return $this->payePieces;
    }

    public function setPayePieces(?string $payePieces): static
    {
        $this->payePieces = $payePieces;

        return $this;
    }

    public function getPayeBillets(): ?string
    {
        return $this->payeBillets;
    }

    public function setPayeBillets(?string $payeBillets): static
    {
        $this->payeBillets = $payeBillets;

        return $this;
    }

    public function getPayeCB(): ?string
    {
        return $this->payeCB;
    }

    public function setPayeCB(?string $payeCB): static
    {
        $this->payeCB = $payeCB;

        return $this;
    }

    public function getPayeTotal(): ?string
    {
        return $this->payeTotal;
    }

    public function setPayeTotal(string $payeTotal): static
    {
        $this->payeTotal = $payeTotal;

        return $this;
    }

    public function getMode(): ?string
    {
        return $this->mode;
    }

    public function setMode(string $mode): static
    {
        $this->mode = $mode;

        return $this;
    }

    public function getRendu(): ?string
    {
        return $this->rendu;
    }

    public function setRendu(?string $rendu): static
    {
        $this->rendu = $rendu;

        return $this;
    }

    public function getTropPercu(): ?string
    {
        return $this->tropPercu;
    }

    public function setTropPercu(?string $tropPercu): static
    {
        $this->tropPercu = $tropPercu;

        return $this;
    }

    public function getNonDistribue(): ?string
    {
        return $this->nonDistribue;
    }

    public function setNonDistribue(?string $nonDistribue): static
    {
        $this->nonDistribue = $nonDistribue;

        return $this;
    }

    public function getCreditCle(): ?string
    {
        return $this->creditCle;
    }

    public function setCreditCle(?string $creditCle): static
    {
        $this->creditCle = $creditCle;

        return $this;
    }

    public function getNumCle(): ?string
    {
        return $this->numCle;
    }

    public function setNumCle(?string $numCle): static
    {
        $this->numCle = $numCle;

        return $this;
    }

    public function getNumPortique(): ?int
    {
        return $this->numPortique;
    }

    public function setNumPortique(int $numPortique): static
    {
        $this->numPortique = $numPortique;

        return $this;
    }
}
