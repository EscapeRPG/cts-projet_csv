<?php

namespace App\Service\Import;

use App\Entity\Reseau;
use App\Import\CsvReader;
use App\Interfaces\CsvImportInterface;
use App\Utils\DateParser;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Base implementation for CSV import services with batching and deduplication.
 */
abstract class AbstractCsvImportService implements CsvImportInterface
{
    protected int $batchSize = 500;
    protected ?Reseau $reseau = null;
    protected array $lastImportStats = [
        'rows_read' => 0,
        'rows_inserted' => 0,
        'rows_ignored' => 0,
        'batches' => 0,
    ];

    /**
     * @param EntityManagerInterface $em Entity manager used for low-level insert operations.
     * @param CsvReader $csvReader CSV reader used to stream rows from uploaded files.
     */
    public function __construct(
        protected EntityManagerInterface $em,
        protected CsvReader              $csvReader
    )
    {
    }

    /**
     * Sets the current network context for mapping imported rows.
     *
     * @param Reseau $reseau Current network.
     *
     * @return void
     */
    public function setReseau(Reseau $reseau): void
    {
        $this->reseau = $reseau;
    }

    /**
    * Returns the target database table name.
    *
    * @return string Table name.
    */
    abstract protected static function getTableName(): string;

    /**
     * Returns target table columns in insertion order.
     *
     * @return array<int, string> Table column names.
     */
    abstract protected static function getColumns(): array;

    /**
     * Returns unique key columns for the target table.
     *
     * @return array<int, string> Unique key column names.
     */
    abstract protected static function getUniqueKeys(): array;

    /**
     * Returns CSV-to-database column mapping.
     *
     * @return array<string, array<int, string>> Mapping by DB column name.
     */
    abstract protected static function getColumnMapping(): array;

    /**
     * Returns date/time columns requiring parsing.
     *
     * @return array<int, string> Date/time column names.
     */
    abstract protected static function getDateColumns(): array;

    /**
     * Returns decimal columns requiring decimal separator normalization.
     *
     * @return array<int, string> Decimal column names.
     */
    abstract protected static function getDecimalColumns(): array;

    /**
     * Imports a CSV file into the target table.
     *
     * @param UploadedFile $file Uploaded CSV file.
     * @param Reseau $reseau Network context associated with the file.
     *
     * @return int Number of rows read from the CSV.
     *
     * @throws Exception
     */
    public function importFromFile(UploadedFile $file, Reseau $reseau): int
    {
        $this->em->getConnection()->getConfiguration()->setMiddlewares([]);
        $this->lastImportStats = [
            'rows_read' => 0,
            'rows_inserted' => 0,
            'rows_ignored' => 0,
            'batches' => 0,
        ];

        if ($this->shouldSkipFile($file, $reseau)) {
            return 0;
        }

        $generator = $this->csvReader->read($file, ';', $reseau->getNom());

        $count = 0;
        $batch = [];
        $insertedTotal = 0;
        $ignoredTotal = 0;
        $batchCount = 0;

        foreach ($generator as $row) {
            $row = $this->mapRow($row);

            $batch[] = $row;
            $count++;

            if (count($batch) >= $this->batchSize) {
                $inserted = $this->insertBatch($batch);
                $insertedTotal += $inserted;
                $ignoredTotal += count($batch) - $inserted;
                $batchCount++;
                $batch = [];
            }
        }

        if (!empty($batch)) {
            $inserted = $this->insertBatch($batch);
            $insertedTotal += $inserted;
            $ignoredTotal += count($batch) - $inserted;
            $batchCount++;
        }

        $this->markFileAsImported($file, $reseau);
        $this->lastImportStats = [
            'rows_read' => $count,
            'rows_inserted' => $insertedTotal,
            'rows_ignored' => $ignoredTotal,
            'batches' => $batchCount,
        ];

        return $count;
    }

    /**
     * Maps one CSV row to ordered table values.
     *
     * @param array<string, mixed> $data Raw CSV row.
     *
     * @return array<int, mixed> Ordered values matching table columns.
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
     * Checks whether a file was already imported for a given network.
     *
     * @param UploadedFile $file Uploaded file.
     * @param Reseau $reseau Network context.
     *
     * @return bool True when the file should be skipped.
     *
     * @throws Exception
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
     * Inserts one batch using an `INSERT IGNORE` statement.
     *
     * @param array<int, array<int, mixed>> $batch Prepared rows batch.
     *
     * @return int Number of rows effectively inserted.
     *
     * @throws Exception
     */
    protected function insertBatch(array $batch): int
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

        $inserted = (int)$this->em->getConnection()->executeStatement($sql);

        unset($values, $sql, $batch);

        return $inserted;
    }

    /**
     * Builds a stable hash of a CSV file content.
     *
     * @param UploadedFile $file Uploaded file.
     *
     * @return string SHA-256 file hash.
     */
    private function getFileHash(UploadedFile $file): string
    {
        return hash_file('sha256', $file->getPathname(), false);
    }

    /**
     * Records a successful file import to prevent duplicate imports.
     *
     * @param UploadedFile $file Uploaded file.
     * @param Reseau $reseau Network context.
     *
     * @return void
     *
     * @throws Exception
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

    /**
     * Returns stats from the latest import execution.
     *
     * @return array<string, int> Import statistics.
     */
    public function getLastImportStats(): array
    {
        return $this->lastImportStats;
    }

}
