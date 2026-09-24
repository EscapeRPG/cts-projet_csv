<?php

namespace App\Tests\Unit\Service\Astikoto;

use App\Entity\{Centre, EquipementStation, ReleveEquipement, ReleveJournalier};
use App\Enum\CategorieEquipement;
use App\Form\ReleveEquipementType;
use App\Form\Model\ReleveEquipementDTO;
use App\Service\Astikoto\ReleveJournalierMapper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Forms;

final class ReleveBorneTest extends TestCase
{
    public function testEmptyAndExplicitZeroSurviveFormSubmission(): void
    {
        foreach (['' => null, '0' => '0.00'] as $input => $expected) {
            $dto = new ReleveEquipementDTO();
            $dto->categorie = CategorieEquipement::BORNE;
            $form = Forms::createFormFactory()->create(ReleveEquipementType::class, $dto);
            $form->submit(['totalCb' => (string) $input, 'totalJetons' => (string) $input]);
            self::assertTrue($form->isSynchronized());
            self::assertSame($expected, $dto->totalCb);
            self::assertSame($expected === null ? null : 0, $dto->totalJetons);
        }
    }

    public function testDraftTotalsRemainEmptyUntilEnteredAndAreRestored(): void
    {
        $centre = new Centre();
        $portique = new EquipementStation($centre, 'P4', 'Portique 4', CategorieEquipement::PORTIQUE);
        $borne = new EquipementStation($centre, 'B4', 'Borne 4', CategorieEquipement::BORNE);
        $borne->associerPortique($portique);
        (new \ReflectionProperty(EquipementStation::class, 'id'))->setValue($portique, 1);
        (new \ReflectionProperty(EquipementStation::class, 'id'))->setValue($borne, 2);
        $p = (new ReleveEquipement())->setEquipement($portique)->setEspeces('20.00');
        $b = (new ReleveEquipement())->setEquipement($borne);
        $releve = new ReleveJournalier();
        (new \ReflectionProperty(ReleveJournalier::class, 'centre'))->setValue($releve, $centre);
        $releve->addReleveEquipement($p)->addReleveEquipement($b);
        $mapper = new ReleveJournalierMapper();
        $dto = $mapper->toDTO($releve);
        self::assertNull($dto->relevesEquipements[1]->totalEspeces);
        $mapper->mapToEntity($dto, $releve);
        self::assertSame('0.00', $b->getEspeces());
        self::assertNull($mapper->toDTO($releve)->relevesEquipements[1]->totalEspeces);
        $dto->relevesEquipements[1]->totalEspeces = '35.00';
        $mapper->mapToEntity($dto, $releve);
        self::assertSame('15.00', $b->getEspeces());
        self::assertSame('35.00', $mapper->toDTO($releve)->relevesEquipements[1]->totalEspeces);
        $dto->relevesEquipements[1]->totalEspeces = '0';
        $mapper->mapToEntity($dto, $releve);
        self::assertSame('-20.00', $b->getEspeces());
        $dto->relevesEquipements[1]->totalEspeces = null;
        $mapper->mapToEntity($dto, $releve);
        self::assertSame('0.00', $b->getEspeces());
        self::assertNull($b->getTotalEspeces());
    }
}
