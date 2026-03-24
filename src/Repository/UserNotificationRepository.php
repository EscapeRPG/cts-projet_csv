<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\UserNotification;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\SecurityBundle\Security;

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
        $qb = $this->createQueryBuilder('un')
            ->innerJoin('un.notification', 'n')
            ->addSelect('n')
            ->leftJoin('n.salarie', 's')
            ->addSelect('s')
            ->andWhere('un.user = :user')
            ->setParameter('user', $user)
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
            ->andWhere('un.user = :user')
            ->andWhere('un.readAt IS NULL')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
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
