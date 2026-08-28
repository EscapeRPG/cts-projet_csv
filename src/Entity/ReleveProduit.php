<?php

namespace App\Entity;

use App\Repository\ReleveProduitRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReleveProduitRepository::class)]
class ReleveProduit
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'relevesProduits')]
    #[ORM\JoinColumn(nullable: false)]
    private ?ReleveJournalier $releveJournalier = null;

    #[ORM\Column(length: 150)]
    private ?string $designation = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $cb = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $especes = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $cheque = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $bl = '0.00';

    #[ORM\Column(options: ['default' => 0])]
    private int $ordreAffichage = 0;

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

    public function getDesignation(): ?string
    {
        return $this->designation;
    }

    public function setDesignation(string $designation): static
    {
        $this->designation = $designation;

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

    public function getBl(): string
    {
        return $this->bl;
    }

    public function setBl(string $bl): static
    {
        $this->bl = $bl;

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

    public function isEmpty(): bool
    {
        return trim((string) $this->designation) === ''
            && self::isZero($this->cb)
            && self::isZero($this->especes)
            && self::isZero($this->cheque)
            && self::isZero($this->bl);
    }

    private static function isZero(string $value): bool
    {
        $value = trim($value);

        return $value === ''
            || preg_match('/^[+-]?0+(?:[.,]0+)?$/', $value) === 1;
    }
}
