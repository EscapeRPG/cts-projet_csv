<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\CreateUserType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[IsGranted("ROLE_ADMIN")]
final class AdminController extends AbstractController
{
    #[Route("/admin/list", name: 'app_users_list')]
    public function list(UserRepository $userRepository): Response
    {
        $users = $userRepository->findAll();

        return $this->render('users/list.html.twig', [
            'users' => $users,
        ]);
    }

    /**
     * @throws TransportExceptionInterface
     */
    #[Route("/admin/add", name: 'app_users_add')]
    public function addUser(
        Request $request,
        EntityManagerInterface $em,
        MailerInterface $mailer
    ): Response
    {
        $form = $this->createForm(CreateUserType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var User $user */
            $user = $form->getData();

            $token = Uuid::v4()->toRfc4122();
            $user->setActivationToken($token);
            $user->setIsActive(false);
            $user->setPassword('!');

            $role = $form->get('roles')->getData();
            $user->setRoles([$role]);

            $em->persist($user);
            $em->flush();

            $activationUrl = $this->generateUrl(
                'app_activate_account',
                ['token' => $token],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            $email = new TemplatedEmail()
                ->from(new Address('emilienfrancois.ct@gmail.com', 'Ker-Milo'))
                ->to($user->getEmail())
                ->subject('Votre compte a été créé')
                ->htmlTemplate('emails/user_created.html.twig')
                ->context([
                    'activationUrl' => $activationUrl,
                    'username' => $user->getUsername(),
                ]);

            $mailer->send($email);

            $this->addFlash('success', 'Utilisateur créé et mail envoyé.');

            return $this->redirectToRoute('app_users_list');
        }

        return $this->render('users/add.html.twig', [
            'form' => $form->createView(),
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

    #[Route("/admin/delete/{id}", name: 'app_users_delete', requirements: ['id' => '\d+'])]
    public function deleteUser(User $user, EntityManagerInterface $em, Request $request): Response
    {
        $token = $request->request->get('_token');

        if (!$this->isCsrfTokenValid('delete-user-' . $user->getId(), $token)) {
            $this->addFlash('error', 'Token invalide, suppression annulée.');
            return $this->redirectToRoute('app_users_list');
        }

        $em->remove($user);
        $em->flush();

        $this->addFlash('success', 'Utilisateur supprimé avec succès.');

        return $this->redirectToRoute('app_users_list');
    }
}
