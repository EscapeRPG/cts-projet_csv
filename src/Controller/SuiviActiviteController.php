<?php

namespace App\Controller;

use App\Repository\SuiviActiviteRepository;
use App\Service\Suivi\SuiviControleursService;
use App\Service\Suivi\SuiviFiltersProvider;
use App\Service\Suivi\SuiviProService;
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
        $synthese = $builder->buildSynthese($rows);

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

        $allClients = $focusProService->getFocusPro($synthese);
        $summary = $this->buildProSummary($allClients);

        $perPage = 17;
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
                'pagination' => $pagination,
            ]
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
}
