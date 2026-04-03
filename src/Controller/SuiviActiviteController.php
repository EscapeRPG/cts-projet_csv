<?php

namespace App\Controller;

use App\Repository\SuiviActiviteRepository;
use App\Service\Suivi\ArrayPaginator;
use App\Service\Suivi\SuiviCentresAnalyticsService;
use App\Service\Suivi\SuiviCentresScope;
use App\Service\Suivi\SuiviCommonViewDataBuilder;
use App\Service\Suivi\SuiviControleursService;
use App\Service\Suivi\SuiviFiltersResolver;
use App\Service\Suivi\SuiviFiltersProvider;
use App\Service\Suivi\SuiviProAnalyticsService;
use App\Service\Suivi\SuiviProService;
use App\Service\Suivi\SuiviSyntheseBuilder;
use Doctrine\DBAL\Exception;
use Psr\Cache\InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_CTS')]
/**
 * Handles activity monitoring pages and filter-dependent API endpoints.
 */
final class SuiviActiviteController extends AbstractController
{
    /**
     * Activity table column definitions keyed by synthesized metric suffix.
     *
     * @var array<string, array{label:string,count:string,ca:string,price:string,type_family:string,vehicle:string}>
     */
    private const array ACTIVITY_COLUMN_DEFINITIONS = [
        'vtp' => [
            'label' => 'VTP',
            'count' => 'nb_vtp',
            'ca' => 'total_ht_vtp',
            'price' => 'prix_moyen_vtp',
            'type_family' => 'VTP',
            'vehicle' => 'VL',
        ],
        'clvtp' => [
            'label' => 'CLVTP',
            'count' => 'nb_clvtp',
            'ca' => 'total_ht_clvtp',
            'price' => 'prix_moyen_clvtp',
            'type_family' => 'VTP',
            'vehicle' => 'CL',
        ],
        'cv' => [
            'label' => 'CV',
            'count' => 'nb_cv',
            'ca' => 'total_ht_cv',
            'price' => 'prix_moyen_cv',
            'type_family' => 'CV',
            'vehicle' => 'VL',
        ],
        'clcv' => [
            'label' => 'CLCV',
            'count' => 'nb_clcv',
            'ca' => 'total_ht_clcv',
            'price' => 'prix_moyen_clcv',
            'type_family' => 'CV',
            'vehicle' => 'CL',
        ],
        'vtc' => [
            'label' => 'VTC',
            'count' => 'nb_vtc',
            'ca' => 'total_ht_vtc',
            'price' => 'prix_moyen_vtc',
            'type_family' => 'VTC',
            'vehicle' => 'VL',
        ],
        'vol' => [
            'label' => 'VOL',
            'count' => 'nb_vol',
            'ca' => 'total_ht_vol',
            'price' => 'prix_moyen_vol',
            'type_family' => 'VOL',
            'vehicle' => 'VL',
        ],
        'clvol' => [
            'label' => 'CLVOL',
            'count' => 'nb_clvol',
            'ca' => 'total_ht_clvol',
            'price' => 'prix_moyen_clvol',
            'type_family' => 'VOL',
            'vehicle' => 'CL',
        ],
    ];

    /**
     * @param SuiviActiviteRepository $repo Data access facade for activity-related queries.
     * @param SuiviSyntheseBuilder $syntheseBuilder Builds synthesized activity structures.
     * @param SuiviControleursService $controleursService Computes controller-level statistics.
     * @param SuiviProService $focusProService Aggregates professional client focus data.
     * @param SuiviFiltersProvider $filtersProvider Provides available filters and dependent values.
     * @param SuiviFiltersResolver $filtersResolver Resolves and normalizes HTTP query filters.
     * @param SuiviCommonViewDataBuilder $commonViewDataBuilder Builds common Twig view payload for filters.
     * @param SuiviProAnalyticsService $proAnalyticsService Computes summary and chart datasets for pro views.
     * @param SuiviCentresAnalyticsService $centresAnalyticsService Computes center-level analytics datasets.
     * @param ArrayPaginator $arrayPaginator Paginates in-memory arrays for listing pages.
     */
    public function __construct(
        private readonly SuiviActiviteRepository $repo,
        private readonly SuiviSyntheseBuilder $syntheseBuilder,
        private readonly SuiviControleursService $controleursService,
        private readonly SuiviProService $focusProService,
        private readonly SuiviFiltersProvider $filtersProvider,
        private readonly SuiviFiltersResolver $filtersResolver,
        private readonly SuiviCentresScope $centresScope,
        private readonly SuiviCommonViewDataBuilder $commonViewDataBuilder,
        private readonly SuiviProAnalyticsService $proAnalyticsService,
        private readonly SuiviCentresAnalyticsService $centresAnalyticsService,
        private readonly ArrayPaginator $arrayPaginator,
    ) {
    }

