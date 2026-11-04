<?php

namespace App\Tests\Unit\ReleveLavage\Service;

use App\Organisation\Entity\Centre;
use App\Organisation\Entity\EquipementStation;
use App\Organisation\Enum\CategorieEquipement;
use App\ReleveLavage\Entity\ReleveEquipement;
use App\ReleveLavage\Entity\ReleveJournalier;
use App\ReleveLavage\Entity\RelevePrestation;
use App\ReleveLavage\Entity\ReleveProduit;
use App\ReleveLavage\Service\RelevePeriodSummary;
use App\Utilisateur\Entity\User;
use PHPUnit\Framework\TestCase;

final class RelevePeriodSummaryTest extends TestCase
{
    public function testPeriodMergesLinesWithoutCountingTokensOrBorneTotalsAsRevenue(): void
    {
        $user = new User();
        $station = new Centre();
        $equipment = new EquipementStation($station, 'B1', 'Borne 1', CategorieEquipement::BORNE);
        $days = [];
        foreach (['2026-11-01', '2026-11-02'] as $date) {
            $day = ReleveJournalier::create($station, new \DateTimeImmutable($date), $user, new \DateTimeImmutable());
            $day->addReleveEquipement((new ReleveEquipement())->setEquipement($equipment)
                ->setCb('10.01')->setEspeces('2.02')->setJetons(7)->setTotalCb('100.00'));
            $day->addReleveProduit((new ReleveProduit())->setDesignation('Shampoing')->setCb('0.10')->setBl('1.20'));
            $day->addRelevePrestation((new RelevePrestation())->setNom('Nettoyage')->setEnCompte('3.00')->setContrat('4.00'));
            $days[] = $day;
        }
        $days[0]->markAsValidated($user, new \DateTimeImmutable());
        $days[1]->setCommentaire('Commentaire du deuxième jour');
        $summary = (new RelevePeriodSummary())->build($days);

        self::assertSame(2, $summary['count']);
        self::assertSame(1, $summary['validatedCount']);
        self::assertCount(1, $summary['sections']['equipements']['rows']);
        self::assertCount(1, $summary['sections']['produits']['rows']);
        self::assertCount(1, $summary['sections']['prestations']['rows']);
        self::assertSame(14, $summary['sections']['equipements']['totals']['jetons']);
        self::assertSame(2406, $summary['sections']['equipements']['totals']['total']);
        self::assertSame(260, $summary['sections']['produits']['totals']['total']);
        self::assertSame(1400, $summary['sections']['prestations']['totals']['total']);
        self::assertSame(4066, $summary['totalCents']);
        self::assertSame('2026-11-02', $summary['comments'][0]['date']->format('Y-m-d'));
    }

    public function testEmptyPeriodAndDistinctEquipmentWithSameName(): void
    {
        $builder = new RelevePeriodSummary();
        self::assertSame(0, $builder->build([])['totalCents']);
        self::assertSame(0, $builder->build([])['count']);

        $station = new Centre();
        $day = ReleveJournalier::create($station, new \DateTimeImmutable('2026-11-01'), new User(), new \DateTimeImmutable());
        foreach ([1, 2] as $_) {
            $equipment = new EquipementStation($station, 'P'.$_, 'Portique', CategorieEquipement::PORTIQUE);
            $day->addReleveEquipement((new ReleveEquipement())->setEquipement($equipment)->setCb('1.00'));
        }
        $summary = $builder->build([$day]);
        self::assertCount(2, $summary['sections']['equipements']['rows']);
        self::assertSame(200, $summary['totalCents']);
    }
}
