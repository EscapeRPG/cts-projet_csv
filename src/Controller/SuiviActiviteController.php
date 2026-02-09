<?php

namespace App\Controller;

use Doctrine\DBAL\Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Repository\SuiviActiviteRepository;

#[IsGranted('ROLE_ADMIN')]
final class SuiviActiviteController extends AbstractController
{
    /**
     * @throws Exception
     */
    #[Route('/suivi/activite', name: 'app_suivi_activite')]
    public function index(Request $request, SuiviActiviteRepository $repo): Response
    {
        $filters = [
            'annee' => $request->query->getInt('annee') ?: null,
            'mois' => array_filter($request->query->all('mois')),
            'societe' => array_filter($request->query->all('societe')),
            'centre' => array_filter($request->query->all('centre')),
            'controleur' => array_filter($request->query->all('controleur')),
        ];

        $data = $repo->getSyntheseGlobale($filters);

        return $this->render('suivis/activite.html.twig', [
            'data' => $data,
            'filters' => $filters,
            'selected' => $filters,
            'anneeCourante' => $filters['annee'],
            'annees' => $repo->getYear(),
            'mois' => $repo->getMonth(),
            'societes' => $repo->getSocietes(),
            'centres' => $repo->getCentres(),
            'controleurs' => $repo->getControleurs(),
        ]);
    }

    /**
     * @throws Exception
     */
    #[Route('/suivi/controleurs', name: 'app_suivi_controleurs')]
    public function suiviControleurs(Request $request, SuiviActiviteRepository $repo): Response
    {
        $filters = [
            'annee' => $request->query->getInt('annee') ?: null,
            'mois' => array_filter($request->query->all('mois')),
            'societe' => array_filter($request->query->all('societe')),
            'centre' => array_filter($request->query->all('centre')),
            'controleur' => array_filter($request->query->all('controleur')),
        ];

        $synthese = $repo->getSyntheseGlobale($filters);

        $controleursStats = [];

        // À compléter suivant le besoin
        $totauxGlobaux = [
            'nb_controles' => 0,
            'prix_moyen' => 0,
            'temps_moyen' => 0,
            'taux_refus' => 0,
        ];

        foreach ($synthese as $societe => $centres) {
            foreach ($centres as $centre) {
                foreach ($centre['salaries'] as $salarie) {
                    $id = $salarie['id'];

                    if (!isset($controleursStats[$id])) {
                        $controleursStats[$id] = [
                            'nom' => $salarie['nom'] . ' ' . $salarie['prenom'],
                            'nb_controles' => 0,
                            'total_presta_ht' => 0,
                            'temps_moyen' => 0,
                            'taux_refus' => 0,
                            'nb_particuliers' => 0,
                            'nb_professionnels' => 0,
                        ];
                    }

                    // --- Nombre de contrôles ---
                    $controleursStats[$id]['nb_controles'] += $salarie['nb_controles'];
                    $totauxGlobaux['nb_controles'] += $salarie['nb_controles'];

                    // --- Prix total (pour moyenne) ---
                    $controleursStats[$id]['total_presta_ht'] += $salarie['total_presta_ht'];
                    $totauxGlobaux['prix_moyen'] += $salarie['total_presta_ht'];

                    // --- Temps total (pour moyenne) ---
                    $controleursStats[$id]['temps_moyen'] += $salarie['temps_total'];
                    $totauxGlobaux['temps_moyen'] += $salarie['temps_total'];

                    // --- Taux de refus ---
                    $controleursStats[$id]['taux_refus'] += $salarie['taux_refus'];
                    $totauxGlobaux['taux_refus'] += $salarie['taux_refus'];

                    // --- Nombre de clients particuliers/pros
                    $controleursStats[$id]['nb_particuliers'] += $salarie['nb_particuliers'];
                    $controleursStats[$id]['nb_professionnels'] += $salarie['nb_professionnels'];
                }
            }
        }

        // --- Moyenne de prix des contrôleurs ---
        foreach ($controleursStats as &$c) {
            $c['prix_moyen'] = $c['nb_controles'] > 0 ? $c['total_presta_ht'] / $c['nb_controles'] : 0;
        }

        // --- Moyenne de temps des contrôleurs ---
        foreach ($controleursStats as &$c) {
            $c['temps_moyen'] = $c['nb_controles'] > 0 ? $c['temps_moyen'] / $c['nb_controles'] : 0;
        }

        // --- Moyenne de refus des contrôleurs ---
        foreach ($controleursStats as &$c) {
            $c['taux_refus'] = $c['nb_controles'] > 0 ? ($c['taux_refus'] / $c['nb_controles'] * 100) : 0;
        }

        // --- Répartition particuliers/pros
        foreach ($controleursStats as &$c) {
            $total = $c['nb_particuliers'] + $c['nb_professionnels'];
            $c['pct_part'] = $total > 0 ? ($c['nb_particuliers'] / $total * 100) : 0;
            $c['pct_pro']  = $total > 0 ? ($c['nb_professionnels'] / $total * 100) : 0;
        }

        // --- Moyennes globales pour chaque stat ---
        $statsPourMoyennes = ['nb_controles', 'prix_moyen', 'temps_moyen', 'taux_refus'];
        $moyennesGlobales = [];
        foreach ($statsPourMoyennes as $stat) {
            $valeurs = array_column($controleursStats, $stat);
            $moyennesGlobales[$stat] = count($valeurs) > 0 ? array_sum($valeurs) / count($valeurs) : 0;
        }

        // --- Trier par nombre de contrôles décroissant ---
        usort($controleursStats, fn($a, $b) => $b['nb_controles'] <=> $a['nb_controles']);

        return $this->render('suivis/controleurs.html.twig', [
            'controleursStats' => $controleursStats,
            'moyennesGlobales' => $moyennesGlobales,
            'filters' => $filters,
            'selected' => $filters,
            'anneeCourante' => $filters['annee'],
            'annees' => $repo->getYear(),
            'mois' => $repo->getMonth(),
            'societes' => $repo->getSocietes(),
            'centres' => $repo->getCentres(),
            'controleurs' => $repo->getControleurs(),
        ]);
    }
}
