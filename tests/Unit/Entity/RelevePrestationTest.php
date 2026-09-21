<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\ReleveJournalier;
use App\Entity\RelevePrestation;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RelevePrestationTest extends TestCase
{
    public function testNewRelevePrestationHasExpectedDefaults(): void
    {
        $ligne = new RelevePrestation();

        self::assertNull($ligne->getId());
        self::assertNull($ligne->getReleveJournalier());
        self::assertNull($ligne->getNom());
        self::assertSame('0.00', $ligne->getCb());
        self::assertSame('0.00', $ligne->getEspeces());
        self::assertSame('0.00', $ligne->getCheque());
        self::assertSame('0.00', $ligne->getEnCompte());
        self::assertSame('0.00', $ligne->getContrat());
        self::assertSame(0, $ligne->getOrdreAffichage());
        self::assertTrue($ligne->isEmpty());
    }

    public function testAssociationCanBeSet(): void
    {
        $ligne = new RelevePrestation();
        $releve = new ReleveJournalier();

        self::assertSame($ligne, $ligne->setReleveJournalier($releve));
        self::assertSame($releve, $ligne->getReleveJournalier());
    }

    public function testValuesCanBeSet(): void
    {
        $ligne = new RelevePrestation();

        self::assertSame($ligne, $ligne->setNom('Lavage complet'));
        self::assertSame($ligne, $ligne->setCb('12.50'));
        self::assertSame($ligne, $ligne->setEspeces('20.00'));
        self::assertSame($ligne, $ligne->setCheque('15.25'));
        self::assertSame($ligne, $ligne->setEnCompte('25.00'));
        self::assertSame($ligne, $ligne->setContrat('30.00'));
        self::assertSame($ligne, $ligne->setOrdreAffichage(3));

        self::assertSame('Lavage complet', $ligne->getNom());
        self::assertSame('12.50', $ligne->getCb());
        self::assertSame('20.00', $ligne->getEspeces());
        self::assertSame('15.25', $ligne->getCheque());
        self::assertSame('25.00', $ligne->getEnCompte());
        self::assertSame('30.00', $ligne->getContrat());
        self::assertSame(3, $ligne->getOrdreAffichage());
        self::assertFalse($ligne->isEmpty());
    }

    #[DataProvider('nonEmptyValueProvider')]
    public function testIsNotEmptyWhenOneValueIsFilled(string $field, string $value): void
    {
        $ligne = new RelevePrestation();

        match ($field) {
            'nom' => $ligne->setNom($value),
            'cb' => $ligne->setCb($value),
            'especes' => $ligne->setEspeces($value),
            'cheque' => $ligne->setCheque($value),
            'enCompte' => $ligne->setEnCompte($value),
            'contrat' => $ligne->setContrat($value),
        };

        self::assertFalse($ligne->isEmpty());
    }

    public static function nonEmptyValueProvider(): iterable
    {
        yield 'nom' => ['nom', 'Lavage complet'];
        yield 'carte bancaire' => ['cb', '10.00'];
        yield 'espèces' => ['especes', '10.00'];
        yield 'chèque' => ['cheque', '10.00'];
        yield 'en compte' => ['enCompte', '10.00'];
        yield 'contrat' => ['contrat', '10.00'];
    }

    #[DataProvider('zeroValueProvider')]
    public function testDecimalZeroFormatsAreConsideredEmpty(string $zero): void
    {
        $ligne = new RelevePrestation()
            ->setNom('   ')
            ->setCb($zero)
            ->setEspeces($zero)
            ->setCheque($zero)
            ->setEnCompte($zero)
            ->setContrat($zero);

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
