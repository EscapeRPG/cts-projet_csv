<?php

namespace App\Service\Notification;

use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Removes expired notifications and their per-user states.
 */
final readonly class NotificationCleanupService
{
    public function __construct(
        private NotificationRepository $notificationRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array{
     *     expired_notifications:int,
     *     deleted_notifications:int,
     *     deleted_user_notifications:int
     * }
     */
    public function purgeExpired(?\DateTimeImmutable $before = null, bool $dryRun = false): array
    {
        $cutoff = $before ?? new \DateTimeImmutable();
        $expiredIds = $this->notificationRepository->findExpiredIds($cutoff);

        if ($dryRun || $expiredIds === []) {
            return [
                'expired_notifications' => count($expiredIds),
                'deleted_notifications' => 0
            ];
        }

        return $this->entityManager->wrapInTransaction(function () use ($expiredIds): array {
            $deletedNotifications = $this->notificationRepository->deleteByIds($expiredIds);

            return [
                'expired_notifications' => count($expiredIds),
                'deleted_notifications' => $deletedNotifications
            ];
        });
    }
}
