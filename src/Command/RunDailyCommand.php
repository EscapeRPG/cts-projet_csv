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
     * Ordered list of console commands to run.
     *
     * @var array<int, array{command:string,args:array<string, mixed>}>
     */
    private const array COMMAND_CHAIN = [
        [
            'command' => 'app:db:ensure-indexes',
            'args' => [],
        ],
        [
            'command' => 'app:import:sftp',
            'args' => [],
        ],
        [
            'command' => 'app:synthese:summary',
            'args' => [],
        ],
        [
            'command' => 'app:synthese:pros',
            'args' => [],
        ],
        [
            'command' => 'app:notifications:purge-expired',
            'args' => [],
        ],
        [
            'command' => 'app:data:purge-centres',
            'args' => [
                '--execute' => true,
            ],
        ],
        [
            'command' => 'app:cts:sync-salarie-centres',
            'args' => [
                '--execute' => true,
            ],
        ],
        [
            'command' => 'app:notifications:birthdays',
            'args' => [],
        ],
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

        foreach (self::COMMAND_CHAIN as $step) {
            $commandName = $step['command'];
            $commandArgs = $step['args'];

            $display = $commandName;
            if ($commandArgs !== []) {
                $display .= ' ' . implode(' ', array_keys($commandArgs));
            }

            $output->writeln(sprintf('<comment>[pipeline] Running %s...</comment>', $display));

            $command = $application->find($commandName);
            $commandInput = new ArrayInput([
                'command' => $commandName,
                ...$commandArgs,
            ]);
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
