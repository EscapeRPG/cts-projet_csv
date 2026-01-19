<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

#[IsGranted('ROLE_USER')]
final class MainController extends AbstractController
{
    #[Route(['/', '/main'], name: 'app_home')]
    public function index(AuthenticationUtils $authenticationUtils): Response
    {
        $user = $authenticationUtils->getLastUsername();

        return $this->render('main/index.html.twig', [
            'controller_name' => 'MainController',
            'user' => $user,
        ]);
    }
}