    /**
     * Renders the activity overview page with per-company and global totals.
     *
     * @param Request $request Current HTTP request containing filter query parameters.
     *
     * @return Response Rendered HTML response for the activity overview page.
     *
     * @throws Exception If data retrieval from persistence fails.
     * @throws InvalidArgumentException If cache keys or cache arguments are invalid.
     */
    #[Route('/cts/suivi/activite', name: 'app_suivi_activite')]
    public function index(Request $request): Response
    {
        $filters = $this->applyDefaultCurrentYearForYearFilteredPages(
            $this->filtersResolver->resolveFromRequest($request)
        );
        $filters = $this->centresScope->apply($filters);

        $queryFilters = $filters;
        $queryFilters['type'] = [];
        $queryFilters['vehicule'] = [];

        $rows = $this->repo->fetchSyntheseRows($queryFilters);
        $synthese = $this->syntheseBuilder->buildSynthese($rows);
        $activityTotals = $this->syntheseBuilder->buildActivityTotals($synthese);

        return $this->render('cts/suivis/activite.html.twig', array_merge(
            $this->commonViewDataBuilder->build($filters),
            [
                'synthese' => $synthese,
                'societeTotals' => $activityTotals['societes'],
                'globalTotals' => $activityTotals['global'],
            ]
        ));
    }

    /**
     * Renders controller-level performance indicators (volume, price, duration, rejection rate, customer mix).
     *
     * @param Request $request Current HTTP request containing filter query parameters.
     *
     * @return Response Rendered HTML response for the controllers statistics page.
     *
     * @throws Exception If data retrieval from persistence fails.
     * @throws InvalidArgumentException If cache keys or cache arguments are invalid.
     */
    #[Route('/cts/suivi/controleurs', name: 'app_suivi_controleurs')]
    public function suiviControleurs(Request $request): Response
    {
        $filters = $this->applyDefaultCurrentYearForYearFilteredPages(
            $this->applyDefaultVehicleFilter(
                $this->filtersResolver->resolveFromRequest($request)
            )
        );
        $filters = $this->centresScope->apply($filters);

        $rows = $this->repo->fetchSyntheseRows($filters);
        $synthese = $this->syntheseBuilder->buildSynthese($rows);

        [$controleursStats, $moyennesGlobales] = $this->controleursService->getControleursStats($synthese);

        if ($request->isXmlHttpRequest()) {
            return $this->render('cts/suivis/_controleurs_results.html.twig', array_merge(
                $this->commonViewDataBuilder->build($filters),
                [
                    'controleursStats' => $controleursStats,
                    'moyennesGlobales' => $moyennesGlobales,
                ]
            ));
        }

        return $this->render('cts/suivis/controleurs.html.twig', array_merge(
            $this->commonViewDataBuilder->build($filters),
            [
                'controleursStats' => $controleursStats,
                'moyennesGlobales' => $moyennesGlobales,
            ]
        ));
    }

