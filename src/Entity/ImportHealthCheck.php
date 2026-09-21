<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Index(name: 'IDX_D1E22214445D170C', columns: ['reseau_id'])]
#[ORM\UniqueConstraint(name: 'uniq_import_health_reseau_date', columns: ['reseau_id', 'check_date'])]
class ImportHealthCheck
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(nullable: true)]
    private ?int $reseauId = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $checkDate = null;

    #[ORM\Column(length: 255)]
    private ?string $reseauName = null;

    #[ORM\Column]
    private int $filesImported = 0;

    #[ORM\Column]
    private int $expectedFiles = 0;

    #[ORM\Column]
    private int $controlesFiles = 0;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $latestImportedAt = null;

    #[ORM\Column(length: 20)]
    private ?string $status = null;

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $issues = [];

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReseauId(): ?int
    {
        return $this->reseauId;
    }

    public function setReseauId(?int $reseauId): static
    {
        $this->reseauId = $reseauId;

        return $this;
    }

    public function getCheckDate(): ?\DateTimeImmutable
    {
        return $this->checkDate;
    }

    public function setCheckDate(\DateTimeImmutable $checkDate): static
    {
        $this->checkDate = $checkDate;

        return $this;
    }

    public function getReseauName(): ?string
    {
        return $this->reseauName;
    }

    public function setReseauName(string $reseauName): static
    {
        $this->reseauName = $reseauName;

        return $this;
    }

    public function getFilesImported(): int
    {
        return $this->filesImported;
    }

    public function setFilesImported(int $filesImported): static
    {
        $this->filesImported = $filesImported;

        return $this;
    }

    public function getExpectedFiles(): int
    {
        return $this->expectedFiles;
    }

    public function setExpectedFiles(int $expectedFiles): static
    {
        $this->expectedFiles = $expectedFiles;

        return $this;
    }

    public function getControlesFiles(): int
    {
        return $this->controlesFiles;
    }

    public function setControlesFiles(int $controlesFiles): static
    {
        $this->controlesFiles = $controlesFiles;

        return $this;
    }

    public function getLatestImportedAt(): ?\DateTimeImmutable
    {
        return $this->latestImportedAt;
    }

    public function setLatestImportedAt(?\DateTimeImmutable $latestImportedAt): static
    {
        $this->latestImportedAt = $latestImportedAt;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    /** @return list<string> */
    public function getIssues(): array
    {
        return $this->issues;
    }

    /** @param list<string> $issues */
    public function setIssues(array $issues): static
    {
        $this->issues = $issues;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}
