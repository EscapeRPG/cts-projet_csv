<?php

namespace App\Command;

use App\Service\Import\ImportHealthCheckService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:imports:health-check',
    description: 'Contrôle les imports quotidiens et historise les anomalies.'
)]
final class CheckImportHealthCommand extends Command
{
    public function __construct(private readonly ImportHealthCheckService $healthCheckService)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('date', null, InputOption::VALUE_REQUIRED, 'Date de référence YYYY-MM-DD.')
            ->addOption('no-notify', null, InputOption::VALUE_NONE, 'Ne crée pas de notification.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('[imports:health-check] Contrôle des imports quotidiens.');

        $dateOption = $input->getOption('date');
        $referenceDate = null;
        if (is_string($dateOption) && $dateOption !== '') {
            $referenceDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $dateOption);
            if (!$referenceDate instanceof \DateTimeImmutable) {
                $io->error('Option --date invalide. Exemple: 2026-06-26.');
                return Command::INVALID;
            }
        }

        $result = $this->healthCheckService->run($referenceDate, !(bool)$input->getOption('no-notify'));

        $io->success(sprintf(
            'Contrôle terminé. lignes=%d, alertes=%d, erreurs=%d, warnings=%d.',
            $result['rows'],
            $result['alerts'],
            $result['errors'],
            $result['warnings']
        ));

        return Command::SUCCESS;
    }
}
