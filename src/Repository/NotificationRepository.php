<?php

namespace App\Repository;

use App\Entity\Notification;
use App\Entity\Salarie;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Notification>
 */
class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    public function findOneByTypeSalarieAndTargetDate(
        string $type,
        ?Salarie $salarie,
        \DateTimeImmutable $targetDate
    ): ?Notification {
        $qb = $this->createQueryBuilder('n')
            ->andWhere('n.type = :type')
            ->andWhere('n.targetDate = :targetDate')
            ->setParameter('type', $type)
            ->setParameter('targetDate', $targetDate);

        if ($salarie === null) {
            $qb->andWhere('n.salarie IS NULL');
        } else {
            $qb
                ->andWhere('n.salarie = :salarie')
                ->setParameter('salarie', $salarie);
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    //    /**
    //     * @return Notification[] Returns an array of Notification objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('n')
    //            ->andWhere('n.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('n.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Notification
    //    {
    //        return $this->createQueryBuilder('n')
    //            ->andWhere('n.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
