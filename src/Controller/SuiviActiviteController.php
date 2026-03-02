<?php

namespace App\Controller;

use App\Repository\SuiviActiviteRepository;
use App\Service\Suivi\SuiviControleursService;
use App\Service\Suivi\SuiviFiltersProvider;
use App\Service\Suivi\SuiviProService;
use App\Service\Suivi\SuiviSyntheseBuilder;
use Doctrine\DBAL\Exception;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class SuiviActiviteController extends AbstractController
{
    /**
     * @throws Exception
     * @throws InvalidArgumentException
     *
     * Affiche un tableau récapitulatif de toutes les informations détaillées de chaque contrôleur
     */
    #[Route('/suivi/activite', name: 'app_suivi_activite')]
    public function index(
        Request                 $request,
        SuiviActiviteRepository $repo,
        SuiviSyntheseBuilder    $builder,
        SuiviFiltersProvider    $filtersProvider
    ): Response
    {
        $filters = $this->buildFilters($request);

        $rows = $repo->fetchSyntheseRows($filters);
        $synthese = $builder->buildSynthese($rows);
        $activityTotals = $builder->buildActivityTotals($synthese);

        return $this->render('suivis/activite.html.twig', array_merge(
            $this->getCommonViewData($filters, $filtersProvider),
            [
                'synthese' => $synthese,
                'societeTotals' => $activityTotals['societes'],
                'globalTotals' => $activityTotals['global'],
            ]
        ));
    }

    /**
     * @throws Exception
     * @throws InvalidArgumentException
     *
     * Affiche les statistiques de chaque contrôleur
     *  - Nombre de contrôles
     *  - Prix moyen pratiqué
     *  - Temps moyen de contrôle
     *  - Taux de refus moyen
     *  - Rapport de contrôles pour particuliers/professionnels
     */
    #[Route('/suivi/controleurs', name: 'app_suivi_controleurs')]
    public function suiviControleurs(
        Request                 $request,
        SuiviActiviteRepository $repo,
        SuiviSyntheseBuilder    $builder,
        SuiviControleursService $controleursService,
        SuiviFiltersProvider    $filtersProvider
    ): Response
    {
        $filters = $this->buildFilters($request);

        $rows = $repo->fetchSyntheseRows($filters);
        $synthese = $builder->buildSynthese($rows);

        [$controleursStats, $moyennesGlobales] = $controleursService->getControleursStats($synthese);

        return $this->render('suivis/controleurs.html.twig', array_merge(
            $this->getCommonViewData($filters, $filtersProvider),
            [
                'controleursStats' => $controleursStats,
                'moyennesGlobales' => $moyennesGlobales,
            ]
        ));
    }

    /**
     * @throws Exception
     * @throws InvalidArgumentException
     *
     * Affiche les détails relatifs aux clients professionnels
     */
    #[Route('/suivi/focus-pro', name: 'app_suivi_focus_pro')]
    public function suiviFocusPro(
        Request                 $request,
        SuiviActiviteRepository $repo,
        SuiviSyntheseBuilder    $builder,
        SuiviFiltersProvider    $filtersProvider,
        SuiviProService         $focusProService,
    ): Response
    {
        $filters = $this->buildFilters($request);

        $rows = $repo->fetchProClients($filters);
        $synthese = $builder->buildClientPro($rows);
        $proCharts = $this->buildProMonthlyCharts($rows);

        $allClients = $focusProService->getFocusPro($synthese);
        $summary = $this->buildProSummary($allClients);

        $perPage = 25;
        $totalItems = count($allClients);
        $totalPages = max(1, (int)ceil($totalItems / $perPage));
        $page = max(1, min($request->query->getInt('page', 1), $totalPages));
        $offset = ($page - 1) * $perPage;
        $clients = array_slice($allClients, $offset, $perPage);

        $pagination = [
            'page' => $page,
            'per_page' => $perPage,
            'total_items' => $totalItems,
            'total_pages' => $totalPages,
            'has_previous' => $page > 1,
            'has_next' => $page < $totalPages,
            'previous_page' => $page > 1 ? $page - 1 : 1,
            'next_page' => $page < $totalPages ? $page + 1 : $totalPages,
        ];

        return $this->render('suivis/professionnels.html.twig', array_merge(
            $this->getCommonViewData($filters, $filtersProvider),
            [
                'clients' => $clients,
                'summary' => $summary,
                'proCharts' => $proCharts,
                'pagination' => $pagination,
            ]
        ));
    }

    /**
     * @throws Exception
     * @throws InvalidArgumentException
     */
    #[Route('/suivi/centres', name: 'app_suivi_centres')]
    public function suiviCentres(
        Request                 $request,
        SuiviActiviteRepository $repo,
        SuiviFiltersProvider    $filtersProvider,
    ): Response
    {
        $filters = $this->buildFilters($request);

        $rows = $repo->fetchCentres($filters);
        $centres = $this->buildCentresRows($rows);
        $summary = $this->buildProSummary($centres);
        $proCharts = $this->buildProMonthlyCharts($rows);

        return $this->render('suivis/centres.html.twig', array_merge(
            $this->getCommonViewData($filters, $filtersProvider),
            [
                'clients' => $centres,
                'summary' => $summary,
                'proCharts' => $proCharts,
            ]
        ));
    }

    /**
     * @throws InvalidArgumentException
     * @throws Exception
     */
    #[Route('/suivi/filters/dependent', name: 'app_suivi_filters_dependent', methods: ['GET'])]
    public function dependentFilters(
        Request $request,
        SuiviFiltersProvider $filtersProvider,
    ): JsonResponse
    {
        $societesFromSociete = $request->query->all('societe');
        $societesFromBracket = $request->query->all('societe[]');

        $rawSocietes = [];
        if (is_array($societesFromSociete)) {
            $rawSocietes = [...$rawSocietes, ...$societesFromSociete];
        }
        if (is_array($societesFromBracket)) {
            $rawSocietes = [...$rawSocietes, ...$societesFromBracket];
        }

        $centresFromCentre = $request->query->all('centre');
        $centresFromBracket = $request->query->all('centre[]');

        $rawCentres = [];
        if (is_array($centresFromCentre)) {
            $rawCentres = [...$rawCentres, ...$centresFromCentre];
        }
        if (is_array($centresFromBracket)) {
            $rawCentres = [...$rawCentres, ...$centresFromBracket];
        }

        $societes = array_values(array_filter(array_map(
            static fn($societe) => trim((string)$societe),
            $rawSocietes
        )));
        $centres = array_values(array_filter(array_map(
            static fn($centre) => trim((string)$centre),
            $rawCentres
        )));

        $filtersData = $filtersProvider->getFilters([
            'societe' => $societes,
            'centre' => $centres,
        ]);

        return $this->json([
            'centres' => array_values($filtersData['centres']),
            'controleurs' => array_values(array_map(static fn($controleur) => [
                'id' => (string)$controleur['id'],
                'nom' => (string)$controleur['nom'],
                'prenom' => (string)$controleur['prenom'],
            ], $filtersData['controleurs'])),
        ]);
    }

    /*
     * Récupère les listes nécessaires à la création des filtres
     */
    private function buildFilters(Request $request): array
    {
        return [
            'annee' => $request->query->getInt('annee') ?: null,
            'mois' => array_filter($request->query->all('mois')),
            'reseau' => $request->query->all('reseau'),
            'societe' => array_filter($request->query->all('societe')),
            'centre' => array_filter($request->query->all('centre')),
            'controleur' => array_filter($request->query->all('controleur')),
            'type' => array_filter($request->query->all('type')),
            'vehicule' => array_filter($request->query->all('vehicule')),
        ];
    }

    /**
     * @throws InvalidArgumentException|Exception
     *
     * Permet d'inclure les filtres dans chacune des fonctions précédentes
     */
    private function getCommonViewData(array $filters, SuiviFiltersProvider $filtersProvider): array
    {
        $filtersData = $filtersProvider->getFilters($filters);

        return [
            'filters' => $filters,
            'selected' => $filters,
            'anneeCourante' => $filters['annee'],
            'annees' => $filtersData['annees'],
            'mois' => $filtersData['mois'],
            'reseaux' => $filtersData['reseaux'],
            'societes' => $filtersData['societes'],
            'centres' => $filtersData['centres'],
            'controleurs' => $filtersData['controleurs'],
            'types_controles' => $filtersData['types_controles'] ?? ['VTP', 'VTC', 'CV', 'VOL'],
            'vehicules' => $filtersData['vehicules'] ?? ['VL', 'CL'],
        ];
    }

    private function buildProSummary(array $clients): array
    {
        $summary = [
            'ca_now' => 0.0,
            'ca_n1' => 0.0,
            'ca_n2' => 0.0,
            'vol_now' => 0,
            'vol_n1' => 0,
            'vol_n2' => 0,
            'per_ctrl_n1' => 0.0,
            'per_ctrl_n2' => 0.0,
            'per_ca_n1' => 0.0,
            'per_ca_n2' => 0.0,
        ];

        foreach ($clients as $client) {
            $summary['ca_now'] += (float)$client['ca_now'];
            $summary['ca_n1'] += (float)$client['ca_n1'];
            $summary['ca_n2'] += (float)$client['ca_n2'];
            $summary['vol_now'] += (int)$client['nb_ctrl_now'];
            $summary['vol_n1'] += (int)$client['nb_ctrl_n1'];
            $summary['vol_n2'] += (int)$client['nb_ctrl_n2'];
        }

        $summary['per_ctrl_n1'] = $summary['vol_n1'] !== 0
            ? (($summary['vol_now'] - $summary['vol_n1']) / $summary['vol_n1']) * 100
            : 0.0;
        $summary['per_ctrl_n2'] = $summary['vol_n2'] !== 0
            ? (($summary['vol_now'] - $summary['vol_n2']) / $summary['vol_n2']) * 100
            : 0.0;
        $summary['per_ca_n1'] = $summary['ca_n1'] !== 0.0
            ? (($summary['ca_now'] - $summary['ca_n1']) / $summary['ca_n1']) * 100
            : 0.0;
        $summary['per_ca_n2'] = $summary['ca_n2'] !== 0.0
            ? (($summary['ca_now'] - $summary['ca_n2']) / $summary['ca_n2']) * 100
            : 0.0;

        return $summary;
    }

    private function buildProMonthlyCharts(array $rows): array
    {
        $yearNow = (int)date('Y');
        $years = [$yearNow, $yearNow - 1, $yearNow - 2];

        $ca = [];
        $volumes = [];
        foreach ($years as $year) {
            $ca[$year] = array_fill(1, 12, 0.0);
            $volumes[$year] = array_fill(1, 12, 0);
        }

        foreach ($rows as $row) {
            $year = (int)($row['annee'] ?? 0);
            $month = (int)($row['mois'] ?? 0);

            if (!in_array($year, $years, true) || $month < 1 || $month > 12) {
                continue;
            }

            $ca[$year][$month] += (float)($row['ca'] ?? 0);
            $volumes[$year][$month] += (int)($row['nb_controles'] ?? 0);
        }

        return [
            'years' => $years,
            'ca' => $ca,
            'volumes' => $volumes,
        ];
    }

    private function buildCentresRows(array $rows): array
    {
        $yearNow = (int)date('Y');
        $yearN1 = $yearNow - 1;
        $yearN2 = $yearNow - 2;

        $centres = [];

        foreach ($rows as $row) {
            $centre = strtoupper((string)($row['agr_centre'] ?? 'Centre inconnu'));
            $societe = (string)($row['societe_nom'] ?? 'Société inconnue');
            $reseau = (string)($row['reseau_nom'] ?? '');
            $key = $societe . '|' . $centre;

            if (!isset($centres[$key])) {
                $centres[$key] = [
                    'nom' => $centre,
                    'societe' => $societe,
                    'reseau' => $reseau,
                    'ca_client_pro' => 0.0,
                    'ca_now' => 0.0,
                    'ca_n1' => 0.0,
                    'ca_n2' => 0.0,
                    'nb_ctrl_now' => 0,
                    'nb_ctrl_n1' => 0,
                    'nb_ctrl_n2' => 0,
                    'per_n1' => 0.0,
                    'per_n2' => 0.0,
                ];
            }

            $annee = (int)($row['annee'] ?? 0);
            $ca = (float)($row['ca'] ?? 0);
            $nbControles = (int)($row['nb_controles'] ?? 0);

            if ($annee === $yearNow) {
                $centres[$key]['ca_now'] += $ca;
                $centres[$key]['nb_ctrl_now'] += $nbControles;
            } elseif ($annee === $yearN1) {
                $centres[$key]['ca_n1'] += $ca;
                $centres[$key]['nb_ctrl_n1'] += $nbControles;
            } elseif ($annee === $yearN2) {
                $centres[$key]['ca_n2'] += $ca;
                $centres[$key]['nb_ctrl_n2'] += $nbControles;
            }

            $centres[$key]['ca_client_pro'] += $ca;
        }

        foreach ($centres as &$centre) {
            $centre['per_n1'] = $centre['nb_ctrl_n1'] !== 0
                ? (($centre['nb_ctrl_now'] - $centre['nb_ctrl_n1']) / $centre['nb_ctrl_n1']) * 100
                : ($centre['nb_ctrl_now'] === 0 ? 0.0 : 100.0);

            $centre['per_n2'] = $centre['nb_ctrl_n2'] !== 0
                ? (($centre['nb_ctrl_now'] - $centre['nb_ctrl_n2']) / $centre['nb_ctrl_n2']) * 100
                : ($centre['nb_ctrl_now'] === 0 ? 0.0 : 100.0);
        }
        unset($centre);

        usort($centres, static fn(array $a, array $b) => $b['ca_client_pro'] <=> $a['ca_client_pro']);

        return $centres;
    }
}
