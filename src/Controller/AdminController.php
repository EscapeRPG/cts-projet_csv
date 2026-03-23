<?php

namespace App\Controller;

use App\Entity\Centre;
use App\Entity\Salarie;
use App\Entity\Societe;
use App\Entity\User;
use App\Form\CreateCentreType;
use App\Form\CreateSalarieType;
use App\Form\CreateSocieteType;
use App\Form\CreateUserType;
use App\Repository\CentreRepository;
use App\Repository\SalarieRepository;
use App\Repository\SocieteRepository;
use App\Repository\UserRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
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
 * Provides administrative CRUD actions for users, employees, companies, and centers.
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

    /**
     * Displays employees with one inline edit form per row.
     *
     * @param Request $request Current HTTP request containing pagination query parameters.
     * @param SalarieRepository $salarieRepository Repository used to retrieve employees.
     * @param FormFactoryInterface $formFactory Form factory used to build per-employee forms.
     *
     * @return Response Rendered HTML response containing employees and their forms.
     */
    #[Route("/admin/salaries/list", name: 'app_salaries_list')]
    public function listSalaries(
        Request             $request,
        SalarieRepository    $salarieRepository,
        FormFactoryInterface $formFactory
    ): Response
    {
        $perPage = 30;
        $totalItems = $salarieRepository->count([]);
        $totalPages = max(1, (int)ceil($totalItems / $perPage));
        $page = max(1, min($request->query->getInt('page', 1), $totalPages));
        $offset = ($page - 1) * $perPage;

        // Temporaire: tri simple par nom pour les modifications manuelles.
        // $salaries = $salarieRepository->findBy([], ['nom' => 'ASC'], $perPage, $offset);
        $salaries = $salarieRepository->findPaginatedOrderedBySociete($perPage, $offset);
        $forms = [];

        foreach ($salaries as $salarie) {
            $forms[$salarie->getId()] = $formFactory
                ->createNamed(
                    'salarie_' . $salarie->getId(),
                    CreateSalarieType::class,
                    $salarie,
                    [
                        'csrf_field_name' => '_token',
                        'csrf_token_id' => 'salarie_' . $salarie->getId(),
                    ]
                )
                ->createView();
        }

        return $this->getResponse($salaries, $forms, $page, $perPage, $totalItems, $totalPages);
    }

    /**
     * Creates a new employee entry.
     *
     * @param Request $request Current HTTP request containing form submission data.
     * @param EntityManagerInterface $em Doctrine entity manager.
     *
     * @return Response Rendered add form on GET/invalid submit, or redirect after creation.
     */
    #[Route("/admin/salaries/add", name: 'app_salaries_add')]
    public function addSalarie(
        Request                $request,
        EntityManagerInterface $em
    ): Response
    {
        $form = $this->createForm(CreateSalarieType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $salarie = $form->getData();

            $em->persist($salarie);
            $em->flush();

            $this->addFlash('success', 'Salarié créé.');

            return $this->redirectToRoute('app_salaries_list');
        }

        return $this->render('salaries/add.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * Updates an existing employee using the inline list form.
     *
     * @param Salarie $salarie Employee entity resolved from route parameter.
     * @param Request $request Current HTTP request containing form submission data.
     * @param EntityManagerInterface $em Doctrine entity manager.
     * @param FormFactoryInterface $formFactory Form factory used to rebuild inline forms.
     *
     * @return Response Redirect response on success, or rendered list with validation errors.
     */
    #[Route('/admin/salaries/update/{id}', name: 'app_salaries_update', methods: ['POST'])]
    public function updateSalarie(
        Salarie                $salarie,
        Request                $request,
        EntityManagerInterface $em,
        FormFactoryInterface   $formFactory
    ): Response
    {
        $form = $formFactory->createNamed(
            'salarie_' . $salarie->getId(),
            CreateSalarieType::class,
            $salarie,
            [
                'csrf_field_name' => '_token',
                'csrf_token_id' => 'salarie_' . $salarie->getId(),
            ]
        );

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($salarie);
            $em->flush();

            $this->addFlash('success', "Salarié {$salarie->getNom()} mis à jour.");

            return $this->redirectToRoute('app_salaries_list', [
                'page' => max(1, $request->query->getInt('page', 1)),
            ]);
        }

        $perPage = 25;
        $totalItems = $em->getRepository(Salarie::class)->count([]);
        $totalPages = max(1, (int)ceil($totalItems / $perPage));
        $page = max(1, min($request->query->getInt('page', 1), $totalPages));
        $offset = ($page - 1) * $perPage;
        $salaries = $em->getRepository(Salarie::class)->findBy([], ['nom' => 'ASC'], $perPage, $offset);
        // /** @var SalarieRepository $salarieRepository */
        // $salarieRepository = $em->getRepository(Salarie::class);
        // $salaries = $salarieRepository->findPaginatedOrderedBySociete($perPage, $offset);
        $forms = [];

        foreach ($salaries as $s) {
            if ($s->getId() === $salarie->getId()) {
                $forms[$s->getId()] = $form->createView();
            } else {
                $forms[$s->getId()] = $formFactory
                    ->createNamed('salarie_' . $s->getId(), CreateSalarieType::class, $s)
                    ->createView();
            }
        }

        $this->addFlash('error', 'Erreur dans le formulaire, vérifiez les champs.');

        return $this->getResponse($salaries, $forms, $page, $perPage, $totalItems, $totalPages);
    }

    /**
     * Displays companies with one inline edit form per row.
     *
     * @param SocieteRepository $societeRepository Repository used to retrieve companies.
     * @param FormFactoryInterface $formFactory Form factory used to build per-company forms.
     *
     * @return Response Rendered HTML response containing companies and their forms.
     */
    #[Route('/admin/societes/list', name: 'app_societes_list')]
    public function listSocietes(
        SocieteRepository    $societeRepository,
        FormFactoryInterface $formFactory
    ): Response
    {
        $societes = $societeRepository->findBy([], ['nom' => 'ASC']);
        $forms = [];

        foreach ($societes as $societe) {
            $forms[$societe->getId()] = $formFactory
                ->createNamed(
                    'societe_' . $societe->getId(),
                    CreateSocieteType::class,
                    $societe,
                    [
                        'csrf_field_name' => '_token',
                        'csrf_token_id' => 'societe_' . $societe->getId(),
                    ]
                )
                ->createView();
        }

        return $this->render('societes/list.html.twig', [
            'societes' => $societes,
            'forms' => $forms,
        ]);
    }

    /**
     * Creates a new company entry.
     *
     * @param Request $request Current HTTP request containing form submission data.
     * @param EntityManagerInterface $em Doctrine entity manager.
     *
     * @return Response Rendered add form on GET/invalid submit, or redirect after creation.
     */
    #[Route('/admin/societes/add', name: 'app_societes_add')]
    public function addSociete(
        Request                $request,
        EntityManagerInterface $em
    ): Response
    {
        $form = $this->createForm(CreateSocieteType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var Societe $societe */
            $societe = $form->getData();

            $em->persist($societe);
            $em->flush();

            $this->addFlash('success', 'Société créée.');

            return $this->redirectToRoute('app_societes_list');
        }

        return $this->render('societes/add.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * Updates an existing company using the inline list form.
     *
     * @param Societe $societe Company entity resolved from route parameter.
     * @param Request $request Current HTTP request containing form submission data.
     * @param EntityManagerInterface $em Doctrine entity manager.
     * @param FormFactoryInterface $formFactory Form factory used to rebuild inline forms.
     *
     * @return Response Redirect response on success, or rendered list with validation errors.
     */
    #[Route('/admin/societes/update/{id}', name: 'app_societes_update', methods: ['POST'])]
    public function updateSociete(
        Societe                $societe,
        Request                $request,
        EntityManagerInterface $em,
        FormFactoryInterface   $formFactory
    ): Response
    {
        $form = $formFactory->createNamed(
            'societe_' . $societe->getId(),
            CreateSocieteType::class,
            $societe,
            [
                'csrf_field_name' => '_token',
                'csrf_token_id' => 'societe_' . $societe->getId(),
            ]
        );

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($societe);
            $em->flush();

            $this->addFlash('success', "Société {$societe->getNom()} mise à jour.");

            return $this->redirectToRoute('app_societes_list');
        }

        $societes = $em->getRepository(Societe::class)->findBy([], ['nom' => 'ASC']);
        $forms = [];

        foreach ($societes as $s) {
            if ($s->getId() === $societe->getId()) {
                $forms[$s->getId()] = $form->createView();
            } else {
                $forms[$s->getId()] = $formFactory
                    ->createNamed('societe_' . $s->getId(), CreateSocieteType::class, $s)
                    ->createView();
            }
        }

        $this->addFlash('error', 'Erreur dans le formulaire, vérifiez les champs.');

        return $this->render('societes/list.html.twig', [
            'societes' => $societes,
            'forms' => $forms,
        ]);
    }

    /**
     * Displays centers with one inline edit form per row.
     *
     * @param CentreRepository $centreRepository Repository used to retrieve centers.
     * @param FormFactoryInterface $formFactory Form factory used to build per-center forms.
     *
     * @return Response Rendered HTML response containing centers and their forms.
     */
    #[Route("/admin/centres/list", name: 'app_centres_list')]
    public function listCentres(
        CentreRepository     $centreRepository,
        FormFactoryInterface $formFactory
    ): Response
    {
        $centres = $centreRepository->findBy([], ['societe' => 'ASC']);
        usort($centres, static function (Centre $a, Centre $b): int {
            $societeA = mb_strtoupper(trim((string)($a->getSociete()?->getNom() ?? '')));
            $societeB = mb_strtoupper(trim((string)($b->getSociete()?->getNom() ?? '')));
            $bySociete = $societeA <=> $societeB;
            if ($bySociete !== 0) {
                return $bySociete;
            }

            $villeA = mb_strtoupper(trim((string)($a->getVille() ?? '')));
            $villeB = mb_strtoupper(trim((string)($b->getVille() ?? '')));
            $byVille = $villeA <=> $villeB;
            if ($byVille !== 0) {
                return $byVille;
            }

            $agrA = mb_strtoupper(trim((string)($a->getAgrCentre() ?? '')));
            $agrB = mb_strtoupper(trim((string)($b->getAgrCentre() ?? '')));

            return $agrA <=> $agrB;
        });
        $forms = [];

        foreach ($centres as $centre) {
            $forms[$centre->getId()] = $formFactory
                ->createNamed(
                    'centre_' . $centre->getId(),
                    CreateCentreType::class,
                    $centre,
                    [
                        'csrf_field_name' => '_token',
                        'csrf_token_id' => 'centre_' . $centre->getId(),
                    ]
                )
                ->createView();
        }

        return $this->render('centres/list.html.twig', [
            'centres' => $centres,
            'forms' => $forms,
        ]);
    }

    /**
     * Creates a new center entry.
     *
     * @param Request $request Current HTTP request containing form submission data.
     * @param EntityManagerInterface $em Doctrine entity manager.
     *
     * @return Response Rendered add form on GET/invalid submit, or redirect after creation.
     */
    #[Route("/admin/centres/add", name: 'app_centres_add')]
    public function addCentre(
        Request                $request,
        EntityManagerInterface $em
    ): Response
    {
        $form = $this->createForm(CreateCentreType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $centre = $form->getData();
            if ($centre->getReseau() !== null) {
                $centre->setReseauNom($form->get('reseauNom')->getData());
            }

            $em->persist($centre);
            $em->flush();

            $this->addFlash('success', 'Centre créé.');

            return $this->redirectToRoute('app_centres_list');
        }

        return $this->render('centres/add.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * Updates an existing center using the inline list form.
     *
     * @param Centre $centre Center entity resolved from route parameter.
     * @param Request $request Current HTTP request containing form submission data.
     * @param EntityManagerInterface $em Doctrine entity manager.
     * @param FormFactoryInterface $formFactory Form factory used to rebuild inline forms.
     *
     * @return Response Redirect response on success, or rendered list with validation errors.
     */
    #[Route('/admin/centres/update/{id}', name: 'app_centres_update', methods: ['POST'])]
    public function updateCentre(
        Centre                 $centre,
        Request                $request,
        EntityManagerInterface $em,
        FormFactoryInterface   $formFactory
    ): Response
    {
        $form = $formFactory->createNamed(
            'centre_' . $centre->getId(),
            CreateCentreType::class,
            $centre,
            [
                'csrf_field_name' => '_token',
                'csrf_token_id' => 'centre_' . $centre->getId(),
            ]
        );

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($centre->getReseau() !== null) {
                $centre->setReseauNom($form->get('reseauNom')->getData());
            }

            $em->persist($centre);
            $em->flush();

            $this->addFlash('success', "Centre {$centre->getVille()} mis à jour.");

            return $this->redirectToRoute('app_centres_list');
        }

        $centres = $em->getRepository(Centre::class)->findAll();
        $forms = [];

        foreach ($centres as $s) {
            if ($s->getId() === $centre->getId()) {
                $forms[$s->getId()] = $form->createView();
            } else {
                $forms[$s->getId()] = $formFactory
                    ->createNamed('centre_' . $s->getId(), CreateCentreType::class, $s)
                    ->createView();
            }
        }

        $this->addFlash('error', 'Erreur dans le formulaire, vérifiez les champs.');

        return $this->render('centres/list.html.twig', [
            'centres' => $centres,
            'forms' => $forms,
        ]);
    }

    /**
     * @param array $salaries
     * @param array $forms
     * @param mixed $page
     * @param int $perPage
     * @param int $totalItems
     * @param mixed $totalPages
     * @return Response
     */
    public function getResponse(array $salaries, array $forms, mixed $page, int $perPage, int $totalItems, mixed $totalPages): Response
    {
        return $this->render('salaries/list.html.twig', [
            'salaries' => $salaries,
            'forms' => $forms,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total_items' => $totalItems,
                'total_pages' => $totalPages,
                'has_previous' => $page > 1,
                'has_next' => $page < $totalPages,
                'previous_page' => $page > 1 ? $page - 1 : 1,
                'next_page' => $page < $totalPages ? $page + 1 : $totalPages,
            ],
        ]);
    }
}