    #[Route('/cts/suivi/controleurs/print', name: 'app_suivi_controleurs_print')]
    public function suiviControleursPrint(Request $request): Response
    {
        $filters = $this->applyDefaultCurrentYearForYearFilteredPages(
            $this->applyDefaultVehicleFilter(
                $this->filtersResolver->resolveFromRequest($request)
            )
        );
        $filters = $this->centresScope->apply($filters);

        $rows = $this->repo->fetchSyntheseRows($filters);
        $synthese = $this->syntheseBuilder->buildSynthese($rows);

        [$controleursStats, $moyennesGlobales] = $this->controleursService->getControleursStats($synthese);

        $view = $this->commonViewDataBuilder->build($filters);
        $view['printFilters'] = $this->buildPrintFilters($filters, $view);

        return $this->render('cts/suivis/print/controleurs.html.twig', array_merge(
            $view,
            [
                'controleursStats' => $controleursStats,
                'moyennesGlobales' => $moyennesGlobales,
            ]
        ));
    }

    /**
     * Renders the professional clients focus page with summary metrics, charts, and pagination.
     *
     * @param Request $request Current HTTP request containing filter and pagination query parameters.
     *
     * @return Response Rendered HTML response for the professional clients page.
     *
     * @throws Exception If data retrieval from persistence fails.
     * @throws InvalidArgumentException If cache keys or cache arguments are invalid.
     */
    #[Route('/cts/suivi/focus-pro', name: 'app_suivi_focus_pro')]
    public function suiviFocusPro(Request $request): Response
    {
        $filters = $this->applyDefaultMonthsToCurrentMonth(
            $this->applyDefaultVehicleFilter(
                $this->filtersResolver->resolveFromRequest($request)
            )
        );
        $filters = $this->centresScope->apply($filters);
        $referenceYear = $this->resolveReferenceYear($filters);

        $rows = $this->repo->fetchProClients($filters);
        $synthese = $this->syntheseBuilder->buildClientPro($rows);
        $proCharts = $this->proAnalyticsService->buildMonthlyCharts($rows, $referenceYear);

        $allClients = $this->focusProService->getFocusPro($synthese);
        $summary = $this->proAnalyticsService->buildSummary($allClients);

        $paginated = $this->arrayPaginator->paginate(
            $allClients,
            $request->query->getInt('page', 1),
            25
        );

        return $this->render('cts/suivis/professionnels.html.twig', array_merge(
            $this->commonViewDataBuilder->build($filters),
            [
                'clients' => $paginated['items'],
                'summary' => $summary,
                'proCharts' => $proCharts,
                'pagination' => $paginated['pagination'],
            ]
        ));
    }

    #[Route('/cts/suivi/focus-pro/print', name: 'app_suivi_focus_pro_print_recap')]
    public function suiviFocusProPrintRecap(Request $request): Response
    {
        $filters = $this->applyDefaultMonthsToCurrentMonth(
            $this->applyDefaultVehicleFilter(
                $this->filtersResolver->resolveFromRequest($request)
            )
        );
        $filters = $this->centresScope->apply($filters);
        $referenceYear = $this->resolveReferenceYear($filters);

        $rows = $this->repo->fetchProClients($filters);
        $synthese = $this->syntheseBuilder->buildClientPro($rows);
        $proCharts = $this->proAnalyticsService->buildMonthlyCharts($rows, $referenceYear);

        $allClients = $this->focusProService->getFocusPro($synthese);
        $summary = $this->proAnalyticsService->buildSummary($allClients);

        $view = $this->commonViewDataBuilder->build($filters);
        $view['printFilters'] = $this->buildPrintFilters($filters, $view);

        return $this->render('cts/suivis/print/professionnels_recap.html.twig', array_merge(
            $view,
            [
                'summary' => $summary,
                'proCharts' => $proCharts,
            ]
        ));
    }

    #[Route('/cts/suivi/focus-pro/print-table', name: 'app_suivi_focus_pro_print_table')]
    public function suiviFocusProPrintTable(Request $request): Response
    {
        $filters = $this->applyDefaultMonthsToCurrentMonth(
            $this->applyDefaultVehicleFilter(
                $this->filtersResolver->resolveFromRequest($request)
            )
        );
        $filters = $this->centresScope->apply($filters);

        $rows = $this->repo->fetchProClients($filters);
        $synthese = $this->syntheseBuilder->buildClientPro($rows);
        $allClients = $this->focusProService->getFocusPro($synthese);

        $view = $this->commonViewDataBuilder->build($filters);
        $view['printFilters'] = $this->buildPrintFilters($filters, $view);

        return $this->render('cts/suivis/print/professionnels_table.html.twig', array_merge(
            $view,
            [
                'clients' => $allClients,
            ]
        ));
    }

