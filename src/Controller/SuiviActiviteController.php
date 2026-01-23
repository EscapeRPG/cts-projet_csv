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
            'societe' => array_filter($request->query->all('societe')),
            'centre' => array_filter($request->query->all('centre')),
            'controleur' => array_filter($request->query->all('controleur')),
        ];

        $data = $repo->getSyntheseGlobale($filters);

        return $this->render('suivi_activite/index.html.twig', [
            'data' => $data,
            'filters' => $filters,
            'selected' => $filters,
            'anneeCourante' => $filters['annee'],
            'annees' => $repo->getYear(),
            'societes' => $repo->getSocietes(),
            'centres' => $repo->getCentres(),
            'controleurs' => $repo->getControleurs(),
        ]);
    }
}
