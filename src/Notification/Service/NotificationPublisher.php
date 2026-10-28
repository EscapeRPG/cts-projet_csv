<?php

namespace App\Notification\Service;

use App\Notification\Entity\Notification;
use App\Notification\Entity\UserNotification;
use App\Utilisateur\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Publishes notifications to active users.
 */
final readonly class NotificationPublisher
{
    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function publishToAllActiveUsers(Notification $notification): int
    {
        $users = $this->userRepository->findActiveUsers();
        $createdRecipients = 0;

        foreach ($users as $user) {
            $userNotification = new UserNotification();
            $userNotification
                ->setNotification($notification)
                ->setUser($user)
                ->setCreatedAt(new \DateTimeImmutable());

            $notification->addUserNotification($userNotification);
            $user->addUserNotification($userNotification);

            $this->entityManager->persist($userNotification);
            ++$createdRecipients;
        }

        return $createdRecipients;
    }
}
