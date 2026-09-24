<?php

namespace App\Tests\Unit\Service\Astikoto;

use App\Entity\Centre;
use App\Repository\ReleveEquipementRepository;
use App\Repository\RelevePrestationRepository;
use App\Repository\ReleveProduitRepository;
use App\Service\Astikoto\ReleveRevenueCalculator;
use PHPUnit\Framework\TestCase;

final class ReleveRevenueCalculatorTest extends TestCase
{
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
        $charts = (new \App\Service\Suivi\SuiviProAnalyticsService())->buildMonthlyCharts($months, 2026);
        self::assertSame(450.0, $charts['ca'][2026][1]);
        self::assertSame(300.0, $charts['ca'][2025][12]);
        self::assertSame(array_fill(1, 12, 0.0), $charts['ca'][2024]);
        self::assertSame(0.0, $charts['ca'][2026][2]);
        self::assertSame(0.0, $calculator->compareRevenues(0, 0));
    }
}
