<?php

namespace App\Controller;

use App\Form\ChangePasswordFormType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class SecurityController extends AbstractController
{
    /**
     * Displays the login form and authentication errors when available.
     *
     * @param AuthenticationUtils $authenticationUtils Security helper for last username and auth errors.
     *
     * @return Response Rendered login page or redirect to home if already authenticated.
     */
    #[Route(path: '/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();

        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    /**
     * Logout endpoint intercepted by Symfony security firewall.
     *
     * @return void
     */
    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    /**
     * Activates a newly created account and lets the user define their password.
     *
     * @param string $token Activation token from email link.
     * @param Request $request Current HTTP request containing form submission data.
     * @param UserRepository $repo Repository used to resolve the user by activation token.
     * @param UserPasswordHasherInterface $hasher Password hasher for initial password setup.
     * @param EntityManagerInterface $em Doctrine entity manager.
     *
     * @return Response Rendered activation form or redirect to login after successful activation.
     */
    #[Route('/activate/{token}', name: 'app_activate_account')]
    public function activate(
        string                      $token,
        Request                     $request,
        UserRepository              $repo,
        UserPasswordHasherInterface $hasher,
        EntityManagerInterface      $em
    ): Response
    {
        $user = $repo->findOneBy(['activationToken' => $token]);

        if (!$user) {
            throw $this->createNotFoundException('Token invalide');
        }

        $form = $this->createForm(ChangePasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $user->setPassword(
                $hasher->hashPassword($user, $form->get('plainPassword')->getData())
            );
            $user->setIsActive(true);
            $user->setActivationToken(null);

            $em->flush();

            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/activate.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
