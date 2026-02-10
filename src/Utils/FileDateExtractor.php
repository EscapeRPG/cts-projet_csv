<?php

namespace App\Utils;

use DateTimeImmutable;

final class FileDateExtractor
{
    /*
     * Récupère la date dans le nom du fichier pour enregistrement en bdd et tri par année des données
     */
    public static function extract(string $filename): ?DateTimeImmutable
    {
        $patterns = [
            // 20240131
            '/(20\d{2})(\d{2})(\d{2})/',
            // 2024-01-31 or 2024_01_31
            '/(20\d{2})[-_](\d{2})[-_](\d{2})/',
            // 31-01-2024 or 31_01_2024
            '/(\d{2})[-_](\d{2})[-_](20\d{2})/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $filename, $m)) {
                if (strlen($m[1]) === 4) {
                    return new DateTimeImmutable("$m[1]-$m[2]-$m[3]");
                }
                return new DateTimeImmutable("$m[3]-$m[2]-$m[1]");
            }
        }

        return null;
    }
}
