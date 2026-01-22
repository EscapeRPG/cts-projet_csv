<?php

namespace App\Interfaces;

use App\Entity\Reseau;
use Symfony\Component\HttpFoundation\File\UploadedFile;

interface CsvImportInterface
{
    public function importFromFile(UploadedFile $file, Reseau $reseau): int;
}
