<?php

namespace App\Service\Voiture;

use App\Entity\Voiture;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class VoitureCertificatCessionStorage
{
    public function __construct(
        private string $voitureUploadRoot,
        private Filesystem $filesystem,
    ) {
    }

    public function absolutePath(string $relativePath): string
    {
        $relativePath = ltrim(str_replace(['\\', '..'], ['/', ''], $relativePath), '/');
        return rtrim($this->voitureUploadRoot, "/\\") . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    }

    /**
     * Stores the uploaded file and returns the relative storage path (to be persisted on the entity).
     */
    public function storeCertificat(Voiture $voiture, UploadedFile $file): string
    {
        $dir = sprintf('voitures/certificats/voiture_%d', (int) $voiture->getId());
        $targetDir = $this->absolutePath($dir);
        $this->filesystem->mkdir($targetDir);

        $ext = $file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'bin';
        $ext = strtolower(preg_replace('/[^a-z0-9]+/i', '', (string) $ext)) ?: 'bin';

        $name = bin2hex(random_bytes(16)) . '.' . $ext;
        $file->move($targetDir, $name);

        return $dir . '/' . $name;
    }

    public function deleteIfExists(?string $relativePath): void
    {
        if (!$relativePath) return;

        $path = $this->absolutePath($relativePath);
        if ($this->filesystem->exists($path)) {
            $this->filesystem->remove($path);
        }
    }
}