    /**
     * Renders the centers page with center-level metrics, revenue split summary, and monthly charts.
     *
     * @param Request $request Current HTTP request containing filter query parameters.
     *
     * @return Response Rendered HTML response for the centers analytics page.
     *
     * @throws Exception If data retrieval from persistence fails.
     * @throws InvalidArgumentException If cache keys or cache arguments are invalid.
     */
    #[Route('/cts/suivi/centres', name: 'app_suivi_centres')]
    public function suiviCentres(Request $request): Response
    {
        $filters = $this->applyDefaultMonthsToCurrentMonth(
            $this->applyDefaultVehicleFilter(
                $this->filtersResolver->resolveFromRequest($request)
            )
        );
        $filters = $this->centresScope->apply($filters);
        $referenceYear = $this->resolveReferenceYear($filters);

        $rows = $this->repo->fetchCentres($filters);
        $centres = $this->centresAnalyticsService->buildCentresRows($rows, $referenceYear);
        $summary = $this->proAnalyticsService->buildSummary($centres);
        $splitSummary = $this->centresAnalyticsService->buildRevenueSplitSummary($centres);
        $proCharts = $this->proAnalyticsService->buildMonthlyCharts($rows, $referenceYear);

        return $this->render('cts/suivis/centres.html.twig', array_merge(
            $this->commonViewDataBuilder->build($filters),
            [
                'clients' => $centres,
                'summary' => $summary,
                'splitSummary' => $splitSummary,
                'proCharts' => $proCharts,
            ]
        ));
    }

    #[Route('/cts/suivi/centres/print', name: 'app_suivi_centres_print_recap')]
    public function suiviCentresPrintRecap(Request $request): Response
    {
        $filters = $this->applyDefaultMonthsToCurrentMonth(
            $this->applyDefaultVehicleFilter(
                $this->filtersResolver->resolveFromRequest($request)
            )
        );
        $filters = $this->centresScope->apply($filters);
        $referenceYear = $this->resolveReferenceYear($filters);

        $rows = $this->repo->fetchCentres($filters);
        $centres = $this->centresAnalyticsService->buildCentresRows($rows, $referenceYear);
        $summary = $this->proAnalyticsService->buildSummary($centres);
        $splitSummary = $this->centresAnalyticsService->buildRevenueSplitSummary($centres);
        $proCharts = $this->proAnalyticsService->buildMonthlyCharts($rows, $referenceYear);

        $view = $this->commonViewDataBuilder->build($filters);
        $view['printFilters'] = $this->buildPrintFilters($filters, $view);

        return $this->render('cts/suivis/print/centres_recap.html.twig', array_merge(
            $view,
            [
                'summary' => $summary,
                'splitSummary' => $splitSummary,
                'proCharts' => $proCharts,
            ]
        ));
    }

    #[Route('/cts/suivi/centres/print-table', name: 'app_suivi_centres_print_table')]
    public function suiviCentresPrintTable(Request $request): Response
    {
        $filters = $this->applyDefaultMonthsToCurrentMonth(
            $this->applyDefaultVehicleFilter(
                $this->filtersResolver->resolveFromRequest($request)
            )
        );
        $filters = $this->centresScope->apply($filters);
        $referenceYear = $this->resolveReferenceYear($filters);

        $rows = $this->repo->fetchCentres($filters);
        $centres = $this->centresAnalyticsService->buildCentresRows($rows, $referenceYear);

        $view = $this->commonViewDataBuilder->build($filters);
        $view['printFilters'] = $this->buildPrintFilters($filters, $view);

        return $this->render('cts/suivis/print/centres_table.html.twig', array_merge(
            $view,
            [
                'clients' => $centres,
            ]
        ));
    }

