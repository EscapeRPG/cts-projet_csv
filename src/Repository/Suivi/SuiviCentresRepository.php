<?php

namespace App\Repository\Suivi;

use Doctrine\DBAL\Exception;

final readonly class SuiviCentresRepository extends AbstractSuiviQueryRepository
{
    /**
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

        $sql = "
            SELECT
                agr_centre,
                MIN(societe_nom) AS societe_nom,
                MIN(reseau_nom) AS reseau_nom,
                annee,
                mois,
                SUM({$nbExpr}) AS nb_controles,
                SUM({$caExpr}) AS ca
            FROM synthese_controles
        ";

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= "
            GROUP BY agr_centre, annee, mois
            ORDER BY societe_nom, agr_centre, annee, mois
        ";

        return $this->connection->executeQuery($sql, $params, $types)->fetchAllAssociative();
    }

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
}

