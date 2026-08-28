<?php

namespace App\Repository;

use App\Entity\ImportedFiles;
use App\Entity\Reseau;
use App\Entity\Centre;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ImportedFiles>
 */
class ImportedFilesRepository extends ServiceEntityRepository
{
    /**
     * @param ManagerRegistry $registry Doctrine manager registry.
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ImportedFiles::class);
    }

    /**
     * Returns the latest file imported for an Astikoto portique.
     */
    public function findLatestForReseauAndPortique(
        Reseau $reseau,
        int $numPortique,
        ?Centre $centre = null,
    ): ?ImportedFiles {
        $qb = $this->createQueryBuilder('importedFile')
            ->andWhere('importedFile.reseau = :reseau')
            ->andWhere('LOWER(importedFile.filename) LIKE :portique')
            ->setParameter('reseau', $reseau)
            ->setParameter('portique', sprintf('%%portique %d%%', $numPortique))
            ->orderBy('importedFile.imported_at', 'DESC')
            ->addOrderBy('importedFile.id', 'DESC')
            ->setMaxResults(1);

        if ($centre !== null) {
            $qb
                ->andWhere('importedFile.centre = :centre')
                ->setParameter('centre', $centre);
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    //    /**
    //     * @return ImportedFiles[] Returns an array of ImportedFiles objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('i')
    //            ->andWhere('i.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('i.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?ImportedFiles
    //    {
    //        return $this->createQueryBuilder('i')
    //            ->andWhere('i.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
