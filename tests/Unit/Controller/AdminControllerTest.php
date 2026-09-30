<?php

namespace App\Tests\Unit\Controller;

use App\Controller\AdminController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Security\Core\Role\RoleHierarchy;
use Symfony\Component\Yaml\Yaml;

final class AdminControllerTest extends TestCase
{
    #[DataProvider('permissionCases')]
    public function testSubmittedPermissionIncludesOnlyItsInheritedPermissions(string $field, string $prefix, string $level): void
    {
        $result = $this->computeRoles([$field => [$prefix . $level]]);
        $expected = match ($level) {
            'ADD' => [$prefix . 'ADD', $prefix . 'EDIT', $prefix . 'VIEW'],
            'EDIT' => [$prefix . 'EDIT', $prefix . 'VIEW'],
            'VIEW' => [$prefix . 'VIEW'],
        };
        $expected[] = 'ROLE_CTS';
        if ($field === 'organigrammes') {
            $expected = array_merge($expected, [
                'ROLE_ORGANIGRAM_STRUCT_VIEW',
                'ROLE_ORGANIGRAM_IMMO_VIEW',
                'ROLE_ORGANIGRAM_HIERARCHY_VIEW',
            ]);
        }

        self::assertNull($result['error']);
        self::assertEqualsCanonicalizing($expected, $result['roles']);
    }

    public static function permissionCases(): iterable
    {
        foreach ([
            'societe' => 'ROLE_LIST_SOCIETES_',
            'centre' => 'ROLE_LIST_CENTRES_',
            'voiture' => 'ROLE_LIST_VOITURES_',
            'salaries' => 'ROLE_LIST_SALARIES_',
            'organigrammes' => 'ROLE_ORGANIGRAM_',
            'encours' => 'ROLE_ENCOURS_',
        ] as $field => $prefix) {
            foreach (['ADD', 'EDIT', 'VIEW'] as $level) {
                yield "$field $level" => [$field, $prefix, $level];
            }
        }
    }

    public function testAdministratorKeepsSingleAdminRole(): void
    {
        self::assertSame(['ROLE_ADMIN'], $this->computeRoles(['isAdmin' => true])['roles']);
    }

    private function computeRoles(array $submitted): array
    {
        $values = array_replace([
            'isAdmin' => false,
            'entreprises' => ['ROLE_CTS'],
            'societe' => [],
            'centre' => [],
            'voiture' => [],
            'salaries' => [],
            'organigrammes' => [],
            'encours' => [],
            'organigrammeStructurel' => false,
            'organigrammeImmobilier' => false,
            'organigrammeHierarchique' => false,
        ], $submitted);
        $children = [];
        foreach ($values as $name => $value) {
            $child = $this->createStub(FormInterface::class);
            $child->method('getData')->willReturn($value);
            $children[$name] = $child;
        }
        $form = $this->createStub(FormInterface::class);
        $form->method('get')->willReturnCallback(static fn (string $name): FormInterface => $children[$name]);

        $security = Yaml::parseFile(dirname(__DIR__, 3) . '/config/packages/security.yaml');
        $controller = new AdminController(
            'test@example.com',
            'Test',
            new NullLogger(),
            new RoleHierarchy(array_map(static fn ($roles): array => (array) $roles, $security['security']['role_hierarchy'])),
        );

        return (new \ReflectionMethod(AdminController::class, 'computeRolesFromForm'))->invoke($controller, $form);
    }
}
