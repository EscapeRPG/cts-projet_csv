<?php

namespace App\Command;

use App\Import\ImportRouter;
use App\Repository\ReseauRepository;
use App\Service\Import\SftpClient;
use Doctrine\DBAL\Connection;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Throwable;

#[AsCommand(
    name: 'app:import:sftp',
    description: 'Import CSV depuis le SFTP'
)]
/**
 * Imports CSV files from SFTP incoming folders with quality controls.
 */
class ImportSftpCommand extends Command
{
    /**
     * Folder-name aliases for SFTP imports.
     *
     * The SFTP folder name is not a stable functional identifier (it may be legacy),
     * while the DB network name can evolve. Keep imports robust by mapping old folder
     * names to the current DB network name.
     *
     * Keys/values must be normalized with normalizeReseauName().
     */
    private const array RESEAU_FOLDER_ALIASES = [
        // autosur folder -> secta network (DB renamed "autosur" => "secta")
        'autosur' => 'secta',
    ];
    /**
     * @param SftpClient $sftpClient SFTP client abstraction for file operations.
     * @param ImportRouter $importRouter Resolves the importer matching each file.
     * @param ReseauRepository $reseauRepository Repository used to validate network codes.
     */
    public function __construct(
        private readonly SftpClient   $sftpClient,
        private readonly ImportRouter $importRouter,
        private readonly ReseauRepository $reseauRepository,
        private readonly Connection $connection,
    )
    {
        parent::__construct();
    }

