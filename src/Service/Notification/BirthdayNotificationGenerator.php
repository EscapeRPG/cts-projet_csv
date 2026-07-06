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
        private readonly SalarieRepository      $salarieRepository,
        private readonly UserRepository         $userRepository,
        private readonly NotificationRepository $notificationRepository,
        private readonly EntityManagerInterface $entityManager,
    )
    {
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
        int                 $daysAhead = self::DEFAULT_DAYS_AHEAD,
        bool                $dryRun = false,
    ): array
    {
        $today = ($referenceDate ?? new \DateTimeImmutable('today'))->setTime(0, 0);
        $users = $this->userRepository->findActiveUsers();

        if ($users === []) {
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

        // Pre-compute each user's allowed centre scope once.
        // - null means unrestricted (ROLE_ADMIN)
        // - [] means "no allowed centres" -> sees nothing (same semantics as list pages)
        /** @var array<int, list<int>|null> $userAllowedCentreIdsByUserId */
        $userAllowedCentreIdsByUserId = [];
        foreach ($users as $user) {
            $id = $user->getId();
            if ($id === null) {
                continue;
            }
            $userAllowedCentreIdsByUserId[$id] = $this->computeUserCentreScopeIds($user);
        }

        foreach ($employees as $employee) {
            $birthDate = $employee->getDateNaissance();
            if ($birthDate === null) {
                continue;
            }

            $targetDate = $this->computeNextBirthdayDate($birthDate, $today);
            $daysUntilBirthday = (int)$today->diff($targetDate)->format('%r%a');

            if ($daysUntilBirthday < 0 || $daysUntilBirthday > $daysAhead) {
                continue;
            }

            ++$matched;

            $notification = $this->notificationRepository
                ->findOneByTypeSalarieAndTargetDate(self::TYPE, $employee, $targetDate);

            if ($notification === null) {
                ++$notificationsCreated;
                $notification = $this->buildNotification($employee, $targetDate);

                if (!$dryRun) {
                    $this->entityManager->persist($notification);
                }
            }

            // Employee visibility is centre-scoped.
            $employeeCentreIds = [];
            foreach ($employee->getCentres() as $centre) {
                $centreId = $centre->getId();
                if ($centreId !== null) {
                    $employeeCentreIds[] = $centreId;
                }
            }
            $employeeCentreIds = array_values(array_unique($employeeCentreIds));

            foreach ($users as $user) {
                $userId = $user->getId();
                $allowedCentreIds = $userId !== null && array_key_exists($userId, $userAllowedCentreIdsByUserId)
                    ? $userAllowedCentreIdsByUserId[$userId]
                    : [];

                if ($allowedCentreIds !== null) {
                    if ($allowedCentreIds === []) {
                        // Scoped user but no allowed centres: sees nothing.
                        continue;
                    }
                    if ($employeeCentreIds === []) {
                        // Employee without centres is not visible to scoped users (list pages use an inner join).
                        continue;
                    }
                    if (!$this->intersects($employeeCentreIds, $allowedCentreIds)) {
                        continue;
                    }
                }

                if ($this->hasRecipient($notification, $user)) {
                    continue;
                }

                ++$recipientsCreated;

                if ($dryRun) {
                    continue;
                }

                $userNotification = new UserNotification()
                    ->setNotification($notification)
                    ->setUser($user)
                    ->setCreatedAt(new \DateTimeImmutable());

                $notification->addUserNotification($userNotification);
                $user->addUserNotification($userNotification);
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
        Salarie            $employee,
        \DateTimeImmutable $targetDate,
    ): Notification
    {
        $fullName = trim(sprintf('%s %s', (string)$employee->getPrenom(), (string)$employee->getNom()));

        $fullNameWithDeterminant = $fullName;
        if ($fullNameWithDeterminant !== '') {
            $startsWithVowel = preg_match('/\A[aeiouyàâäæéèêëîïôöùûüœ]/iu', $fullNameWithDeterminant) === 1;
            $fullNameWithDeterminant = ($startsWithVowel ? "d'" : 'de ') . $fullNameWithDeterminant;
        }

        return new Notification()
            ->setType(self::TYPE)
            ->setSalarie($employee)
            ->setMessage(sprintf(
                'Anniversaire %s le %s',
                $fullNameWithDeterminant,
                $targetDate->format('d/m/Y'),
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
    ): \DateTimeImmutable
    {
        $candidate = $this->buildBirthdayOccurrence((int)$referenceDate->format('Y'), $birthDate);

        if ($candidate < $referenceDate) {
            return $this->buildBirthdayOccurrence(((int)$referenceDate->format('Y')) + 1, $birthDate);
        }

        return $candidate;
    }

    /**
     * @throws \DateMalformedStringException
     */
    private function buildBirthdayOccurrence(int $year, \DateTimeImmutable $birthDate): \DateTimeImmutable
    {
        $month = (int)$birthDate->format('m');
        $day = (int)$birthDate->format('d');

        if ($month === 2 && $day === 29 && !checkdate($month, $day, $year)) {
            return new \DateTimeImmutable(sprintf('%04d-02-28', $year));
        }

        return new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day));
    }

    private function hasRecipient(Notification $notification, User $user): bool
    {
        $userId = $user->getId();
        if ($userId === null) {
            return false;
        }

        foreach ($notification->getUserNotifications() as $userNotification) {
            if ($userNotification->getUser()?->getId() === $userId) {
                return true;
            }
        }

        return false;
    }

    /**
     * Mirrors ListsController::getCurrentUserCentreScopeIds(), but for an arbitrary user entity.
     *
     * @return list<int>|null Null means unrestricted (ROLE_ADMIN).
     */
    private function computeUserCentreScopeIds(User $user): ?array
    {
        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return null;
        }

        $ids = [];

        // Prefer societes scope (covers encours + centres) when defined.
        if ($user->getSocietes()->count() > 0) {
            foreach ($user->getSocietes() as $societe) {
                if (!$societe instanceof \App\Entity\Societe) {
                    continue;
                }
                foreach ($societe->getCentre() as $centre) {
                    if (!$centre instanceof \App\Entity\Centre) {
                        continue;
                    }
                    $id = $centre->getId();
                    if ($id !== null) {
                        $ids[] = $id;
                    }
                }
            }
        } else {
            // Backward compat: old scope stored as explicit centres.
            foreach ($user->getCentres() as $centre) {
                $id = $centre->getId();
                if ($id !== null) {
                    $ids[] = $id;
                }
            }
        }

        $ids = array_values(array_unique($ids));
        sort($ids);

        return $ids;
    }

    /**
     * @param list<int> $a
     * @param list<int> $b
     */
    private function intersects(array $a, array $b): bool
    {
        // Iterate on the smallest list to keep it cheap.
        if (count($a) > count($b)) {
            [$a, $b] = [$b, $a];
        }

        $set = array_fill_keys($b, true);
        foreach ($a as $v) {
            if (isset($set[$v])) {
                return true;
            }
        }

        return false;
    }
}
