<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\CreateUserType;
use App\Repository\CentreRepository;
use App\Repository\SocieteRepository;
use App\Repository\UserRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
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
    private const array ROLE_ENTREPRISES = ['ROLE_CTS', 'ROLE_ASTIKOTO'];
    private const array ROLE_SOCIETE = ['ROLE_LIST_SOCIETES_VIEW', 'ROLE_LIST_SOCIETES_EDIT', 'ROLE_LIST_SOCIETES_ADD'];
    private const array ROLE_CENTRE = ['ROLE_LIST_CENTRES_VIEW', 'ROLE_LIST_CENTRES_EDIT', 'ROLE_LIST_CENTRES_ADD'];
    private const array ROLE_VOITURE = ['ROLE_LIST_VOITURES_VIEW', 'ROLE_LIST_VOITURES_EDIT', 'ROLE_LIST_VOITURES_ADD'];
    private const array ROLE_SALARIES = ['ROLE_LIST_SALARIES_VIEW', 'ROLE_LIST_SALARIES_EDIT', 'ROLE_LIST_SALARIES_ADD'];
    private const array ROLE_ORGANIGRAM_BASE = ['ROLE_ORGANIGRAM_VIEW', 'ROLE_ORGANIGRAM_EDIT', 'ROLE_ORGANIGRAM_ADD'];
    private const array ROLE_ORGANIGRAM_SCOPES = ['ROLE_ORGANIGRAM_STRUCT_VIEW', 'ROLE_ORGANIGRAM_IMMO_VIEW', 'ROLE_ORGANIGRAM_HIERARCHY_VIEW'];
    private const array ROLE_ENCOURS = ['ROLE_ENCOURS_VIEW', 'ROLE_ENCOURS_EDIT', 'ROLE_ENCOURS_ADD'];

    private const array ADD_TO_VIEW = [
        'ROLE_LIST_SOCIETES_ADD' => 'ROLE_LIST_SOCIETES_VIEW',
        'ROLE_LIST_CENTRES_ADD' => 'ROLE_LIST_CENTRES_VIEW',
        'ROLE_LIST_VOITURES_ADD' => 'ROLE_LIST_VOITURES_VIEW',
        'ROLE_LIST_SALARIES_ADD' => 'ROLE_LIST_SALARIES_VIEW',
    ];

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
        $users = $userRepository->findAllWithCentres();

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
        MailerInterface        $mailer,
        SocieteRepository      $societeRepository,
        CentreRepository       $centreRepository,
    ): Response
    {
        $form = $this->createForm(CreateUserType::class, null, [
            'societe_repository' => $societeRepository,
            'centre_repository' => $centreRepository,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var User $user */
            $user = $form->getData();

            $token = Uuid::v4()->toRfc4122();
            $user->setActivationToken($token);
            $user->setIsActive(false);
            $user->setPassword('!');

            $result = $this->computeRolesFromForm($form);
            if ($result['error'] instanceof FormError) {
                // Attach the error to the right place for better UX.
                $target = $result['error_field'] ? $form->get($result['error_field']) : $form;
                $target->addError($result['error']);

                return $this->render('users/add.html.twig', [
                    'form' => $form->createView(),
                ]);
            }

            /** @var array<int, string> $roles */
            $roles = $result['roles'];
            $user->setRoles($roles);

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
     * Updates an existing user (roles + scope).
     */
    #[Route('/admin/users/edit/{id}', name: 'app_users_edit', requirements: ['id' => '\d+'])]
    public function editUser(
        Request $request,
        User $user,
        EntityManagerInterface $em,
        SocieteRepository $societeRepository,
        CentreRepository $centreRepository
    ): Response
    {
        $form = $this->createForm(CreateUserType::class, $user, [
            'societe_repository' => $societeRepository,
            'centre_repository' => $centreRepository,
        ]);

        $this->prefillUserForm($form, $user);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $result = $this->computeRolesFromForm($form);
            if ($result['error'] instanceof FormError) {
                $target = $result['error_field'] ? $form->get($result['error_field']) : $form;
                $target->addError($result['error']);

                return $this->render('users/edit.html.twig', [
                    'form' => $form->createView(),
                    'user' => $user,
                ]);
            }

            /** @var array<int, string> $roles */
            $roles = $result['roles'];
            $user->setRoles($roles);

            try {
                $em->flush();
            } catch (UniqueConstraintViolationException $e) {
                $this->logger->warning('Tentative de mise a jour utilisateur avec username deja existant', [
                    'username' => $user->getUsername(),
                    'email' => $user->getEmail(),
                ]);
                $form->get('username')->addError(new FormError('Ce nom d\'utilisateur existe déjà.'));

                return $this->render('users/edit.html.twig', [
                    'form' => $form->createView(),
                    'user' => $user,
                ]);
            }

            $this->addFlash('success', 'Utilisateur mis à jour.');
            return $this->redirectToRoute('app_users_list');
        }

        return $this->render('users/edit.html.twig', [
            'form' => $form->createView(),
            'user' => $user,
        ]);
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

    private function prefillUserForm(FormInterface $form, User $user): void
    {
        $currentRoles = $user->getRoles();

        $form->get('isAdmin')->setData(in_array('ROLE_ADMIN', $currentRoles, true));
        $form->get('entreprises')->setData(self::intersectRoles($currentRoles, self::ROLE_ENTREPRISES));
        $form->get('societe')->setData(self::intersectRoles($currentRoles, self::ROLE_SOCIETE));
        $form->get('centre')->setData(self::intersectRoles($currentRoles, self::ROLE_CENTRE));
        $form->get('voiture')->setData(self::intersectRoles($currentRoles, self::ROLE_VOITURE));
        $form->get('salaries')->setData(self::intersectRoles($currentRoles, self::ROLE_SALARIES));
        $form->get('organigrammes')->setData(self::intersectRoles($currentRoles, self::ROLE_ORGANIGRAM_BASE));
        $form->get('encours')->setData(self::intersectRoles($currentRoles, self::ROLE_ENCOURS));

        $hasAnyOrgScope = (bool) array_intersect($currentRoles, self::ROLE_ORGANIGRAM_SCOPES);
        $hasOrgBase = (bool) array_intersect($currentRoles, self::ROLE_ORGANIGRAM_BASE);
        if ($hasOrgBase && !$hasAnyOrgScope) {
            // Backward compatibility: old users with only ROLE_ORGANIGRAM_* base perms see all organigrams by default.
            $form->get('organigrammeStructurel')->setData(true);
            $form->get('organigrammeImmobilier')->setData(true);
            $form->get('organigrammeHierarchique')->setData(true);
            return;
        }

        $form->get('organigrammeStructurel')->setData(in_array('ROLE_ORGANIGRAM_STRUCT_VIEW', $currentRoles, true));
        $form->get('organigrammeImmobilier')->setData(in_array('ROLE_ORGANIGRAM_IMMO_VIEW', $currentRoles, true));
        $form->get('organigrammeHierarchique')->setData(in_array('ROLE_ORGANIGRAM_HIERARCHY_VIEW', $currentRoles, true));
    }

    /**
     * @return array{roles: array<int, string>, listPerms: array<int, string>, entreprises: array<int, string>, error: ?FormError, error_field: ?string}
     */
    private function computeRolesFromForm(FormInterface $form): array
    {
        $isAdmin = (bool) $form->get('isAdmin')->getData();

        /** @var array<int, string> $entreprises */
        $entreprises = $form->get('entreprises')->getData() ?? [];
        /** @var array<int, string> $societePerms */
        $societePerms = $form->get('societe')->getData() ?? [];
        /** @var array<int, string> $centrePerms */
        $centrePerms = $form->get('centre')->getData() ?? [];
        /** @var array<int, string> $voiturePerms */
        $voiturePerms = $form->get('voiture')->getData() ?? [];
        /** @var array<int, string> $salariesPerms */
        $salariesPerms = $form->get('salaries')->getData() ?? [];
        /** @var array<int, string> $organigrammesPerms */
        $organigrammesPerms = $form->get('organigrammes')->getData() ?? [];
        /** @var array<int, string> $encoursPerms */
        $encoursPerms = $form->get('encours')->getData() ?? [];

        $orgStruct = (bool) $form->get('organigrammeStructurel')->getData();
        $orgImmo = (bool) $form->get('organigrammeImmobilier')->getData();
        $orgHier = (bool) $form->get('organigrammeHierarchique')->getData();

        $roles = [];
        if ($isAdmin) {
            $roles[] = 'ROLE_ADMIN';
            return [
                'roles' => $roles,
                'listPerms' => [],
                'entreprises' => [],
                'error' => null,
                'error_field' => null,
            ];
        }

        $listPerms = array_merge($societePerms, $centrePerms, $voiturePerms, $salariesPerms, $organigrammesPerms, $encoursPerms);
        $roles = array_merge($roles, $entreprises, $listPerms);

        // Organigram scopes (structurel / immobilier / hierarchique).
        if ($organigrammesPerms !== []) {
            $selectedAny = $orgStruct || $orgImmo || $orgHier;

            // If no specific organigram is selected, consider it as "all" for convenience.
            if (!$selectedAny) {
                $orgStruct = true;
                $orgImmo = true;
                $orgHier = true;
            }

            if ($orgStruct) $roles[] = 'ROLE_ORGANIGRAM_STRUCT_VIEW';
            if ($orgImmo) $roles[] = 'ROLE_ORGANIGRAM_IMMO_VIEW';
            if ($orgHier) $roles[] = 'ROLE_ORGANIGRAM_HIERARCHY_VIEW';
        }

        // If add permission is granted, ensure the corresponding view permission is also present.
        foreach ($roles as $role) {
            $addRole = (string) $role;
            $viewRole = self::ADD_TO_VIEW[$addRole] ?? null;
            if (is_string($viewRole)) {
                $roles[] = $viewRole;
            }
        }

        $roles = array_values(array_unique(array_filter($roles, static fn (string $r): bool => $r !== 'ROLE_USER')));

        if ($entreprises === []) {
            return [
                'roles' => [],
                'listPerms' => $listPerms,
                'entreprises' => $entreprises,
                'error' => new FormError('Veuillez sélectionner au moins une entreprise (CTS et/ou Astikoto).'),
                'error_field' => 'entreprises',
            ];
        }

        if ($listPerms === []) {
            return [
                'roles' => [],
                'listPerms' => $listPerms,
                'entreprises' => $entreprises,
                'error' => new FormError('Veuillez sélectionner au moins un accès (lister et/ou ajouter) sur une ou plusieurs listes.'),
                'error_field' => null,
            ];
        }

        return [
            'roles' => $roles,
            'listPerms' => $listPerms,
            'entreprises' => $entreprises,
            'error' => null,
            'error_field' => null,
        ];
    }

    /**
     * @param array<int, string> $roles
     * @param array<int, string> $allowed
     * @return array<int, string>
     */
    private static function intersectRoles(array $roles, array $allowed): array
    {
        return array_values(array_intersect($roles, $allowed));
    }
}
