<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
/**
 * Exposes main authenticated application pages.
 */
final class MainController extends AbstractController
{
    /**
     * Renders the main dashboard page.
     *
     * @return Response Rendered HTML response for the dashboard.
     */
    #[Route(['/', '/main'], name: 'app_home')]
    public function index(): Response
    {
        return $this->render('main/dashboard.html.twig', [
        ]);
    }
}