    #[Route('/cts/suivi/activite/print', name: 'app_suivi_activite_print')]
    public function suiviActivitePrint(Request $request): Response
    {
        $filters = $this->applyDefaultCurrentYearForYearFilteredPages(
            $this->filtersResolver->resolveFromRequest($request)
        );
        $filters = $this->centresScope->apply($filters);

        $queryFilters = $filters;
        $queryFilters['type'] = [];
        $queryFilters['vehicule'] = [];

        $rows = $this->repo->fetchSyntheseRows($queryFilters);
        $synthese = $this->syntheseBuilder->buildSynthese($rows);
        $activityTotals = $this->syntheseBuilder->buildActivityTotals($synthese);

        $view = $this->commonViewDataBuilder->build($filters);
        $view['printFilters'] = $this->buildPrintFilters($filters, $view);

        return $this->render('cts/suivis/print/activite.html.twig', array_merge(
            $view,
            [
                'synthese' => $synthese,
                'societeTotals' => $activityTotals['societes'],
                'globalTotals' => $activityTotals['global'],
            ]
        ));
    }

    /**
     * Returns dependent filter values (centers and controllers) for selected companies/centers.
     *
     * @param Request $request Current HTTP request containing partial filter selections.
     *
     * @return JsonResponse JSON payload with filtered centers and controllers.
     *
     * @throws InvalidArgumentException If cache keys or cache arguments are invalid.
     * @throws Exception If data retrieval from persistence fails.
     */
    #[Route('/cts/suivi/filters/dependent', name: 'app_suivi_filters_dependent', methods: ['GET'])]
    public function dependentFilters(
        Request $request,
    ): JsonResponse
    {
        $selectedFilters = $this->filtersResolver->resolveDependentSelections($request);
        $selectedFilters = $this->centresScope->apply($selectedFilters);
        $filtersData = $this->filtersProvider->getFilters($selectedFilters);

        return $this->json([
            'centres' => array_values($filtersData['centres']),
            'controleurs' => array_values(array_map(static fn ($controleur) => [
                'id' => (string) $controleur['id'],
                'nom' => (string) $controleur['nom'],
                'prenom' => (string) $controleur['prenom'],
            ], $filtersData['controleurs'])),
        ]);
    }

    /**
     * Resolves the reference year used for year-based analytics calculations.
     *
     * @param array<string, mixed> $filters Normalized filters array.
     *
     * @return int Selected filter year when provided, otherwise current year.
     */
    private function resolveReferenceYear(array $filters): int
    {
        return is_int($filters['annee']) && $filters['annee'] > 0
            ? $filters['annee']
            : (int) date('Y');
    }

    /**
     * Ensures pages with explicit year tabs default to the current year.
     *
     * @param array<string, mixed> $filters Normalized filters array.
     *
     * @return array<string, mixed> Filters with current year fallback.
     */
    private function applyDefaultCurrentYearForYearFilteredPages(array $filters): array
    {
        if (!is_int($filters['annee']) || $filters['annee'] <= 0) {
            $filters['annee'] = (int) date('Y');
        }

        return $filters;
    }

    /**
     * Ensures vehicle filters default to VL when none are explicitly selected.
     *
     * @param array<string, mixed> $filters Normalized filters array.
     *
     * @return array<string, mixed> Filters with default vehicle selection.
     */
    private function applyDefaultVehicleFilter(array $filters): array
    {
        $vehicleFilterWasExplicitlySubmitted = (bool)($filters['vehicule_filter_present'] ?? false);

        if (
            !$vehicleFilterWasExplicitlySubmitted
            && (!isset($filters['vehicule']) || !is_array($filters['vehicule']) || $filters['vehicule'] === [])
        ) {
            $filters['vehicule'] = ['VL'];
        }

        return $filters;
    }

