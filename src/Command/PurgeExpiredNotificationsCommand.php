<?php

namespace App\Command;

use App\Service\Notification\NotificationCleanupService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:notifications:purge-expired',
    description: 'Supprime les notifications expirées et leurs états de remise par utilisateur.'
)]
/**
 * Removes expired notifications and their per-user delivery states.
 */
final class PurgeExpiredNotificationsCommand extends Command
{
    /**
     * @param NotificationCleanupService $notificationCleanupService Notification purge service.
     */
    public function __construct(private readonly NotificationCleanupService $notificationCleanupService)
    {
        parent::__construct();
    }

    /**
     * Configures command options.
     */
    protected function configure(): void
    {
        $this
            ->addOption(
                'before',
                null,
                InputOption::VALUE_REQUIRED,
                'Date/heure limite au format YYYY-MM-DD HH:MM:SS (par défaut: maintenant).'
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Compte les notifications expirées sans les supprimer.'
            );
    }

    /**
     * Executes the expired notifications purge.
     *
     * @param InputInterface $input Console input.
     * @param OutputInterface $output Console output.
     *
     * @return int Command exit status.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('[purge-expired] Démarrage du nettoyage des notifications obsolètes.');

        $beforeOption = $input->getOption('before');
        $dryRun = (bool) $input->getOption('dry-run');

        $before = null;
        if (is_string($beforeOption) && $beforeOption !== '') {
            $before = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $beforeOption);
            if (!$before instanceof \DateTimeImmutable || $before->format('Y-m-d H:i:s') !== $beforeOption) {
                $io->error('L\'option --before doit être au format YYYY-MM-DD HH:MM:SS.');

                return Command::INVALID;
            }
        }

        $result = $this->notificationCleanupService->purgeExpired($before, $dryRun);

        $message = $dryRun
            ? 'Simulation terminée. %d notification(s) expirée(s) détectée(s).'
            : 'Purge terminée. %d notification(s) expirée(s), %d notification(s) supprimée(s), %d remise(s) utilisateur supprimée(s).';

        $io->success(sprintf(
            $message,
            $result['expired_notifications'],
            $result['deleted_notifications'],
            $result['deleted_user_notifications']
        ));

        return Command::SUCCESS;
    }
}
