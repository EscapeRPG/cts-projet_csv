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
use App\Service\Voiture\VoitureCertificatCessionStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
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
    #[IsGranted('ROLE_LIST_SALARIES_VIEW')]
    #[Route("/cts/salaries/list", name: 'app_salaries_list')]
    public function listSalaries(
        Request              $request,
        SalarieRepository    $salarieRepository,
        FormFactoryInterface $formFactory
    ): Response {
        $q = trim((string) $request->query->get('q', ''));
        $perPage = 30;
        $paginationData = $this->computePagination($request, $perPage, $salarieRepository->countSearch($q));

        // Temporaire: tri simple par nom pour les modifications manuelles.
        // $salaries = $salarieRepository->findBy([], ['nom' => 'ASC'], $perPage, $paginationData['offset']);
        $salaries = $salarieRepository->findPaginatedOrderedBySocieteSearch($perPage, $paginationData['offset'], $q);

        $forms = $this->buildInlineEditForms($salaries, $formFactory, 'salarie_', CreateSalarieType::class);

        return $this->renderSalariesList($salaries, $forms, $paginationData['view']);
    }

    #[IsGranted('ROLE_LIST_SALARIES_VIEW')]
    #[Route("/cts/salaries/list/partial", name: 'app_salaries_list_partial')]
    public function listSalariesPartial(
        Request              $request,
        SalarieRepository    $salarieRepository,
        FormFactoryInterface $formFactory
    ): Response {
        $q = trim((string) $request->query->get('q', ''));
        $perPage = 30;
        $paginationData = $this->computePagination($request, $perPage, $salarieRepository->countSearch($q));
        $salaries = $salarieRepository->findPaginatedOrderedBySocieteSearch($perPage, $paginationData['offset'], $q);
        $forms = $this->buildInlineEditForms($salaries, $formFactory, 'salarie_', CreateSalarieType::class);

        return $this->render('cts/salaries/_list_results.html.twig', [
            'salaries' => $salaries,
            'forms' => $forms,
            'pagination' => $paginationData['view'],
        ]);
    }

    /**
     * Displays employees (uneditable).
     */
    #[IsGranted('ROLE_LIST_SALARIES_VIEW')]
    #[Route("/cts/salaries/list-salaries", name: 'app_salaries_list_uneditable')]
    public function listSalariesNonEditable(Request $request, SalarieRepository $salarieRepository): Response
    {
        $q = trim((string) $request->query->get('q', ''));
        $salaries = $salarieRepository->findOrderedByNomPrenomSearch($q);

        return $this->render('cts/salaries/list_uneditable.html.twig', [
            'salaries' => $salaries,
        ]);
    }

    #[IsGranted('ROLE_LIST_SALARIES_VIEW')]
    #[Route("/cts/salaries/list-salaries/partial", name: 'app_salaries_list_uneditable_partial')]
    public function listSalariesNonEditablePartial(Request $request, SalarieRepository $salarieRepository): Response
    {
        $q = trim((string) $request->query->get('q', ''));
        $salaries = $salarieRepository->findOrderedByNomPrenomSearch($q);

        return $this->render('cts/salaries/_list_results_uneditable.html.twig', [
            'salaries' => $salaries,
        ]);
    }

    #[IsGranted('ROLE_LIST_SALARIES_ADD')]
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
                'q' => $request->query->get('q'),
            ]);
        }

        /** @var SalarieRepository $repo */
        $repo = $em->getRepository(Salarie::class);
        $q = trim((string) $request->query->get('q', ''));
        $perPage = 30;
        $paginationData = $this->computePagination($request, $perPage, $repo->countSearch($q));

        $salaries = $repo->findPaginatedOrderedBySocieteSearch($perPage, $paginationData['offset'], $q);
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
    #[IsGranted('ROLE_LIST_SOCIETES_VIEW')]
    #[Route('/cts/societes/list', name: 'app_societes_list')]
    public function listSocietes(Request $request, SocieteRepository $societeRepository, FormFactoryInterface $formFactory): Response
    {
        $q = trim((string) $request->query->get('q', ''));
        $societes = $societeRepository->findOrderedByNomSearch($q);
        $forms = $this->buildInlineEditForms($societes, $formFactory, 'societe_', CreateSocieteType::class);

        return $this->render('cts/societes/list.html.twig', [
            'societes' => $societes,
            'forms' => $forms,
        ]);
    }

    #[IsGranted('ROLE_LIST_SOCIETES_VIEW')]
    #[Route('/cts/societes/list/partial', name: 'app_societes_list_partial')]
    public function listSocietesPartial(Request $request, SocieteRepository $societeRepository, FormFactoryInterface $formFactory): Response
    {
        $q = trim((string) $request->query->get('q', ''));
        $societes = $societeRepository->findOrderedByNomSearch($q);
        $forms = $this->buildInlineEditForms($societes, $formFactory, 'societe_', CreateSocieteType::class);

        return $this->render('cts/societes/_list_results.html.twig', [
            'societes' => $societes,
            'forms' => $forms,
        ]);
    }

    /**
     * Displays companies (uneditable).
     */
    #[IsGranted('ROLE_LIST_SOCIETES_VIEW')]
    #[Route("/cts/societes/list-societes", name: 'app_societes_list_uneditable')]
    public function listSocietesNonEditable(Request $request, SocieteRepository $societeRepository): Response
    {
        $q = trim((string) $request->query->get('q', ''));
        $societes = $societeRepository->findOrderedByNomSearch($q);

        return $this->render('cts/societes/list_uneditable.html.twig', [
            'societes' => $societes,
        ]);
    }

    #[IsGranted('ROLE_LIST_SOCIETES_VIEW')]
    #[Route("/cts/societes/list-societes/partial", name: 'app_societes_list_uneditable_partial')]
    public function listSocietesNonEditablePartial(Request $request, SocieteRepository $societeRepository): Response
    {
        $q = trim((string) $request->query->get('q', ''));
        $societes = $societeRepository->findOrderedByNomSearch($q);

        return $this->render('cts/societes/_list_results_uneditable.html.twig', [
            'societes' => $societes,
        ]);
    }

    #[IsGranted('ROLE_LIST_SOCIETES_ADD')]
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

            return $this->redirectToRoute('app_societes_list', [
                'q' => $request->query->get('q'),
            ]);
        }

        /** @var SocieteRepository $repo */
        $repo = $em->getRepository(Societe::class);
        $q = trim((string) $request->query->get('q', ''));
        $societes = $repo->findOrderedByNomSearch($q);
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
    #[IsGranted('ROLE_LIST_CENTRES_VIEW')]
    #[Route("/cts/centres/list", name: 'app_centres_list')]
    public function listCentres(Request $request, CentreRepository $centreRepository, FormFactoryInterface $formFactory): Response
    {
        $q = trim((string) $request->query->get('q', ''));
        $centres = $centreRepository->findOrderedBySocieteVilleAgrSearch($q);

        $forms = $this->buildInlineEditForms($centres, $formFactory, 'centre_', CreateCentreType::class);

        return $this->render('cts/centres/list.html.twig', [
            'centres' => $centres,
            'forms' => $forms,
        ]);
    }

    #[IsGranted('ROLE_LIST_CENTRES_VIEW')]
    #[Route("/cts/centres/list/partial", name: 'app_centres_list_partial')]
    public function listCentresPartial(Request $request, CentreRepository $centreRepository, FormFactoryInterface $formFactory): Response
    {
        $q = trim((string) $request->query->get('q', ''));
        $centres = $centreRepository->findOrderedBySocieteVilleAgrSearch($q);
        $forms = $this->buildInlineEditForms($centres, $formFactory, 'centre_', CreateCentreType::class);

        return $this->render('cts/centres/_list_results.html.twig', [
            'centres' => $centres,
            'forms' => $forms,
        ]);
    }

    /**
     * Displays centers (uneditable).
     */
    #[IsGranted('ROLE_LIST_CENTRES_VIEW')]
    #[Route("/cts/centres/list-centres", name: 'app_centres_list_uneditable')]
    public function listCentresNonEditable(Request $request, CentreRepository $centreRepository): Response
    {
        $q = trim((string) $request->query->get('q', ''));
        $centres = $centreRepository->findOrderedBySocieteVilleAgrSearch($q);

        return $this->render('cts/centres/list_uneditable.html.twig', [
            'centres' => $centres,
        ]);
    }

    #[IsGranted('ROLE_LIST_CENTRES_VIEW')]
    #[Route("/cts/centres/list-centres/partial", name: 'app_centres_list_uneditable_partial')]
    public function listCentresNonEditablePartial(Request $request, CentreRepository $centreRepository): Response
    {
        $q = trim((string) $request->query->get('q', ''));
        $centres = $centreRepository->findOrderedBySocieteVilleAgrSearch($q);

        return $this->render('cts/centres/_list_results_uneditable.html.twig', [
            'centres' => $centres,
        ]);
    }

    #[IsGranted('ROLE_LIST_CENTRES_ADD')]
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

            return $this->redirectToRoute('app_centres_list', [
                'q' => $request->query->get('q'),
            ]);
        }

        /** @var CentreRepository $repo */
        $repo = $em->getRepository(Centre::class);
        $q = trim((string) $request->query->get('q', ''));
        $centres = $repo->findOrderedBySocieteVilleAgrSearch($q);
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
    #[IsGranted('ROLE_LIST_VOITURES_VIEW')]
    #[Route("/cts/voitures/list-voitures", name: 'app_voitures_list_uneditable')]
    public function listVoituresNonEditable(Request $request, VoitureRepository $voitureRepository): Response
    {
        $q = trim((string) $request->query->get('q', ''));
        $voitures = $voitureRepository->findOrderedBySocieteSearch($q);

        return $this->render('cts/voitures/list_uneditable.html.twig', [
            'voitures' => $voitures,
        ]);
    }

    #[IsGranted('ROLE_LIST_VOITURES_VIEW')]
    #[Route("/cts/voitures/list-voitures/partial", name: 'app_voitures_list_uneditable_partial')]
    public function listVoituresNonEditablePartial(Request $request, VoitureRepository $voitureRepository): Response
    {
        $q = trim((string) $request->query->get('q', ''));
        $voitures = $voitureRepository->findOrderedBySocieteSearch($q);

        return $this->render('cts/voitures/_list_results_uneditable.html.twig', [
            'voitures' => $voitures,
        ]);
    }

    /**
     * Displays vehicles with one inline edit form per row.
     */
    #[IsGranted('ROLE_LIST_VOITURES_VIEW')]
    #[Route("/cts/voitures/list", name: 'app_voitures_list')]
    public function listVoitures(
        Request              $request,
        VoitureRepository    $voitureRepository,
        FormFactoryInterface $formFactory
    ): Response {
        $q = trim((string) $request->query->get('q', ''));
        // One form per row: keep the page size small to avoid dev profiler OOMs.
        $perPage = 20;
        $paginationData = $this->computePagination($request, $perPage, $voitureRepository->countSearch($q));

        $voitures = $voitureRepository->findPaginatedOrderedBySocieteSearch($perPage, $paginationData['offset'], $q);
        $forms = $this->buildInlineEditForms($voitures, $formFactory, 'voiture_', CreateVoitureType::class);

        return $this->render('cts/voitures/list.html.twig', [
            'voitures' => $voitures,
            'forms' => $forms,
            'pagination' => $paginationData['view'],
        ]);
    }

    #[IsGranted('ROLE_LIST_VOITURES_VIEW')]
    #[Route("/cts/voitures/list/partial", name: 'app_voitures_list_partial')]
    public function listVoituresPartial(
        Request              $request,
        VoitureRepository    $voitureRepository,
        FormFactoryInterface $formFactory
    ): Response {
        $q = trim((string) $request->query->get('q', ''));
        $perPage = 20;
        $paginationData = $this->computePagination($request, $perPage, $voitureRepository->countSearch($q));
        $voitures = $voitureRepository->findPaginatedOrderedBySocieteSearch($perPage, $paginationData['offset'], $q);
        $forms = $this->buildInlineEditForms($voitures, $formFactory, 'voiture_', CreateVoitureType::class);

        return $this->render('cts/voitures/_list_results.html.twig', [
            'voitures' => $voitures,
            'forms' => $forms,
            'pagination' => $paginationData['view'],
        ]);
    }

    #[IsGranted('ROLE_LIST_VOITURES_ADD')]
    #[Route("/admin/cts/voitures/add", name: 'app_voitures_add')]
    public function addVoiture(
        Request $request,
        EntityManagerInterface $em,
        VoitureCertificatCessionStorage $storage,
    ): Response
    {
        $form = $this->createForm(CreateVoitureType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            try {
                if (!$form->isValid()) {
                    return $this->render('cts/voitures/add.html.twig', [
                        'form' => $form->createView(),
                    ]);
                }
            } catch (\Symfony\Component\HttpFoundation\File\Exception\FileException $e) {
                $this->addFlash('error', self::formatUploadRuntimeException($e));
                return $this->redirectToRoute('app_voitures_add');
            }

            $voiture = $form->getData();

            $em->persist($voiture);
            $em->flush();

            $uploaded = $form->get('certificatCessionFile')->getData();
            if ($uploaded instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
                if (!$uploaded->isValid()) {
                    $this->addFlash('error', self::formatUploadErrorMessage($uploaded->getError()));
                    return $this->redirectToRoute('app_voitures_list');
                }

                // Read metadata before move(): after moving, the tmp file no longer exists.
                $originalName = $uploaded->getClientOriginalName();
                $mime = (string) ($uploaded->getMimeType() ?? '');
                $size = $uploaded->getSize();

                $relativePath = $storage->storeCertificat($voiture, $uploaded);
                $voiture->setCertificatCessionPath($relativePath);
                $voiture->setCertificatCessionOriginalName($originalName);
                $voiture->setCertificatCessionMime($mime !== '' ? $mime : null);
                $voiture->setCertificatCessionSize($size);
                $voiture->setCertificatCessionUploadedAt(new \DateTimeImmutable());
                $em->flush();
            }

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
        VoitureRepository      $voitureRepository,
        VoitureCertificatCessionStorage $storage,
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

        if ($form->isSubmitted()) {
            try {
                if (!$form->isValid()) {
                    throw new \RuntimeException('Formulaire invalide.');
                }
            } catch (\Symfony\Component\HttpFoundation\File\Exception\FileException $e) {
                $this->addFlash('error', self::formatUploadRuntimeException($e));
                return $this->redirectToRoute('app_voitures_list', [
                    'page' => $request->query->getInt('page', 1),
                    'q' => $request->query->get('q'),
                ]);
            } catch (\RuntimeException) {
                // fall through to the existing invalid form rendering below
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $oldPath = $voiture->getCertificatCessionPath();
            $uploaded = $form->get('certificatCessionFile')->getData();
            if ($uploaded instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
                if (!$uploaded->isValid()) {
                    $this->addFlash('error', self::formatUploadErrorMessage($uploaded->getError()));
                    return $this->redirectToRoute('app_voitures_list', [
                        'page' => $request->query->getInt('page', 1),
                        'q' => $request->query->get('q'),
                    ]);
                }

                // Read metadata before move(): after moving, the tmp file no longer exists.
                $originalName = $uploaded->getClientOriginalName();
                $mime = (string) ($uploaded->getMimeType() ?? '');
                $size = $uploaded->getSize();

                $relativePath = $storage->storeCertificat($voiture, $uploaded);
                $voiture->setCertificatCessionPath($relativePath);
                $voiture->setCertificatCessionOriginalName($originalName);
                $voiture->setCertificatCessionMime($mime !== '' ? $mime : null);
                $voiture->setCertificatCessionSize($size);
                $voiture->setCertificatCessionUploadedAt(new \DateTimeImmutable());
            }

            $em->persist($voiture);
            $em->flush();

            if ($uploaded instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
                $storage->deleteIfExists($oldPath);
            }

            $this->addFlash('success', "Voiture {$voiture->getImmatriculation()} mise à jour.");

            return $this->redirectToRoute('app_voitures_list', [
                'page' => $request->query->getInt('page', 1),
                'q' => $request->query->get('q'),
            ]);
        }

        $this->addFlash('error', 'Erreur dans le formulaire, vérifiez les champs.');

        $q = trim((string) $request->query->get('q', ''));
        $perPage = 20;
        $paginationData = $this->computePagination($request, $perPage, $voitureRepository->countSearch($q));

        $voitures = $voitureRepository->findPaginatedOrderedBySocieteSearch($perPage, $paginationData['offset'], $q);
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

    private static function formatUploadErrorMessage(int $errorCode): string
    {
        return match ($errorCode) {
            \UPLOAD_ERR_INI_SIZE, \UPLOAD_ERR_FORM_SIZE => 'Upload impossible: fichier trop volumineux. Vérifiez upload_max_filesize et post_max_size.',
            \UPLOAD_ERR_PARTIAL => 'Upload incomplet: le fichier n\'a été que partiellement envoyé.',
            \UPLOAD_ERR_NO_FILE => 'Aucun fichier n\'a été envoyé.',
            \UPLOAD_ERR_NO_TMP_DIR => 'Upload impossible: dossier temporaire manquant côté serveur.',
            \UPLOAD_ERR_CANT_WRITE => 'Upload impossible: écriture sur disque impossible côté serveur.',
            \UPLOAD_ERR_EXTENSION => 'Upload bloqué par une extension PHP côté serveur.',
            default => 'Upload impossible: erreur inconnue.',
        };
    }

    private static function formatUploadRuntimeException(\Symfony\Component\HttpFoundation\File\Exception\FileException $e): string
    {
        $openBasedir = (string) ini_get('open_basedir');
        $uploadTmpDir = (string) ini_get('upload_tmp_dir');

        $base = 'Upload impossible: le fichier temporaire n\'est pas accessible côté serveur.';
        $details = trim($e->getMessage());

        $hint = [];
        if ($openBasedir !== '') {
            $hint[] = "open_basedir est actif ({$openBasedir}).";
        }
        if ($uploadTmpDir !== '') {
            $hint[] = "upload_tmp_dir={$uploadTmpDir}.";
        }
        $hint[] = 'Solution: ajouter le dossier temporaire PHP dans open_basedir, ou configurer upload_tmp_dir vers un dossier accessible (ex: <projet>/var/tmp).';

        return $base . ' ' . $details . ' ' . implode(' ', $hint);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/cts/voitures/{id}/certificat-cession/upload', name: 'app_voitures_certificat_upload', methods: ['POST'])]
    public function uploadVoitureCertificatCession(
        Voiture $voiture,
        Request $request,
        EntityManagerInterface $em,
        VoitureCertificatCessionStorage $storage,
    ): Response {
        $token = (string) $request->request->get(self::CSRF_FIELD_NAME, '');
        if (!$this->isCsrfTokenValid('voiture_certificat_upload_' . $voiture->getId(), $token)) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_voitures_list', [
                'page' => $request->query->getInt('page', 1),
                'q' => $request->query->get('q'),
            ]);
        }

        $file = $request->files->get('certificat');
        if (!$file) {
            $this->addFlash('warning', 'Aucun fichier sélectionné.');
            return $this->redirectToRoute('app_voitures_list', [
                'page' => $request->query->getInt('page', 1),
                'q' => $request->query->get('q'),
            ]);
        }
        if (!$file instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
            $this->addFlash('error', 'Fichier invalide.');
            return $this->redirectToRoute('app_voitures_list', [
                'page' => $request->query->getInt('page', 1),
                'q' => $request->query->get('q'),
            ]);
        }

        $mime = (string) ($file->getMimeType() ?? '');
        $allowed = ['application/pdf', 'image/jpeg', 'image/png'];
        if (!in_array($mime, $allowed, true)) {
            $this->addFlash('error', 'Format non autorisé. Formats acceptés: PDF, JPG, PNG.');
            return $this->redirectToRoute('app_voitures_list', [
                'page' => $request->query->getInt('page', 1),
                'q' => $request->query->get('q'),
            ]);
        }
        if ($file->getSize() !== null && $file->getSize() > 15 * 1024 * 1024) {
            $this->addFlash('error', 'Fichier trop volumineux (15 Mo max).');
            return $this->redirectToRoute('app_voitures_list', [
                'page' => $request->query->getInt('page', 1),
                'q' => $request->query->get('q'),
            ]);
        }

        // Replace existing certificate if any.
        $storage->deleteIfExists($voiture->getCertificatCessionPath());

        $relativePath = $storage->storeCertificat($voiture, $file);
        $voiture->setCertificatCessionPath($relativePath);
        $voiture->setCertificatCessionOriginalName($file->getClientOriginalName());
        $voiture->setCertificatCessionMime($mime !== '' ? $mime : null);
        $voiture->setCertificatCessionSize($file->getSize());
        $voiture->setCertificatCessionUploadedAt(new \DateTimeImmutable());

        $em->persist($voiture);
        $em->flush();

        $this->addFlash('success', 'Certificat de cession uploadé.');

        return $this->redirectToRoute('app_voitures_list', [
            'page' => $request->query->getInt('page', 1),
            'q' => $request->query->get('q'),
        ]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/cts/voitures/{id}/certificat-cession/download', name: 'app_voitures_certificat_download', methods: ['GET'])]
    public function downloadVoitureCertificatCession(
        Voiture $voiture,
        VoitureCertificatCessionStorage $storage,
    ): Response {
        $relative = $voiture->getCertificatCessionPath();
        if (!$relative) {
            throw $this->createNotFoundException('Aucun certificat associé à cette voiture.');
        }

        $absolute = $storage->absolutePath($relative);
        if (!is_file($absolute)) {
            throw $this->createNotFoundException('Fichier introuvable.');
        }

        $immatriculation = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) $voiture->getImmatriculation());
        $immatriculation = trim((string) $immatriculation, '_');
        $downloadName = $immatriculation !== '' ? ('certificat_cession_' . $immatriculation) : 'certificat_cession';

        $ext = pathinfo($absolute, PATHINFO_EXTENSION);
        if ($ext) {
            $downloadName .= '.' . $ext;
        }

        $response = new BinaryFileResponse($absolute);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $downloadName);

        return $response;
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/cts/voitures/{id}/certificat-cession/view', name: 'app_voitures_certificat_view', methods: ['GET'])]
    public function viewVoitureCertificatCession(
        Voiture $voiture,
        VoitureCertificatCessionStorage $storage,
    ): Response {
        $relative = $voiture->getCertificatCessionPath();
        if (!$relative) {
            throw $this->createNotFoundException('Aucun certificat associé à cette voiture.');
        }

        $absolute = $storage->absolutePath($relative);
        if (!is_file($absolute)) {
            throw $this->createNotFoundException('Fichier introuvable.');
        }

        $response = new BinaryFileResponse($absolute);

        $immatriculation = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) $voiture->getImmatriculation());
        $immatriculation = trim((string) $immatriculation, '_');
        $name = $immatriculation !== '' ? ('certificat_cession_' . $immatriculation) : 'certificat_cession';
        $ext = pathinfo($absolute, PATHINFO_EXTENSION);
        if ($ext) {
            $name .= '.' . $ext;
        }

        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $name);

        $mime = $voiture->getCertificatCessionMime();
        if (is_string($mime) && $mime !== '') {
            $response->headers->set('Content-Type', $mime);
        }

        return $response;
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/cts/voitures/{id}/certificat-cession/delete', name: 'app_voitures_certificat_delete', methods: ['POST'])]
    public function deleteVoitureCertificatCession(
        Voiture $voiture,
        Request $request,
        EntityManagerInterface $em,
        VoitureCertificatCessionStorage $storage,
    ): Response {
        $token = (string) $request->request->get(self::CSRF_FIELD_NAME, '');
        if (!$this->isCsrfTokenValid('voiture_certificat_delete_' . $voiture->getId(), $token)) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_voitures_list', [
                'page' => $request->query->getInt('page', 1),
                'q' => $request->query->get('q'),
            ]);
        }

        $storage->deleteIfExists($voiture->getCertificatCessionPath());
        $voiture->setCertificatCessionPath(null);
        $voiture->setCertificatCessionOriginalName(null);
        $voiture->setCertificatCessionMime(null);
        $voiture->setCertificatCessionSize(null);
        $voiture->setCertificatCessionUploadedAt(null);

        $em->persist($voiture);
        $em->flush();

        $this->addFlash('success', 'Certificat supprimé.');

        return $this->redirectToRoute('app_voitures_list', [
            'page' => $request->query->getInt('page', 1),
            'q' => $request->query->get('q'),
        ]);
    }

    #[IsGranted('ROLE_LIST_SALARIES_VIEW')]
    #[Route('/cts/salaries/list/print', name: 'app_salaries_list_print')]
    public function listSalariesPrint(Request $request, SalarieRepository $salarieRepository): Response
    {
        $q = trim((string) $request->query->get('q', ''));
        $perPage = 30;
        $paginationData = $this->computePagination($request, $perPage, $salarieRepository->countSearch($q));
        $salaries = $salarieRepository->findPaginatedOrderedBySocieteSearch($perPage, $paginationData['offset'], $q);

        return $this->render('cts/lists/print/salaries.html.twig', [
            'salaries' => $salaries,
            'pagination' => $paginationData['view'],
            'q' => $q,
            'printTitle' => 'Liste des salariés',
            'printVariant' => 'lists',
            'printOrientation' => 'landscape',
            'autoPrint' => true,
        ]);
    }

    #[IsGranted('ROLE_LIST_SALARIES_VIEW')]
    #[Route('/cts/salaries/list-salaries/print', name: 'app_salaries_list_uneditable_print')]
    public function listSalariesNonEditablePrint(Request $request, SalarieRepository $salarieRepository): Response
    {
        $q = trim((string) $request->query->get('q', ''));
        $salaries = $salarieRepository->findOrderedByNomPrenomSearch($q);

        return $this->render('cts/lists/print/salaries.html.twig', [
            'salaries' => $salaries,
            'pagination' => null,
            'q' => $q,
            'printTitle' => 'Liste des salariés',
            'printVariant' => 'lists',
            'printOrientation' => 'landscape',
            'autoPrint' => true,
        ]);
    }

    #[IsGranted('ROLE_LIST_SOCIETES_VIEW')]
    #[Route('/cts/societes/list/print', name: 'app_societes_list_print')]
    public function listSocietesPrint(Request $request, SocieteRepository $societeRepository): Response
    {
        $q = trim((string) $request->query->get('q', ''));
        $societes = $societeRepository->findOrderedByNomSearch($q);

        return $this->render('cts/lists/print/societes.html.twig', [
            'societes' => $societes,
            'q' => $q,
            'printTitle' => 'Liste des sociétés',
            'printVariant' => 'lists',
            'printOrientation' => 'portrait',
            'autoPrint' => true,
        ]);
    }

    #[IsGranted('ROLE_LIST_CENTRES_VIEW')]
    #[Route('/cts/centres/list/print', name: 'app_centres_list_print')]
    public function listCentresPrint(Request $request, CentreRepository $centreRepository): Response
    {
        $q = trim((string) $request->query->get('q', ''));
        $centres = $centreRepository->findOrderedBySocieteVilleAgrSearch($q);

        return $this->render('cts/lists/print/centres.html.twig', [
            'centres' => $centres,
            'q' => $q,
            'printTitle' => 'Liste des centres',
            'printVariant' => 'lists',
            'printOrientation' => 'landscape',
            'autoPrint' => true,
        ]);
    }

    #[IsGranted('ROLE_LIST_VOITURES_VIEW')]
    #[Route('/cts/voitures/list/print', name: 'app_voitures_list_print')]
    public function listVoituresPrint(Request $request, VoitureRepository $voitureRepository): Response
    {
        $q = trim((string) $request->query->get('q', ''));
        $perPage = 20;
        $paginationData = $this->computePagination($request, $perPage, $voitureRepository->countSearch($q));
        $voitures = $voitureRepository->findPaginatedOrderedBySocieteSearch($perPage, $paginationData['offset'], $q);

        return $this->render('cts/lists/print/voitures.html.twig', [
            'voitures' => $voitures,
            'pagination' => $paginationData['view'],
            'q' => $q,
            'printTitle' => 'Liste des voitures',
            'printVariant' => 'lists',
            'printOrientation' => 'landscape',
            'autoPrint' => true,
        ]);
    }

    #[IsGranted('ROLE_LIST_VOITURES_VIEW')]
    #[Route('/cts/voitures/list-voitures/print', name: 'app_voitures_list_uneditable_print')]
    public function listVoituresNonEditablePrint(Request $request, VoitureRepository $voitureRepository): Response
    {
        $q = trim((string) $request->query->get('q', ''));
        $voitures = $voitureRepository->findOrderedBySocieteSearch($q);

        return $this->render('cts/lists/print/voitures.html.twig', [
            'voitures' => $voitures,
            'pagination' => null,
            'q' => $q,
            'printTitle' => 'Liste des voitures',
            'printVariant' => 'lists',
            'printOrientation' => 'landscape',
            'autoPrint' => true,
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
