<?php

namespace App\Controller;

use App\Entity\Centre;
use App\Entity\Salarie;
use App\Entity\Societe;
use App\Entity\Voiture;
use App\Form\CreateCentreType;
use App\Form\CreateSalarieType;
use App\Form\CreateSocieteType;
use App\Form\CreateVoitureType;
use App\Repository\CentreRepository;
use App\Repository\SalarieRepository;
use App\Repository\SocieteRepository;
use App\Repository\VoitureRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_CTS')]
/**
 * Provides list pages for CTS entities (employees, companies, centers, cars).
 *
 * Add/update actions remain restricted to administrators.
 */
final class ListsController extends AbstractController
{
    private const string CSRF_FIELD_NAME = '_token';

    /**
     * Displays employees with one inline edit form per row.
     */
    #[Route("/cts/salaries/list", name: 'app_salaries_list')]
    public function listSalaries(
        Request              $request,
        SalarieRepository    $salarieRepository,
        FormFactoryInterface $formFactory
    ): Response {
        $perPage = 30;
        $paginationData = $this->computePagination($request, $perPage, $salarieRepository->count([]));

        // Temporaire: tri simple par nom pour les modifications manuelles.
        // $salaries = $salarieRepository->findBy([], ['nom' => 'ASC'], $perPage, $paginationData['offset']);
        $salaries = $salarieRepository->findPaginatedOrderedBySociete($perPage, $paginationData['offset']);

        $forms = $this->buildInlineEditForms($salaries, $formFactory, 'salarie_', CreateSalarieType::class);

        return $this->renderSalariesList($salaries, $forms, $paginationData['view']);
    }

    /**
     * Displays employees (non editable).
     */
    #[Route("/cts/salaries/list-salaries", name: 'app_salaries_list_uneditable')]
    public function listSalariesNonEditable(SalarieRepository $salarieRepository): Response
    {
        $salaries = $salarieRepository->findBy([], ['nom' => 'ASC']);

        return $this->render('cts/salaries/list_uneditable.html.twig', [
            'salaries' => $salaries,
        ]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route("/admin/cts/salaries/add", name: 'app_salaries_add')]
    public function addSalarie(Request $request, EntityManagerInterface $em): Response
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

        return $this->render('cts/salaries/add.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/cts/salaries/update/{id}', name: 'app_salaries_update', methods: ['POST'])]
    public function updateSalarie(
        Salarie                $salarie,
        Request                $request,
        EntityManagerInterface $em,
        FormFactoryInterface   $formFactory
    ): Response {
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
        $paginationData = $this->computePagination($request, $perPage, $em->getRepository(Salarie::class)->count([]));

        $salaries = $em->getRepository(Salarie::class)->findBy([], ['nom' => 'ASC'], $perPage, $paginationData['offset']);
        $forms = $this->buildInlineEditForms(
            $salaries,
            $formFactory,
            'salarie_',
            CreateSalarieType::class,
            $salarie->getId(),
            $form->createView()
        );

        $this->addFlash('error', 'Erreur dans le formulaire, vérifiez les champs.');

        return $this->renderSalariesList($salaries, $forms, $paginationData['view']);
    }

