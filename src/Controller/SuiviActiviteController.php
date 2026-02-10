<?php

namespace App\Controller;

use App\Repository\SuiviActiviteRepository;
use App\Service\Suivi\SuiviControleursService;
use App\Service\Suivi\SuiviFiltersProvider;
use App\Service\Suivi\SuiviSyntheseBuilder;
use Doctrine\DBAL\Exception;
use Psr\Cache\InvalidArgumentException;
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
        $synthese = $builder->build($rows);

        return $this->render('suivis/activite.html.twig', array_merge(
            $this->getCommonViewData($filters, $filtersProvider),
            ['synthese' => $synthese]
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
        $synthese = $builder->build($rows);

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
        SuiviFiltersProvider    $filtersProvider
    ): Response
    {
        $filters = $this->buildFilters($request);

        $rows = $repo->fetchSyntheseRows($filters);
        $synthese = $builder->build($rows);

        // TODO : focusProService->getFocusProData($synthese)

        return $this->render('suivis/focus-pro.html.twig', array_merge(
            $this->getCommonViewData($filters, $filtersProvider),
            ['synthese' => $synthese]
        ));
    }

    /*
     * Récupère les listes nécessaires à la création des filtres
     */
    private function buildFilters(Request $request): array
    {
        return [
            'annee' => $request->query->getInt('annee') ?: null,
            'mois' => array_filter($request->query->all('mois')),
            'societe' => array_filter($request->query->all('societe')),
            'centre' => array_filter($request->query->all('centre')),
            'controleur' => array_filter($request->query->all('controleur')),
        ];
    }

    /**
     * @throws InvalidArgumentException
     *
     * Permet d'inclure les filtres dans chacune des fonctions précédentes
     */
    private function getCommonViewData(array $filters, SuiviFiltersProvider $filtersProvider): array
    {
        $filtersData = $filtersProvider->getFilters();

        return [
            'filters' => $filters,
            'selected' => $filters,
            'anneeCourante' => $filters['annee'],
            'annees' => $filtersData['annees'],
            'mois' => $filtersData['mois'],
            'societes' => $filtersData['societes'],
            'centres' => $filtersData['centres'],
            'controleurs' => $filtersData['controleurs'],
        ];
    }
}
