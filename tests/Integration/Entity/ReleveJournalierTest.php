<?php

declare(strict_types=1);

namespace App\Tests\Integration\Entity;

use App\Kernel;
use App\Entity\Centre;
use App\Entity\EquipementStation;
use App\Entity\ReleveEquipement;
use App\Entity\ReleveJournalier;
use App\Entity\RelevePrestation;
use App\Entity\ReleveProduit;
use App\Entity\Reseau;
use App\Entity\Societe;
use App\Entity\User;
use App\Enum\CategorieEquipement;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ReleveJournalierTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->entityManager->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        $connection = $this->entityManager->getConnection();
        if ($connection->isTransactionActive()) {
            $connection->rollBack();
        }

        $this->entityManager->close();
        parent::tearDown();
    }

    public function testPersistsAndReloadsCompleteReleve(): void
    {
        [$centre, $auteur] = $this->createContext();
        $equipement = new EquipementStation($centre, 'BORNE-01', 'Borne 1', CategorieEquipement::BORNE);
        $this->entityManager->persist($equipement);

        $releve = $this->createReleve($centre, $auteur);
        $ligneEquipement = (new ReleveEquipement())
            ->setEquipement($equipement)
            ->setCb('12.50')
            ->setJetons(3);
        $produit = (new ReleveProduit())
            ->setDesignation('Produit lave-vitres')
            ->setEspeces('8.20')
            ->setOrdreAffichage(1);
        $prestation = (new RelevePrestation())
            ->setNom('Lavage complet')
            ->setContrat('25.00')
            ->setOrdreAffichage(2);

        $releve
            ->addReleveEquipement($ligneEquipement)
            ->addReleveProduit($produit)
            ->addRelevePrestation($prestation);

        $this->entityManager->persist($releve);
        $this->entityManager->flush();
        $releveId = $releve->getId();
        $this->entityManager->clear();

        $releveBdd = $this->entityManager->find(ReleveJournalier::class, $releveId);

        self::assertInstanceOf(ReleveJournalier::class, $releveBdd);
        self::assertSame('2026-08-28', $releveBdd->getDateReleve()?->format('Y-m-d'));
        self::assertCount(1, $releveBdd->getRelevesEquipements());
        self::assertCount(1, $releveBdd->getRelevesProduits());
        self::assertCount(1, $releveBdd->getRelevesPrestations());

        $releveBddEquipement = $releveBdd->getRelevesEquipements()->first();
        self::assertInstanceOf(ReleveEquipement::class, $releveBddEquipement);
        self::assertSame('12.50', $releveBddEquipement->getCb());
        self::assertSame(3, $releveBddEquipement->getJetons());
        self::assertSame($releveBdd, $releveBddEquipement->getReleveJournalier());

        $releveBddProduit = $releveBdd->getRelevesProduits()->first();
        self::assertInstanceOf(ReleveProduit::class, $releveBddProduit);
        self::assertSame('Produit lave-vitres', $releveBddProduit->getDesignation());
        self::assertSame('8.20', $releveBddProduit->getEspeces());

        $releveBddPrestation = $releveBdd->getRelevesPrestations()->first();
        self::assertInstanceOf(RelevePrestation::class, $releveBddPrestation);
        self::assertSame('Lavage complet', $releveBddPrestation->getNom());
        self::assertSame('25.00', $releveBddPrestation->getContrat());
    }

    public function testRemovingChildrenDeletesThemFromDatabase(): void
    {
        [$centre, $auteur] = $this->createContext();
        $equipement = new EquipementStation($centre, 'BORNE-01', 'Borne 1', CategorieEquipement::BORNE);
        $this->entityManager->persist($equipement);

        $releve = $this->createReleve($centre, $auteur);
        $ligneEquipement = (new ReleveEquipement())->setEquipement($equipement);
        $produit = (new ReleveProduit())->setDesignation('Produit lave-vitres');
        $prestation = (new RelevePrestation())->setNom('Lavage complet');
        $releve
            ->addReleveEquipement($ligneEquipement)
            ->addReleveProduit($produit)
            ->addRelevePrestation($prestation);

        $this->entityManager->persist($releve);
        $this->entityManager->flush();
        $ligneEquipementId = $ligneEquipement->getId();
        $produitId = $produit->getId();
        $prestationId = $prestation->getId();

        $releve
            ->removeReleveEquipement($ligneEquipement)
            ->removeReleveProduit($produit)
            ->removeRelevePrestation($prestation);
        $this->entityManager->flush();
        $this->entityManager->clear();

        self::assertNull($this->entityManager->find(ReleveEquipement::class, $ligneEquipementId));
        self::assertNull($this->entityManager->find(ReleveProduit::class, $produitId));
        self::assertNull($this->entityManager->find(RelevePrestation::class, $prestationId));
    }

    public function testRemovingParentDeletesItsChildren(): void
    {
        [$centre, $auteur] = $this->createContext();
        $equipement = new EquipementStation($centre, 'BORNE-01', 'Borne 1', CategorieEquipement::BORNE);
        $this->entityManager->persist($equipement);

        $releve = $this->createReleve($centre, $auteur);
        $ligneEquipement = (new ReleveEquipement())->setEquipement($equipement);
        $produit = (new ReleveProduit())->setDesignation('Produit lave-vitres');
        $prestation = (new RelevePrestation())->setNom('Lavage complet');
        $releve
            ->addReleveEquipement($ligneEquipement)
            ->addReleveProduit($produit)
            ->addRelevePrestation($prestation);

        $this->entityManager->persist($releve);
        $this->entityManager->flush();
        $releveId = $releve->getId();
        $ligneEquipementId = $ligneEquipement->getId();
        $produitId = $produit->getId();
        $prestationId = $prestation->getId();

        $this->entityManager->remove($releve);
        $this->entityManager->flush();
        $this->entityManager->clear();

        self::assertNull($this->entityManager->find(ReleveJournalier::class, $releveId));
        self::assertNull($this->entityManager->find(ReleveEquipement::class, $ligneEquipementId));
        self::assertNull($this->entityManager->find(ReleveProduit::class, $produitId));
        self::assertNull($this->entityManager->find(RelevePrestation::class, $prestationId));
    }

    public function testCannotPersistTwoRelevesForSameCentreAndDate(): void
    {
        [$centre, $auteur] = $this->createContext();
        $this->entityManager->persist($this->createReleve($centre, $auteur));
        $this->entityManager->persist($this->createReleve($centre, $auteur));

        $this->expectException(UniqueConstraintViolationException::class);
        $this->entityManager->flush();
    }

    public function testCannotPersistSameEquipementTwiceInOneReleve(): void
    {
        [$centre, $auteur] = $this->createContext();
        $equipement = new EquipementStation($centre, 'BORNE-01', 'Borne 1', CategorieEquipement::BORNE);
        $releve = $this->createReleve($centre, $auteur);
        $premiereLigne = (new ReleveEquipement())
            ->setReleveJournalier($releve)
            ->setEquipement($equipement);
        $secondeLigne = (new ReleveEquipement())
            ->setReleveJournalier($releve)
            ->setEquipement($equipement);

        $this->entityManager->persist($equipement);
        $this->entityManager->persist($releve);
        $this->entityManager->persist($premiereLigne);
        $this->entityManager->persist($secondeLigne);

        $this->expectException(UniqueConstraintViolationException::class);
        $this->entityManager->flush();
    }

    /** @return array{Centre, User} */
    private function createContext(): array
    {
        $suffix = bin2hex(random_bytes(6));
        $reseau = (new Reseau())->setNom('Réseau '.$suffix);
        $societe = (new Societe())->setNom('Société '.$suffix);
        $centre = (new Centre())
            ->setVille('Nantes')
            ->setCp('44000')
            ->setReseau($reseau)
            ->setSociete($societe);
        $auteur = (new User())
            ->setUsername('integration-'.$suffix)
            ->setEmail('integration-'.$suffix.'@example.test')
            ->setPassword('not-a-real-password')
            ->setIsActive(true);

        $this->entityManager->persist($reseau);
        $this->entityManager->persist($societe);
        $this->entityManager->persist($centre);
        $this->entityManager->persist($auteur);

        return [$centre, $auteur];
    }

    private function createReleve(Centre $centre, User $auteur): ReleveJournalier
    {
        return ReleveJournalier::create(
            $centre,
            new \DateTimeImmutable('2026-08-28'),
            $auteur,
            new \DateTimeImmutable('2026-08-28 12:00:00'),
        );
    }
}
