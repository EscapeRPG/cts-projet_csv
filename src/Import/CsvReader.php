<?php

namespace App\Import;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Streams CSV files row-by-row with normalized headers.
 */
final class CsvReader
{
    /**
     * Reads a CSV file and yields associative rows using normalized headers.
     *
     * @param UploadedFile $file Uploaded CSV file.
     * @param string $delimiter CSV delimiter.
     * @param string|null $reseauCode Optional network code used for header remapping rules.
     *
     * @return \Generator<int, array<string, mixed>> Generator yielding normalized CSV rows.
     *
     * @throws \RuntimeException When the CSV file cannot be opened.
     */
    public function read(UploadedFile $file, string $delimiter = ';', ?string $reseauCode = null): \Generator
    {
        $handle = fopen($file->getPathname(), 'r');

        if ($handle === false) {
            throw new \RuntimeException('Impossible d’ouvrir le fichier CSV');
        }

        $headers = null;

        try {
            // If the file starts with an UTF-8 BOM, consume it so fgetcsv() can properly
            // recognize the enclosure (") on the first column.
            $prefix = fread($handle, 3);
            if ($prefix !== "\xEF\xBB\xBF") {
                rewind($handle);
            }

            while (($row = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
                if ($headers === null) {
                    $headers = array_map(
                        fn($h) => $this->normalizeHeader($h),
                        $row
                    );

                    $headers = $this->remapHeaders($headers, $file, $reseauCode);

                    continue;
                }

                if (count($row) !== count($headers)) {
                    if (count($row) > count($headers)) {
                        $row = array_slice($row, 0, count($headers));
                    } else {
                        $row = array_pad($row, count($headers), null);
                    }
                }

                yield array_combine($headers, $row);
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Normalizes a CSV header label for stable database-field matching.
     *
     * @param string $header Raw CSV header label.
     *
     * @return string Normalized header label.
     */
    private function normalizeHeader(string $header): string
    {
        // Remove UTF-8 BOM when present (common on first column headers).
        $header = preg_replace('/^\x{FEFF}/u', '', $header) ?? $header;

        // Normalize whitespace (including NBSP) early.
        $header = str_replace("\u{00A0}", ' ', $header);
        $header = trim($header);

        // Lowercase for stable matching.
        $header = mb_strtolower($header);

        // Try to make headers resilient to accents/punctuation differences.
        if (function_exists('iconv')) {
            $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $header);
            if ($converted !== false) {
                $header = $converted;
            }
        }

        // Convert to a simple "snake-ish" identifier.
        // This makes headers like "Date export" or "NUM TVA INTRA" match "date_export"/"num_tva_intra".
        $header = preg_replace('/[^a-z0-9]+/', '_', $header) ?? $header;
        $header = preg_replace('/_+/', '_', $header) ?? $header;
        $header = trim($header, '_');

        // Special-case common CSV variations where IDs are exported with a separator ("id client" => "idclient").
        $header = preg_replace('/^id_/', 'id', $header) ?? $header;

        return $header;
    }

    /**
     * Applies network-specific header remapping rules.
     *
     * @param array<int, string> $headers Normalized headers from the source CSV file.
     * @param UploadedFile $file Uploaded CSV file.
     * @param string|null $reseauCode Optional network code.
     *
     * @return array<int, string> Remapped headers when a rule matches, original headers otherwise.
     */
    private function remapHeaders(array $headers, UploadedFile $file, ?string $reseauCode): array
    {
        $filename = strtolower($file->getClientOriginalName());

        // Cas spécifique client SGS + fichier CONTROLES
        if ($reseauCode === 'sgs' && str_contains($filename, '[controles]')) {
            return [
                'idcontrole',
                'date_export',
                'num_pv_ctrl',
                'num_lia_ctrl',
                'immat_vehicule',
                'num_serie_vehicule',
                'date_prise_rdv',
                'type_rdv',
                'deb_ctrl',
                'fin_ctrl',
                'date_ctrl',
                'temps_ctrl',
                'ref_temps',
                'res_ctrl',
                'type_ctrl',
                'modele_vehicule',
                'annee_circulation',
            ];
        }

        return $headers;
    }
}

