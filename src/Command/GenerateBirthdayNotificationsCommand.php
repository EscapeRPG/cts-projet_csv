<?php

namespace App\Command;

use App\Service\Notification\BirthdayNotificationGenerator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:notifications:birthdays',
    description: 'Generates notifications for upcoming employee birthdays.'
)]
final class GenerateBirthdayNotificationsCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption(
                'date',
                null,
                InputOption::VALUE_REQUIRED,
                'Reference date in YYYY-MM-DD format.',
            )
            ->addOption(
                'days',
                null,
                InputOption::VALUE_REQUIRED,
                'Number of days ahead to inspect.',
                BirthdayNotificationGenerator::DEFAULT_DAYS_AHEAD
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Simulates notification generation without persisting changes.',
            );
    }

    public function __construct(private readonly BirthdayNotificationGenerator $generator)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dateOption = $input->getOption('date');
        $daysOption = $input->getOption('days');
        $dryRun = (bool) $input->getOption('dry-run');

        if (!is_numeric((string) $daysOption) || (int) $daysOption < 0) {
            $io->error('L\'option --days doit être un entier positif ou nul.');

            return Command::INVALID;
        }

        $referenceDate = null;
        if (is_string($dateOption) && $dateOption !== '') {
            $referenceDate = \DateTimeImmutable::createFromFormat('Y-m-d', $dateOption);
            if (!$referenceDate instanceof \DateTimeImmutable || $referenceDate->format('Y-m-d') !== $dateOption) {
                $io->error('L\'option --date doit être au format YYYY-MM-DD.');

                return Command::INVALID;
            }
        }

        $result = $this->generator->generate($referenceDate, (int) $daysOption, $dryRun);

        $io->success(sprintf(
            'Traitement terminé. %d salarié(s) inspecté(s), %d anniversaire(s) trouvé(s), %d notification(s) créée(s), %d destinataire(s) ajouté(s).',
            $result['scanned'],
            $result['matched'],
            $result['notifications_created'],
            $result['recipients_created']
        ));

        return Command::SUCCESS;
    }
}
