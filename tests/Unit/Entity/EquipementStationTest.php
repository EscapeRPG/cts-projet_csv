<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Centre;
use App\Entity\EquipementStation;
use App\Enum\CategorieEquipement;
use PHPUnit\Framework\TestCase;

class EquipementStationTest extends TestCase
{
    private Centre $centre;
    private EquipementStation $borne;
    private EquipementStation $portique;
    private EquipementStation $equipement;

    protected function setUp(): void
    {
        parent::setUp();

        $this->centre = new Centre();
    }

    private function createBorne(): EquipementStation
    {
        return new EquipementStation(
            $this->centre,
            'BORNE-1',
            'Borne 1',
            CategorieEquipement::BORNE,
        );
    }

    private function createPortique(): EquipementStation
    {
        return new EquipementStation(
            $this->centre,
            'PORTIQUE-1',
            'Portique 1',
            CategorieEquipement::PORTIQUE,
        );
    }

    private function createAutreEquipement(): EquipementStation
    {
        return new EquipementStation(
            $this->centre,
            'AUTRE-1',
            'Autre équipement',
            CategorieEquipement::AUTRE,
        );
    }

    public function testNewEquipementStation(): void
    {
        $equipement = $this->createAutreEquipement();

        self::assertSame($this->centre, $equipement->getCentre());
        self::assertSame('AUTRE-1', $equipement->getCode());
        self::assertSame('Autre équipement', $equipement->getLibelle());
        self::assertSame(CategorieEquipement::AUTRE, $equipement->getCategorie());
    }

    public function testAssociateBorneWithPortique(): void
    {
        $borne = $this->createBorne();
        $portique = $this->createPortique();

        $borne->associerPortique($portique);

        self::assertSame($portique, $borne->getPortiqueAssocie());
    }

    public function testAssociateNonBorneWithPortique(): void
    {
        $equipement = $this->createAutreEquipement();
        $portique = $this->createPortique();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Seule une borne peut être associée à un portique.');

        $equipement->associerPortique($portique);
    }

    public function testBorneCannotBeAssociatedWithNonPortique(): void
    {
        $borne = $this->createBorne();
        $equipement = $this->createAutreEquipement();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage("L'équipement associé doit être un portique.");

        $borne->associerPortique($equipement);
    }

    public function testBorneAssociatedWithPortiqueCannotChangeCategorie(): void
    {
        $borne = $this->createBorne();
        $portique = $this->createPortique();

        $borne->associerPortique($portique);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage("Un équipement associé à un portique doit rester une borne.");

        $borne->setCategorie(CategorieEquipement::AUTRE);
    }

    public function testAssociateEquipementsFromDifferentCentres(): void
    {
        $autreCentre = new Centre();
        $borne = $this->createBorne();
        $portique = new EquipementStation(
            $autreCentre,
            'PORTIQUE-1',
            'Portique 1',
            CategorieEquipement::PORTIQUE
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage("La borne et le portique doivent appartenir au même centre.");

        $borne->associerPortique($portique);
    }

    public function testPortiqueCanReceiveImportNumber(): void
    {
        $portique = $this->createPortique();

        $portique->setNumeroPortiqueImport(4);

        self::assertSame(4, $portique->getNumeroPortiqueImport());
    }

    public function testNonPortiqueCannotReceiveImportNumber(): void
    {
        $borne = $this->createBorne();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Un numéro de portique importé ne peut être associé qu’à un portique.');

        $borne->setNumeroPortiqueImport(4);
    }

    public function testPortiqueWithImportNumberCannotChangeCategorie(): void
    {
        $portique = $this->createPortique();
        $portique->setNumeroPortiqueImport(4);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Un numéro de portique importé ne peut être associé qu’à un portique.');

        $portique->setCategorie(CategorieEquipement::AUTRE);
    }

    public function testImportNumberMustBePositive(): void
    {
        $portique = $this->createPortique();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Le numéro de portique importé doit être strictement positif.');

        $portique->setNumeroPortiqueImport(0);
    }
}
