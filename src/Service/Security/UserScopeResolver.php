<?php

namespace App\Service\Security;

use App\Entity\Centre;
use App\Entity\Societe;
use App\Entity\User;
use App\Enum\TypeCentre;
use Symfony\Bundle\SecurityBundle\Security;

final class UserScopeResolver
{
    public function __construct(private Security $security)
    {
    }

    /**
     * @return list<int>|null Null means "no restriction" (admin).
     */
    public function centreIds(TypeCentre $type): ?array
    {
        $user = $this->security->getUser();

        if ($this->security->isGranted('ROLE_ADMIN')) {
            return null;
        }

        if (!$user instanceof User) {
            return [];
        }

        $centres = [];
        if ($user->getSocietes()->count() > 0) {
            foreach ($user->getSocietes() as $societe) {
                foreach ($societe->getCentre() as $centre) {
                    $centres[] = $centre;
                }
            }
        } else {
            foreach ($user->getCentres() as $centre) {
                $centres[] = $centre;
            }
        }

        return $this->extractCentreIds($centres, $type);
    }

    /**
     * @return list<int>|null Null means "no restriction" (admin).
     */
    public function societeIds(): ?array
    {
        $user = $this->security->getUser();

        if ($this->security->isGranted('ROLE_ADMIN')) {
            return null;
        }

        if (!$user instanceof User) {
            return [];
        }

        $ids = [];
        if ($user->getSocietes()->count() > 0) {
            foreach ($user->getSocietes() as $societe) {
                if ($societe instanceof Societe && $societe->getId() !== null) {
                    $ids[] = $societe->getId();
                }
            }
        } else {
            foreach ($user->getCentres() as $centre) {
                if (!$centre->isControleTechnique()) {
                    continue;
                }

                $id = $centre->getSociete()?->getId();
                if ($id !== null) {
                    $ids[] = $id;
                }
            }
        }

        return $this->normalizeIds($ids);
    }

    /**
     * @param array $centres
     * @return list<int>
     */
    private function extractCentreIds(iterable $centres, TypeCentre $type): array
    {
        $ids = [];
        foreach ($centres as $centre) {
            if (!$centre instanceof Centre || $centre->getType() !== $type) {
                continue;
            }

            $id = $centre->getId();
            if ($id !== null) {
                $ids[] = $id;
            }
        }

        return $this->normalizeIds($ids);
    }

    /**
     * @param list<int> $ids
     * @return list<int>
     */
    private function normalizeIds(array $ids): array
    {
        $ids = array_values(array_unique($ids));
        sort($ids);

        return $ids;
    }
}
