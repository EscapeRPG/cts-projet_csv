<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\CreateUserType;
use App\Repository\UserRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[IsGranted("ROLE_ADMIN")]
/**
 * Provides administrative CRUD actions for application users.
 */
final class AdminController extends AbstractController
{
    /**
     * @param string $mailerFromAddress Sender email address used for administrative notifications.
     * @param string $mailerFromName Sender display name used for administrative notifications.
     * @param LoggerInterface $logger Application logger for admin-related warnings and errors.
     */
    public function __construct(
        private readonly string          $mailerFromAddress,
        private readonly string          $mailerFromName,
        private readonly LoggerInterface $logger
    )
    {
    }

    /**
     * Displays the list of application users.
     *
     * @param UserRepository $userRepository Repository used to retrieve users.
     *
     * @return Response Rendered HTML response containing the users list.
     */
    #[Route("/admin/users/list", name: 'app_users_list')]
    public function list(UserRepository $userRepository): Response
    {
        $users = $userRepository->findAll();

        return $this->render('users/list.html.twig', [
            'users' => $users,
        ]);
    }

    /**
     * Creates a new user, persists it, and sends the account activation email.
     *
     * @param Request $request Current HTTP request containing form submission data.
     * @param EntityManagerInterface $em Doctrine entity manager.
     * @param MailerInterface $mailer Mailer service used to send activation emails.
     *
     * @return Response Rendered add form on GET/invalid submit, or redirect after creation.
     */
    #[Route("/admin/users/add", name: 'app_users_add')]
    public function addUser(
        Request                $request,
        EntityManagerInterface $em,
        MailerInterface        $mailer
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

            if ($role === 'ROLE_CTS' && $user->getSalarie() === null) {
                $form->get('salarie')->addError(new FormError('Pour un compte CTS, veuillez sélectionner le salarié associé.'));

                return $this->render('users/add.html.twig', [
                    'form' => $form->createView(),
                ]);
            }

            try {
                $em->persist($user);
                $em->flush();
            } catch (UniqueConstraintViolationException $e) {
                $this->logger->warning('Tentative de création utilisateur avec username déjà existant', [
                    'username' => $user->getUsername(),
                    'email' => $user->getEmail(),
                ]);
                $form->get('username')->addError(new FormError('Ce nom d\'utilisateur existe déjà.'));

                return $this->render('users/add.html.twig', [
                    'form' => $form->createView(),
                ]);
            }

            $activationUrl = $this->generateUrl(
                'app_activate_account',
                ['token' => $token],
                UrlGeneratorInterface::ABSOLUTE_URL
            );
            $supportUrl = $this->generateUrl(
                'app_support',
                ['context' => 'creation'],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            $email = new TemplatedEmail()
                ->from(new Address($this->mailerFromAddress, $this->mailerFromName))
                ->to($user->getEmail())
                ->subject('Votre compte a été créé')
                ->htmlTemplate('emails/user_created.html.twig')
                ->context([
                    'activationUrl' => $activationUrl,
                    'supportUrl' => $supportUrl,
                    'username' => $user->getUsername(),
                ]);

            try {
                $mailer->send($email);
                $this->addFlash('success', 'Utilisateur créé et mail envoyé.');
            } catch (\Throwable $e) {
                $this->logger->error('Echec envoi mail activation utilisateur', [
                    'email' => $user->getEmail(),
                    'username' => $user->getUsername(),
                    'exception' => $e->getMessage(),
                ]);
                $this->addFlash('warning', 'Utilisateur créé, mais le mail d\'activation n\'a pas pu être envoyé.');
            }

            return $this->redirectToRoute('app_users_list');
        }

        return $this->render('users/add.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * Toggles user role between import and administrator privileges.
     *
     * @param User $user User entity resolved from route parameter.
     * @param EntityManagerInterface $em Doctrine entity manager.
     *
     * @return Response Redirect response to the users list.
     */
    #[Route('/admin/users/promote/{id}', name: 'app_users_promote', requirements: ['id' => '\d+'])]
    public function promoteUser(User $user, EntityManagerInterface $em): Response
    {
        $roles = $user->getRoles();

        if (in_array('ROLE_ADMIN', $roles)) {
            $roles = array_diff($roles, ['ROLE_ADMIN']);
            $roles[] = 'ROLE_USER';
        } else {
            $roles = array_diff($roles, ['ROLE_USER']);
            $roles[] = 'ROLE_ADMIN';
        }

        $user->setRoles(array_values($roles));
        $em->flush();

        return $this->redirectToRoute('app_users_list');
    }

    /**
     * Deletes a user after CSRF token validation.
     *
     * @param User $user User entity resolved from route parameter.
     * @param EntityManagerInterface $em Doctrine entity manager.
     * @param Request $request Current HTTP request containing the CSRF token.
     *
     * @return Response Redirect response to the users list.
     */
    #[Route("/admin/users/delete/{id}", name: 'app_users_delete', requirements: ['id' => '\d+'])]
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
