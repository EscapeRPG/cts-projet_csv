<?php

namespace App\Service\Suivi;

use Symfony\Component\HttpFoundation\Request;

/**
 * Resolves and normalizes HTTP query filters used by monitoring pages.
 */
final class SuiviFiltersResolver
{
    /**
     * Resolves full filter payload from request query parameters.
     *
     * @param Request $request HTTP request.
     *
     * @return array<string, mixed> Normalized filters array.
     */
    public function resolveFromRequest(Request $request): array
    {
        return [
            'annee' => $request->query->getInt('annee') ?: null,
            'mois' => $this->collectFromVariants($request, 'mois'),
            'reseau' => $this->collectFromVariants($request, 'reseau'),
            'societe' => $this->collectFromVariants($request, 'societe'),
            'centre' => $this->collectFromVariants($request, 'centre'),
            'controleur' => $this->collectFromVariants($request, 'controleur'),
            'type' => $this->collectFromVariants($request, 'type'),
            'vehicule' => $this->collectFromVariants($request, 'vehicule'),
            'vehicule_filter_present' => $request->query->has('vehicule_filter_present'),
        ];
    }

    /**
     * Resolves dependent filter selections (company and center).
     *
     * @param Request $request HTTP request.
     *
     * @return array{societe:array<int,string>,centre:array<int,string>} Normalized dependent selections.
     */
    public function resolveDependentSelections(Request $request): array
    {
        return [
            'societe' => $this->collectFromVariants($request, 'societe'),
            'centre' => $this->collectFromVariants($request, 'centre'),
        ];
    }

    /**
     * Collects values from both `name` and `name[]` query variants.
     *
     * @param Request $request HTTP request.
     * @param string $name Query parameter base name.
     *
     * @return array<int, string> Normalized values.
     */
    private function collectFromVariants(Request $request, string $name): array
    {
        $fromName = $request->query->all($name);
        $fromBracket = $request->query->all($name . '[]');

        $raw = [];
        if (is_array($fromName)) {
            $raw = [...$raw, ...$fromName];
        }
        if (is_array($fromBracket)) {
            $raw = [...$raw, ...$fromBracket];
        }

        return $this->normalizeStringArray($raw);
    }

    /**
     * Trims and removes empty entries from a string array.
     *
     * @param array<int, mixed> $values Raw values.
     *
     * @return array<int, string> Normalized non-empty values.
     */
    private function normalizeStringArray(array $values): array
    {
        return array_values(array_filter(array_map(
            static fn ($value) => trim((string) $value),
            $values
        )));
    }
}
