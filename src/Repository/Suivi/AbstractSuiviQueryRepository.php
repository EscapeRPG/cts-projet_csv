<?php

namespace App\Repository\Suivi;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

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

    public function __construct(
        protected Connection $connection,
        protected CacheInterface $cache
    ) {
    }

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

    protected function resolveYears(array $filters): array
    {
        if (!empty($filters['annee'])) {
            return [(int)$filters['annee']];
        }

        return [(date('Y') - 2), (date('Y') - 1), (int)date('Y')];
    }

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

    protected function buildRawTypesFromFamilies(array $selectedTypeFamilies): array
    {
        $rawTypes = [];
        foreach ($selectedTypeFamilies as $family) {
            $rawTypes = [...$rawTypes, ...(self::TYPE_CTRL_MAP[$family] ?? [])];
        }

        return array_values(array_unique($rawTypes));
    }

    protected function isAllTypeFamiliesSelected(array $selectedTypeFamilies): bool
    {
        return count($selectedTypeFamilies) === count(self::TYPE_FAMILIES);
    }

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

    protected function isAllVehicleTypesSelected(array $selectedVehicleTypes): bool
    {
        return count($selectedVehicleTypes) === count(self::VEHICLE_TYPES);
    }

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