    /**
     * Ensures month filters default from January to the current month when omitted.
     *
     * @param array<string, mixed> $filters Normalized filters array.
     *
     * @return array<string, mixed> Filters with default month selection.
     */
    private function applyDefaultMonthsToCurrentMonth(array $filters): array
    {
        if (!isset($filters['mois']) || !is_array($filters['mois']) || $filters['mois'] === []) {
            $filters['mois'] = array_map(
                static fn (int $month): string => (string) $month,
                range(1, (int) date('n'))
            );
        }

        return $filters;
    }

    /**
     * @param array<string, mixed> $filters
     * @param array<string, mixed> $viewData Output of SuiviCommonViewDataBuilder::build()
     * @return array<int, array{label: string, value: string}>
     */
    private function buildPrintFilters(array $filters, array $viewData): array
    {
        $formatList = static function (array $items, int $limit = 12): string {
            $items = array_values(array_filter(array_map(
                static fn ($v): string => trim((string) $v),
                $items
            )));

            if ($items === []) {
                return 'Tous';
            }

            if (count($items) <= $limit) {
                return implode(', ', $items);
            }

            $head = array_slice($items, 0, $limit);
            $rest = count($items) - $limit;
            return implode(', ', $head) . " (+{$rest})";
        };

        $items = [];

        $annee = $filters['annee'] ?? null;
        if (is_int($annee) && $annee > 0) {
            $items[] = ['label' => 'Annee', 'value' => (string) $annee];
        }

        $moisSelected = $filters['mois'] ?? [];
        $moisLabels = [];
        $moisMap = is_array($viewData['mois'] ?? null) ? $viewData['mois'] : [];
        if (is_array($moisSelected)) {
            foreach ($moisSelected as $m) {
                $key = (int) $m;
                if ($key > 0 && isset($moisMap[$key])) {
                    $moisLabels[] = (string) $moisMap[$key];
                }
            }
        }
        $items[] = ['label' => 'Mois', 'value' => $formatList($moisLabels)];

        $items[] = ['label' => 'Reseaux', 'value' => $formatList(is_array($filters['reseau'] ?? null) ? $filters['reseau'] : [])];
        $items[] = ['label' => 'Societes', 'value' => $formatList(is_array($filters['societe'] ?? null) ? $filters['societe'] : [])];

        $centresSelected = is_array($filters['centre'] ?? null) ? $filters['centre'] : [];
        $centreMap = [];
        foreach (($viewData['centres'] ?? []) as $c) {
            if (!is_array($c)) continue;
            $id = trim((string) ($c['agr_centre'] ?? ''));
            $label = trim((string) ($c['label'] ?? $id));
            if ($id !== '') {
                $centreMap[$id] = $label;
            }
        }
        $centresLabels = [];
        foreach ($centresSelected as $id) {
            $id = trim((string) $id);
            if ($id === '' || $id === '__none__') continue;
            $centresLabels[] = $centreMap[$id] ?? $id;
        }
        $items[] = ['label' => 'Centres', 'value' => $formatList($centresLabels)];

        $controleursSelected = is_array($filters['controleur'] ?? null) ? $filters['controleur'] : [];
        $controleurMap = [];
        foreach (($viewData['controleurs'] ?? []) as $c) {
            if (!is_array($c)) continue;
            $id = trim((string) ($c['id'] ?? ''));
            if ($id === '') continue;
            $nom = trim((string) ($c['nom'] ?? ''));
            $prenom = trim((string) ($c['prenom'] ?? ''));
            $controleurMap[$id] = trim($nom . ' ' . $prenom);
        }
        $controleursLabels = [];
        foreach ($controleursSelected as $id) {
            $id = trim((string) $id);
            if ($id === '') continue;
            $controleursLabels[] = $controleurMap[$id] ?? $id;
        }
        $items[] = ['label' => 'Controleurs', 'value' => $formatList($controleursLabels)];

        $items[] = ['label' => 'Types', 'value' => $formatList(is_array($filters['type'] ?? null) ? $filters['type'] : [])];
        $items[] = ['label' => 'Vehicules', 'value' => $formatList(is_array($filters['vehicule'] ?? null) ? $filters['vehicule'] : [])];

        return $items;
    }
}
