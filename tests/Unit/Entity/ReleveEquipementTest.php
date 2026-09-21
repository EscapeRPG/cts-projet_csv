<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\EquipementStation;
use App\Entity\ReleveEquipement;
use App\Entity\ReleveJournalier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ReleveEquipementTest extends TestCase
{
    public function testNewReleveEquipementHasExpectedDefaults(): void
    {
        $ligne = new ReleveEquipement();

        self::assertNull($ligne->getId());
        self::assertNull($ligne->getReleveJournalier());
        self::assertNull($ligne->getEquipement());

        self::assertSame('0.00', $ligne->getCb());
        self::assertSame('0.00', $ligne->getEspeces());
        self::assertSame('0.00', $ligne->getCheque());
        self::assertSame(0, $ligne->getJetons());
        self::assertSame('0.00', $ligne->getBl());

        self::assertTrue($ligne->isEmpty());
    }

    public function testAssociationsCanBeSet(): void
    {
        $ligne = new ReleveEquipement();
        $releve = new ReleveJournalier();

        $equipement = $this->createStub(EquipementStation::class);

        self::assertSame($ligne, $ligne->setReleveJournalier($releve));
        self::assertSame($ligne, $ligne->setEquipement($equipement));

        self::assertSame($releve, $ligne->getReleveJournalier());
        self::assertSame($equipement, $ligne->getEquipement());
    }

    public function testValuesCanBeSet(): void
    {
        $ligne = new ReleveEquipement();

        self::assertSame($ligne, $ligne->setCb('12.50'));
        self::assertSame($ligne, $ligne->setEspeces('20.00'));
        self::assertSame($ligne, $ligne->setCheque('15.25'));
        self::assertSame($ligne, $ligne->setJetons(4));
        self::assertSame($ligne, $ligne->setBl('30.00'));

        self::assertSame('12.50', $ligne->getCb());
        self::assertSame('20.00', $ligne->getEspeces());
        self::assertSame('15.25', $ligne->getCheque());
        self::assertSame(4, $ligne->getJetons());
        self::assertSame('30.00', $ligne->getBl());

        self::assertFalse($ligne->isEmpty());
    }

    #[DataProvider('nonEmptyValueProvider')]
    public function testIsNotEmptyWhenOneValueIsNonZero(
        string $field,
        string|int $value,
    ): void {
        $ligne = new ReleveEquipement();

        match ($field) {
            'cb' => $ligne->setCb($value),
            'especes' => $ligne->setEspeces($value),
            'cheque' => $ligne->setCheque($value),
            'jetons' => $ligne->setJetons($value),
            'bl' => $ligne->setBl($value),
        };

        self::assertFalse($ligne->isEmpty());
    }

    public static function nonEmptyValueProvider(): iterable
    {
        yield 'carte bancaire' => ['cb', '10.00'];
        yield 'espèces' => ['especes', '10.00'];
        yield 'chèque' => ['cheque', '10.00'];
        yield 'jetons' => ['jetons', 1];
        yield 'bon de lavage' => ['bl', '10.00'];
    }

    #[DataProvider('zeroValueProvider')]
    public function testDecimalZeroFormatsAreConsideredEmpty(string $zero): void
    {
        $ligne = new ReleveEquipement()
            ->setCb($zero)
            ->setEspeces($zero)
            ->setCheque($zero)
            ->setBl($zero);

        self::assertTrue($ligne->isEmpty());
    }

    public static function zeroValueProvider(): iterable
    {
        yield 'chaîne vide' => [''];
        yield 'zéro' => ['0'];
        yield 'zéro décimal avec point' => ['0.00'];
        yield 'zéro décimal avec virgule' => ['0,00'];
        yield 'zéro positif' => ['+0.00'];
        yield 'zéro négatif' => ['-0.00'];
        yield 'zéro entouré d’espaces' => [' 0.00 '];
    }
}
