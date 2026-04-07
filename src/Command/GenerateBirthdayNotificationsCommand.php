<?php

namespace App\Command;

use App\Service\Notification\BirthdayNotificationGenerator;
use DateMalformedStringException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:notifications:birthdays',
    description: 'Génère des notifications pour les anniversaires de salariés à venir.'
)]
/**
 * Generates notifications for upcoming employee birthdays.
 */
final class GenerateBirthdayNotificationsCommand extends Command
{
    /**
     * Configures command options.
     */
    protected function configure(): void
    {
        $this
            ->addOption(
                'date',
                null,
                InputOption::VALUE_REQUIRED,
                'Date de référence au format YYYY-MM-DD.',
            )
            ->addOption(
                'days',
                null,
                InputOption::VALUE_REQUIRED,
                'Nombre de jours à venir à inspecter.',
                BirthdayNotificationGenerator::DEFAULT_DAYS_AHEAD
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Simule la génération sans enregistrer en base.',
            );
    }

    /**
     * @param BirthdayNotificationGenerator $generator Generates birthday notifications.
     */
    public function __construct(private readonly BirthdayNotificationGenerator $generator)
    {
        parent::__construct();
    }

    /**
     * Executes birthday notification generation.
     *
     * @param InputInterface $input Console input.
     * @param OutputInterface $output Console output.
     *
     * @return int Command exit status.
     *
     * @throws DateMalformedStringException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('[notifications:birthdays] Démarrage de la création automatique de notification(s) d\'anniversaire(s).');

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
