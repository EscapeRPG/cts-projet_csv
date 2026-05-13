<?php

namespace App\Service\Encours;

use App\Repository\EncoursBancaireRepository;
use App\Repository\EncoursMontantRepository;
use Doctrine\DBAL\Exception;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\Request;

/**
 * Builds view data for the "encours bancaires" page (filters + table groups + totals).
 */
final readonly class EncoursPageBuilder
{
    public function __construct(
        private EncoursBancaireRepository $encoursBancaireRepository,
        private EncoursMontantRepository $encoursMontantRepository,
        private EncoursService $encoursService,
    ) {
    }

    /**
     * @return array{
     *   type: string,
     *   annees: list<int>,
     *   anneesAffichees: list<int>,
     *   anneeDepuis: int|null,
     *   anneeJusqua: int|null,
     *   societes: array<int,string>,
     *   societeIds: list<int>,
     *   groupes: array<string, array{lignes: array<string, array<string, mixed>>, totauxParAnnee: array<int, float|int>}>,
     *   globalTotalsParAnnee: array<int, float>
     * }
     * @throws Exception
     */
    public function build(Request $request, string $defaultType = 'exploitation', ?array $societeScopeIds = null): array
    {
        $type = trim((string) $request->query->get('type', $defaultType));
        if ($type !== 'exploitation' && $type !== 'immobilier') {
            $type = $defaultType;
        }

        try {
            $rawSociete = $request->query->all('societe');
        } catch (BadRequestException) {
            $rawSociete = $request->query->get('societe');
        }
        $societeIds = $this->normalizeSocieteIds($rawSociete);
        $anneeDepuis = $this->normalizePositiveInt($request->query->get('annee_debut'));
        $anneeJusqua = $this->normalizePositiveInt($request->query->get('annee_fin'));

        if ($anneeDepuis === null) {
            $anneeDepuis = $this->normalizePositiveInt($request->query->get('annee'));
        }

        if ($anneeDepuis !== null && $anneeJusqua !== null && $anneeJusqua < $anneeDepuis) {
            [$anneeDepuis, $anneeJusqua] = [$anneeJusqua, $anneeDepuis];
        }

        $encoursAll = $this->encoursBancaireRepository->getResults(null, $type, $societeScopeIds);

        $annees = $this->encoursMontantRepository->getYears($type, $societeScopeIds);
        $annees = array_values(array_map(static fn($v): int => (int)$v, $annees));
        sort($annees);

        $anneesAffichees = $annees;
        if ($anneeDepuis !== null || $anneeJusqua !== null) {
            $from = $anneeDepuis ?? PHP_INT_MIN;
            $to = $anneeJusqua ?? PHP_INT_MAX;
            $anneesAffichees = array_values(array_filter(
                $annees,
                static fn(int $y): bool => $y >= $from && $y <= $to
            ));
        }

        $encours = $encoursAll;
        if ($societeIds !== []) {
            $wanted = array_fill_keys($societeIds, true);
            $encours = array_values(array_filter($encours, static function ($ligne) use ($wanted): bool {
                $id = $ligne->getSociete()?->getId();
                return $id !== null && isset($wanted[$id]);
            }));
        }

        $societes = $this->buildSocietesOptions($encoursAll);

        $groupes = $this->encoursService->getEncours($encours);
        $globalTotalsParAnnee = $this->encoursService->buildGlobals($groupes);

        return [
            'type' => $type,
            'annees' => $annees,
            'anneesAffichees' => $anneesAffichees,
            'anneeDepuis' => $anneeDepuis,
            'anneeJusqua' => $anneeJusqua,
            'societes' => $societes,
            'societeIds' => $societeIds,
            'groupes' => $groupes,
            'globalTotalsParAnnee' => $globalTotalsParAnnee,
        ];
    }

    private function normalizePositiveInt(mixed $raw): ?int
    {
        if (!is_numeric($raw)) {
            return null;
        }
        $v = (int)$raw;
        return $v > 0 ? $v : null;
    }

    /**
     * Supports both `?societe=1` and `?societe[]=1&societe[]=2` syntaxes.
     *
     * @return list<int>
     */
    private function normalizeSocieteIds(mixed $raw): array
    {
        $values = [];
        if (is_array($raw)) {
            foreach ($raw as $item) {
                if (is_array($item)) {
                    $values = [...$values, ...$item];
                    continue;
                }
                $values[] = $item;
            }
        } elseif (is_string($raw) || is_int($raw) || is_float($raw)) {
            $raw = trim((string)$raw);
            if ($raw !== '') {
                $values = [$raw];
            }
        }

        $ids = [];
        foreach ($values as $v) {
            if (!is_numeric($v)) {
                continue;
            }
            $id = (int)$v;
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        $ids = array_values(array_unique($ids));
        sort($ids);
        return $ids;
    }

    /**
     * @param array<int, mixed> $encoursAll
     * @return array<int,string>
     */
    private function buildSocietesOptions(array $encoursAll): array
    {
        $societes = [];
        foreach ($encoursAll as $ligne) {
            if (!$ligne) {
                continue;
            }
            $societe = $ligne->getSociete();
            $id = $societe?->getId();
            $nom = $societe?->getNom();
            if ($id !== null && $nom !== null && $nom !== '') {
                $societes[$id] = [
                    'nom' => $nom,
                    'order' => $societe?->getOrderViewEncours(),
                ];
            }
        }

        uasort($societes, static function (array $a, array $b): int {
            $oa = is_numeric($a['order'] ?? null) ? (int) $a['order'] : PHP_INT_MAX;
            $ob = is_numeric($b['order'] ?? null) ? (int) $b['order'] : PHP_INT_MAX;
            if ($oa !== $ob) return $oa <=> $ob;
            return strnatcasecmp((string) ($a['nom'] ?? ''), (string) ($b['nom'] ?? ''));
        });

        $out = [];
        foreach ($societes as $id => $row) {
            $out[(int) $id] = (string) ($row['nom'] ?? '');
        }
        return $out;
    }
}
