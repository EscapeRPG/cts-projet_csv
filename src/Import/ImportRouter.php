<?php

namespace App\Import;

use App\Interfaces\CsvImportInterface;

/**
 * Resolves the import service to use based on an uploaded file name.
 */
class ImportRouter
{
    /**
     * @param CsvImportInterface $centresClientsImport Import service for `centres_clients` files.
     * @param CsvImportInterface $clientsImport Import service for `clients` files.
     * @param CsvImportInterface $clientsControlesImport Import service for `clients_controles` files.
     * @param CsvImportInterface $controlesImport Import service for `controles` files.
     * @param CsvImportInterface $controlesFacturesImport Import service for `controles_factures` files.
     * @param CsvImportInterface $controlesNonFacturesImport Import service for `controles_non_factures` files.
     * @param CsvImportInterface $facturesImport Import service for `factures` files.
     * @param CsvImportInterface $facturesReglementsImport Import service for `factures_reglements` files.
     * @param CsvImportInterface $prestasNonFactureesImport Import service for `prestas_non_facturees` files.
     * @param CsvImportInterface $reglementsImport Import service for `reglements` files.
     */
    public function __construct(
        private CsvImportInterface $centresClientsImport,
        private CsvImportInterface $clientsImport,
        private CsvImportInterface $clientsControlesImport,
        private CsvImportInterface $controlesImport,
        private CsvImportInterface $controlesFacturesImport,
        private CsvImportInterface $controlesNonFacturesImport,
        private CsvImportInterface $facturesImport,
        private CsvImportInterface $facturesReglementsImport,
        private CsvImportInterface $prestasNonFactureesImport,
        private CsvImportInterface $reglementsImport,
    ) {
    }

    /**
     * Returns the matching import service for a given file name.
     *
     * @param string $filename Uploaded file name.
     *
     * @return CsvImportInterface Matching CSV import service.
     *
     * @throws \RuntimeException When no importer matches the provided file name.
     */
    public function getImporterForFile(string $filename): CsvImportInterface
    {
        $filename = strtolower($filename);

        return match (true) {
            str_contains($filename, 'centres_clients') => $this->centresClientsImport,
            str_contains($filename, 'clients_controles') => $this->clientsControlesImport,
            str_contains($filename, 'controles_non_factures') => $this->controlesNonFacturesImport,
            str_contains($filename, 'controles_factures') => $this->controlesFacturesImport,
            str_contains($filename, 'factures_reglements') => $this->facturesReglementsImport,
            str_contains($filename, 'prestas_non_factures') => $this->prestasNonFactureesImport,
            str_contains($filename, 'prestas_non_facturees') => $this->prestasNonFactureesImport,
            str_contains($filename, 'controles') => $this->controlesImport,
            str_contains($filename, 'factures') => $this->facturesImport,
            str_contains($filename, 'clients') => $this->clientsImport,
            str_contains($filename, 'reglements') => $this->reglementsImport,

            default => throw new \RuntimeException(
                "Type de fichier non reconnu : $filename"
            ),
        };
    }
}
