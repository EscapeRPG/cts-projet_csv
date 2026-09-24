<?php

namespace App\Tests\Integration;

use App\Entity\Centre;
use App\Entity\ImportedFiles;
use App\Entity\PortiqueImporte;
use App\Entity\Reseau;
use App\Enum\TypeCentre;
use App\Import\CsvReader;
use App\Repository\ImportedFilesRepository;
use App\Repository\PortiqueImporteRepository;
use App\Service\Import\ImportAstikotoFileService;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class ImportAstikotoTest extends TestCase
{
    public function testCashAggregationIncludesBillsOrCoinsWhenTheOtherAmountIsNull(): void
    {
        $em = $this->entityManager();
        foreach ([[4, null, '20.00'], [5, '3.50', null], [6, '2.50', '10.00'], [7, null, null]] as [$numero, $pieces, $billets]) {
            $em->getConnection()->insert('portique_importe', [
                'centre_id' => 45, 'date' => '2026-03-06', 'num_portique' => $numero,
                'paye_pieces' => $pieces, 'paye_billets' => $billets,
                'paye_total' => 0, 'mode' => 'ESPECES',
            ]);
        }
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($em);
        $repository = new PortiqueImporteRepository($registry);
        $totals = $repository->aggregateByPortiqueForStationAndDate($this->centre(45), new \DateTimeImmutable('2026-03-06'));
        self::assertCount(4, $totals);
        foreach ([4 => 20, 5 => 3.5, 6 => 12.5, 7 => 0] as $numero => $expected) {
            self::assertEquals($expected, $totals[$numero]['especes']);
        }
        self::assertSame([], $repository->aggregateByPortiqueForStationAndDate($this->centre(46), new \DateTimeImmutable('2026-03-06')));
        self::assertSame([], $repository->aggregateByPortiqueForStationAndDate($this->centre(45), new \DateTimeImmutable('2026-03-07')));
        $em->getConnection()->close();
    }

    public function testLatestImportsMatchTheExactPortiqueAndStation(): void
    {
        $em = $this->entityManager();
        $reseau = (new Reseau())->setNom('astikoto');
        (new \ReflectionProperty(Reseau::class, 'id'))->setValue($reseau, 1);
        foreach ([
            ['Portique 4.csv', 1, '2026-09-20'],
            ['Portique-4.csv', 1, '2026-09-21'],
            ['Portique 40.csv', 1, '2026-09-22'],
            ['Portique 4.csv', 2, '2026-09-23'],
        ] as [$filename, $centreId, $date]) {
            $em->getConnection()->insert('imported_files', [
                'filename' => $filename, 'centre_id' => $centreId,
                'reseau_id' => 1, 'file_hash' => 'test', 'imported_at' => $date . ' 10:00:00',
            ]);
        }
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($em);
        $repository = new ImportedFilesRepository($registry);
        $latest = $repository->findLatestByPortiqueForCentre($reseau, $this->centre(1));
        self::assertCount(2, $latest);
        self::assertSame('Portique-4.csv', $latest[4]->getFilename());
        self::assertSame('Portique 40.csv', $latest[40]->getFilename());
        self::assertSame([], $repository->findLatestByPortiqueForCentre($reseau, $this->centre(3)));
        $em->getConnection()->close();
    }

    public function testImportAndDuplicatesAreScopedToTheStation(): void
    {
        $em = $this->entityManager();
        $service = new ImportAstikotoFileService($em, new CsvReader());
        $reseau = (new Reseau())->setNom('astikoto');
        (new \ReflectionProperty(Reseau::class, 'id'))->setValue($reseau, 1);
        $path = tempnam(sys_get_temp_dir(), 'astikoto');
        file_put_contents($path, "Export\nDate;Heure;Service vendu;Paye total;Mode\n24/09/2026;10:30;Lavage;12,50;CB\n");
        $file = new UploadedFile($path, 'Portique 4.csv', 'text/csv', null, true);

        try {
            self::assertSame(1, $service->importFromFileForCentre($file, $reseau, $this->centre(1)));
            self::assertSame(0, $service->importFromFileForCentre($file, $reseau, $this->centre(1)));
            self::assertTrue($service->wasLastFileSkipped());
            self::assertSame(1, $service->importFromFileForCentre($file, $reseau, $this->centre(2)));
            $rows = $em->getConnection()->fetchAllAssociative('SELECT centre_id, num_portique, date, paye_total FROM portique_importe ORDER BY centre_id');
            self::assertCount(2, $rows);
            self::assertSame([1, 2], array_column($rows, 'centre_id'));
            self::assertSame(4, $rows[0]['num_portique']);
            self::assertSame('2026-09-24', $rows[0]['date']);
            self::assertEquals(12.5, $rows[0]['paye_total']);
            self::assertEquals(2, $em->getConnection()->fetchOne('SELECT COUNT(*) FROM imported_files'));
        } finally {
            unlink($path);
            $em->getConnection()->close();
        }
    }

    public function testFailedImportRollsBackAlreadyInsertedBatches(): void
    {
        $em = $this->entityManager();
        $service = new ImportAstikotoFileService($em, new CsvReader());
        $reseau = (new Reseau())->setNom('astikoto');
        (new \ReflectionProperty(Reseau::class, 'id'))->setValue($reseau, 1);
        $path = tempnam(sys_get_temp_dir(), 'astikoto');
        file_put_contents($path, "Export\nDate;Paye total;Mode\n" . str_repeat("24/09/2026;12,50;CB\n", 500) . "invalid;12,50;CB\n");
        try {
            try {
                $service->importFromFileForCentre(new UploadedFile($path, 'Portique 4.csv', 'text/csv', null, true), $reseau, $this->centre(1));
                self::fail('An invalid date must fail the import.');
            } catch (\RuntimeException $exception) {
                self::assertStringContainsString('Format de date invalide', $exception->getMessage());
            }
            self::assertEquals(0, $em->getConnection()->fetchOne('SELECT COUNT(*) FROM portique_importe'));
            self::assertEquals(0, $em->getConnection()->fetchOne('SELECT COUNT(*) FROM imported_files'));
        } finally {
            unlink($path);
            $em->getConnection()->close();
        }
    }

    private function entityManager(): EntityManager
    {
        $config = ORMSetup::createAttributeMetadataConfiguration([dirname(__DIR__, 2) . '/src/Entity'], true);
        $config->setNamingStrategy(new UnderscoreNamingStrategy(CASE_LOWER));
        $em = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);
        (new SchemaTool($em))->createSchema([$em->getClassMetadata(PortiqueImporte::class), $em->getClassMetadata(ImportedFiles::class)]);

        return $em;
    }

    private function centre(int $id): Centre
    {
        $centre = (new Centre())->setType(TypeCentre::STATION_LAVAGE);
        (new \ReflectionProperty(Centre::class, 'id'))->setValue($centre, $id);

        return $centre;
    }
}
