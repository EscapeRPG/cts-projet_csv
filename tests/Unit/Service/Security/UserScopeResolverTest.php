<?php

namespace App\Tests\Unit\Service\Security;

use App\Entity\Centre;
use App\Entity\Societe;
use App\Entity\User;
use App\Enum\TypeCentre;
use App\Service\Security\UserScopeResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

final class UserScopeResolverTest extends TestCase
{
    public function testAdministratorHasNoScopeRestriction(): void
    {
        $resolver = $this->resolver(null, true);

        self::assertNull($resolver->centreIds(TypeCentre::STATION_LAVAGE));
        self::assertNull($resolver->societeIds());
    }

    public function testSocieteScopeTakesPriorityAndFiltersCentresByType(): void
    {
        $controleTechnique = $this->centre(20, TypeCentre::CONTROLE_TECHNIQUE);
        $station = $this->centre(10, TypeCentre::STATION_LAVAGE);
        $ignoredExplicitCentre = $this->centre(30, TypeCentre::STATION_LAVAGE);
        $societe = $this->societe(2)
            ->addCentre($controleTechnique)
            ->addCentre($station);
        $user = (new User())
            ->addSociete($societe)
            ->addCentre($ignoredExplicitCentre);

        $resolver = $this->resolver($user);

        self::assertSame([10], $resolver->centreIds(TypeCentre::STATION_LAVAGE));
        self::assertSame([20], $resolver->centreIds(TypeCentre::CONTROLE_TECHNIQUE));
        self::assertSame([2], $resolver->societeIds());
    }

    public function testLegacyCentreScopeIsSortedAndDeduplicatesSocietes(): void
    {
        $societe = $this->societe(4);
        $first = $this->centre(8, TypeCentre::CONTROLE_TECHNIQUE)->setSociete($societe);
        $second = $this->centre(3, TypeCentre::CONTROLE_TECHNIQUE)->setSociete($societe);
        $station = $this->centre(1, TypeCentre::STATION_LAVAGE)->setSociete($societe);
        $user = (new User())->addCentre($first)->addCentre($second)->addCentre($station);

        $resolver = $this->resolver($user);

        self::assertSame([3, 8], $resolver->centreIds(TypeCentre::CONTROLE_TECHNIQUE));
        self::assertSame([1], $resolver->centreIds(TypeCentre::STATION_LAVAGE));
        self::assertSame([4], $resolver->societeIds());
    }

    private function resolver(?User $user, bool $isAdmin = false): UserScopeResolver
    {
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);
        $security->method('isGranted')->with('ROLE_ADMIN')->willReturn($isAdmin);

        return new UserScopeResolver($security);
    }

    private function centre(int $id, TypeCentre $type): Centre
    {
        $centre = (new Centre())->setType($type);
        $this->setId($centre, $id);

        return $centre;
    }

    private function societe(int $id): Societe
    {
        $societe = new Societe();
        $this->setId($societe, $id);

        return $societe;
    }

    private function setId(object $entity, int $id): void
    {
        $property = new \ReflectionProperty($entity, 'id');
        $property->setValue($entity, $id);
    }
}
