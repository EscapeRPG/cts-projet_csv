<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\UserNotification;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserNotification>
 */
class UserNotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserNotification::class);
    }

    /**
     * Returns the most recent notifications for a given user.
     *
     * @return array<int, UserNotification>
     */
    public function findRecentForUser(User $user, int $limit = 10, bool $onlyUnread = false): array
    {
        $now = new \DateTimeImmutable();
        $qb = $this->createQueryBuilder('un')
            ->innerJoin('un.notification', 'n')
            ->addSelect('n')
            ->leftJoin('n.salarie', 's')
            ->addSelect('s')
            ->andWhere('un.user = :user')
            ->andWhere('n.expiresAt >= :now')
            ->setParameter('user', $user)
            ->setParameter('now', $now)
            ->orderBy('n.targetDate', 'ASC')
            ->addOrderBy('un.createdAt', 'DESC')
            ->setMaxResults($limit);

        if ($onlyUnread) {
            $qb->andWhere('un.readAt IS NULL');
        }

        return $qb->getQuery()->getResult();
    }

    public function countUnreadForUser(User $user): int
    {
        return (int) $this->createQueryBuilder('un')
            ->select('COUNT(un.id)')
            ->innerJoin('un.notification', 'n')
            ->andWhere('un.user = :user')
            ->andWhere('un.readAt IS NULL')
            ->andWhere('n.expiresAt >= :now')
            ->setParameter('user', $user)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @throws Exception
     */
    public function deleteByNotificationIds(array $notificationIds): int
    {
        if ($notificationIds === []) {
            return 0;
        }

        return $this->getEntityManager()
            ->getConnection()
            ->executeStatement(
                'DELETE FROM user_notification WHERE notification_id IN (?)',
                [$notificationIds],
                [ArrayParameterType::INTEGER]
            );
    }

    //    /**
    //     * @return UserNotification[] Returns an array of UserNotification objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('u.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?UserNotification
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
