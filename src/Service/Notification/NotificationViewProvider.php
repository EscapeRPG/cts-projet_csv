<?php

namespace App\Service\Notification;

use App\Entity\User;
use App\Entity\UserNotification;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Builds notification view data for shared UI components.
 */
final readonly class NotificationViewProvider
{
    public function __construct(
        private Security            $security,
        private NotificationService $notificationService,
    ) {
    }

    /**
     * @return array{
     *     notifications: array<int, UserNotification>,
     *     unread_count: int
     * }
     */
    public function getNavigationData(int $limit = 10): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return [
                'notifications' => [],
                'unread_count' => 0,
            ];
        }

        return [
            'notifications' => $this->notificationService->getRecentForUser($user, $limit, true),
            'unread_count' => $this->notificationService->countUnreadForUser($user),
        ];
    }
}
