<?php

namespace App\Service\Notification;

use App\Entity\Notification;
use App\Entity\Salarie;
use App\Entity\User;
use App\Entity\UserNotification;
use App\Repository\NotificationRepository;
use App\Repository\SalarieRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Generates per-user notifications for upcoming employee birthdays.
 */
final class BirthdayNotificationGenerator
{
    public const string TYPE = 'birthday_upcoming';
    public const int DEFAULT_DAYS_AHEAD = 3;

    public function __construct(
        private readonly SalarieRepository $salarieRepository,
        private readonly UserRepository $userRepository,
        private readonly NotificationRepository $notificationRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array{
     *     scanned:int,
     *     matched:int,
     *     notifications_created:int,
     *     recipients_created:int
     * }
     * @throws \DateMalformedStringException
     */
    public function generate(
        ?\DateTimeImmutable $referenceDate = null,
        int $daysAhead = self::DEFAULT_DAYS_AHEAD,
        bool $dryRun = false,
    ): array {
        $today = ($referenceDate ?? new \DateTimeImmutable('today'))->setTime(0, 0);
        $admins = $this->userRepository->findActiveAdmins();

        if ($admins === []) {
            return [
                'scanned' => 0,
                'matched' => 0,
                'notifications_created' => 0,
                'recipients_created' => 0,
            ];
        }

        $employees = $this->salarieRepository->findActiveWithBirthday();
        $scanned = count($employees);
        $matched = 0;
        $notificationsCreated = 0;
        $recipientsCreated = 0;

        foreach ($employees as $employee) {
            $birthDate = $employee->getDateNaissance();
            if ($birthDate === null) {
                continue;
            }

            $targetDate = $this->computeNextBirthdayDate($birthDate, $today);
            $daysUntilBirthday = (int) $today->diff($targetDate)->format('%r%a');

            if ($daysUntilBirthday < 0 || $daysUntilBirthday > $daysAhead) {
                continue;
            }

            ++$matched;

            $notification = $this->notificationRepository
                ->findOneByTypeSalarieAndTargetDate(self::TYPE, $employee, $targetDate);

            if ($notification === null) {
                ++$notificationsCreated;
                $notification = $this->buildNotification($employee, $targetDate, $today);

                if (!$dryRun) {
                    $this->entityManager->persist($notification);
                }
            }

            foreach ($admins as $admin) {
                if ($this->hasRecipient($notification, $admin)) {
                    continue;
                }

                ++$recipientsCreated;

                if ($dryRun) {
                    continue;
                }

                $userNotification = new UserNotification()
                    ->setNotification($notification)
                    ->setUser($admin)
                    ->setCreatedAt(new \DateTimeImmutable());

                $notification->addUserNotification($userNotification);
                $admin->addUserNotification($userNotification);
                $this->entityManager->persist($userNotification);
            }
        }

        if (!$dryRun && ($notificationsCreated > 0 || $recipientsCreated > 0)) {
            $this->entityManager->flush();
        }

        return [
            'scanned' => $scanned,
            'matched' => $matched,
            'notifications_created' => $notificationsCreated,
            'recipients_created' => $recipientsCreated,
        ];
    }

    private function buildNotification(
        Salarie $employee,
        \DateTimeImmutable $targetDate,
        \DateTimeImmutable $today,
    ): Notification {
        $fullName = trim(sprintf('%s %s', $employee->getPrenom(), $employee->getNom()));
        $daysUntilBirthday = (int) $today->diff($targetDate)->format('%a');
        $dayLabel = $daysUntilBirthday === 0 ? 'aujourd\'hui' : sprintf('dans %d jour%s', $daysUntilBirthday, $daysUntilBirthday > 1 ? 's' : '');

        return new Notification()
            ->setType(self::TYPE)
            ->setSalarie($employee)
            ->setMessage(sprintf(
                'L\'anniversaire de %s est prévu le %s (%s).',
                $fullName,
                $targetDate->format('d/m/Y'),
                $dayLabel
            ))
            ->setTargetDate($targetDate)
            ->setCreatedAt(new \DateTimeImmutable())
            ->setExpiresAt($targetDate->setTime(23, 59, 59));
    }

    /**
     * @throws \DateMalformedStringException
     */
    private function computeNextBirthdayDate(
        \DateTimeImmutable $birthDate,
        \DateTimeImmutable $referenceDate,
    ): \DateTimeImmutable {
        $candidate = $this->buildBirthdayOccurrence((int) $referenceDate->format('Y'), $birthDate);

        if ($candidate < $referenceDate) {
            return $this->buildBirthdayOccurrence(((int) $referenceDate->format('Y')) + 1, $birthDate);
        }

        return $candidate;
    }

    /**
     * @throws \DateMalformedStringException
     */
    private function buildBirthdayOccurrence(int $year, \DateTimeImmutable $birthDate): \DateTimeImmutable
    {
        $month = (int) $birthDate->format('m');
        $day = (int) $birthDate->format('d');

        if ($month === 2 && $day === 29 && !checkdate($month, $day, $year)) {
            return new \DateTimeImmutable(sprintf('%04d-02-28', $year));
        }

        return new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day));
    }

    private function hasRecipient(Notification $notification, User $user): bool
    {
        foreach ($notification->getUserNotifications() as $userNotification) {
            if ($userNotification->getUser() === $user) {
                return true;
            }
        }

        return false;
    }
}
