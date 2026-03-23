<?php

namespace App\Controller;

use App\Repository\SuiviActiviteRepository;
use App\Service\Suivi\ArrayPaginator;
use App\Service\Suivi\SuiviCentresAnalyticsService;
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

#[IsGranted('ROLE_ADMIN')]
/**
 * Handles activity monitoring pages and filter-dependent API endpoints.
 */
final class SuiviActiviteController extends AbstractController
{
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
    #[Route('/suivi/activite', name: 'app_suivi_activite')]
    public function index(Request $request): Response
    {
        $filters = $this->applyDefaultCurrentYearForYearFilteredPages(
            $this->applyDefaultVehicleFilter(
                $this->filtersResolver->resolveFromRequest($request)
            )
        );

        $rows = $this->repo->fetchSyntheseRows($filters);
        $synthese = $this->syntheseBuilder->buildSynthese($rows);
        $activityTotals = $this->syntheseBuilder->buildActivityTotals($synthese);

        return $this->render('suivis/activite.html.twig', array_merge(
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
    #[Route('/suivi/controleurs', name: 'app_suivi_controleurs')]
    public function suiviControleurs(Request $request): Response
    {
        $filters = $this->applyDefaultCurrentYearForYearFilteredPages(
            $this->applyDefaultVehicleFilter(
                $this->filtersResolver->resolveFromRequest($request)
            )
        );

        $rows = $this->repo->fetchSyntheseRows($filters);
        $synthese = $this->syntheseBuilder->buildSynthese($rows);

        [$controleursStats, $moyennesGlobales] = $this->controleursService->getControleursStats($synthese);

        if ($request->isXmlHttpRequest()) {
            return $this->render('suivis/_controleurs_results.html.twig', array_merge(
                $this->commonViewDataBuilder->build($filters),
                [
                    'controleursStats' => $controleursStats,
                    'moyennesGlobales' => $moyennesGlobales,
                ]
            ));
        }

        return $this->render('suivis/controleurs.html.twig', array_merge(
            $this->commonViewDataBuilder->build($filters),
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
    #[Route('/suivi/focus-pro', name: 'app_suivi_focus_pro')]
    public function suiviFocusPro(Request $request): Response
    {
        $filters = $this->applyDefaultVehicleFilter(
            $this->filtersResolver->resolveFromRequest($request)
        );
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

        return $this->render('suivis/professionnels.html.twig', array_merge(
            $this->commonViewDataBuilder->build($filters),
            [
                'clients' => $paginated['items'],
                'summary' => $summary,
                'proCharts' => $proCharts,
                'pagination' => $paginated['pagination'],
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
    #[Route('/suivi/centres', name: 'app_suivi_centres')]
    public function suiviCentres(Request $request): Response
    {
        $filters = $this->applyDefaultVehicleFilter(
            $this->filtersResolver->resolveFromRequest($request)
        );
        $referenceYear = $this->resolveReferenceYear($filters);

        $rows = $this->repo->fetchCentres($filters);
        $centres = $this->centresAnalyticsService->buildCentresRows($rows, $referenceYear);
        $summary = $this->proAnalyticsService->buildSummary($centres);
        $splitSummary = $this->centresAnalyticsService->buildRevenueSplitSummary($centres);
        $proCharts = $this->proAnalyticsService->buildMonthlyCharts($rows, $referenceYear);

        return $this->render('suivis/centres.html.twig', array_merge(
            $this->commonViewDataBuilder->build($filters),
            [
                'clients' => $centres,
                'summary' => $summary,
                'splitSummary' => $splitSummary,
                'proCharts' => $proCharts,
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
    #[Route('/suivi/filters/dependent', name: 'app_suivi_filters_dependent', methods: ['GET'])]
    public function dependentFilters(
        Request $request,
    ): JsonResponse
    {
        $selectedFilters = $this->filtersResolver->resolveDependentSelections($request);
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
}
