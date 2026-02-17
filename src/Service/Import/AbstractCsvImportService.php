<?php

namespace App\Service\Import;

use App\Entity\Reseau;
use App\Import\CsvReader;
use App\Interfaces\CsvImportInterface;
use App\Utils\DateParser;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

abstract class AbstractCsvImportService implements CsvImportInterface
{
    protected int $batchSize = 500;
    protected ?Reseau $reseau = null;

    public function __construct(
        protected EntityManagerInterface $em,
        protected CsvReader              $csvReader
    )
    {
    }

    public function setReseau(Reseau $reseau): void
    {
        $this->reseau = $reseau;
    }

    /*
    * À définir dans le service concret pour connaître les noms et types de tables
    */
    abstract protected static function getTableName(): string;

    abstract protected static function getColumns(): array;

    abstract protected static function getUniqueKeys(): array;

    abstract protected static function getColumnMapping(): array;

    abstract protected static function getDateColumns(): array;

    abstract protected static function getDecimalColumns(): array;

    /**
     * @throws Exception
     *
     *  Import générique
     */
    public function importFromFile(UploadedFile $file, Reseau $reseau): int
    {
        $this->em->getConnection()->getConfiguration()->setMiddlewares([]);

        if ($this->shouldSkipFile($file, $reseau)) {
            return 0;
        }

        $generator = $this->csvReader->read($file, ';', $reseau->getNom());

        $count = 0;
        $batch = [];

        foreach ($generator as $row) {
            $row = $this->mapRow($row);

            $batch[] = $row;
            $count++;

            if (count($batch) >= $this->batchSize) {
                $this->insertBatch($batch);
                $batch = [];
            }
        }

        if (!empty($batch)) {
            $this->insertBatch($batch);
        }

        $this->markFileAsImported($file, $reseau);

        return $count;
    }

    /*
     * Mappe une ligne CSV aux colonnes
     */
    protected function mapRow(array $data): array
    {
        $mapping = static::getColumnMapping();
        $dateColumns = static::getDateColumns();
        $decimalColumns = static::getDecimalColumns();

        $row = [];

        foreach (static::getColumns() as $column) {
            if ($column === 'reseau_id') {
                if (!$this->reseau) {
                    throw new \LogicException("Reseau non défini pour l'import");
                }

                $row[] = $this->reseau->getId();
                continue;
            }

            $value = null;

            // mapping CSV → BDD
            if (isset($mapping[$column])) {
                foreach ($mapping[$column] as $csvKey) {
                    if (array_key_exists($csvKey, $data)) {
                        $value = $data[$csvKey];
                        break;
                    }
                }
            } else {
                $value = $data[$column] ?? null;
            }

            // conversion date
            if ($value !== null && in_array($column, $dateColumns, true)) {
                if ($column === 'deb_ctrl' || $column === 'fin_ctrl') {
                    $value = DateParser::parseDate($value)?->format('H:i:s');
                } else {
                    $value = DateParser::parseDate($value)?->format('Y-m-d H:i:s');
                }
            }

            // conversion décimales
            if ($value !== null && in_array($column, $decimalColumns, true)) {
                $value = str_replace(',', '.', $value);
            }

            $row[] = $value;
        }

        return $row;
    }

    /**
     * @throws Exception
     *
     * Vérifie si le fichier a déjà été importé
     */
    protected function shouldSkipFile(UploadedFile $file, Reseau $reseau): bool
    {
        $hash = $this->getFileHash($file);

        return (bool)$this->em->getConnection()->fetchOne(
            'SELECT 1 FROM imported_files WHERE filename = :name AND file_hash = :hash AND reseau_id = :reseau',
            [
                'name' => $file->getClientOriginalName(),
                'hash' => $hash,
                'reseau' => $reseau->getId(),
            ]
        );
    }


    /**
     * @throws Exception
     *
     * Insert un batch en SQL
     */
    protected function insertBatch(array $batch): void
    {
        $columns = static::getColumns();
        $table = static::getTableName();
        $values = [];

        foreach ($batch as $row) {
            $rowSql = array_map(function ($v) {
                if ($v === null) {
                    return 'NULL';
                }

                return $this->em->getConnection()->quote((string)$v);
            }, $row);

            $values[] = '(' . implode(',', $rowSql) . ')';
        }

        $sql = sprintf(
            'INSERT IGNORE INTO %s (%s) VALUES %s',
            $table,
            implode(',', $columns),
            implode(',', $values)
        );

        $this->em->getConnection()->executeStatement($sql);

        unset($values, $sql, $batch);
    }

    /*
     * Récupère le nom du fichier pour enregistrement BDD
     */
    private function getFileHash(UploadedFile $file): string
    {
        return hash_file('sha256', $file->getPathname(), false);
    }

    /**
     * @throws Exception
     *
     * Enregistre le nom du fichier en BDD pour éviter multi-import
     */
    protected function markFileAsImported(UploadedFile $file, Reseau $reseau): void
    {
        $this->em->getConnection()->insert('imported_files', [
            'filename' => $file->getClientOriginalName(),
            'file_hash' => $this->getFileHash($file),
            'imported_at' => new \DateTimeImmutable()->format('Y-m-d H:i:s'),
            'reseau_id' => $reseau->getId(),
        ]);
    }

}
