<?php

namespace App\Interfaces;

use Symfony\Component\HttpFoundation\File\UploadedFile;

interface CsvImportInterface
{
    public function importFromFile(UploadedFile $file): int;
}
