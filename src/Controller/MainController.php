<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\CreateUserType;
use App\Repository\UserRepository;
use App\Service\UserMailer;
use Doctrine\ORM\EntityManagerInterface;
use Random\RandomException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
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
            'controller_name' => 'MainController',
            'user' => $user,
        ]);
    }

    #[Route("/admin/list", name: 'app_users_list')]
    public function list(UserRepository $userRepository): Response
    {
        $users = $userRepository->findAll();

        return $this->render('users/list.html.twig', [
            'users' => $users,
        ]);
    }

    #[Route('/admin/users/promote/{id}', name: 'app_users_promote', requirements: ['id' => '\d+'])]
    public function promoteUser(User $user, EntityManagerInterface $em): Response
    {
        $roles = $user->getRoles();

        if (in_array('ROLE_ADMIN', $roles)) {
            $roles = array_diff($roles, ['ROLE_ADMIN']);
            $roles[] = 'ROLE_IMPORT';
        } else {
            $roles = array_diff($roles, ['ROLE_IMPORT']);
            $roles[] = 'ROLE_ADMIN';
        }

        $user->setRoles(array_values($roles));
        $em->flush();

        return $this->redirectToRoute('app_users_list');
    }

    /**
     * @throws RandomException
     * @throws TransportExceptionInterface
     */
    #[Route("/admin/add", name: 'app_users_add')]
    public function addUser(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher,
        UserMailer $userMailer
    ): Response
    {
        $form = $this->createForm(CreateUserType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var User $user */
            $user = $form->getData();

            $plainPassword = $this->generatePass();

            $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);

            $user->setPassword($hashedPassword);

            $role = $form->get('roles')->getData();

            $user->setRoles([$role]);

            $user->setMustChangePassword(true);

            $em->persist($user);
            $em->flush();

            $userMailer->sendWelcomeEmail($user, $plainPassword);

            $this->addFlash('success', 'Utilisateur créé et mail envoyé.');

            return $this->redirectToRoute('app_users_list');
        }

        return $this->render('users/add.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @throws RandomException
     */
    private function generatePass(int $length = 12): string
    {
        return bin2hex(random_bytes($length / 2));
    }
}
