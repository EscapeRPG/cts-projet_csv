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

    #[ORM\ManyToOne(inversedBy: 'importedFiles')]
    private ?Reseau $reseau = null;

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
}
