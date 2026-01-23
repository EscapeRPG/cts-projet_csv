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
            fn ($d) => !in_array($d, ['.', '..']) && is_dir($this->basePath.'/'.$d)
        );
    }

    public function listIncomingFiles(string $reseau): array
    {
        $path = $this->getIncomingPath($reseau);

        return array_values(array_filter(
            scandir($path),
            fn ($f) => str_ends_with($f, '.csv')
        ));
    }

    public function getIncomingPath(string $reseau): string
    {
        return "{$this->basePath}/{$reseau}/incoming";
    }

    public function moveToProcessed(string $reseau, string $filename): bool
    {
        $src = "{$this->basePath}/{$reseau}/incoming/{$filename}";
        $dest = "{$this->basePath}/{$reseau}/processed/{$filename}";

        return rename($src, $dest);
    }

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
}
