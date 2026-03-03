<?php

namespace App\Repository\Suivi;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Shared query helpers for activity-tracking repositories.
 */
abstract readonly class AbstractSuiviQueryRepository
{
    protected const array TYPE_FAMILIES = ['VTP', 'VTC', 'CV', 'VOL'];
    protected const array VEHICLE_TYPES = ['CL', 'VL'];

    protected const array TYPE_CTRL_MAP = [
        'VTP' => ['VTP', 'VLCTP', 'VLVT', 'VLVP', 'CLVTP', 'CLCTP'],
        'CV' => ['CV', 'VLCV', 'VLCVC', 'CLCV'],
        'VTC' => ['VTC', 'VLCTC'],
        'VOL' => ['VOL', 'VP', 'VT', 'CLVP', 'CLVT'],
    ];

    /**
     * @param Connection $connection DBAL connection used to execute SQL queries.
     * @param CacheInterface $cache Cache service for query result memoization.
     */
    public function __construct(
        protected Connection $connection,
        protected CacheInterface $cache
    ) {
    }

    /**
     * Applies dimension filters targeting synthesized monthly tables.
     *
     * @param array<string, mixed> $filters Selected filters.
     * @param array<int, string> $where WHERE clauses accumulator.
     * @param array<string, mixed> $params SQL parameters accumulator.
     * @param array<string, mixed> $types SQL parameter types accumulator.
     * @param string|null $yearParam Parameter name to use for years, or null to skip year filtering.
     * @param bool $uniqueMonths Whether to deduplicate month values.
     * @param bool $includeControleur Whether to apply controller filter.
     *
     * @return void
     */
    protected function applySyntheseDimensionFilters(
        array $filters,
        array &$where,
        array &$params,
        array &$types,
        ?string $yearParam = 'annee',
        bool $uniqueMonths = false,
        bool $includeControleur = true
    ): void {
        if ($yearParam !== null) {
            $years = $this->resolveYears($filters);
            $where[] = sprintf('annee IN (:%s)', $yearParam);
            $params[$yearParam] = $years;
            $types[$yearParam] = ArrayParameterType::INTEGER;
        }

        $months = $this->resolveMonths($filters, $uniqueMonths);
        $where[] = 'mois IN (:mois)';
        $params['mois'] = $months;
        $types['mois'] = ArrayParameterType::INTEGER;

        if (!empty($filters['reseau'])) {
            $where[] = 'reseau_nom IN (:reseaux)';
            $params['reseaux'] = $filters['reseau'];
            $types['reseaux'] = ArrayParameterType::STRING;
        }

        if (!empty($filters['societe'])) {
            $where[] = 'societe_nom IN (:societes)';
            $params['societes'] = $filters['societe'];
            $types['societes'] = ArrayParameterType::STRING;
        }

        if (!empty($filters['centre'])) {
            $where[] = 'agr_centre IN (:centres)';
            $params['centres'] = $filters['centre'];
            $types['centres'] = ArrayParameterType::STRING;
        }

        if ($includeControleur && !empty($filters['controleur'])) {
            $where[] = 'salarie_id IN (:controleurs)';
            $params['controleurs'] = $filters['controleur'];
            $types['controleurs'] = ArrayParameterType::INTEGER;
        }
    }

    /**
     * Applies date and dimension filters targeting raw source joins.
     *
     * @param array<string, mixed> $filters Selected filters.
     * @param array<int, string> $where WHERE clauses accumulator.
     * @param array<string, mixed> $params SQL parameters accumulator.
     * @param array<string, mixed> $types SQL parameter types accumulator.
     * @param string $dateColumn SQL date column/expression used for period filtering.
     * @param string $reseauExpression SQL expression used for network filtering.
     * @param string $societeExpression SQL expression used for company filtering.
     * @param string $centreExpression SQL expression used for center filtering.
     * @param string|null $controleurExpression Optional SQL expression for controller filtering.
     *
     * @return void
     */
    protected function applyRawDateAndDimensionFilters(
        array $filters,
        array &$where,
        array &$params,
        array &$types,
        string $dateColumn,
        string $reseauExpression,
        string $societeExpression,
        string $centreExpression,
        ?string $controleurExpression = null
    ): void {
        $this->applyDatePeriodsFilter(
            $where,
            $params,
            $dateColumn,
            $this->resolveYears($filters),
            $this->resolveMonths($filters)
        );

        if (!empty($filters['reseau'])) {
            $where[] = $reseauExpression . ' IN (:reseaux)';
            $params['reseaux'] = $filters['reseau'];
            $types['reseaux'] = ArrayParameterType::STRING;
        }

        if (!empty($filters['societe'])) {
            $where[] = $societeExpression . ' IN (:societes)';
            $params['societes'] = $filters['societe'];
            $types['societes'] = ArrayParameterType::STRING;
        }

        if (!empty($filters['centre'])) {
            $where[] = $centreExpression . ' IN (:centres)';
            $params['centres'] = $filters['centre'];
            $types['centres'] = ArrayParameterType::STRING;
        }

        if ($controleurExpression !== null && !empty($filters['controleur'])) {
            $where[] = $controleurExpression . ' IN (:controleurs)';
            $params['controleurs'] = $filters['controleur'];
            $types['controleurs'] = ArrayParameterType::INTEGER;
        }
    }

    /**
     * Resolves selected years, defaulting to the rolling N-2..N window.
     *
     * @param array<string, mixed> $filters Selected filters.
     *
     * @return array<int, int> Selected years.
     */
    protected function resolveYears(array $filters): array
    {
        if (!empty($filters['annee'])) {
            return [(int)$filters['annee']];
        }

        return [(date('Y') - 2), (date('Y') - 1), (int)date('Y')];
    }

    /**
     * Resolves selected months, optionally returning unique values only.
     *
     * @param array<string, mixed> $filters Selected filters.
     * @param bool $unique Whether to deduplicate months.
     *
     * @return array<int, int> Selected month numbers.
     */
    protected function resolveMonths(array $filters, bool $unique = false): array
    {
        $months = !empty($filters['mois'])
            ? array_map('intval', $filters['mois'])
            : range(1, 12);

        if (!$unique) {
            return $months;
        }

        return array_values(array_unique($months));
    }

    /**
     * Normalizes and validates selected type families.
     *
     * @param array<int, mixed> $selected Raw selected type family values.
     *
     * @return array<int, string> Normalized type families.
     */
    protected function normalizeTypeFamilies(array $selected): array
    {
        $normalized = array_values(array_unique(array_filter(array_map(
            static fn($value) => strtoupper(trim((string)$value)),
            $selected
        ))));

        if (empty($normalized)) {
            return self::TYPE_FAMILIES;
        }

        $filtered = array_values(array_intersect($normalized, self::TYPE_FAMILIES));

        return empty($filtered) ? self::TYPE_FAMILIES : $filtered;
    }

    /**
     * Expands type families to raw control type codes.
     *
     * @param array<int, string> $selectedTypeFamilies Selected type families.
     *
     * @return array<int, string> Raw control type codes.
     */
    protected function buildRawTypesFromFamilies(array $selectedTypeFamilies): array
    {
        $rawTypes = [];
        foreach ($selectedTypeFamilies as $family) {
            $rawTypes = [...$rawTypes, ...(self::TYPE_CTRL_MAP[$family] ?? [])];
        }

        return array_values(array_unique($rawTypes));
    }

    /**
     * Indicates whether all available type families are selected.
     *
     * @param array<int, string> $selectedTypeFamilies Selected type families.
     *
     * @return bool True when all type families are selected.
     */
    protected function isAllTypeFamiliesSelected(array $selectedTypeFamilies): bool
    {
        return count($selectedTypeFamilies) === count(self::TYPE_FAMILIES);
    }

    /**
     * Normalizes and validates selected vehicle categories.
     *
     * @param array<int, mixed> $selected Raw selected vehicle values.
     *
     * @return array<int, string> Normalized vehicle categories.
     */
    protected function normalizeVehicleTypes(array $selected): array
    {
        $normalized = array_values(array_unique(array_filter(array_map(
            static fn($value) => strtoupper(trim((string)$value)),
            $selected
        ))));

        if (empty($normalized)) {
            return self::VEHICLE_TYPES;
        }

        $filtered = array_values(array_intersect($normalized, self::VEHICLE_TYPES));

        return empty($filtered) ? self::VEHICLE_TYPES : $filtered;
    }

    /**
     * Indicates whether all available vehicle categories are selected.
     *
     * @param array<int, string> $selectedVehicleTypes Selected vehicle categories.
     *
     * @return bool True when all vehicle categories are selected.
     */
    protected function isAllVehicleTypesSelected(array $selectedVehicleTypes): bool
    {
        return count($selectedVehicleTypes) === count(self::VEHICLE_TYPES);
    }

    /**
     * Applies a vehicle-specific filter on raw control type column.
     *
     * @param array<int, string> $where WHERE clauses accumulator.
     * @param string $column SQL column/expression containing raw control type.
     * @param array<int, string> $selectedVehicleTypes Selected vehicle categories.
     *
     * @return void
     */
    protected function applyVehicleFilter(array &$where, string $column, array $selectedVehicleTypes): void
    {
        if ($this->isAllVehicleTypesSelected($selectedVehicleTypes)) {
            return;
        }

        if ($selectedVehicleTypes === ['CL']) {
            $where[] = $column . " LIKE 'CL%'";
            return;
        }

        if ($selectedVehicleTypes === ['VL']) {
            $where[] = $column . " NOT LIKE 'CL%'";
        }
    }

    /**
     * Returns cached rows for a payload or computes them via resolver callback.
     *
     * @param string $prefix Cache key prefix.
     * @param array<string, mixed> $payload Input payload used to build stable cache key.
     * @param callable $resolver Resolver returning query rows on cache miss.
     *
     * @return array<int, array<string, mixed>> Query rows.
     */
    protected function cachedRows(string $prefix, array $payload, callable $resolver): array
    {
        $cacheKey = $prefix . '_' . sha1(json_encode($this->normalizeForCache($payload)));

        try {
            return $this->cache->get($cacheKey, function (ItemInterface $item) use ($resolver) {
                $item->expiresAfter(600);
                return $resolver();
            });
        } catch (\Throwable) {
            return $resolver();
        }
    }

    /**
     * Normalizes values recursively to build deterministic cache keys.
     *
     * @param mixed $value Arbitrary value.
     *
     * @return mixed Normalized value preserving semantic equality.
     */
    protected function normalizeForCache(mixed $value): mixed
    {
        if (is_array($value)) {
            $isList = array_keys($value) === range(0, count($value) - 1);
            if ($isList) {
                $normalized = array_map(fn($v) => $this->normalizeForCache($v), $value);
                sort($normalized);
                return $normalized;
            }

            $normalized = [];
            $keys = array_keys($value);
            sort($keys);
            foreach ($keys as $key) {
                $normalized[$key] = $this->normalizeForCache($value[$key]);
            }

            return $normalized;
        }

        return $value;
    }

    /**
     * Applies an optimized date-period filter using one global range or month ranges.
     *
     * @param array<int, string> $where WHERE clauses accumulator.
     * @param array<string, mixed> $params SQL parameters accumulator.
     * @param string $column SQL date column/expression.
     * @param array<int, int> $years Selected years.
     * @param array<int, int> $months Selected months.
     *
     * @return void
     */
    protected function applyDatePeriodsFilter(
        array &$where,
        array &$params,
        string $column,
        array $years,
        array $months
    ): void {
        $years = array_values(array_unique(array_map('intval', $years)));
        sort($years);

        $months = array_values(array_unique(array_map('intval', $months)));
        sort($months);

        $allMonths = $months === range(1, 12);
        if ($allMonths) {
            $minYear = min($years);
            $maxYear = max($years);

            $where[] = sprintf('%s >= :period_global_from AND %s < :period_global_to', $column, $column);
            $params['period_global_from'] = sprintf('%04d-01-01 00:00:00', $minYear);
            $params['period_global_to'] = sprintf('%04d-01-01 00:00:00', $maxYear + 1);
            return;
        }

        $ranges = [];
        $rangeIndex = 0;
        foreach ($years as $year) {
            foreach ($months as $month) {
                $fromKey = sprintf('period_%d_from', $rangeIndex);
                $toKey = sprintf('period_%d_to', $rangeIndex);

                $from = new \DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $year, $month));
                $to = $from->modify('+1 month');

                $params[$fromKey] = $from->format('Y-m-d H:i:s');
                $params[$toKey] = $to->format('Y-m-d H:i:s');

                $ranges[] = sprintf('(%s >= :%s AND %s < :%s)', $column, $fromKey, $column, $toKey);
                $rangeIndex++;
            }
        }

        if ($ranges !== []) {
            $where[] = '(' . implode(' OR ', $ranges) . ')';
        }
    }
}
