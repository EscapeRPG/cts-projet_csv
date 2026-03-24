<?php

namespace App\Service;

use App\Entity\Notification;
use App\Service\Notification\BirthdayNotificationGenerator;
use App\Service\Notification\NotificationViewProvider;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFunction;

/**
 * Exposes application-level Twig globals.
 */
class AppExtension extends AbstractExtension implements GlobalsInterface
{
    /**
     * @param Security $security Security helper used to resolve current user.
     * @param SyntheseMetaProvider $syntheseMetaProvider Provider exposing synthesis metadata.
     */
    public function __construct(
        private Security $security,
        private SyntheseMetaProvider $syntheseMetaProvider,
        private NotificationViewProvider $notificationViewProvider,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('notification_display_message', $this->getNotificationDisplayMessage(...)),
        ];
    }

    /**
     * Returns Twig global variables.
     *
     * @return array<string, mixed> Twig globals map.
     */
    public function getGlobals(): array
    {
        $notificationData = $this->notificationViewProvider->getNavigationData();

        return [
            'user' => $this->security->getUser(),
            'database_last_update_at' => $this->syntheseMetaProvider->getLastDatabaseUpdateAt(),
            'notifications' => $notificationData['notifications'],
            'unread_notifications_count' => $notificationData['unread_count'],
        ];
    }

    public function getNotificationDisplayMessage(Notification $notification): string
    {
        if (
            $notification->getType() !== BirthdayNotificationGenerator::TYPE
            || $notification->getSalarie() === null
            || $notification->getTargetDate() === null
        ) {
            return (string) $notification->getMessage();
        }

        $today = new \DateTimeImmutable('today');
        $targetDate = $notification->getTargetDate();
        $daysUntil = (int) $today->diff($targetDate)->format('%r%a');

        $suffix = match (true) {
            $daysUntil < 0 => '',
            $daysUntil === 0 => ' (aujourd\'hui)',
            $daysUntil === 1 => ' (demain)',
            default => sprintf(' (dans %d jours)', $daysUntil),
        };

        return sprintf(
            'Anniversaire de %s %s le %s%s.',
            $notification->getSalarie()->getPrenom(),
            $notification->getSalarie()->getNom(),
            $targetDate->format('d/m/Y'),
            $suffix
        );
    }
}
