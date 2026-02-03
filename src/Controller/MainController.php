<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class MainController extends AbstractController
{
    #[Route(['/', '/main'], name: 'app_home')]
    public function index(): Response
    {
        $user = $this->getUser();

        return $this->render('main/index.html.twig', [
            'user' => $user,
        ]);
    }
}
