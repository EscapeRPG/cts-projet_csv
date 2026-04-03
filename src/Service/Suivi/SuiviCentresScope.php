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
 * ROLE_CTS: restricted to centres linked to the authenticated user's Salarie.
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

        $salarie = $user->getSalarie();
        if ($salarie === null) {
            throw new AccessDeniedException('Compte CTS non lié à un salarié. Veuillez contacter un administrateur.');
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

        if ($allowed === []) {
            throw new AccessDeniedException('Aucun centre n\'est rattaché à ce salarié.');
        }

        return $allowed;
    }
}

