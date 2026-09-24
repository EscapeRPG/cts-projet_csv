<?php

namespace App\Repository;

use App\Entity\Centre;
use App\Entity\PortiqueImporte;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PortiqueImporte>
 */
class PortiqueImporteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PortiqueImporte::class);
    }

    /**
     * @return array<int, array{
     *     cb: string,
     *     especes: string,
     *     jetons: int
     * }>
     * @throws Exception
     */
    public function aggregateByPortiqueForStationAndDate(
        Centre             $station,
        \DateTimeImmutable $date,
    ): array
    {
        $rows = $this->createQueryBuilder('p')
            ->select('p.numPortique AS numero')
            ->addSelect('COALESCE(SUM(p.payeCB), 0) AS cb')
            ->addSelect('COALESCE(SUM(COALESCE(p.payePieces, 0) + COALESCE(p.payeBillets, 0)), 0) AS especes')
            ->addSelect(
                '
                SUM(
                    (
                        COALESCE(p.payeJetons1, 0)
                        + COALESCE(p.payeJetons2, 0)
                    ) / 2
                ) AS jetons'
            )
            ->andWhere('p.centre = :station')
            ->andWhere('p.date = :date')
            ->andWhere('p.numPortique IS NOT NULL')
            ->setParameter('station', $station)
            ->setParameter('date', $date, Types::DATE_IMMUTABLE)
            ->groupBy('p.numPortique')
            ->orderBy('p.numPortique', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $importsByPortique = [];

        foreach ($rows as $row) {
            $numero = (int)$row['numero'];

            $importsByPortique[$numero] = [
                'cb' => (string)$row['cb'],
                'especes' => (string)$row['especes'],
                'jetons' => (int)$row['jetons'],
            ];
        }

        return $importsByPortique;
    }
}
