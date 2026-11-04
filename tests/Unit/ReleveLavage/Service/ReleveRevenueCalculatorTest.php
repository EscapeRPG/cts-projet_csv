<?php

namespace App\Tests\Unit\ReleveLavage\Service;

use App\Organisation\Entity\Centre;
use App\ReleveLavage\Repository\ReleveEquipementRepository;
use App\ReleveLavage\Repository\RelevePrestationRepository;
use App\ReleveLavage\Repository\ReleveProduitRepository;
use App\ReleveLavage\Service\ReleveRevenueCalculator;
use PHPUnit\Framework\TestCase;

final class ReleveRevenueCalculatorTest extends TestCase
{
    public function testAnnualBreakdownKeepsCategoriesSeparateAndUsesCalendarYear(): void
    {
        $station = new Centre();
        $repositories = [];
        foreach ([ReleveEquipementRepository::class, ReleveProduitRepository::class, RelevePrestationRepository::class] as $index => $class) {
            $repository = $this->createMock($class);
            $repository->expects(self::once())->method('sumRevenueForPeriod')
                ->willReturnCallback(function (Centre $actualStation, \DateTimeImmutable $from, \DateTimeImmutable $until) use ($station, $index): int {
                    self::assertSame($station, $actualStation);
                    self::assertSame('2024-01-01 00:00:00', $from->format('Y-m-d H:i:s'));
                    self::assertSame('2025-01-01 00:00:00', $until->format('Y-m-d H:i:s'));
                    return ($index + 1) * 1234;
                });
            $repositories[] = $repository;
        }

        $revenue = (new ReleveRevenueCalculator(...$repositories))->getAnnualRevenueBreakdown(
            $station,
            new \DateTimeImmutable('2024-02-29 15:30:00'),
        );
        self::assertSame(1234, $revenue->equipementsCents);
        self::assertSame(2468, $revenue->produitsCents);
        self::assertSame(3702, $revenue->prestationsCents);
        self::assertSame(7404, $revenue->getTotalCents());
    }

    public function testAnnualRevenuesUseFullCalendarYearsAndSumAllCategories(): void
    {
        $station = new Centre();
        $repositories = [];
        foreach ([ReleveEquipementRepository::class, ReleveProduitRepository::class, RelevePrestationRepository::class] as $class) {
            $repository = $this->createMock($class);
            $repository->expects(self::once())->method('sumRevenueByDate')
                ->willReturnCallback(function (Centre $actualStation, \DateTimeImmutable $from, \DateTimeImmutable $until) use ($station): array {
                    self::assertSame($station, $actualStation);
                    self::assertSame('01-01 00:00:00', $from->format('m-d H:i:s'));
                    self::assertSame('2024-01-01', $from->format('Y-m-d'));
                    self::assertSame('2027-01-01', $until->format('Y-m-d'));
                    return [
                        ['date' => new \DateTimeImmutable('2025-12-31'), 'revenueCents' => 10000],
                        ['date' => new \DateTimeImmutable('2026-01-01'), 'revenueCents' => 10001],
                        ['date' => new \DateTimeImmutable('2026-01-31'), 'revenueCents' => 4999],
                    ];
                });
            $repositories[] = $repository;
        }

        $calculator = new ReleveRevenueCalculator(...$repositories);
        $date = new \DateTimeImmutable('2026-09-23 15:30:00');
        $months = $calculator->getMonthlyRevenues($station, $date);
        self::assertSame([
            ['annee' => 2025, 'mois' => 12, 'revenueCents' => 30000, 'ca' => 300.0],
            ['annee' => 2026, 'mois' => 1, 'revenueCents' => 45000, 'ca' => 450.0],
        ], $months);
        self::assertSame([
            ['year' => 2024, 'revenueCents' => 0, 'evolution' => null],
            ['year' => 2025, 'revenueCents' => 30000, 'evolution' => 50.0],
            ['year' => 2026, 'revenueCents' => 45000, 'evolution' => null],
        ], $calculator->buildAnnualRevenues($months, $date));
        $charts = (new \App\SuiviActivite\Service\SuiviProAnalyticsService())->buildMonthlyCharts($months, 2026);
        self::assertSame(450.0, $charts['ca'][2026][1]);
        self::assertSame(300.0, $charts['ca'][2025][12]);
        self::assertSame(array_fill(1, 12, 0.0), $charts['ca'][2024]);
        self::assertSame(0.0, $charts['ca'][2026][2]);
        self::assertSame(0.0, $calculator->compareRevenues(0, 0));
    }
}
