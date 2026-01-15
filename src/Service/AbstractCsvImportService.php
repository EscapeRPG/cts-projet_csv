<?php

namespace App\Service;

use App\Import\CsvReader;
use App\Interfaces\CsvImportInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

abstract class AbstractCsvImportService implements CsvImportInterface
{
    protected int $batchSize = 500;

    public function __construct(
        protected EntityManagerInterface $em,
        protected CsvReader $csvReader,
    ) {
    }

    final public function importFromFile(UploadedFile $file): int
    {
        $count = 0;

        foreach ($this->csvReader->read($file, ';') as $lineNumber => $data) {
            $data = array_combine(
                array_map(fn($k) => preg_replace('/^\x{FEFF}/u', '', $k), array_keys($data)),
                $data
            );

            try {
                if ($this->handleRow($data, $lineNumber) === true) {
                    $count++;
                }

                if ($count > 0 && $count % $this->batchSize === 0) {
                    $this->flushAndClear();
                }
            } catch (\Throwable $e) {
                throw new \RuntimeException(
                    sprintf(
                        'Erreur ligne %d (%s) : %s',
                        $lineNumber + 1,
                        $file->getClientOriginalName(),
                        $e->getMessage()
                    ),
                    previous: $e
                );
            }
        }

        $this->em->flush();

        return $count;
    }

    protected function flushAndClear(): void
    {
        $this->em->flush();
        $this->em->clear();
        $this->resetCaches();
    }

    /**
     * Retourne true si une ligne est importée
     * false si ignorée (doublon par ex)
     */
    abstract protected function handleRow(array $data, int $lineNumber): bool;

    /**
     * Permet aux services enfants de vider leurs caches
     */
    protected function resetCaches(): void
    {
    }
}
