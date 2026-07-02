<?php

namespace App\Service\Import;

use App\Entity\Notification;
use App\Repository\ReseauRepository;
use Doctrine\DBAL\Connection;

final readonly class ImportHealthCheckService
{
    private const int HISTORY_DAYS = 14;
    private const int BASELINE_DAYS = 45;
    private const float WARNING_RATIO = 0.80;

    public function __construct(
        private Connection $connection,
        private ReseauRepository $reseauRepository,
    ) {
    }

    /**
     * @return array{rows:int, alerts:int, errors:int, warnings:int}
     */
    public function run(?\DateTimeImmutable $referenceDate = null, bool $notify = true): array
    {
        $referenceDate ??= new \DateTimeImmutable('today');
        $historyStart = $referenceDate->modify('-' . (self::HISTORY_DAYS - 1) . ' days');
        $baselineStart = $referenceDate->modify('-' . self::BASELINE_DAYS . ' days');

        $dailyStats = $this->buildDailyImportStats($baselineStart);
        $rows = 0;
        $alerts = [];
        $errors = 0;
        $warnings = 0;

        foreach ($this->reseauRepository->findBy(['isActive' => true], ['id' => 'ASC']) as $reseau) {
            $reseauId = (int)$reseau->getId();
            $reseauName = (string)$reseau->getNom();
            $expectedFiles = $this->computeExpectedFiles($dailyStats[$reseauId] ?? []);

            if ($expectedFiles <= 0) {
                continue;
            }

            $latestImportDate = $this->findLatestImportDate($dailyStats[$reseauId] ?? []);
            foreach ($this->dateRange($historyStart, $referenceDate) as $checkDate) {
                $dateKey = $checkDate->format('Y-m-d');
                $stats = $dailyStats[$reseauId][$dateKey] ?? [
                    'files_imported' => 0,
                    'controles_files' => 0,
                    'latest_imported_at' => null,
                ];

                $issues = $this->detectIssues(
                    $dateKey,
                    (int)$stats['files_imported'],
                    (int)$stats['controles_files'],
                    $expectedFiles,
                    $latestImportDate,
                    $referenceDate
                );
                $status = $this->statusFromIssues($issues);

                if ($status === 'error') {
                    ++$errors;
                } elseif ($status === 'warning') {
                    ++$warnings;
                }

                if ($status !== 'ok' && $this->shouldNotifyForDate($checkDate, $referenceDate)) {
                    $alerts[] = sprintf(
                        '%s %s: %s',
                        $reseauName,
                        $checkDate->format('d/m/Y'),
                        implode(' ; ', $issues)
                    );
                }

                $this->upsertHealthRow(
                    $reseauId,
                    $reseauName,
                    $dateKey,
                    (int)$stats['files_imported'],
                    $expectedFiles,
                    (int)$stats['controles_files'],
                    $stats['latest_imported_at'],
                    $status,
                    $issues
                );
                ++$rows;
            }
        }

        $this->connection->executeStatement(
            'DELETE FROM import_health_check WHERE check_date < :min_date',
            ['min_date' => $historyStart->format('Y-m-d')]
        );

        if ($notify && $alerts !== []) {
            $this->publishAlertNotification($referenceDate, $alerts);
        }

        return [
            'rows' => $rows,
            'alerts' => count($alerts),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * @return array<int, array<string, array{files_imported:int, controles_files:int, latest_imported_at:?string}>>
     */
    private function buildDailyImportStats(\DateTimeImmutable $from): array
    {
        $records = $this->connection->fetchAllAssociative(
            'SELECT reseau_id, filename, imported_at FROM imported_files WHERE imported_at >= :from_date',
            ['from_date' => $from->format('Y-m-d 00:00:00')]
        );

        $stats = [];
        foreach ($records as $record) {
            $reseauId = (int)($record['reseau_id'] ?? 0);
            $filename = (string)($record['filename'] ?? '');
            if ($reseauId <= 0 || !$this->extractFileDate($filename)) {
                continue;
            }

            $dateKey = $this->extractFileDate($filename);
            $stats[$reseauId][$dateKey] ??= [
                'files_imported' => 0,
                'controles_files' => 0,
                'latest_imported_at' => null,
            ];

            ++$stats[$reseauId][$dateKey]['files_imported'];
            if ($this->isControleFile($filename)) {
                ++$stats[$reseauId][$dateKey]['controles_files'];
            }

            $importedAt = (string)($record['imported_at'] ?? '');
            if (
                $importedAt !== ''
                && (
                    $stats[$reseauId][$dateKey]['latest_imported_at'] === null
                    || $importedAt > $stats[$reseauId][$dateKey]['latest_imported_at']
                )
            ) {
                $stats[$reseauId][$dateKey]['latest_imported_at'] = $importedAt;
            }
        }

        return $stats;
    }

    private function extractFileDate(string $filename): ?string
    {
        if (!preg_match('/(?<!\d)(\d{2})_(\d{2})_(\d{4})(?!\d)/', $filename, $matches)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', (int)$matches[3], (int)$matches[2], (int)$matches[1]);
    }

    private function isControleFile(string $filename): bool
    {
        $lower = strtolower($filename);

        if (
            str_contains($lower, 'clients_controles')
            || str_contains($lower, 'controles_factures')
            || str_contains($lower, 'controles_non_factures')
        ) {
            return false;
        }

        return str_ends_with($lower, '_controles.csv') || str_ends_with($lower, '[controles].csv');
    }

    /**
     * @param array<string, array{files_imported:int, controles_files:int, latest_imported_at:?string}> $dailyStats
     */
    private function computeExpectedFiles(array $dailyStats): int
    {
        $counts = [];
        foreach ($dailyStats as $stats) {
            $count = (int)$stats['files_imported'];
            if ($count > 0) {
                $counts[] = $count;
            }
        }

        if ($counts === []) {
            return 0;
        }

        $frequencies = array_count_values($counts);
        arsort($frequencies);

        return (int)array_key_first($frequencies);
    }

    /**
     * @param array<string, array{files_imported:int, controles_files:int, latest_imported_at:?string}> $dailyStats
     */
    private function findLatestImportDate(array $dailyStats): ?string
    {
        $dates = array_keys(array_filter(
            $dailyStats,
            static fn(array $stats): bool => (int)$stats['files_imported'] > 0
        ));
        sort($dates);

        return $dates === [] ? null : end($dates);
    }

    /**
     * @return list<string>
     */
    private function detectIssues(
        string $dateKey,
        int $filesImported,
        int $controlesFiles,
        int $expectedFiles,
        ?string $latestImportDate,
        \DateTimeImmutable $referenceDate
    ): array {
        $issues = [];

        if ($filesImported === 0) {
            $issues[] = sprintf('aucun fichier importé, environ %d attendu(s)', $expectedFiles);
        } elseif ($filesImported < max(1, (int)floor($expectedFiles * self::WARNING_RATIO))) {
            $issues[] = sprintf('lot partiel: %d fichier(s) importé(s) sur environ %d attendu(s)', $filesImported, $expectedFiles);
        }

        if ($filesImported > 0 && $controlesFiles === 0) {
            $issues[] = 'aucun fichier controles dans le lot';
        }

        if ($dateKey === $referenceDate->format('Y-m-d') && $latestImportDate !== null) {
            $last = new \DateTimeImmutable($latestImportDate);
            $ageDays = (int)$last->diff($referenceDate)->format('%a');
            if ($ageDays >= 2) {
                $issues[] = sprintf('aucun import depuis %d jour(s)', $ageDays);
            }
        }

        return $issues;
    }

    /**
     * @param list<string> $issues
     */
    private function statusFromIssues(array $issues): string
    {
        if ($issues === []) {
            return 'ok';
        }

        foreach ($issues as $issue) {
            if (str_starts_with($issue, 'aucun fichier') || str_starts_with($issue, 'aucun import')) {
                return 'error';
            }
        }

        return 'warning';
    }

    private function shouldNotifyForDate(\DateTimeImmutable $checkDate, \DateTimeImmutable $referenceDate): bool
    {
        $ageDays = (int)$checkDate->diff($referenceDate)->format('%a');

        return $ageDays <= 1;
    }

    /**
     * @param list<string> $issues
     */
    private function upsertHealthRow(
        int $reseauId,
        string $reseauName,
        string $checkDate,
        int $filesImported,
        int $expectedFiles,
        int $controlesFiles,
        ?string $latestImportedAt,
        string $status,
        array $issues
    ): void {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $this->connection->executeStatement(
            'INSERT INTO import_health_check (reseau_id, check_date, reseau_name, files_imported, expected_files, controles_files, latest_imported_at, status, issues, created_at, updated_at)
             VALUES (:reseau_id, :check_date, :reseau_name, :files_imported, :expected_files, :controles_files, :latest_imported_at, :status, :issues, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE reseau_name = VALUES(reseau_name), files_imported = VALUES(files_imported), expected_files = VALUES(expected_files), controles_files = VALUES(controles_files), latest_imported_at = VALUES(latest_imported_at), status = VALUES(status), issues = VALUES(issues), updated_at = VALUES(updated_at)',
            [
                'reseau_id' => $reseauId,
                'check_date' => $checkDate,
                'reseau_name' => $reseauName,
                'files_imported' => $filesImported,
                'expected_files' => $expectedFiles,
                'controles_files' => $controlesFiles,
                'latest_imported_at' => $latestImportedAt,
                'status' => $status,
                'issues' => json_encode($issues, JSON_THROW_ON_ERROR),
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    /**
     * @param list<string> $alerts
     */
    private function publishAlertNotification(\DateTimeImmutable $referenceDate, array $alerts): void
    {
        $existing = $this->connection->fetchOne(
            'SELECT id FROM notification WHERE type = :type AND target_date = :target_date LIMIT 1',
            [
                'type' => 'import_health_alert',
                'target_date' => $referenceDate->format('Y-m-d'),
            ]
        );
        if ($existing !== false) {
            return;
        }

        $message = 'Imports suspects: ' . implode(' | ', array_slice($alerts, 0, 5));
        if (count($alerts) > 5) {
            $message .= sprintf(' | +%d autre(s)', count($alerts) - 5);
        }

        $notification = (new Notification())
            ->setType('import_health_alert')
            ->setMessage($message)
            ->setTargetDate($referenceDate)
            ->setCreatedAt(new \DateTimeImmutable())
            ->setExpiresAt($referenceDate->modify('+14 days'));

        $this->connection->beginTransaction();
        try {
            $this->connection->insert('notification', [
                'type' => $notification->getType(),
                'salarie_id' => null,
                'message' => $notification->getMessage(),
                'target_date' => $notification->getTargetDate()?->format('Y-m-d'),
                'created_at' => $notification->getCreatedAt()?->format('Y-m-d H:i:s'),
                'expires_at' => $notification->getExpiresAt()?->format('Y-m-d H:i:s'),
            ]);
            $notificationId = (int)$this->connection->lastInsertId();
            $this->publishExistingNotificationToAdmins($notificationId);
            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }

    private function publishExistingNotificationToAdmins(int $notificationId): void
    {
        $admins = $this->connection->fetchAllAssociative(
            "SELECT id FROM `user` WHERE is_active = 1 AND roles LIKE '%ROLE_ADMIN%'"
        );
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        foreach ($admins as $admin) {
            $this->connection->insert('user_notification', [
                'notification_id' => $notificationId,
                'user_id' => (int)$admin['id'],
                'read_at' => null,
                'created_at' => $now,
            ]);
        }
    }

    /**
     * @return iterable<\DateTimeImmutable>
     */
    private function dateRange(\DateTimeImmutable $start, \DateTimeImmutable $end): iterable
    {
        for ($date = $start; $date <= $end; $date = $date->modify('+1 day')) {
            yield $date;
        }
    }
}
