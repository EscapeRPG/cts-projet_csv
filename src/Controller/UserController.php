<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
/**
 * Exposes user-facing profile pages.
 */
final class UserController extends AbstractController
{
    /**
     * Displays the profile page only when the requested user matches the authenticated user.
     *
     * @param UserRepository $userRepository Repository used to retrieve the profile owner.
     * @param int $id User identifier from route parameter.
     *
     * @return Response Rendered profile page or redirect to home when access is not allowed.
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
