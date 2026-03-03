<?php

namespace App\Repository\Suivi;

use Doctrine\DBAL\Exception;

/**
 * Provides center-level aggregates for activity monitoring pages.
 */
final readonly class SuiviCentresRepository extends AbstractSuiviQueryRepository
{
    /**
     * Returns center-level aggregates for selected filters.
     *
     * @param array<string, mixed> $filters Selected filters.
     *
     * @return array<int, array<string, mixed>> Aggregated rows by center and period.
     *
     * @throws Exception
     */
    public function fetchCentres(array $filters = []): array
    {
        $selectedTypeFamilies = $this->normalizeTypeFamilies($filters['type'] ?? []);
        $selectedVehicleTypes = $this->normalizeVehicleTypes($filters['vehicule'] ?? []);

        $where = [];
        $params = [];
        $types = [];
        $this->applySyntheseDimensionFilters(
            $filters,
            $where,
            $params,
            $types,
            yearParam: 'annees',
            uniqueMonths: true
        );

        [$nbExpr, $caExpr] = $this->buildCentresMetricExpressions($selectedTypeFamilies, $selectedVehicleTypes);
        [$nbProExpr, $nbPartExpr] = $this->buildCentresSplitNbExpressions($selectedTypeFamilies, $selectedVehicleTypes);
        [$caProExpr, $caPartExpr] = $this->buildCentresSplitCaExpressions($selectedTypeFamilies, $selectedVehicleTypes);

        $sql = "
            SELECT
                agr_centre,
                MIN(societe_nom) AS societe_nom,
                MIN(reseau_nom) AS reseau_nom,
                annee,
                mois,
                SUM({$nbExpr}) AS nb_controles,
                SUM({$nbProExpr}) AS nb_pro,
                SUM({$nbPartExpr}) AS nb_part,
                SUM({$caExpr}) AS ca,
                SUM({$caProExpr}) AS ca_pro,
                SUM({$caPartExpr}) AS ca_part
            FROM synthese_controles
        ";

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= "
            GROUP BY agr_centre, annee, mois
            ORDER BY societe_nom, agr_centre, annee, mois
        ";

        return $this->cachedRows(
            'suivi_centres',
            ['filters' => $filters, 'types' => $selectedTypeFamilies, 'vehicle' => $selectedVehicleTypes],
            fn() => $this->connection->executeQuery($sql, $params, $types)->fetchAllAssociative()
        );
    }

    /**
     * Builds SQL expressions for controls count and revenue metrics.
     *
     * @param array<int, string> $selectedTypeFamilies Selected type families.
     * @param array<int, string> $selectedVehicleTypes Selected vehicle categories.
     *
     * @return array{0:string,1:string} Count expression and revenue expression.
     */
    private function buildCentresMetricExpressions(array $selectedTypeFamilies, array $selectedVehicleTypes): array
    {
        $nbByFamilyVehicle = [
            'VTP' => ['VL' => 'nb_vtp', 'CL' => 'nb_clvtp'],
            'CV' => ['VL' => 'nb_cv', 'CL' => 'nb_clcv'],
            'VTC' => ['VL' => 'nb_vtc'],
            'VOL' => ['VL' => 'nb_vol', 'CL' => 'nb_clvol'],
        ];

        $caByFamilyVehicle = [
            'VTP' => ['VL' => 'total_ht_vtp', 'CL' => 'total_ht_clvtp'],
            'CV' => ['VL' => 'total_ht_cv', 'CL' => 'total_ht_clcv'],
            'VTC' => ['VL' => 'total_ht_vtc'],
            'VOL' => ['VL' => 'total_ht_vol', 'CL' => 'total_ht_clvol'],
        ];

        $selectedFamilies = $selectedTypeFamilies;
        if (empty($selectedFamilies)) {
            $selectedFamilies = self::TYPE_FAMILIES;
        }

        $selectedVehicles = $selectedVehicleTypes;
        if (empty($selectedVehicles)) {
            $selectedVehicles = self::VEHICLE_TYPES;
        }

        $nbColumns = [];
        $caColumns = [];

        foreach ($selectedFamilies as $family) {
            foreach ($selectedVehicles as $vehicle) {
                if (isset($nbByFamilyVehicle[$family][$vehicle])) {
                    $nbColumns[] = $nbByFamilyVehicle[$family][$vehicle];
                }
                if (isset($caByFamilyVehicle[$family][$vehicle])) {
                    $caColumns[] = $caByFamilyVehicle[$family][$vehicle];
                }
            }
        }

        $nbColumns = array_values(array_unique($nbColumns));
        $caColumns = array_values(array_unique($caColumns));

        $nbExpr = $nbColumns === [] ? '0' : implode(' + ', $nbColumns);
        $caExpr = $caColumns === [] ? '0' : implode(' + ', $caColumns);

        return [$nbExpr, $caExpr];
    }

    /**
     * Builds SQL expressions for revenue split (professional/individual).
     *
     * @param array<int, string> $selectedTypeFamilies Selected type families.
     * @param array<int, string> $selectedVehicleTypes Selected vehicle categories.
     *
     * @return array{0:string,1:string} Professional and individual revenue expressions.
     */
    private function buildCentresSplitCaExpressions(array $selectedTypeFamilies, array $selectedVehicleTypes): array
    {
        $caPartByFamilyVehicle = [
            'VTP' => ['VL' => 'total_ht_vtp_particuliers', 'CL' => 'total_ht_clvtp_particuliers'],
            'CV' => ['VL' => 'total_ht_cv_particuliers', 'CL' => 'total_ht_clcv_particuliers'],
            'VTC' => ['VL' => 'total_ht_vtc_particuliers'],
            'VOL' => ['VL' => 'total_ht_vol_particuliers', 'CL' => 'total_ht_clvol_particuliers'],
        ];

        $caProByFamilyVehicle = [
            'VTP' => ['VL' => 'total_ht_vtp_professionnels', 'CL' => 'total_ht_clvtp_professionnels'],
            'CV' => ['VL' => 'total_ht_cv_professionnels', 'CL' => 'total_ht_clcv_professionnels'],
            'VTC' => ['VL' => 'total_ht_vtc_professionnels'],
            'VOL' => ['VL' => 'total_ht_vol_professionnels', 'CL' => 'total_ht_clvol_professionnels'],
        ];

        $selectedFamilies = $selectedTypeFamilies === [] ? self::TYPE_FAMILIES : $selectedTypeFamilies;
        $selectedVehicles = $selectedVehicleTypes === [] ? self::VEHICLE_TYPES : $selectedVehicleTypes;

        $caPartColumns = [];
        $caProColumns = [];

        foreach ($selectedFamilies as $family) {
            foreach ($selectedVehicles as $vehicle) {
                if (isset($caPartByFamilyVehicle[$family][$vehicle])) {
                    $caPartColumns[] = $caPartByFamilyVehicle[$family][$vehicle];
                }
                if (isset($caProByFamilyVehicle[$family][$vehicle])) {
                    $caProColumns[] = $caProByFamilyVehicle[$family][$vehicle];
                }
            }
        }

        $caPartColumns = array_values(array_unique($caPartColumns));
        $caProColumns = array_values(array_unique($caProColumns));

        $caPartExpr = $caPartColumns === [] ? '0' : implode(' + ', $caPartColumns);
        $caProExpr = $caProColumns === [] ? '0' : implode(' + ', $caProColumns);

        return [$caProExpr, $caPartExpr];
    }

    /**
     * Builds SQL expressions for controls split (professional/individual).
     *
     * @param array<int, string> $selectedTypeFamilies Selected type families.
     * @param array<int, string> $selectedVehicleTypes Selected vehicle categories.
     *
     * @return array{0:string,1:string} Professional and individual volume expressions.
     */
    private function buildCentresSplitNbExpressions(array $selectedTypeFamilies, array $selectedVehicleTypes): array
    {
        $nbPartByFamilyVehicle = [
            'VTP' => ['VL' => 'nb_vtp_particuliers', 'CL' => 'nb_clvtp_particuliers'],
            'CV' => ['VL' => 'nb_cv_particuliers', 'CL' => 'nb_clcv_particuliers'],
            'VTC' => ['VL' => 'nb_vtc_particuliers'],
            'VOL' => ['VL' => 'nb_vol_particuliers', 'CL' => 'nb_clvol_particuliers'],
        ];

        $nbProByFamilyVehicle = [
            'VTP' => ['VL' => 'nb_vtp_professionnels', 'CL' => 'nb_clvtp_professionnels'],
            'CV' => ['VL' => 'nb_cv_professionnels', 'CL' => 'nb_clcv_professionnels'],
            'VTC' => ['VL' => 'nb_vtc_professionnels'],
            'VOL' => ['VL' => 'nb_vol_professionnels', 'CL' => 'nb_clvol_professionnels'],
        ];

        $selectedFamilies = $selectedTypeFamilies === [] ? self::TYPE_FAMILIES : $selectedTypeFamilies;
        $selectedVehicles = $selectedVehicleTypes === [] ? self::VEHICLE_TYPES : $selectedVehicleTypes;

        $nbPartColumns = [];
        $nbProColumns = [];

        foreach ($selectedFamilies as $family) {
            foreach ($selectedVehicles as $vehicle) {
                if (isset($nbPartByFamilyVehicle[$family][$vehicle])) {
                    $nbPartColumns[] = $nbPartByFamilyVehicle[$family][$vehicle];
                }
                if (isset($nbProByFamilyVehicle[$family][$vehicle])) {
                    $nbProColumns[] = $nbProByFamilyVehicle[$family][$vehicle];
                }
            }
        }

        $nbPartColumns = array_values(array_unique($nbPartColumns));
        $nbProColumns = array_values(array_unique($nbProColumns));

        $nbPartExpr = $nbPartColumns === [] ? '0' : implode(' + ', $nbPartColumns);
        $nbProExpr = $nbProColumns === [] ? '0' : implode(' + ', $nbProColumns);

        return [$nbProExpr, $nbPartExpr];
    }
}