    /**
     * Configures quality-control command options.
     *
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->addOption(
                'max-ignored-rate',
                null,
                InputOption::VALUE_REQUIRED,
                'Taux max de lignes ignorées (en %), au-delà le fichier passe en erreur.',
                '1.0'
            )
            ->addOption(
                'min-rows-for-rate',
                null,
                InputOption::VALUE_REQUIRED,
                'Nombre minimal de lignes lues avant d’appliquer la règle de taux.',
                '100'
            )
            ->addOption(
                'strict',
                null,
                InputOption::VALUE_NONE,
                'Si activé, toute ligne ignorée provoque une erreur.'
            );
    }

    /**
     * Executes SFTP imports for each available network and incoming file.
     *
     * @param InputInterface $input Console input.
     * @param OutputInterface $output Console output.
     *
     * @return int Command exit status.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $failedFiles = 0;

        $maxIgnoredRateOption = $input->getOption('max-ignored-rate');
        $maxIgnoredRate = is_numeric($maxIgnoredRateOption) ? (float)$maxIgnoredRateOption : 1.0;
        if ($maxIgnoredRate < 0) {
            $maxIgnoredRate = 0.0;
        }

        $minRowsOption = $input->getOption('min-rows-for-rate');
        $minRowsForRate = is_numeric($minRowsOption) ? (int)$minRowsOption : 100;
        if ($minRowsForRate < 1) {
            $minRowsForRate = 1;
        }

        $strict = (bool)$input->getOption('strict');

        $io->title(
            '[app:import:sftp] Démarrage de l\'import des fichiers depuis le dossier sftp.',
        );

        $io->definitionList(
            ['Taux max ignoré (%)' => $maxIgnoredRate],
            ['Lignes min (pour appliquer le taux)' => $minRowsForRate],
            ['Strict' => $strict ? 'oui' : 'non'],
        );

        $activeReseaux = $this->reseauRepository->findBy(['isActive' => true]);
        $allReseaux = $this->reseauRepository->findAll();
        $activeReseauxByNormalizedName = [];
        $allReseauxByNormalizedName = [];

        foreach ($allReseaux as $reseau) {
            $normalized = $this->normalizeReseauName($reseau->getNom() ?? '');
            if ($normalized !== '') {
                $allReseauxByNormalizedName[$normalized] = $reseau;
            }
        }

        foreach ($activeReseaux as $activeReseau) {
            $normalized = $this->normalizeReseauName($activeReseau->getNom() ?? '');
            if ($normalized !== '') {
                $activeReseauxByNormalizedName[$normalized] = $activeReseau;
            }
        }

        foreach ($this->sftpClient->listReseaux() as $reseauCode) {
            $io->writeln("  <comment>Réseau :</comment> $reseauCode");

            // Vérifie que le réseau importé existe bel et bien (matching souple)
            $normalizedCode = $this->normalizeReseauName($reseauCode);
            $normalizedCode = self::RESEAU_FOLDER_ALIASES[$normalizedCode] ?? $normalizedCode;
            $reseau = $activeReseauxByNormalizedName[$normalizedCode] ?? null;

            if (!$reseau) {
                $reseau = $allReseauxByNormalizedName[$normalizedCode] ?? null;
                if ($reseau && !$reseau->isActive()) {
                    $io->writeln("<warning>Le réseau \"$reseauCode\" est inactif en base, import maintenu.</warning>");
                }
            }

            if (!$reseau) {
                $io->writeln("<warning>Le réseau \"$reseauCode\" est inconnu, n'existe pas ou est inactif.</warning>");
                $io->writeln('<comment>Réseaux actifs attendus :</comment> ' . implode(', ', array_map(
                    static fn($r) => $r->getNom() ?? '',
                    $activeReseaux
                )));
                continue;
            }

            foreach ($this->sftpClient->listIncomingFiles($reseauCode) as $file) {
                $io->writeln("  - $file");

                $path = $this->sftpClient->getIncomingPath($reseauCode) . '/' . $file;

                try {
                    if (!$this->sftpClient->isIncomingFileStable($reseauCode, $file)) {
                        $io->writeln("    <warning>Fichier non stable (upload en cours ou récent), reporté au prochain passage.</warning>");
                        continue;
                    }

                    $uploadedFile = new UploadedFile(
                        $path,
                        $file,
                        null,
                        null,
                        true
                    );

                    $importer = $this->importRouter->getImporterForFile($file);

                    if (method_exists($importer, 'setReseau')) {
                        $importer->setReseau($reseau);
                    }

                    $readCount = $importer->importFromFile($uploadedFile, $reseau);
                    $fileHash = method_exists($importer, 'getLastFileHash')
                        ? $importer->getLastFileHash()
                        : hash_file('sha256', $path, false);

                    if (method_exists($importer, 'getLastImportStats')) {
                        $stats = $importer->getLastImportStats();
                        $rowsRead = (int)($stats['rows_read'] ?? $readCount);
                        $rowsInserted = (int)($stats['rows_inserted'] ?? 0);
                        $rowsIgnored = (int)($stats['rows_ignored'] ?? 0);
                        $batchCount = (int)($stats['batches'] ?? 0);
                        $ignoredRate = $rowsRead > 0 ? ($rowsIgnored / $rowsRead) * 100 : 0.0;

                        $io->writeln(sprintf(
                            "    <info>Lignes lues:</info> %d, <info>insérées:</info> %d, <info>ignorées:</info> %d, <info>lots:</info> %d. <info>Taux ignoré:</info> %.3f%%",
                            $rowsRead,
                            $rowsInserted,
                            $rowsIgnored,
                            $batchCount,
                            $ignoredRate
                        ));

                        if ($strict && $rowsIgnored > 0) {
                            throw new RuntimeException(sprintf(
                                'Mode strict: %d lignes ignorées détectées.',
                                $rowsIgnored
                            ));
                        }

                        if (!$strict && $rowsRead >= $minRowsForRate && $ignoredRate > $maxIgnoredRate) {
                            throw new RuntimeException(sprintf(
                                'Taux de lignes ignorées trop élevé: %.3f%% > %.3f%% (lues=%d, ignorées=%d).',
                                $ignoredRate,
                                $maxIgnoredRate,
                                $rowsRead,
                                $rowsIgnored
                            ));
                        }
                    } else {
                        $io->writeln(sprintf("    <info>Lignes lues:</info> %d", $readCount));
                    }

                    if (!is_string($fileHash) || $fileHash === '') {
                        throw new RuntimeException('Impossible de calculer le hash du fichier.');
                    }

                    $archivePath = $this->sftpClient->archiveProcessedVersion($reseauCode, $file, $fileHash);
                    if ($archivePath === null) {
                        throw new RuntimeException('Impossible d’archiver la version dans processed.');
                    }

                    $importedFileId = method_exists($importer, 'getLastImportedFileId')
                        ? $importer->getLastImportedFileId()
                        : null;
                    if (is_int($importedFileId) && $importedFileId > 0) {
                        $this->connection->update('imported_files', ['archive_path' => $archivePath], ['id' => $importedFileId]);
                    } elseif (method_exists($importer, 'wasLastFileSkipped') && $importer->wasLastFileSkipped()) {
                        $existingId = $this->connection->fetchOne(
                            'SELECT id FROM imported_files WHERE filename = :filename AND file_hash = :hash AND reseau_id = :reseau ORDER BY id DESC LIMIT 1',
                            ['filename' => $file, 'hash' => $fileHash, 'reseau' => $reseau->getId()]
                        );
                        if ($existingId !== false) {
                            $this->connection->update('imported_files', ['archive_path' => $archivePath], ['id' => (int)$existingId]);
                        }
                    }

                    if (method_exists($importer, 'wasLastFileSkipped') && $importer->wasLastFileSkipped()) {
                        $io->writeln('    <comment>Doublon exact déjà archivé, import ignoré.</comment>');
                    }
                    if (method_exists($importer, 'wasLastFileCorrection') && $importer->wasLastFileCorrection()) {
                        $io->warning('Version corrective détectée (même nom, hash différent). Une reconstruction par app:imports:replay est nécessaire pour appliquer d’éventuelles suppressions de lignes.');
                    }
                } catch (Throwable $e) {
                    $failedFiles++;
                    $io->error("<error>Erreur : {$e->getMessage()}</error>");

                    // Déplace vers error
                    $this->sftpClient->moveToErrorSafe($reseauCode, $file);
                }
            }
        }

        if ($failedFiles > 0) {
            $io->error(sprintf('%d fichier(s) en erreur. Le pipeline est interrompu.', $failedFiles));
            return Command::FAILURE;
        }

        $io->success('Import des fichiers terminé.');

        return Command::SUCCESS;
    }

    /**
     * Normalizes a network name to a canonical alphanumeric form for matching.
     *
     * @param string $value Raw network name.
     *
     * @return string Normalized network key.
     */
    private function normalizeReseauName(string $value): string
    {
        $value = trim($value);
        $value = str_replace(['_', '-'], ' ', $value);

        if (function_exists('iconv')) {
            $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if ($converted !== false) {
                $value = $converted;
            }
        }

        $value = strtolower($value);
        return preg_replace('/[^a-z0-9]+/', '', $value) ?? $value;
    }
}
