<?php

namespace App\Command;

use App\Import\ImportRouter;
use App\Repository\ReseauRepository;
use App\Service\Import\SftpClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

#[AsCommand(
    name: 'app:import:sftp',
    description: 'Import CSV depuis le SFTP'
)]
class ImportSftpCommand extends Command
{
    public function __construct(
        private SftpClient $sftpClient,
        private ImportRouter $importRouter,
        private ReseauRepository $reseauRepository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        foreach ($this->sftpClient->listReseaux() as $reseauCode) {
            $output->writeln("Réseau : $reseauCode");

            $reseau = $this->reseauRepository->findOneBy([
                'nom' => $reseauCode,
                'isActive' => true
            ]);

            if (!$reseau) {
                $output->writeln("<comment>Le réseau $reseauCode est inconnu, n'existe pas ou est inactif !</comment>");
                continue;
            }

            foreach ($this->sftpClient->listIncomingFiles($reseauCode) as $file) {
                $output->writeln("  - $file");

                $path = $this->sftpClient->getIncomingPath($reseauCode) . '/' . $file;

                try {
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

                    $importer->importFromFile($uploadedFile, $reseau);

                    // Si moveToProcessed échoue, on lève une exception
                    if (!$this->sftpClient->moveToProcessed($reseauCode, $file)) {
                        throw new \RuntimeException("Impossible de déplacer vers processed");
                    }

                } catch (\Throwable $e) {

                    $output->writeln("<error>Erreur : {$e->getMessage()}</error>");

                    // ici on déplace vers error *depuis le dossier où le fichier se trouve réellement*
                    $this->sftpClient->moveToErrorSafe($reseauCode, $file);
                }
            }
        }

        return Command::SUCCESS;
    }
}
