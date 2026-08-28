<?php

namespace App\Entity;

use App\Repository\ImportedFilesRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ImportedFilesRepository::class)]
class ImportedFiles
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $filename = null;

    #[ORM\Column(length: 255)]
    private ?string $file_hash = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $imported_at = null;

    #[ORM\ManyToOne]
    private ?Reseau $reseau = null;

    #[ORM\ManyToOne]
    private ?Centre $centre = null;

    #[ORM\Column(length: 1024, nullable: true)]
    private ?string $archive_path = null;

    #[ORM\Column(length: 20, options: ['default' => 'active'])]
    private string $status = 'active';

    #[ORM\Column(options: ['default' => true])]
    private bool $is_active = true;

    #[ORM\Column(nullable: true)]
    private ?int $supersedes_id = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $rows_read = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $rows_inserted = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $rows_ignored = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFilename(): ?string
    {
        return $this->filename;
    }

    public function setFilename(string $filename): static
    {
        $this->filename = $filename;

        return $this;
    }

    public function getFileHash(): ?string
    {
        return $this->file_hash;
    }

    public function setFileHash(string $file_hash): static
    {
        $this->file_hash = $file_hash;

        return $this;
    }

    public function getImportedAt(): ?\DateTimeImmutable
    {
        return $this->imported_at;
    }

    public function setImportedAt(\DateTimeImmutable $imported_at): static
    {
        $this->imported_at = $imported_at;

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

    public function getCentre(): ?Centre
    {
        return $this->centre;
    }

    public function setCentre(?Centre $centre): static
    {
        $this->centre = $centre;

        return $this;
    }

    public function getArchivePath(): ?string { return $this->archive_path; }
    public function setArchivePath(?string $archivePath): static { $this->archive_path = $archivePath; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }
    public function isActive(): bool { return $this->is_active; }
    public function setIsActive(bool $isActive): static { $this->is_active = $isActive; return $this; }
    public function getSupersedesId(): ?int { return $this->supersedes_id; }
    public function setSupersedesId(?int $supersedesId): static { $this->supersedes_id = $supersedesId; return $this; }
    public function getRowsRead(): int { return $this->rows_read; }
    public function setRowsRead(int $rowsRead): static { $this->rows_read = $rowsRead; return $this; }
    public function getRowsInserted(): int { return $this->rows_inserted; }
    public function setRowsInserted(int $rowsInserted): static { $this->rows_inserted = $rowsInserted; return $this; }
    public function getRowsIgnored(): int { return $this->rows_ignored; }
    public function setRowsIgnored(int $rowsIgnored): static { $this->rows_ignored = $rowsIgnored; return $this; }
}
