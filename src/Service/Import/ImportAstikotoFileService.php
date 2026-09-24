<?php

namespace App\Service\Import;

use App\Entity\Centre;
use App\Entity\Reseau;
use App\Enum\TypeCentre;
use Doctrine\DBAL\Exception;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Imports internal Astikoto sales exported by numbered portiques.
 */
final class ImportAstikotoFileService extends AbstractCsvImportService
{
    private ?Centre $centre = null;
    private int $dataRowNumber = 0;

    protected static function getTableName(): string
    {
        return 'portique_importe';
    }

    protected static function getColumns(): array
    {
        return [
            'centre_id',
            'date',
            'heure',
            'service_vendu',
            'jetons',
            'jetons_gratuits',
            'recharge_cle',
            'recharge_cle_offerte',
            'cles',
            'prix_total',
            'supplement',
            'ticket_promo',
            'code_remise',
            'ticket_rembt',
            'paye_cle',
            'paye_jetons1',
            'paye_jetons2',
            'paye_pieces',
            'paye_billets',
            'paye_cb',
            'paye_total',
            'mode',
            'rendu',
            'trop_percu',
            'non_distribue',
            'credit_cle',
            'num_cle',
            'num_portique',
        ];
    }

    protected static function getColumnMapping(): array
    {
        return [
            'supplement' => ['avec_suppl'],
            'paye_jetons1' => ['paye_jetons_1'],
            'paye_jetons2' => ['paye_jetons_2'],
            'non_distribue' => ['non_distri'],
        ];
    }

    protected static function getDateColumns(): array
    {
        return ['date', 'heure'];
    }

    protected static function getDecimalColumns(): array
    {
        return [
            'recharge_cle',
            'recharge_cle_offerte',
            'prix_total',
            'supplement',
            'ticket_promo',
            'ticket_rembt',
            'paye_cle',
            'paye_jetons1',
            'paye_jetons2',
            'paye_pieces',
            'paye_billets',
            'paye_cb',
            'paye_total',
            'rendu',
            'trop_percu',
            'non_distribue',
            'credit_cle',
        ];
    }

    protected function getSourceEncoding(): string
    {
        return 'Windows-1252';
    }

    protected function getHeaderLinesToSkip(): int
    {
        return 1;
    }

    protected function prepareRow(array $row, UploadedFile $file): ?array
    {
        if (array_all($row, static fn (mixed $value): bool => trim(str_replace(
            ["\u{00A0}", "\u{202F}", "\u{FEFF}"],
            '',
            (string) $value,
        )) === '')) {
            return null;
        }

        ++$this->dataRowNumber;
        if (!array_key_exists('date', $row)) {
            throw new \RuntimeException('Colonne « Date » introuvable dans les en-têtes du CSV. Vérifiez la ligne de titre et le séparateur « ; ».');
        }
        if (trim((string) $row['date']) === '') {
            throw new \RuntimeException(sprintf(
                'Date absente dans la ligne de données n° %d (hors en-têtes et lignes vides). Aucune donnée de ce fichier n’a été enregistrée.',
                $this->dataRowNumber,
            ));
        }
        if ($this->centre === null || $this->centre->getId() === null) {
            throw new \LogicException('Aucune station de lavage valide n’a été sélectionnée.');
        }

        $row['centre_id'] = $this->centre->getId();

        foreach (['jetons', 'jetons_gratuits', 'cles'] as $column) {
            $value = trim((string) ($row[$column] ?? ''));
            $row[$column] = $value === '' ? null : (int) $value;
        }

        $row['num_portique'] = $this->extractPortiqueNumber(
            $file->getClientOriginalName()
        );

        return $row;
    }

    public function importFromFileForCentre(
        UploadedFile $file,
        Reseau $reseau,
        Centre $centre,
    ): int {
        if ($centre->getType() !== TypeCentre::STATION_LAVAGE) {
            throw new \InvalidArgumentException('Le centre sélectionné n’est pas une station de lavage.');
        }

        $this->centre = $centre;
        $this->dataRowNumber = 0;

        try {
            return $this->em->getConnection()->transactional(
                fn (): int => parent::importFromFile($file, $reseau),
            );
        } finally {
            $this->centre = null;
        }
    }

    /**
     * Astikoto filenames are only unique within a station.
     *
     * @throws Exception
     */
    protected function shouldSkipFile(UploadedFile $file, Reseau $reseau): bool
    {
        if ($this->centre?->getId() === null) {
            throw new \LogicException('Aucune station de lavage valide n’a été sélectionnée.');
        }

        return (bool) $this->em->getConnection()->fetchOne(
            <<<'SQL'
                SELECT 1
                FROM imported_files
                WHERE filename = :name
                  AND reseau_id = :reseau
                  AND centre_id = :centre
                SQL,
            [
                'name' => $file->getClientOriginalName(),
                'reseau' => $reseau->getId(),
                'centre' => $this->centre->getId(),
            ],
        );
    }

    /**
     * @throws Exception
     */
    protected function markFileAsImported(UploadedFile $file, Reseau $reseau): void
    {
        if ($this->centre?->getId() === null) {
            throw new \LogicException('Aucune station de lavage valide n’a été sélectionnée.');
        }

        $this->em->getConnection()->insert('imported_files', [
            'filename' => $file->getClientOriginalName(),
            'file_hash' => hash_file('sha256', $file->getPathname(), false),
            'imported_at' => new \DateTimeImmutable()->format('Y-m-d H:i:s'),
            'reseau_id' => $reseau->getId(),
            'centre_id' => $this->centre->getId(),
        ]);
    }

    private function extractPortiqueNumber(string $filename): int
    {
        if (preg_match('/\bportique[\s_-]+(\d+)\b/i', $filename, $matches) !== 1) {
            throw new \RuntimeException(sprintf(
                'Numéro de portique introuvable dans le nom du fichier « %s ».',
                $filename
            ));
        }

        return (int) $matches[1];
    }
}
