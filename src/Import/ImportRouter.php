<?php

namespace App\Import;

use App\Interfaces\CsvImportInterface;

class ImportRouter
{
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

    public function getImporterForFile(string $filename): CsvImportInterface
    {
        $filename = strtolower($filename);

        return match (true) {
            str_contains($filename, 'centres_clients') => $this->centresClientsImport,
            str_contains($filename, 'clients_controles') => $this->clientsControlesImport,
            str_contains($filename, 'controles_non_factures') => $this->controlesNonFacturesImport,
            str_contains($filename, 'controles_factures') => $this->controlesFacturesImport,
            str_contains($filename, 'factures_reglements') => $this->facturesReglementsImport,
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
