<?php

declare(strict_types=1);

use App\Kernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Dotenv\Dotenv;

// This script is intended to be run by OVH "Taches planifiees (CRON)" on mutualized hosting.
// Keep it under the configured web root (project/public), but block web access.
//
// Note: OVH may execute PHP jobs with different SAPIs depending on hosting configuration.
// Blocking only on PHP_SAPI !== 'cli' can therefore prevent the cron from running.
// We instead block only when the script is called from the web server.
if (isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] !== '') {
    http_response_code(404);
    exit;
}

$projectRoot = dirname(__DIR__, 2); // .../project
chdir($projectRoot);

require_once $projectRoot . '/vendor/autoload.php';

// Force production env for scheduled jobs (OVH cron may not provide env vars).
$_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = 'prod';
$_SERVER['APP_DEBUG'] = $_ENV['APP_DEBUG'] = '0';

// Load .env* files if present (matches Symfony recommended bootstrap behaviour).
new Dotenv()->bootEnv($projectRoot . '/.env');

$lockPath = sys_get_temp_dir() . '/kermilo_daily_run.lock';
$lockHandle = @fopen($lockPath, 'c');
if (!is_resource($lockHandle)) {
    // Avoid failing hard if temp dir is restricted; just run without lock.
    $lockHandle = null;
} elseif (!@flock($lockHandle, LOCK_EX | LOCK_NB)) {
    // Already running.
    exit(0);
}

// OVH mutualized hosting can run in a chroot where /tmp is not writable/visible.
// Prefer APP_LOG_DIR if provided, otherwise fall back to the project var/log directory.
$logDir = (string)($_SERVER['APP_LOG_DIR'] ?? $_ENV['APP_LOG_DIR'] ?? ($projectRoot . '/var/log'));
$logDir = rtrim($logDir, "/\\");
$logFile = ($logDir !== '' ? $logDir : sys_get_temp_dir()) . '/cron_daily_run.log';

$output = new BufferedOutput();
$exitCode = 1;

try {
    $kernel = new Kernel($_SERVER['APP_ENV'], false);
    $app = new Application($kernel);
    $app->setAutoExit(false);

    $input = new ArrayInput([
        'command' => 'app:daily:run',
        '--env' => 'prod',
        '--no-debug' => true,
        '--no-interaction' => true,
    ]);
    $input->setInteractive(false);

    $exitCode = (int) $app->run($input, $output);
} catch (Throwable $e) {
    $output->writeln('[cron] Unhandled exception: ' . $e->getMessage());
    $exitCode = 1;
} finally {
    $out = $output->fetch();
    $timestamp = (new DateTimeImmutable())->format(DateTimeInterface::ATOM);
    $payload = sprintf("[%s] exit=%d\n%s\n", $timestamp, $exitCode, $out);

    // Best-effort logging; don't fail the cron if logging is not possible.
    // Best-effort logging; try the configured directory first, then temp dir.
    if (!@is_dir($logDir)) {
        @mkdir($logDir, 0777, true);
    }
    $ok = @file_put_contents($logFile, $payload, FILE_APPEND);
    if ($ok === false) {
        $fallback = rtrim(sys_get_temp_dir(), "/\\") . '/cron_daily_run.log';
        @file_put_contents($fallback, $payload, FILE_APPEND);
    }

    // Also print to stdout so OVH can email the output when configured.
    echo $payload;

    if (is_resource($lockHandle)) {
        @flock($lockHandle, LOCK_UN);
        @fclose($lockHandle);
    }
}

exit($exitCode);
