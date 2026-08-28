<?php

namespace App\Command;

use App\Import\ImportRouter;
use App\Repository\ReseauRepository;
use App\Service\Import\SftpClient;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpFoundation\File\UploadedFile;

#[AsCommand(name: 'app:imports:replay', description: 'Rejoue les versions actives archivées sans modifier l’historique imported_files.')]
final class ReplayArchivedImportsCommand extends Command
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ReseauRepository $reseauRepository,
        private readonly ImportRouter $importRouter,
        private readonly SftpClient $sftpClient,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('reseau', null, InputOption::VALUE_REQUIRED, 'Nom exact du réseau à rejouer.')
            ->addOption('execute', null, InputOption::VALUE_NONE, 'Exécute réellement le rejeu.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $reseauName = trim((string)$input->getOption('reseau'));
        $reseau = $this->reseauRepository->findOneBy(['nom' => $reseauName]);
        if ($reseau === null) {
            $io->error('Réseau introuvable. Utilisez son nom exact en base.');
            return Command::INVALID;
        }

        $versions = $this->connection->fetchAllAssociative(
            "SELECT filename, file_hash, archive_path FROM imported_files WHERE reseau_id = :reseau AND centre_id IS NULL AND is_active = 1 AND status = 'active' ORDER BY filename",
            ['reseau' => $reseau->getId()]
        );
        $execute = (bool)$input->getOption('execute');
        $io->definitionList(['Réseau' => $reseauName], ['Versions actives' => count($versions)], ['Mode' => $execute ? 'EXÉCUTION' : 'APERÇU']);
        if (!$execute) {
            $io->note('Aucune donnée modifiée. Relancez avec --execute après avoir purgé les tables métier voulues.');
            return Command::SUCCESS;
        }

        foreach ($versions as $version) {
            $relativePath = trim((string)($version['archive_path'] ?? ''));
            $path = $relativePath !== ''
                ? $this->sftpClient->resolveArchivedPath($relativePath)
                : $this->sftpClient->findLegacyProcessedPath((string)$version['filename'], (string)$version['file_hash']);
            if ($path === null || !hash_equals(strtolower((string)$version['file_hash']), strtolower((string)hash_file('sha256', $path)))) {
                $io->error(sprintf('Archive absente ou hash invalide : %s', $version['filename']));
                return Command::FAILURE;
            }

            $importer = $this->importRouter->getImporterForFile((string)$version['filename']);
            if (!method_exists($importer, 'setReplayMode')) {
                $io->error('L’importeur ne prend pas en charge le mode rejeu.');
                return Command::FAILURE;
            }

            $importer->setReplayMode(true);
            try {
                $importer->importFromFile(new UploadedFile($path, (string)$version['filename'], null, null, true), $reseau);
            } finally {
                $importer->setReplayMode(false);
            }
            $io->writeln(sprintf('<info>Rejoué :</info> %s', $version['filename']));
        }

        $io->success(sprintf('%d version(s) active(s) rejouée(s). Lancez ensuite les commandes de synthèse.', count($versions)));
        return Command::SUCCESS;
    }
}
