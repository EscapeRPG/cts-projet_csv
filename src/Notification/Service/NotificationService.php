<?php

namespace App\Notification\Service;

use App\Utilisateur\Entity\User;
use App\Notification\Entity\UserNotification;
use App\Notification\Repository\UserNotificationRepository;

/**
 * Reads notification data for the authenticated application users.
 */
final readonly class NotificationService
{
    public function __construct(private UserNotificationRepository $userNotificationRepository)
    {
    }

    /**
     * @return array<int, UserNotification>
     */
    public function getRecentForUser(User $user, int $limit = 10, bool $onlyUnread = false): array
    {
        return $this->userNotificationRepository->findRecentForUser($user, $limit, $onlyUnread);
    }

    public function countUnreadForUser(User $user): int
    {
        return $this->userNotificationRepository->countUnreadForUser($user);
    }
}
