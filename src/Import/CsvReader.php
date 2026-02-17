<?php

namespace App\Import;

use Symfony\Component\HttpFoundation\File\UploadedFile;

final class CsvReader
{
    public function read(UploadedFile $file, string $delimiter = ';', ?string $reseauCode = null): \Generator
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

    /*
     * Renomme les headers des fichiers csv CONTROLES de SGS suite à un souci de nommage
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

