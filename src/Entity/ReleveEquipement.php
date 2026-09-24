<?php

namespace App\Entity;

use App\Repository\ReleveEquipementRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: ReleveEquipementRepository::class)]
#[ORM\UniqueConstraint(
    name: 'UNIQ_RELEVE_EQUIPEMENT_RELEVE_EQUIPEMENT',
    columns: ['releve_journalier_id', 'equipement_id'],
)]
#[UniqueEntity(
    fields: ['releveJournalier', 'equipement'],
    message: 'Cet équipement possède déjà une ligne dans ce relevé.',
)]
class ReleveEquipement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'relevesEquipements')]
    #[ORM\JoinColumn(nullable: false)]
    private ?ReleveJournalier $releveJournalier = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?EquipementStation $equipement = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $cb = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $especes = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $cheque = '0.00';

    #[ORM\Column(options: ['default' => 0])]
    private int $jetons = 0;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $bl = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    private ?string $totalCb = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    private ?string $totalEspeces = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    private ?string $totalCheque = null;

    #[ORM\Column(nullable: true)]
    private ?int $totalJetons = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    private ?string $totalBl = null;

    public function getTotalCb(): ?string
    {
        return $this->totalCb;
    }

    public function setTotalCb(?string $value): static
    {
        $this->totalCb = $value;
        return $this;
    }

    public function getTotalEspeces(): ?string
    {
        return $this->totalEspeces;
    }

    public function setTotalEspeces(?string $value): static
    {
        $this->totalEspeces = $value;
        return $this;
    }

    public function getTotalCheque(): ?string
    {
        return $this->totalCheque;
    }

    public function setTotalCheque(?string $value): static
    {
        $this->totalCheque = $value;
        return $this;
    }

    public function getTotalJetons(): ?int
    {
        return $this->totalJetons;
    }

    public function setTotalJetons(?int $value): static
    {
        $this->totalJetons = $value;
        return $this;
    }

    public function getTotalBl(): ?string
    {
        return $this->totalBl;
    }

    public function setTotalBl(?string $value): static
    {
        $this->totalBl = $value;
        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReleveJournalier(): ?ReleveJournalier
    {
        return $this->releveJournalier;
    }

    public function setReleveJournalier(?ReleveJournalier $releveJournalier): static
    {
        $this->releveJournalier = $releveJournalier;

        return $this;
    }

    public function getEquipement(): ?EquipementStation
    {
        return $this->equipement;
    }

    public function setEquipement(?EquipementStation $equipement): static
    {
        $this->equipement = $equipement;

        return $this;
    }

    public function getCb(): string
    {
        return $this->cb;
    }

    public function setCb(string $cb): static
    {
        $this->cb = $cb;

        return $this;
    }

    public function getEspeces(): string
    {
        return $this->especes;
    }

    public function setEspeces(string $especes): static
    {
        $this->especes = $especes;

        return $this;
    }

    public function getCheque(): string
    {
        return $this->cheque;
    }

    public function setCheque(string $cheque): static
    {
        $this->cheque = $cheque;

        return $this;
    }

    public function getJetons(): int
    {
        return $this->jetons;
    }

    public function setJetons(int $jetons): static
    {
        $this->jetons = $jetons;

        return $this;
    }

    public function getBl(): string
    {
        return $this->bl;
    }

    public function setBl(string $bl): static
    {
        $this->bl = $bl;

        return $this;
    }

    public function isEmpty(): bool
    {
        return self::isZero($this->cb)
            && self::isZero($this->especes)
            && self::isZero($this->cheque)
            && $this->jetons === 0
            && self::isZero($this->bl);
    }

    private static function isZero(string $value): bool
    {
        $value = trim($value);

        return $value === ''
            || preg_match('/^[+-]?0+(?:[.,]0+)?$/', $value) === 1;
    }
}
