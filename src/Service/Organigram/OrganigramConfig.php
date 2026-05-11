<?php

namespace App\Service\Organigram;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * File-backed configuration for organigram PDF + page mapping.
 *
 * Kept intentionally simple: a single current PDF and a small JSON settings file.
 */
final class OrganigramConfig
{
    private const DIR = 'organigram';
    private const PDF_NAME = 'organigram.pdf';
    private const SETTINGS_NAME = 'settings.json';

    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly Filesystem $fs,
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
}
