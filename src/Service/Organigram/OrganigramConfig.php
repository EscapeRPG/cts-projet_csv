<?php

namespace App\Service\Organigram;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Independent published PDFs, staged imports and read-only support for legacy settings.
 */
final class OrganigramConfig
{
    public const KEYS = ['structurel', 'immobilier', 'hierarchique'];
    private const DIR = 'organigram';
    private const PDF_NAME = 'organigram.pdf';
    private const SETTINGS_NAME = 'settings.json';

    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly Filesystem $fs,
        private readonly PdfPageExtractor $extractor,
    ) {
    }

    private function getBaseDir(): string
    {
        return rtrim($this->kernel->getProjectDir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . self::DIR;
    }

    public function getPdfPath(): string
    {
        return $this->getBaseDir() . DIRECTORY_SEPARATOR . self::PDF_NAME;
    }

    public function hasPdf(): bool
    {
        return is_file($this->getPdfPath());
    }

    public function ensureBaseDir(): void
    {
        $this->fs->mkdir($this->getBaseDir());
    }

    /**
     * @return array{structurel:int|null, immobilier:int|null, hierarchique:int|null}
     */
    public function getMapping(): array
    {
        $defaults = ['structurel' => null, 'immobilier' => null, 'hierarchique' => null];
        $path = $this->getBaseDir() . DIRECTORY_SEPARATOR . self::SETTINGS_NAME;
        if (!is_file($path)) {
            return $defaults;
        }

        $raw = @file_get_contents($path);
        if (!is_string($raw) || trim($raw) === '') {
            return $defaults;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return $defaults;
        }

        $out = $defaults;
        foreach (array_keys($defaults) as $k) {
            $v = $decoded[$k] ?? null;
            if (is_int($v) && $v > 0) {
                $out[$k] = $v;
            } elseif (is_string($v) && ctype_digit($v) && (int) $v > 0) {
                $out[$k] = (int) $v;
            }
        }
        return $out;
    }

    /**
     * @param array{structurel?:int|null, immobilier?:int|null, hierarchique?:int|null} $mapping
     */
    public function saveMapping(array $mapping): void
    {
        $this->ensureBaseDir();

        $current = $this->getMapping();
        foreach (['structurel', 'immobilier', 'hierarchique'] as $k) {
            if (!array_key_exists($k, $mapping)) {
                continue;
            }
            $v = $mapping[$k];
            $current[$k] = is_int($v) && $v > 0 ? $v : null;
        }

        $path = $this->getBaseDir() . DIRECTORY_SEPARATOR . self::SETTINGS_NAME;
        $this->fs->dumpFile($path, json_encode($current, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public function getPdfMtime(): ?int
    {
        $path = $this->getPdfPath();
        if (!is_file($path)) return null;
        $mtime = @filemtime($path);
        return is_int($mtime) ? $mtime : null;
    }

    public function stageImport(UploadedFile $file): string
    {
        // Reject unsupported or invalid PDFs before making the import available.
        $this->extractor->pageCount($file->getPathname());
        $id = bin2hex(random_bytes(16));
        $directory = $this->getBaseDir() . '/imports';
        $this->fs->mkdir($directory);
        $file->move($directory, $id . '.pdf');

        return $id;
    }

    public function getImportPath(string $id): ?string
    {
        if (!preg_match('/^[a-f0-9]{32}$/D', $id)) return null;
        $path = $this->getBaseDir() . '/imports/' . $id . '.pdf';

        return is_file($path) ? $path : null;
    }

    public function discardImport(string $id): void
    {
        $path = $this->getImportPath($id);
        if ($path !== null) $this->fs->remove($path);
    }

    /** Blank associations leave the published organigram unchanged. */
    public function publishImport(string $id, array $mapping): void
    {
        $source = $this->getImportPath($id);
        if ($source === null) throw new \InvalidArgumentException('Le PDF importé est introuvable.');
        $prepared = [];
        foreach (self::KEYS as $key) {
            $page = $mapping[$key] ?? null;
            if ($page === null) continue;
            if (!is_int($page) || $page < 1) {
                throw new \InvalidArgumentException('Numéro de page invalide.');
            }
            $prepared[$key] = $this->extractor->extract($source, $page);
        }
        if ($prepared === []) throw new \InvalidArgumentException('Sélectionnez au moins une association.');

        // All extraction must succeed before any published association changes.
        $this->withPublicationLock(function () use ($prepared): void {
            $this->savePrepared($this->readPublished(), $prepared);
        });
    }

    public function getDocumentPath(string $key): ?string
    {
        if (!in_array($key, self::KEYS, true)) throw new \InvalidArgumentException('Organigramme inconnu.');

        return $this->withPublicationLock(function () use ($key): ?string {
            $published = $this->readPublished();
            if (isset($published[$key])) return $this->getBaseDir() . '/' . $published[$key];

            // Existing installations keep their original PDF and settings as a backup.
            $page = $this->getMapping()[$key];
            if ($page === null || !$this->hasPdf()) return null;
            $published = $this->savePrepared($published, [$key => $this->extractor->extract($this->getPdfPath(), $page)]);

            return $this->getBaseDir() . '/' . $published[$key];
        });
    }

    private function readPublished(): array
    {
        $path = $this->getBaseDir() . '/published.json';
        if (!is_file($path)) return [];

        return json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }

    private function savePrepared(array $published, array $prepared): array
    {
        $generation = 'published/' . bin2hex(random_bytes(16));
        foreach ($prepared as $key => $bytes) {
            $relativePath = $generation . '/' . $key . '.pdf';
            $this->fs->dumpFile($this->getBaseDir() . '/' . $relativePath, $bytes);
            $published[$key] = $relativePath;
        }
        // A single atomic manifest replacement publishes the whole selection.
        $this->fs->dumpFile($this->getBaseDir() . '/published.json', json_encode($published, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));

        return $published;
    }

    private function withPublicationLock(callable $callback): mixed
    {
        $this->ensureBaseDir();
        $lock = fopen($this->getBaseDir() . '/publish.lock', 'c');
        if ($lock === false) throw new \RuntimeException('Impossible de verrouiller les organigrammes.');
        try {
            if (!flock($lock, LOCK_EX)) throw new \RuntimeException('Impossible de verrouiller les organigrammes.');
            return $callback();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
