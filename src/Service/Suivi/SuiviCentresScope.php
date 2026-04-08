<?php

namespace App\Service\Suivi;

use App\Entity\User;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Enforces "centres" visibility constraints for monitoring ("suivi") pages.
 *
 * ROLE_ADMIN: unrestricted.
 * ROLE_CTS: restricted to centres linked to the authenticated user (or, as a fallback, the user's Salarie).
 * If no centres are configured for the CTS account, the scope is considered unrestricted.
 */
final readonly class SuiviCentresScope
{
    private const string PARAM_ALLOWED_CENTRES = 'allowed_centres';

    public function __construct(
        private Security $security,
        private Connection $connection,
    ) {
    }

    /**
     * Enriches filters with a server-side centre scope.
     *
     * - Adds `allowed_centres` for the filters provider.
     * - Forces `centre` to remain within the allowed scope.
     *
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function apply(array $filters): array
    {
        $allowed = $this->resolveAllowedCentres();
        if ($allowed === null) {
            return $filters;
        }

        $filters[self::PARAM_ALLOWED_CENTRES] = $allowed;

        $requested = $filters['centre'] ?? [];
        if (!is_array($requested) || $requested === []) {
            // No centre filter submitted: keep the request within allowed scope.
            $filters['centre'] = $allowed;
            return $filters;
        }

        $intersection = array_values(array_intersect($requested, $allowed));
        $filters['centre'] = $intersection !== [] ? $intersection : ['__none__'];

        return $filters;
    }

    /**
     * @return array<int, string>|null Null means unrestricted.
     */
    private function resolveAllowedCentres(): ?array
    {
        if ($this->security->isGranted('ROLE_ADMIN')) {
            return null;
        }

        if (!$this->security->isGranted('ROLE_CTS')) {
            return null;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedException('Utilisateur non authentifié.');
        }

        // Preferred scope: centres explicitly assigned to the user.
        $allowed = $this->connection->fetchFirstColumn(
            "
                SELECT c.agr_centre
                FROM centre c
                INNER JOIN user_centre uc ON uc.centre_id = c.id
                WHERE uc.user_id = :user_id
                ORDER BY c.agr_centre
            ",
            ['user_id' => $user->getId()]
        );

        $allowed = array_values(array_filter(array_map(
            static fn ($value): string => trim((string) $value),
            $allowed
        )));

        if ($allowed !== []) {
            return $allowed;
        }

        // Fallback scope: centres linked to the user's salarie (legacy behaviour).
        $salarie = $user->getSalarie();
        if ($salarie === null) {
            return null;
        }

        $allowed = $this->connection->fetchFirstColumn(
            "
                SELECT c.agr_centre
                FROM centre c
                INNER JOIN salarie_centre sc ON sc.centre_id = c.id
                WHERE sc.salarie_id = :salarie_id
                ORDER BY c.agr_centre
            ",
            ['salarie_id' => $salarie->getId()]
        );

        $allowed = array_values(array_filter(array_map(
            static fn ($value): string => trim((string) $value),
            $allowed
        )));

        return $allowed !== [] ? $allowed : null;
    }
}
