<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

#[AsCommand(
    name: 'app:daily:run',
    description: 'Exécute les commandes d’import et de synthèse de manière séquentielle.'
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
            'command' => 'app:synthese:reglements',
            'args' => [],
        ],
        [
            'command' => 'app:imports:health-check',
            'args' => [],
        ],
        [
            'command' => 'app:data:purge-centres',
            'args' => [
                '--execute' => true,
            ],
        ],
        [
            'command' => 'app:data:purge-societes',
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
            'command' => 'app:notifications:purge-expired',
            'args' => [
                '--before' => true,
            ],
        ],
        [
            'command' => 'app:notifications:birthdays',
            'args' => [],
        ],
        [
            'command' => 'cache:pool:clear',
            'args' => [
                '--all' => true,
            ],
        ],
        [
            'command' => 'cache:clear',
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
            $output->writeln('<error>[pipeline] L’application console n’est pas disponible.</error>');
            return Command::FAILURE;
        }

        $output->writeln('<info>[pipeline] Démarrage de la chaîne de commandes...</info>');

        foreach (self::COMMAND_CHAIN as $step) {
            $commandName = $step['command'];
            $commandArgs = $step['args'];

            $display = $commandName;
            if ($commandArgs !== []) {
                $display .= ' ' . implode(' ', array_keys($commandArgs));
            }

            $output->writeln(sprintf('<comment>[pipeline] Exécution de %s...</comment>', $display));

            if (str_starts_with($commandName, 'cache:')) {
                if ($commandName === 'cache:clear') {
                    $this->scheduleCacheClearAfterParentExit($commandArgs, $output);
                    return Command::SUCCESS;
                }

                $exitCode = $this->runCommandInSeparateProcess($commandName, $commandArgs, $output);

                if ($exitCode !== Command::SUCCESS) {
                    $output->writeln(sprintf(
                        '<error>[pipeline] La commande %s a échoué avec le code de sortie %d.</error>',
                        $commandName,
                        $exitCode
                    ));

                    return $exitCode;
                }

                continue;
            }

            $command = $application->find($commandName);
            $commandInput = new ArrayInput([
                'command' => $commandName,
                ...$commandArgs,
            ]);
            $commandInput->setInteractive(false);

            $exitCode = $command->run($commandInput, $output);

            if ($exitCode !== Command::SUCCESS) {
                $output->writeln(sprintf(
                    '<error>[pipeline] La commande %s a échoué avec le code de sortie %d.</error>',
                    $commandName,
                    $exitCode
                ));

                return $exitCode;
            }
        }

        $output->writeln('<info>[pipeline] Toutes les commandes se sont terminées avec succès.</info>');

        return Command::SUCCESS;
    }

    /**
     * Runs memory-sensitive framework commands in a fresh PHP process.
     *
     * @param string $commandName Console command name.
     * @param array<string, mixed> $commandArgs Console command arguments/options.
     * @param OutputInterface $output Console output.
     *
     * @return int Process exit code.
     */
    private function runCommandInSeparateProcess(string $commandName, array $commandArgs, OutputInterface $output): int
    {
        $projectDir = dirname(__DIR__, 2);
        $processArgs = [
            PHP_BINARY,
            $projectDir . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'console',
            $commandName,
            '--no-debug',
            '--env=' . ($_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? 'dev'),
            ...$this->buildProcessArguments($commandArgs),
        ];

        $process = new Process($processArgs, $projectDir, [
            'APP_DEBUG' => '0',
        ]);
        $process->setTimeout(null);

        return $process->run(static function (string $type, string $buffer) use ($output): void {
            $output->write($buffer);
        });
    }

    /**
     * Defers cache:clear until this Symfony process has released its current container files.
     *
     * @param array<string, mixed> $commandArgs Console command arguments/options.
     * @param OutputInterface $output Console output.
     *
     * @return void
     */
    private function scheduleCacheClearAfterParentExit(array $commandArgs, OutputInterface $output): void
    {
        $projectDir = dirname(__DIR__, 2);
        $logDir = $projectDir . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'log';
        $logPath = $logDir . DIRECTORY_SEPARATOR . 'daily-cache-clear.log';
        $env = $_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? 'dev';
        $console = $projectDir . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'console';
        $processArgs = [
            PHP_BINARY,
            $console,
            'cache:clear',
            '--no-debug',
            '--env=' . $env,
            ...$this->buildProcessArguments($commandArgs),
        ];

        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }

        file_put_contents(
            $logPath,
            sprintf("[%s] cache:clear planifié après app:daily:run.\n", date('Y-m-d H:i:s'))
        );

        if (DIRECTORY_SEPARATOR === '\\') {
            $powershellCommand = sprintf(
                "Start-Sleep -Seconds 2; Set-Location -LiteralPath %s; \$env:APP_DEBUG = '0'; & %s %s *> %s; exit \$LASTEXITCODE",
                $this->quotePowerShellString($projectDir),
                $this->quotePowerShellString(PHP_BINARY),
                implode(' ', array_map([$this, 'quotePowerShellString'], array_slice($processArgs, 1))),
                $this->quotePowerShellString($logPath)
            );
            $deferredCommand = sprintf(
                'cmd /C start "" /B powershell -NoProfile -WindowStyle Hidden -Command %s',
                escapeshellarg($powershellCommand)
            );
        } else {
            $commandLine = implode(' ', array_map('escapeshellarg', $processArgs));
            $deferredCommand = sprintf(
                'APP_DEBUG=0 sh -c %s > /dev/null 2>&1 &',
                escapeshellarg(
                    'sleep 2; cd '
                    . escapeshellarg($projectDir)
                    . ' && '
                    . $commandLine
                    . ' > '
                    . escapeshellarg($logPath)
                    . ' 2>&1'
                )
            );
        }

        pclose(popen($deferredCommand, 'r'));
        $output->writeln(sprintf(
            '<info>[pipeline] cache:clear planifié dans un process détaché. Log: %s</info>',
            $logPath
        ));
    }

    /**
     * Quotes a string literal for a PowerShell command.
     *
     * @param string $value Raw value.
     *
     * @return string PowerShell single-quoted string.
     */
    private function quotePowerShellString(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }

    /**
     * Converts ArrayInput-style arguments to CLI process arguments.
     *
     * @param array<string, mixed> $commandArgs Console command arguments/options.
     *
     * @return array<int, string>
     */
    private function buildProcessArguments(array $commandArgs): array
    {
        $processArgs = [];

        foreach ($commandArgs as $name => $value) {
            if ($value === false || $value === null) {
                continue;
            }

            if ($value === true) {
                $processArgs[] = $name;
                continue;
            }

            if (is_array($value)) {
                foreach ($value as $item) {
                    $processArgs[] = sprintf('%s=%s', $name, (string) $item);
                }
                continue;
            }

            $processArgs[] = sprintf('%s=%s', $name, (string) $value);
        }

        return $processArgs;
    }
}
