<?php

namespace App\Service\Notification;

use App\Entity\User;
use App\Entity\UserNotification;
use App\Repository\UserNotificationRepository;

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