    /**
     * Displays companies with one inline edit form per row.
     */
    #[Route('/cts/societes/list', name: 'app_societes_list')]
    public function listSocietes(SocieteRepository $societeRepository, FormFactoryInterface $formFactory): Response
    {
        $societes = $societeRepository->findBy([], ['nom' => 'ASC']);
        $forms = $this->buildInlineEditForms($societes, $formFactory, 'societe_', CreateSocieteType::class);

        return $this->render('cts/societes/list.html.twig', [
            'societes' => $societes,
            'forms' => $forms,
        ]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/cts/societes/add', name: 'app_societes_add')]
    public function addSociete(Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(CreateSocieteType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $societe = $form->getData();

            $em->persist($societe);
            $em->flush();

            $this->addFlash('success', 'Société créée.');

            return $this->redirectToRoute('app_societes_list');
        }

        return $this->render('cts/societes/add.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/cts/societes/update/{id}', name: 'app_societes_update', methods: ['POST'])]
    public function updateSociete(
        Societe                $societe,
        Request                $request,
        EntityManagerInterface $em,
        FormFactoryInterface   $formFactory
    ): Response {
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
        $forms = $this->buildInlineEditForms(
            $societes,
            $formFactory,
            'societe_',
            CreateSocieteType::class,
            $societe->getId(),
            $form->createView()
        );

        $this->addFlash('error', 'Erreur dans le formulaire, vérifiez les champs.');

        return $this->render('cts/societes/list.html.twig', [
            'societes' => $societes,
            'forms' => $forms,
        ]);
    }

    /**
     * Displays centers with one inline edit form per row.
     */
    #[Route("/cts/centres/list", name: 'app_centres_list')]
    public function listCentres(CentreRepository $centreRepository, FormFactoryInterface $formFactory): Response
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

        $forms = $this->buildInlineEditForms($centres, $formFactory, 'centre_', CreateCentreType::class);

        return $this->render('cts/centres/list.html.twig', [
            'centres' => $centres,
            'forms' => $forms,
        ]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route("/admin/cts/centres/add", name: 'app_centres_add')]
    public function addCentre(Request $request, EntityManagerInterface $em): Response
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

        return $this->render('cts/centres/add.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/cts/centres/update/{id}', name: 'app_centres_update', methods: ['POST'])]
    public function updateCentre(
        Centre                 $centre,
        Request                $request,
        EntityManagerInterface $em,
        FormFactoryInterface   $formFactory
    ): Response {
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
        $forms = $this->buildInlineEditForms(
            $centres,
            $formFactory,
            'centre_',
            CreateCentreType::class,
            $centre->getId(),
            $form->createView()
        );

        $this->addFlash('error', 'Erreur dans le formulaire, vérifiez les champs.');

        return $this->render('cts/centres/list.html.twig', [
            'centres' => $centres,
            'forms' => $forms,
        ]);
    }

    /**
     * Displays vehicles (uneditable).
     */
    #[Route("/cts/voitures/list-voitures", name: 'app_voitures_list_uneditable')]
    public function listVoituresNonEditable(VoitureRepository $voitureRepository): Response
    {
        $voitures = $voitureRepository->findBy([], ['societe' => 'ASC']);

        return $this->render('cts/voitures/list_uneditable.html.twig', [
            'voitures' => $voitures,
        ]);
    }

    /**
     * Displays vehicles with one inline edit form per row.
     */
    #[Route("/cts/voitures/list", name: 'app_voitures_list')]
    public function listVoitures(
        Request              $request,
        VoitureRepository    $voitureRepository,
        FormFactoryInterface $formFactory
    ): Response {
        // One form per row: keep the page size small to avoid dev profiler OOMs.
        $perPage = 20;
        $paginationData = $this->computePagination($request, $perPage, $voitureRepository->count([]));

        $voitures = $voitureRepository->findPaginatedOrderedBySociete($perPage, $paginationData['offset']);
        $forms = $this->buildInlineEditForms($voitures, $formFactory, 'voiture_', CreateVoitureType::class);

        return $this->render('cts/voitures/list.html.twig', [
            'voitures' => $voitures,
            'forms' => $forms,
            'pagination' => $paginationData['view'],
        ]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route("/admin/cts/voitures/add", name: 'app_voitures_add')]
    public function addVoiture(Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(CreateVoitureType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $voiture = $form->getData();

            $em->persist($voiture);
            $em->flush();

            $this->addFlash('success', 'Voiture créée.');

            return $this->redirectToRoute('app_voitures_list');
        }

        return $this->render('cts/voitures/add.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/cts/voitures/update/{id}', name: 'app_voitures_update', methods: ['POST'])]
    public function updateVoiture(
        Voiture                $voiture,
        Request                $request,
        EntityManagerInterface $em,
        FormFactoryInterface   $formFactory,
        VoitureRepository      $voitureRepository
    ): Response {
        $form = $formFactory->createNamed(
            'voiture_' . $voiture->getId(),
            CreateVoitureType::class,
            $voiture,
            [
                'csrf_field_name' => '_token',
                'csrf_token_id' => 'voiture_' . $voiture->getId(),
            ]
        );

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($voiture);
            $em->flush();

            $this->addFlash('success', "Voiture {$voiture->getImmatriculation()} mise à jour.");

            return $this->redirectToRoute('app_voitures_list', [
                'page' => $request->query->getInt('page', 1),
            ]);
        }

        $this->addFlash('error', 'Erreur dans le formulaire, vérifiez les champs.');

        $perPage = 20;
        $paginationData = $this->computePagination($request, $perPage, $voitureRepository->count([]));

        $voitures = $voitureRepository->findPaginatedOrderedBySociete($perPage, $paginationData['offset']);
        $forms = $this->buildInlineEditForms(
            $voitures,
            $formFactory,
            'voiture_',
            CreateVoitureType::class,
            $voiture->getId(),
            $form->createView()
        );

        return $this->render('cts/voitures/list.html.twig', [
            'voitures' => $voitures,
            'forms' => $forms,
            'pagination' => $paginationData['view'],
        ]);
    }

    private function renderSalariesList(array $salaries, array $forms, array $paginationView): Response
    {
        return $this->render('cts/salaries/list.html.twig', [
            'salaries' => $salaries,
            'forms' => $forms,
            'pagination' => $paginationView,
        ]);
    }

    /**
     * @return array{page:int,offset:int,total_pages:int,view:array<string,int|bool>}
     */
    private function computePagination(Request $request, int $perPage, int $totalItems): array
    {
        $totalPages = max(1, (int)ceil($totalItems / $perPage));
        $page = max(1, min($request->query->getInt('page', 1), $totalPages));
        $offset = ($page - 1) * $perPage;

        return [
            'page' => $page,
            'offset' => $offset,
            'total_pages' => $totalPages,
            'view' => $this->buildPaginationView($page, $perPage, $totalItems, $totalPages),
        ];
    }

    /**
     * @return array{page:int,per_page:int,total_items:int,total_pages:int,has_previous:bool,has_next:bool,previous_page:int,next_page:int}
     */
    private function buildPaginationView(int $page, int $perPage, int $totalItems, int $totalPages): array
    {
        return [
            'page' => $page,
            'per_page' => $perPage,
            'total_items' => $totalItems,
            'total_pages' => $totalPages,
            'has_previous' => $page > 1,
            'has_next' => $page < $totalPages,
            'previous_page' => $page > 1 ? $page - 1 : 1,
            'next_page' => $page < $totalPages ? $page + 1 : $totalPages,
        ];
    }

    /**
     * Builds one inline edit form view per entity row.
     *
     * @param iterable<object> $entities
     *
     * @return array<int, FormView>
     */
    private function buildInlineEditForms(
        iterable $entities,
        FormFactoryInterface $formFactory,
        string $namePrefix,
        string $formTypeClass,
        ?int $overrideEntityId = null,
        ?FormView $overrideFormView = null,
    ): array {
        $forms = [];
        foreach ($entities as $entity) {
            if (!method_exists($entity, 'getId')) {
                throw new \LogicException(sprintf('Expected entity with getId(), got %s.', get_debug_type($entity)));
            }

            /** @var int $id */
            $id = $entity->getId();

            if ($overrideEntityId !== null && $overrideFormView !== null && $id === $overrideEntityId) {
                $forms[$id] = $overrideFormView;
                continue;
            }

            $forms[$id] = $formFactory
                ->createNamed(
                    $namePrefix . $id,
                    $formTypeClass,
                    $entity,
                    [
                        'csrf_field_name' => self::CSRF_FIELD_NAME,
                        'csrf_token_id' => $namePrefix . $id,
                    ]
                )
                ->createView();
        }

        return $forms;
    }
}
