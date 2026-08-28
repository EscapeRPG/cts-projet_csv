<?php

namespace App\Service\Suivi;

use App\Entity\User;
use App\Enum\TypeCentre;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Enforces "centres" visibility constraints for monitoring ("suivi") pages.
 *
 * ROLE_ADMIN: unrestricted.
 * ROLE_CTS: restricted to centres linked to the authenticated user.
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
                  AND c.type = :centre_type
                ORDER BY c.agr_centre
            ",
            [
                'user_id' => $user->getId(),
                'centre_type' => TypeCentre::CONTROLE_TECHNIQUE->value,
            ]
        );

        $allowed = array_values(array_filter(array_map(
            static fn ($value): string => trim((string) $value),
            $allowed
        )));

        if ($allowed !== []) {
            return $allowed;
        }
        return null;
    }
}
