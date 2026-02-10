<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class UserController extends AbstractController
{
    /*
     * Affiche le profil de l'utilisateur connecté
     */
    #[Route('/profile/{id}', name: 'app_profile', requirements: ['id' => '\d+'])]
    public function profile(
        UserRepository $userRepository,
        int            $id,
    ): Response
    {
        $userConnected = $this->getUser();
        $user = $userRepository->findUserById($id);

        if ($userConnected === $user) {
            return $this->render('users/profile.html.twig', [
                'user' => $user,
                'id' => $id
            ]);
        } else {
            return $this->redirectToRoute('app_home');
        }

    }
}
