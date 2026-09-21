<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\ReleveJournalier;
use App\Entity\ReleveProduit;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ReleveProduitTest extends TestCase
{
    public function testNewReleveProduitHasExpectedDefaults(): void
    {
        $ligne = new ReleveProduit();

        self::assertNull($ligne->getId());
        self::assertNull($ligne->getReleveJournalier());
        self::assertNull($ligne->getDesignation());
        self::assertSame('0.00', $ligne->getCb());
        self::assertSame('0.00', $ligne->getEspeces());
        self::assertSame('0.00', $ligne->getCheque());
        self::assertSame('0.00', $ligne->getBl());
        self::assertSame(0, $ligne->getOrdreAffichage());
        self::assertTrue($ligne->isEmpty());
    }

    public function testAssociationCanBeSet(): void
    {
        $ligne = new ReleveProduit();
        $releve = new ReleveJournalier();

        self::assertSame($ligne, $ligne->setReleveJournalier($releve));
        self::assertSame($releve, $ligne->getReleveJournalier());
    }

    public function testValuesCanBeSet(): void
    {
        $ligne = new ReleveProduit();

        self::assertSame($ligne, $ligne->setDesignation('Shampoing'));
        self::assertSame($ligne, $ligne->setCb('12.50'));
        self::assertSame($ligne, $ligne->setEspeces('20.00'));
        self::assertSame($ligne, $ligne->setCheque('15.25'));
        self::assertSame($ligne, $ligne->setBl('30.00'));
        self::assertSame($ligne, $ligne->setOrdreAffichage(2));

        self::assertSame('Shampoing', $ligne->getDesignation());
        self::assertSame('12.50', $ligne->getCb());
        self::assertSame('20.00', $ligne->getEspeces());
        self::assertSame('15.25', $ligne->getCheque());
        self::assertSame('30.00', $ligne->getBl());
        self::assertSame(2, $ligne->getOrdreAffichage());
        self::assertFalse($ligne->isEmpty());
    }

    #[DataProvider('nonEmptyValueProvider')]
    public function testIsNotEmptyWhenOneValueIsFilled(string $field, string $value): void
    {
        $ligne = new ReleveProduit();

        match ($field) {
            'designation' => $ligne->setDesignation($value),
            'cb' => $ligne->setCb($value),
            'especes' => $ligne->setEspeces($value),
            'cheque' => $ligne->setCheque($value),
            'bl' => $ligne->setBl($value),
        };

        self::assertFalse($ligne->isEmpty());
    }

    public static function nonEmptyValueProvider(): iterable
    {
        yield 'désignation' => ['designation', 'Shampoing'];
        yield 'carte bancaire' => ['cb', '10.00'];
        yield 'espèces' => ['especes', '10.00'];
        yield 'chèque' => ['cheque', '10.00'];
        yield 'bon de lavage' => ['bl', '10.00'];
    }

    #[DataProvider('zeroValueProvider')]
    public function testDecimalZeroFormatsAreConsideredEmpty(string $zero): void
    {
        $ligne = new ReleveProduit()
            ->setDesignation('   ')
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
