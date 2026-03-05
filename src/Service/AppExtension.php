<?php

namespace App\Service;

use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

/**
 * Exposes application-level Twig globals.
 */
class AppExtension extends AbstractExtension implements GlobalsInterface
{
    /**
     * @param Security $security Security helper used to resolve current user.
     * @param SyntheseMetaProvider $syntheseMetaProvider Provider exposing synthesis metadata.
     */
    public function __construct(
        private Security $security,
        private SyntheseMetaProvider $syntheseMetaProvider
    ) {
    }

    /**
     * Returns Twig global variables.
     *
     * @return array<string, mixed> Twig globals map.
     */
    public function getGlobals(): array
    {
        return [
            'user' => $this->security->getUser(),
            'database_last_update_at' => $this->syntheseMetaProvider->getLastDatabaseUpdateAt(),
        ];
    }
}
