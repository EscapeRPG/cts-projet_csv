<?php

namespace App\Service\Import;

class SftpClient
{
    private string $mode;
    private string $basePath;

    public function __construct(string $mode, string $basePath)
    {
        $this->mode = $mode;
        $this->basePath = $basePath;
    }

    public function listReseaux(): array
    {
        return array_filter(
            scandir($this->basePath),
            fn($d) => !in_array($d, ['.', '..']) && is_dir($this->basePath . '/' . $d)
        );
    }

    public function listIncomingFiles(string $reseau): array
    {
        $path = $this->getIncomingPath($reseau);
        if (!is_dir($path)) {
            return [];
        }

        $files = array_values(array_filter(
            scandir($path),
            fn($f) => str_ends_with(strtolower($f), '.csv') && is_file($path . '/' . $f)
        ));

        sort($files, SORT_NATURAL | SORT_FLAG_CASE);

        return $files;
    }

    public function getIncomingPath(string $reseau): string
    {
        return "{$this->basePath}/{$reseau}/incoming";
    }

    /*
     * Transfère le fichier vers le dossier "processed" en cas de réussite
     */
    public function moveToProcessed(string $reseau, string $filename): bool
    {
        $src = "{$this->basePath}/{$reseau}/incoming/{$filename}";
        $dest = "{$this->basePath}/{$reseau}/processed/{$filename}";

        return rename($src, $dest);
    }

    /*
     * Transfère le fichier vers le dossier "error" en cas d'erreur
     */
    public function moveToErrorSafe(string $reseau, string $filename): bool
    {
        $incoming = "{$this->basePath}/{$reseau}/incoming/{$filename}";
        $processed = "{$this->basePath}/{$reseau}/processed/{$filename}";

        // si le fichier est encore dans incoming
        if (file_exists($incoming)) {
            return rename($incoming, "{$this->basePath}/{$reseau}/error/{$filename}");
        }

        // sinon, s'il est déjà dans processed
        if (file_exists($processed)) {
            return rename($processed, "{$this->basePath}/{$reseau}/error/{$filename}");
        }

        return false;
    }

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
}
