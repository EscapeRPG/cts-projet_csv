<?php

namespace App\Service\Import;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;

/**
 * Filesystem-backed client used to handle SFTP-like import folders.
 */
class SftpClient
{
    private string $mode;
    private string $basePath;

    /**
     * @param string $mode SFTP operating mode.
     * @param string $basePath Base directory containing network folders.
     */
    public function __construct(string $mode, string $basePath)
    {
        $this->mode = $mode;
        $this->basePath = $basePath;
    }

    /**
     * Lists available network directories.
     *
     * @return array<int, string> Network directory names.
     */
    public function listReseaux(): array
    {
        return array_filter(
            scandir($this->basePath),
            fn($d) => !in_array($d, ['.', '..']) && is_dir($this->basePath . '/' . $d)
        );
    }

    /**
     * Lists incoming CSV files for a network.
     *
     * @param string $reseau Network code.
     *
     * @return array<int, string> Sorted CSV file names.
     */
    public function listIncomingFiles(string $reseau): array
    {
        $path = $this->getIncomingPath($reseau);
        if (!is_dir($path)) {
            return [];
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
        );

        $files = [];
        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }

            $relativePath = str_replace('\\', '/', $iterator->getSubPathname());
            if (!str_ends_with(strtolower($relativePath), '.csv')) {
                continue;
            }

            $files[] = $relativePath;
        }

        sort($files, SORT_NATURAL | SORT_FLAG_CASE);

        return $files;
    }

    /**
     * Returns incoming directory path for a network.
     *
     * @param string $reseau Network code.
     *
     * @return string Incoming directory path.
     */
    public function getIncomingPath(string $reseau): string
    {
        return "{$this->basePath}/{$reseau}/incoming";
    }

    /**
     * Moves a successfully imported file to the `processed` folder.
     *
     * @param string $reseau Network code.
     * @param string $filename File name.
     *
     * @return bool True on successful move.
     */
    public function moveToProcessed(string $reseau, string $filename): bool
    {
        $src = "{$this->basePath}/{$reseau}/incoming/{$filename}";
        $dest = "{$this->basePath}/{$reseau}/processed/{$filename}";

        $this->ensureParentDirectoryExists($dest);

        return rename($src, $dest);
    }

    /** Moves an incoming file to its immutable, content-addressed processed path. */
    public function archiveProcessedVersion(string $reseau, string $filename, string $hash): ?string
    {
        if (preg_match('/^[a-f0-9]{64}$/i', $hash) !== 1) {
            throw new \InvalidArgumentException('Hash SHA-256 invalide.');
        }

        $src = "{$this->basePath}/{$reseau}/incoming/{$filename}";
        if (!is_file($src)) {
            return null;
        }

        $normalizedName = str_replace(['\\', '/'], '__', $filename);
        $logicalDirectory = pathinfo($normalizedName, PATHINFO_FILENAME);
        $basename = basename(str_replace('\\', '/', $filename));
        $relativePath = "{$reseau}/processed/{$logicalDirectory}/" . strtolower($hash) . "/{$basename}";
        $dest = "{$this->basePath}/{$relativePath}";
        $this->ensureParentDirectoryExists($dest);

        if (is_file($dest)) {
            if (!hash_equals(strtolower($hash), strtolower((string)hash_file('sha256', $dest)))) {
                throw new \RuntimeException('Une archive existe déjà à ce chemin avec un contenu différent.');
            }

            return unlink($src) ? $relativePath : null;
        }

        return rename($src, $dest) ? $relativePath : null;
    }

    public function resolveArchivedPath(string $relativePath): ?string
    {
        $candidate = $this->basePath . '/' . ltrim(str_replace('\\', '/', $relativePath), '/');
        $resolvedBase = realpath($this->basePath);
        $resolvedFile = realpath($candidate);
        if ($resolvedBase === false || $resolvedFile === false || !is_file($resolvedFile)) {
            return null;
        }

        $basePrefix = rtrim(str_replace('\\', '/', $resolvedBase), '/') . '/';
        $filePath = str_replace('\\', '/', $resolvedFile);
        return str_starts_with($filePath, $basePrefix) ? $resolvedFile : null;
    }

    public function resolveLegacyProcessedPath(string $reseau, string $filename): ?string
    {
        return $this->resolveArchivedPath("{$reseau}/processed/{$filename}");
    }

    /** Finds a pre-versioning processed file even when its folder uses a legacy network alias. */
    public function findLegacyProcessedPath(string $filename, string $expectedHash): ?string
    {
        foreach ($this->listReseaux() as $reseauFolder) {
            $path = $this->resolveLegacyProcessedPath($reseauFolder, $filename);
            if ($path === null) {
                continue;
            }

            $actualHash = hash_file('sha256', $path, false);
            if (is_string($actualHash) && hash_equals(strtolower($expectedHash), strtolower($actualHash))) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Moves a failed file to the `error` folder from incoming or processed folders.
     *
     * @param string $reseau Network code.
     * @param string $filename File name.
     *
     * @return bool True on successful move.
     */
    public function moveToErrorSafe(string $reseau, string $filename): bool
    {
        $incoming = "{$this->basePath}/{$reseau}/incoming/{$filename}";
        $processed = "{$this->basePath}/{$reseau}/processed/{$filename}";
        $errorDestination = "{$this->basePath}/{$reseau}/error/{$filename}";

        // si le fichier est encore dans incoming
        if (file_exists($incoming)) {
            $this->ensureParentDirectoryExists($errorDestination);

            return rename($incoming, $errorDestination);
        }

        // sinon, s'il est déjà dans processed
        if (file_exists($processed)) {
            $this->ensureParentDirectoryExists($errorDestination);

            return rename($processed, $errorDestination);
        }

        return false;
    }

    /**
     * Checks whether an incoming file is stable enough to be imported.
     *
     * @param string $reseau Network code.
     * @param string $filename File name.
     * @param int $minAgeSeconds Minimum file age before import.
     *
     * @return bool True when file size and mtime are stable.
     */
    public function isIncomingFileStable(string $reseau, string $filename, int $minAgeSeconds = 120): bool
    {
        $path = "{$this->basePath}/{$reseau}/incoming/{$filename}";
        if (!is_file($path)) {
            return false;
        }

        $firstSize = filesize($path);
        $firstMTime = filemtime($path);
        if ($firstSize === false || $firstMTime === false) {
            return false;
        }

        // Évite d'importer un fichier encore en cours de dépôt.
        if ((time() - (int)$firstMTime) < $minAgeSeconds) {
            return false;
        }

        usleep(300000);
        clearstatcache(true, $path);

        $secondSize = filesize($path);
        $secondMTime = filemtime($path);
        if ($secondSize === false || $secondMTime === false) {
            return false;
        }

        return (int)$firstSize === (int)$secondSize && (int)$firstMTime === (int)$secondMTime;
    }

    private function ensureParentDirectoryExists(string $path): void
    {
        $directory = dirname($path);
        if (is_dir($directory)) {
            return;
        }

        mkdir($directory, 0777, true);
    }
}
