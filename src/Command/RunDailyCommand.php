<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:daily:run',
    description: 'Runs import and synthesis commands sequentially.'
)]
/**
 * Orchestrates the daily import and synthesis pipeline.
 */
final class RunDailyCommand extends Command
{
    /**
     * @var array<int, string> Ordered list of console commands to run.
     */
    private const array COMMAND_CHAIN = [
        'app:import:sftp',
        'app:synthese:summary',
        'app:synthese:pros',
    ];

    /**
     * Executes the pipeline commands in order and stops on first failure.
     *
     * @param InputInterface $input Console input.
     * @param OutputInterface $output Console output.
     *
     * @return int Command exit status.
     * @throws ExceptionInterface
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $application = $this->getApplication();
        if ($application === null) {
            $output->writeln('<error>[pipeline] Console application is not available.</error>');
            return Command::FAILURE;
        }

        $output->writeln('<info>[pipeline] Starting command chain...</info>');

        foreach (self::COMMAND_CHAIN as $commandName) {
            $output->writeln(sprintf('<comment>[pipeline] Running %s...</comment>', $commandName));

            $command = $application->find($commandName);
            $commandInput = new ArrayInput(['command' => $commandName]);
            $commandInput->setInteractive(false);

            $exitCode = $command->run($commandInput, $output);

            if ($exitCode !== Command::SUCCESS) {
                $output->writeln(sprintf(
                    '<error>[pipeline] Command %s failed with exit code %d.</error>',
                    $commandName,
                    $exitCode
                ));

                return $exitCode;
            }
        }

        $output->writeln('<info>[pipeline] All commands completed successfully.</info>');

        return Command::SUCCESS;
    }
}
