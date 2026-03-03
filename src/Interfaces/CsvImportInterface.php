<?php

namespace App\Interfaces;

use App\Entity\Reseau;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Contract for CSV import services.
 */
interface CsvImportInterface
{
    /**
     * Imports data from an uploaded CSV file for a given network.
     *
     * @param UploadedFile $file Uploaded CSV file to import.
     * @param Reseau $reseau Network context associated with the file.
     *
     * @return int Number of processed rows.
     */
    public function importFromFile(UploadedFile $file, Reseau $reseau): int;
}
