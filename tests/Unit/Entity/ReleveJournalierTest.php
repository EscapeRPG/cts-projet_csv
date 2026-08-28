<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Centre;
use App\Entity\EquipementStation;
use App\Entity\ReleveJournalier;
use App\Entity\ReleveProduit;
use App\Entity\RelevePrestation;
use App\Entity\ReleveEquipement;
use App\Entity\User;
use App\Enum\StatutReleve;
use App\Enum\CategorieEquipement;
use PHPUnit\Framework\TestCase;

final class ReleveJournalierTest extends TestCase
{
    private Centre $centre;
    private User $auteur;
    private \DateTimeImmutable $dateReleve;
    private \DateTimeImmutable $dateCreation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->centre = new Centre();
        $this->auteur = new User();
        $this->dateReleve = new \DateTimeImmutable('2026-06-30');
        $this->dateCreation = new \DateTimeImmutable('2026-06-30 18:30:00');
    }

    private function createReleve(): ReleveJournalier
    {
        return ReleveJournalier::create(
            $this->centre,
            $this->dateReleve,
            $this->auteur,
            $this->dateCreation,
        );
    }

    public function testNewReleve(): void
    {
        $releve = $this->createReleve();

        self::assertNull($releve->getId());
        self::assertSame($this->centre, $releve->getCentre());
        self::assertSame($this->dateReleve, $releve->getDateReleve());

        self::assertSame(StatutReleve::BROUILLON, $releve->getStatut());
        self::assertTrue($releve->isDraft());
        self::assertFalse($releve->isValidated());

        self::assertSame($this->auteur, $releve->getCreatedBy());
        self::assertSame($this->auteur, $releve->getUpdatedBy());
        self::assertSame($this->dateCreation, $releve->getCreatedAt());
        self::assertSame($this->dateCreation, $releve->getUpdatedAt());

        self::assertNull($releve->getValidatedBy());
        self::assertNull($releve->getValidatedAt());

        self::assertCount(0, $releve->getRelevesEquipements());
        self::assertCount(0, $releve->getRelevesProduits());
        self::assertCount(0, $releve->getRelevesPrestations());
        self::assertTrue($releve->isReleveEmpty());
    }

    public function testValidateReleve(): void
    {
        $releve = $this->createReleve();
        $dateValidation = new \DateTimeImmutable('2026-06-30 18:45:00');

        $releve->markAsValidated($this->auteur, $dateValidation);

        self::assertFalse($releve->isDraft());
        self::assertTrue($releve->isValidated());
        self::assertSame(StatutReleve::VALIDE, $releve->getStatut());
        self::assertSame($this->auteur, $releve->getValidatedBy());
        self::assertSame($dateValidation, $releve->getValidatedAt());
    }

    public function testModifiedReleve(): void
    {
        $releve = $this->createReleve();
        $dateModification = new \DateTimeImmutable('2026-07-01 09:00:00');
        $dateAutreModification = new \DateTimeImmutable('2026-07-10 14:00:00');
        $auteurModification = new User();
        $auteurAutreModification = new User();

        $releve->markAsModified($auteurModification, $dateModification);

        self::assertFalse($releve->isValidated());
        self::assertTrue($releve->isDraft());
        self::assertSame(StatutReleve::BROUILLON, $releve->getStatut());
        self::assertNull($releve->getValidatedAt());
        self::assertNull($releve->getValidatedBy());
        self::assertSame($this->auteur, $releve->getCreatedBy());
        self::assertSame($auteurModification, $releve->getUpdatedBy());
        self::assertSame($dateModification, $releve->getUpdatedAt());

        $releve->markAsModified($auteurAutreModification, $dateAutreModification);

        self::assertFalse($releve->isValidated());
        self::assertTrue($releve->isDraft());
        self::assertNull($releve->getValidatedAt());
        self::assertNull($releve->getValidatedBy());
        self::assertSame($this->auteur, $releve->getCreatedBy());
        self::assertSame($auteurAutreModification, $releve->getUpdatedBy());
        self::assertSame($dateAutreModification, $releve->getUpdatedAt());
    }

    public function testValidateThenModifyReleve(): void
    {
        $releve = $this->createReleve();
        $dateValidation = new \DateTimeImmutable('2026-06-30 18:45:00');
        $dateModification = new \DateTimeImmutable('2026-07-01 09:00:00');
        $auteurModification = new User();

        $releve->markAsValidated($this->auteur, $dateValidation);
        $releve->markAsModified($auteurModification, $dateModification);

        self::assertFalse($releve->isValidated());
        self::assertTrue($releve->isDraft());
        self::assertSame(StatutReleve::BROUILLON, $releve->getStatut());
        self::assertNull($releve->getValidatedAt());
        self::assertNull($releve->getValidatedBy());
        self::assertSame($this->auteur, $releve->getCreatedBy());
        self::assertSame($this->dateCreation, $releve->getCreatedAt());
        self::assertSame($auteurModification, $releve->getUpdatedBy());
        self::assertSame($dateModification, $releve->getUpdatedAt());
    }

    public function testModifiedReleveCanBeValidatedAgain(): void
    {
        $releve = $this->createReleve();
        $releve->markAsValidated(
            $this->auteur,
            new \DateTimeImmutable('2026-06-30 18:45:00'),
        );

        $releve->markAsModified(
            new User(),
            new \DateTimeImmutable('2026-07-01 09:00:00'),
        );

        $nouveauValidateur = new User();
        $nouvelleValidation = new \DateTimeImmutable('2026-07-01 10:00:00');

        $releve->markAsValidated(
            $nouveauValidateur,
            $nouvelleValidation,
        );

        self::assertTrue($releve->isValidated());
        self::assertSame($nouveauValidateur, $releve->getValidatedBy());
        self::assertSame($nouvelleValidation, $releve->getValidatedAt());
    }

    public function testAddReleveProduit(): void
    {
        $releve = $this->createReleve();
        $produit = new ReleveProduit();

        $releve->addReleveProduit($produit);

        self::assertCount(1, $releve->getRelevesProduits());
        self::assertTrue($releve->getRelevesProduits()->contains($produit));
        self::assertSame($releve, $produit->getReleveJournalier());
    }

    public function testAddingSameProductTwice(): void
    {
        $releve = $this->createReleve();
        $produit = new ReleveProduit();

        $releve->addReleveProduit($produit);
        $releve->addReleveProduit($produit);

        self::assertCount(1, $releve->getRelevesProduits());
    }

    public function testRemoveReleveProduit(): void
    {
        $releve = $this->createReleve();
        $produit = new ReleveProduit();

        $releve->addReleveProduit($produit);
        $releve->removeReleveProduit($produit);

        self::assertCount(0, $releve->getRelevesProduits());
        self::assertFalse($releve->getRelevesProduits()->contains($produit));
        self::assertNull($produit->getReleveJournalier());
    }

    public function testAddTwoProductsThenRemoveOne(): void
    {
        $releve = $this->createReleve();
        $produit1 = new ReleveProduit();
        $produit2 = new ReleveProduit();

        $releve->addReleveProduit($produit1);
        $releve->addReleveProduit($produit2);

        self::assertCount(2, $releve->getRelevesProduits());
        self::assertTrue($releve->getRelevesProduits()->contains($produit1));
        self::assertTrue($releve->getRelevesProduits()->contains($produit2));

        $releve->removeReleveProduit($produit1);

        self::assertCount(1, $releve->getRelevesProduits());
        self::assertFalse($releve->getRelevesProduits()->contains($produit1));
        self::assertTrue($releve->getRelevesProduits()->contains($produit2));
        self::assertNull($produit1->getReleveJournalier());
        self::assertSame($releve, $produit2->getReleveJournalier());
    }

    public function testAddRelevePrestation(): void
    {
        $releve = $this->createReleve();
        $prestation = new RelevePrestation();

        $releve->addRelevePrestation($prestation);

        self::assertCount(1, $releve->getRelevesPrestations());
        self::assertTrue($releve->getRelevesPrestations()->contains($prestation));
        self::assertSame($releve, $prestation->getReleveJournalier());
    }

    public function testAddingSamePrestationTwice(): void
    {
        $releve = $this->createReleve();
        $prestation = new RelevePrestation();

        $releve->addRelevePrestation($prestation);
        $releve->addRelevePrestation($prestation);

        self::assertCount(1, $releve->getRelevesPrestations());
    }

    public function testRemoveRelevePrestation(): void
    {
        $releve = $this->createReleve();
        $prestation = new RelevePrestation();

        $releve->addRelevePrestation($prestation);
        $releve->removeRelevePrestation($prestation);

        self::assertCount(0, $releve->getRelevesPrestations());
        self::assertFalse($releve->getRelevesPrestations()->contains($prestation));
        self::assertNull($prestation->getReleveJournalier());
    }

    public function testAddTwoPrestationsThenRemoveOne(): void
    {
        $releve = $this->createReleve();
        $prestation1 = new RelevePrestation();
        $prestation2 = new RelevePrestation();

        $releve->addRelevePrestation($prestation1);
        $releve->addRelevePrestation($prestation2);

        self::assertCount(2, $releve->getRelevesPrestations());
        self::assertTrue($releve->getRelevesPrestations()->contains($prestation1));
        self::assertTrue($releve->getRelevesPrestations()->contains($prestation2));

        $releve->removeRelevePrestation($prestation1);

        self::assertCount(1, $releve->getRelevesPrestations());
        self::assertFalse($releve->getRelevesPrestations()->contains($prestation1));
        self::assertTrue($releve->getRelevesPrestations()->contains($prestation2));
        self::assertNull($prestation1->getReleveJournalier());
        self::assertSame($releve, $prestation2->getReleveJournalier());
    }

    public function testAddReleveEquipement(): void
    {
        $releve = $this->createReleve();
        $equipement = new EquipementStation(
            $centre = $this->centre,
            $code = 'Portique',
            $libelle = 'Portique',
            $categorie = CategorieEquipement::PORTIQUE,
        );
        $releveEquipement = new ReleveEquipement();
        $releveEquipement->setEquipement($equipement);

        $releve->addReleveEquipement($releveEquipement);

        self::assertCount(1, $releve->getRelevesEquipements());
        self::assertTrue($releve->getRelevesEquipements()->contains($releveEquipement));
        self::assertSame($releve, $releveEquipement->getReleveJournalier());
    }

    public function testAddingSameEquipementTwice(): void
    {
        $releve = $this->createReleve();
        $equipement = new EquipementStation(
            $centre = $this->centre,
            $code = 'Portique',
            $libelle = 'Portique',
            $categorie = CategorieEquipement::PORTIQUE,
        );
        $releveEquipement = new ReleveEquipement();
        $releveEquipement->setEquipement($equipement);

        $releve->addReleveEquipement($releveEquipement);
        $releve->addReleveEquipement($releveEquipement);

        self::assertCount(1, $releve->getRelevesEquipements());
    }

    public function testRemoveReleveEquipement(): void
    {
        $releve = $this->createReleve();
        $equipement = new EquipementStation(
            $centre = $this->centre,
            $code = 'Portique',
            $libelle = 'Portique',
            $categorie = CategorieEquipement::PORTIQUE,
        );
        $releveEquipement = new ReleveEquipement();
        $releveEquipement->setEquipement($equipement);

        $releve->addReleveEquipement($releveEquipement);
        $releve->removeReleveEquipement($releveEquipement);

        self::assertCount(0, $releve->getRelevesEquipements());
        self::assertFalse($releve->getRelevesEquipements()->contains($releveEquipement));
        self::assertNull($releveEquipement->getReleveJournalier());
    }

    public function testAddTwoEquipementsThenRemoveOne(): void
    {
        $releve = $this->createReleve();
        $equipement1 = new EquipementStation(
            $centre = $this->centre,
            $code = 'Portique',
            $libelle = 'Portique',
            $categorie = CategorieEquipement::PORTIQUE,
        );
        $equipement2 = new EquipementStation(
            $centre = $this->centre,
            $code = 'Aspirateur',
            $libelle = 'Aspirateur',
            $categorie = CategorieEquipement::AUTRE,
        );
        $releveEquipement1 = new ReleveEquipement();
        $releveEquipement2 = new ReleveEquipement();
        $releveEquipement1->setEquipement($equipement1);
        $releveEquipement2->setEquipement($equipement2);

        $releve->addReleveEquipement($releveEquipement1);
        $releve->addReleveEquipement($releveEquipement2);

        self::assertCount(2, $releve->getRelevesEquipements());
        self::assertTrue($releve->getRelevesEquipements()->contains($releveEquipement1));
        self::assertTrue($releve->getRelevesEquipements()->contains($releveEquipement2));

        $releve->removeReleveEquipement($releveEquipement1);

        self::assertCount(1, $releve->getRelevesEquipements());
        self::assertFalse($releve->getRelevesEquipements()->contains($releveEquipement1));
        self::assertTrue($releve->getRelevesEquipements()->contains($releveEquipement2));
        self::assertNull($releveEquipement1->getReleveJournalier());
        self::assertSame($releve, $releveEquipement2->getReleveJournalier());
    }
}
