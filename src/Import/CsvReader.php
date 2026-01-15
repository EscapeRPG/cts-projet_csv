<?php

namespace App\Import;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class CsvReader
{
    public function read(UploadedFile $file, string $delimiter = ';'): iterable
    {
        $handle = fopen($file->getPathname(), 'r');
        $headers = array_map(
            fn ($h) => strtolower(trim($h)),
            fgetcsv($handle, 0, $delimiter)
        );


        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            yield array_combine($headers, $row);
        }

        fclose($handle);
    }
}

