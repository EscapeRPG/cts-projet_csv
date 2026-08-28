<?php

namespace App\Entity;

use App\Repository\RelevePrestationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RelevePrestationRepository::class)]
class RelevePrestation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'relevesPrestations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?ReleveJournalier $releveJournalier = null;

    #[ORM\Column(length: 150)]
    private ?string $nom = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $cb = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $especes = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $cheque = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $enCompte = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $contrat = '0.00';

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

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

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

    public function getEnCompte(): string
    {
        return $this->enCompte;
    }

    public function setEnCompte(string $enCompte): static
    {
        $this->enCompte = $enCompte;

        return $this;
    }

    public function getContrat(): string
    {
        return $this->contrat;
    }

    public function setContrat(string $contrat): static
    {
        $this->contrat = $contrat;

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
        return trim((string) $this->nom) === ''
            && self::isZero($this->cb)
            && self::isZero($this->especes)
            && self::isZero($this->cheque)
            && self::isZero($this->enCompte)
            && self::isZero($this->contrat);
    }

    private static function isZero(string $value): bool
    {
        $value = trim($value);

        return $value === ''
            || preg_match('/^[+-]?0+(?:[.,]0+)?$/', $value) === 1;
    }
}
