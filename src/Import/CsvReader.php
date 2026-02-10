<?php

namespace App\Import;

use Symfony\Component\HttpFoundation\File\UploadedFile;

final class CsvReader
{
    public function read(UploadedFile $file, string $delimiter = ';'): \Generator
    {
        $handle = fopen($file->getPathname(), 'r');

        if ($handle === false) {
            throw new \RuntimeException('Impossible d’ouvrir le fichier CSV');
        }

        $headers = null;

        while (($row = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
            if ($headers === null) {
                $headers = array_map(
                    fn($h) => $this->normalizeHeader($h),
                    $row
                );
                continue;
            }

            yield array_combine($headers, $row);
        }

        fclose($handle);
    }

    /*
     * S'assure que les titres des colonnes des fichiers csv ne possèdent pas de caractères invisbles pour matcher la bdd lors de l'importation
     */
    private function normalizeHeader(string $header): string
    {
        $header = preg_replace('/^\x{FEFF}/u', '', $header);
        return trim(mb_strtolower($header));
    }
}

