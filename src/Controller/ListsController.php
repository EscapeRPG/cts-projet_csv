<?php

namespace App\Controller;

use App\Entity\Centre;
use App\Entity\EncoursBancaire;
use App\Entity\Salarie;
use App\Entity\Societe;
use App\Entity\User;
use App\Entity\Voiture;
use App\Form\CreateCentreType;
use App\Form\CreateEncoursBancaireType;
use App\Form\CreateSalarieType;
use App\Form\CreateSocieteType;
use App\Form\CreateVoitureType;
use App\Repository\CentreRepository;
use App\Repository\SalarieRepository;
use App\Repository\SocieteRepository;
use App\Repository\VoitureRepository;
use App\Service\Encours\EncoursPageBuilder;
use App\Service\Voiture\VoitureCertificatCessionStorage;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Exception\DriverException as DbalDriverException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
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
        SocieteRepository    $societeRepository,
        CentreRepository     $centreRepository,
    ): Response
    {
        $q = trim((string)$request->query->get('q', ''));
        $centreIds = $this->getCurrentUserCentreScopeIds();
        $includeActive = $request->query->getInt('active', 1) === 1;
        $includeInactive = $request->query->getInt('inactive', 0) === 1;
        if (!$includeActive && !$includeInactive) {
            $includeActive = true;
            $includeInactive = true;
        }

        // Bulk-edit: load the full result set (no pagination).
        $salaries = $salarieRepository->findOrderedBySocieteSearch($q, $centreIds, $includeActive, $includeInactive);
        $societes = $societeRepository->findOrderedByNomSearch(null, $centreIds);
        $centres = $centreRepository->findOrderedBySocieteVilleAgrSearch(null, $centreIds);

        return $this->renderSalariesList($salaries, $societes, $centres, null);
    }

    #[IsGranted('ROLE_LIST_SALARIES_VIEW')]
    #[Route("/cts/salaries/list/partial", name: 'app_salaries_list_partial')]
    public function listSalariesPartial(
        Request              $request,
        SalarieRepository    $salarieRepository,
        SocieteRepository    $societeRepository,
        CentreRepository     $centreRepository,
    ): Response
    {
        $q = trim((string)$request->query->get('q', ''));
        $centreIds = $this->getCurrentUserCentreScopeIds();
        $includeActive = $request->query->getInt('active', 1) === 1;
        $includeInactive = $request->query->getInt('inactive', 0) === 1;
        if (!$includeActive && !$includeInactive) {
            $includeActive = true;
            $includeInactive = true;
        }
        $salaries = $salarieRepository->findOrderedBySocieteSearch($q, $centreIds, $includeActive, $includeInactive);
        $societes = $societeRepository->findOrderedByNomSearch(null, $centreIds);
        $centres = $centreRepository->findOrderedBySocieteVilleAgrSearch(null, $centreIds);

        return $this->render('cts/salaries/_list_results.html.twig', [
            'salaries' => $salaries,
            'societes' => $societes,
            'centres' => $centres,
            'pagination' => null,
        ]);
    }

    /**
     * Displays employees (uneditable).
     */
    #[IsGranted('ROLE_LIST_SALARIES_VIEW')]
    #[Route("/cts/salaries/list-salaries", name: 'app_salaries_list_uneditable')]
    public function listSalariesNonEditable(Request $request, SalarieRepository $salarieRepository): Response
    {
        $q = trim((string)$request->query->get('q', ''));
        $includeActive = $request->query->getInt('active', 1) === 1;
        $includeInactive = $request->query->getInt('inactive', 0) === 1;
        if (!$includeActive && !$includeInactive) {
            $includeActive = true;
            $includeInactive = true;
        }
        $salaries = $salarieRepository->findOrderedByNomPrenomSearch($q, $this->getCurrentUserCentreScopeIds(), $includeActive, $includeInactive);

        return $this->render('cts/salaries/list_uneditable.html.twig', [
            'salaries' => $salaries,
        ]);
    }

    #[IsGranted('ROLE_LIST_SALARIES_VIEW')]
    #[Route("/cts/salaries/list-salaries/partial", name: 'app_salaries_list_uneditable_partial')]
    public function listSalariesNonEditablePartial(Request $request, SalarieRepository $salarieRepository): Response
    {
        $q = trim((string)$request->query->get('q', ''));
        $includeActive = $request->query->getInt('active', 1) === 1;
        $includeInactive = $request->query->getInt('inactive', 0) === 1;
        if (!$includeActive && !$includeInactive) {
            $includeActive = true;
            $includeInactive = true;
        }
        $salaries = $salarieRepository->findOrderedByNomPrenomSearch($q, $this->getCurrentUserCentreScopeIds(), $includeActive, $includeInactive);

        return $this->render('cts/salaries/_list_results_uneditable.html.twig', [
            'salaries' => $salaries,
        ]);
    }

    #[IsGranted('ROLE_LIST_SALARIES_ADD')]
    #[Route("/cts/salaries/add", name: 'app_salaries_add')]
    public function addSalarie(Request $request, EntityManagerInterface $em): Response
    {
        $centreIds = $this->getCurrentUserCentreScopeIds();

        $form = $this->createForm(CreateSalarieType::class, null, [
            'centre_scope_ids' => $centreIds,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $salarie = $form->getData();

            $em->persist($salarie);
            $em->flush();

            $this->addFlash('success', 'Salarié créé.');

            return $this->redirectToRoute('app_salaries_list_uneditable');
        }

        return $this->render('cts/salaries/add.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[IsGranted('ROLE_LIST_SALARIES_EDIT')]
    #[Route('/cts/salaries/bulk-update', name: 'app_salaries_bulk_update', methods: ['POST'])]
    public function bulkUpdateSalaries(
        Request $request,
        SalarieRepository $salarieRepository,
        SocieteRepository $societeRepository,
        CentreRepository $centreRepository,
        EntityManagerInterface $em,
    ): Response {
        $centreIds = $this->getCurrentUserCentreScopeIds();
        $includeActive = $request->query->getInt('active', 1) === 1;
        $includeInactive = $request->query->getInt('inactive', 0) === 1;
        if (!$includeActive && !$includeInactive) {
            $includeActive = true;
            $includeInactive = true;
        }

        $q = trim((string)$request->query->get('q', ''));
        $salaries = $salarieRepository->findOrderedBySocieteSearch($q, $centreIds, $includeActive, $includeInactive);
        $payload = $request->request->all('changes');
        if (!$this->isCsrfTokenValid('salaries_bulk', (string) $request->request->get(self::CSRF_FIELD_NAME, ''))) {
            $this->addFlash('error', 'Jeton CSRF invalide, rechargez la page.');
            return $this->redirectToRoute('app_salaries_list', [
                'page' => max(1, $request->query->getInt('page', 1)),
                'q' => $request->query->get('q'),
                'active' => $includeActive ? 1 : 0,
                'inactive' => $includeInactive ? 1 : 0,
            ]);
        }
        if (!is_array($payload) || $payload === []) {
            $this->addFlash('warning', 'Aucune modification détectée.');
            return $this->redirectToRoute('app_salaries_list', [
                'page' => max(1, $request->query->getInt('page', 1)),
                'q' => $request->query->get('q'),
                'active' => $includeActive ? 1 : 0,
                'inactive' => $includeInactive ? 1 : 0,
            ]);
        }

        // Security: only allow updating salaries that are in the current page result set (and already centre-scoped).
        $allowedById = [];
        foreach ($salaries as $s) {
            if (!$s instanceof Salarie) {
                continue;
            }
            $id = $s->getId();
            if ($id !== null) {
                $allowedById[(string)$id] = $s;
            }
        }

        $errors = [];
        $changedCount = 0;

        foreach ($payload as $id => $fields) {
            $id = trim((string)$id);
            if ($id === '' || !isset($allowedById[$id])) {
                continue;
            }
            if (!is_array($fields)) {
                continue;
            }

            $salarie = $allowedById[$id];
            $rowErrors = $this->applyManualSalarieUpdate($salarie, $fields, $centreIds, $em);
            if ($rowErrors !== []) {
                $errors[] = sprintf('[%s %s] %s', (string)$salarie->getNom(), (string)$salarie->getPrenom(), implode(' | ', $rowErrors));
                continue;
            }

            ++$changedCount;
        }

        if ($errors !== []) {
            foreach ($errors as $msg) {
                $this->addFlash('error', $msg);
            }
            $societes = $societeRepository->findOrderedByNomSearch(null, $centreIds);
            $centres = $centreRepository->findOrderedBySocieteVilleAgrSearch(null, $centreIds);
            return $this->renderSalariesList($salaries, $societes, $centres, null);
        }

        if ($changedCount === 0) {
            $this->addFlash('warning', 'Aucune modification appliquée.');
            return $this->redirectToRoute('app_salaries_list', [
                'page' => max(1, $request->query->getInt('page', 1)),
                'q' => $request->query->get('q'),
                'active' => $includeActive ? 1 : 0,
                'inactive' => $includeInactive ? 1 : 0,
            ]);
        }

        $em->flush();
        $this->addFlash('success', 'Modifications enregistrées.');

        return $this->redirectToRoute('app_salaries_list_uneditable', [
            'q' => $request->query->get('q'),
            'active' => $includeActive ? 1 : 0,
            'inactive' => $includeInactive ? 1 : 0,
        ]);
    }

    /**
     * Displays companies with one inline edit form per row.
     */
    #[IsGranted('ROLE_LIST_SOCIETES_VIEW')]
    #[Route('/cts/societes/list', name: 'app_societes_list')]
    public function listSocietes(Request $request, SocieteRepository $societeRepository): Response
    {
        $q = trim((string)$request->query->get('q', ''));
        $societes = $societeRepository->findOrderedByNomSearch($q, $this->getCurrentUserCentreScopeIds());

        return $this->render('cts/societes/list.html.twig', [
            'societes' => $societes,
        ]);
    }

    #[IsGranted('ROLE_LIST_SOCIETES_VIEW')]
    #[Route('/cts/societes/list/partial', name: 'app_societes_list_partial')]
    public function listSocietesPartial(Request $request, SocieteRepository $societeRepository): Response
    {
        $q = trim((string)$request->query->get('q', ''));
        $societes = $societeRepository->findOrderedByNomSearch($q, $this->getCurrentUserCentreScopeIds());

        return $this->render('cts/societes/_list_results.html.twig', [
            'societes' => $societes,
        ]);
    }

    /**
     * Displays companies (uneditable).
     */
    #[IsGranted('ROLE_LIST_SOCIETES_VIEW')]
    #[Route("/cts/societes/list-societes", name: 'app_societes_list_uneditable')]
    public function listSocietesNonEditable(Request $request, SocieteRepository $societeRepository): Response
    {
        $q = trim((string)$request->query->get('q', ''));
        $societes = $societeRepository->findOrderedByNomSearch($q, $this->getCurrentUserCentreScopeIds());

        return $this->render('cts/societes/list_uneditable.html.twig', [
            'societes' => $societes,
        ]);
    }

    #[IsGranted('ROLE_LIST_SOCIETES_VIEW')]
    #[Route("/cts/societes/list-societes/partial", name: 'app_societes_list_uneditable_partial')]
    public function listSocietesNonEditablePartial(Request $request, SocieteRepository $societeRepository): Response
    {
        $q = trim((string)$request->query->get('q', ''));
        $societes = $societeRepository->findOrderedByNomSearch($q, $this->getCurrentUserCentreScopeIds());

        return $this->render('cts/societes/_list_results_uneditable.html.twig', [
            'societes' => $societes,
        ]);
    }

    #[IsGranted('ROLE_LIST_SOCIETES_ADD')]
    #[Route('/cts/societes/add', name: 'app_societes_add')]
    public function addSociete(Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(CreateSocieteType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $societe = $form->getData();

            $em->persist($societe);
            $em->flush();

            $this->addFlash('success', 'Société créée.');

            return $this->redirectToRoute('app_societes_list_uneditable');
        }

        return $this->render('cts/societes/add.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[IsGranted('ROLE_LIST_SOCIETES_EDIT')]
    #[Route('/cts/societes/bulk-update', name: 'app_societes_bulk_update', methods: ['POST'])]
    public function bulkUpdateSocietes(
        Request $request,
        SocieteRepository $societeRepository,
        EntityManagerInterface $em,
    ): Response {
        if (!$this->isCsrfTokenValid('societes_bulk', (string) $request->request->get(self::CSRF_FIELD_NAME, ''))) {
            $this->addFlash('error', 'Jeton CSRF invalide, rechargez la page.');
            return $this->redirectToRoute('app_societes_list', [
                'q' => $request->query->get('q'),
            ]);
        }

        $q = trim((string)$request->query->get('q', ''));
        $centreIds = $this->getCurrentUserCentreScopeIds();
        $societes = $societeRepository->findOrderedByNomSearch($q, $centreIds);

        $allowedById = [];
        foreach ($societes as $s) {
            $id = $s->getId();
            if ($id !== null) {
                $allowedById[(string)$id] = $s;
            }
        }

        $payload = $request->request->all('changes');
        if (!is_array($payload) || $payload === []) {
            $this->addFlash('warning', 'Aucune modification détectée.');
            return $this->redirectToRoute('app_societes_list', [
                'q' => $request->query->get('q'),
            ]);
        }

        $errors = [];
        $changed = 0;
        foreach ($payload as $id => $fields) {
            $id = trim((string)$id);
            if ($id === '' || !isset($allowedById[$id])) {
                continue;
            }
            if (!is_array($fields)) {
                continue;
            }

            $societe = $allowedById[$id];
            $rowErrors = $this->applyManualSocieteUpdate($societe, $fields);
            if ($rowErrors !== []) {
                $errors[] = sprintf('[%s] %s', (string)$societe->getNom(), implode(' | ', $rowErrors));
                continue;
            }
            ++$changed;
        }

        if ($errors !== []) {
            foreach ($errors as $msg) {
                $this->addFlash('error', $msg);
            }
            return $this->render('cts/societes/list.html.twig', [
                'societes' => $societes,
            ]);
        }

        if ($changed === 0) {
            $this->addFlash('warning', 'Aucune modification appliquée.');
            return $this->redirectToRoute('app_societes_list', [
                'q' => $request->query->get('q'),
            ]);
        }

        $em->flush();
        $this->addFlash('success', 'Modifications enregistrées.');

        return $this->redirectToRoute('app_societes_list_uneditable', [
            'q' => $request->query->get('q'),
        ]);
    }

    /**
     * Displays centers with one inline edit form per row.
     */
    #[IsGranted('ROLE_LIST_CENTRES_VIEW')]
    #[Route("/cts/centres/list", name: 'app_centres_list')]
    public function listCentres(
        Request $request,
        CentreRepository $centreRepository,
        SocieteRepository $societeRepository,
        \App\Repository\ReseauRepository $reseauRepository,
    ): Response
    {
        $q = trim((string)$request->query->get('q', ''));
        $centreIds = $this->getCurrentUserCentreScopeIds();
        $centres = $centreRepository->findOrderedBySocieteVilleAgrSearch($q, $centreIds);
        $societes = $societeRepository->findOrderedByNomSearch(null, $centreIds);
        $reseaux = $this->findReseauxForCentreScope($reseauRepository, $centreIds);

        return $this->render('cts/centres/list.html.twig', [
            'centres' => $centres,
            'societes' => $societes,
            'reseaux' => $reseaux,
        ]);
    }

    #[IsGranted('ROLE_LIST_CENTRES_VIEW')]
    #[Route("/cts/centres/list/partial", name: 'app_centres_list_partial')]
    public function listCentresPartial(
        Request $request,
        CentreRepository $centreRepository,
        SocieteRepository $societeRepository,
        \App\Repository\ReseauRepository $reseauRepository,
    ): Response
    {
        $q = trim((string)$request->query->get('q', ''));
        $centreIds = $this->getCurrentUserCentreScopeIds();
        $centres = $centreRepository->findOrderedBySocieteVilleAgrSearch($q, $centreIds);
        $societes = $societeRepository->findOrderedByNomSearch(null, $centreIds);
        $reseaux = $this->findReseauxForCentreScope($reseauRepository, $centreIds);

        return $this->render('cts/centres/_list_results.html.twig', [
            'centres' => $centres,
            'societes' => $societes,
            'reseaux' => $reseaux,
        ]);
    }

    /**
     * Displays centers (uneditable).
     */
    #[IsGranted('ROLE_LIST_CENTRES_VIEW')]
    #[Route("/cts/centres/list-centres", name: 'app_centres_list_uneditable')]
    public function listCentresNonEditable(Request $request, CentreRepository $centreRepository): Response
    {
        $q = trim((string)$request->query->get('q', ''));
        $centres = $centreRepository->findOrderedBySocieteVilleAgrSearch($q, $this->getCurrentUserCentreScopeIds());

        return $this->render('cts/centres/list_uneditable.html.twig', [
            'centres' => $centres,
        ]);
    }

    #[IsGranted('ROLE_LIST_CENTRES_VIEW')]
    #[Route("/cts/centres/list-centres/partial", name: 'app_centres_list_uneditable_partial')]
    public function listCentresNonEditablePartial(Request $request, CentreRepository $centreRepository): Response
    {
        $q = trim((string)$request->query->get('q', ''));
        $centres = $centreRepository->findOrderedBySocieteVilleAgrSearch($q, $this->getCurrentUserCentreScopeIds());

        return $this->render('cts/centres/_list_results_uneditable.html.twig', [
            'centres' => $centres,
        ]);
    }

    #[IsGranted('ROLE_LIST_CENTRES_ADD')]
    #[Route("/cts/centres/add", name: 'app_centres_add')]
    public function addCentre(Request $request, EntityManagerInterface $em): Response
    {
        $centreIds = $this->getCurrentUserCentreScopeIds();

        $form = $this->createForm(CreateCentreType::class, null, [
            'centre_scope_ids' => $centreIds,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $centre = $form->getData();

            if ($centre->getReseau() !== null) {
                $centre->setReseauNom($form->get('reseauNom')->getData());
            }

            $em->persist($centre);
            $em->flush();

            $this->addFlash('success', 'Centre créé.');

            return $this->redirectToRoute('app_centres_list_uneditable');
        }

        return $this->render('cts/centres/add.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[IsGranted('ROLE_LIST_CENTRES_EDIT')]
    #[Route('/cts/centres/bulk-update', name: 'app_centres_bulk_update', methods: ['POST'])]
    public function bulkUpdateCentres(
        Request $request,
        CentreRepository $centreRepository,
        SocieteRepository $societeRepository,
        \App\Repository\ReseauRepository $reseauRepository,
        EntityManagerInterface $em,
    ): Response {
        if (!$this->isCsrfTokenValid('centres_bulk', (string) $request->request->get(self::CSRF_FIELD_NAME, ''))) {
            $this->addFlash('error', 'Jeton CSRF invalide, rechargez la page.');
            return $this->redirectToRoute('app_centres_list', [
                'q' => $request->query->get('q'),
            ]);
        }

        $q = trim((string)$request->query->get('q', ''));
        $centreIds = $this->getCurrentUserCentreScopeIds();
        $centres = $centreRepository->findOrderedBySocieteVilleAgrSearch($q, $centreIds);
        $societes = $societeRepository->findOrderedByNomSearch(null, $centreIds);
        $reseaux = $this->findReseauxForCentreScope($reseauRepository, $centreIds);

        $allowedById = [];
        foreach ($centres as $c) {
            $id = $c->getId();
            if ($id !== null) {
                $allowedById[(string)$id] = $c;
            }
        }

        $payload = $request->request->all('changes');
        if (!is_array($payload) || $payload === []) {
            $this->addFlash('warning', 'Aucune modification détectée.');
            return $this->redirectToRoute('app_centres_list', [
                'q' => $request->query->get('q'),
            ]);
        }

        $societeIds = array_fill_keys(array_filter(array_map(static fn($s) => $s->getId(), $societes)), true);
        $reseauIds = array_fill_keys(array_filter(array_map(static fn($r) => $r->getId(), $reseaux)), true);

        $errors = [];
        $changed = 0;
        foreach ($payload as $id => $fields) {
            $id = trim((string)$id);
            if ($id === '' || !isset($allowedById[$id])) {
                continue;
            }
            if (!is_array($fields)) {
                continue;
            }

            $centre = $allowedById[$id];
            $rowErrors = $this->applyManualCentreUpdate(
                $centre,
                $fields,
                $societeRepository,
                $reseauRepository,
                $societeIds,
                $reseauIds
            );
            if ($rowErrors !== []) {
                $errors[] = sprintf('[%s] %s', (string)$centre->getVille(), implode(' | ', $rowErrors));
                continue;
            }
            ++$changed;
        }

        if ($errors !== []) {
            foreach ($errors as $msg) {
                $this->addFlash('error', $msg);
            }
            return $this->render('cts/centres/list.html.twig', [
                'centres' => $centres,
                'societes' => $societes,
                'reseaux' => $reseaux,
            ]);
        }

        if ($changed === 0) {
            $this->addFlash('warning', 'Aucune modification appliquée.');
            return $this->redirectToRoute('app_centres_list', [
                'q' => $request->query->get('q'),
            ]);
        }

        $em->flush();
        $this->addFlash('success', 'Modifications enregistrées.');

        return $this->redirectToRoute('app_centres_list_uneditable', [
            'q' => $request->query->get('q'),
        ]);
    }

    /**
     * Displays vehicles (uneditable).
     */
    #[IsGranted('ROLE_LIST_VOITURES_VIEW')]
    #[Route("/cts/voitures/list-voitures", name: 'app_voitures_list_uneditable')]
    public function listVoituresNonEditable(Request $request, VoitureRepository $voitureRepository): Response
    {
        $q = trim((string)$request->query->get('q', ''));
        $includeActive = $request->query->getInt('active', 1) === 1;
        $includeInactive = $request->query->getInt('inactive', 0) === 1;
        if (!$includeActive && !$includeInactive) {
            $includeActive = true;
            $includeInactive = true;
        }
        $voitures = $voitureRepository->findOrderedBySocieteSearch($q, $this->getCurrentUserCentreScopeIds(), $includeActive, $includeInactive);

        return $this->render('cts/voitures/list_uneditable.html.twig', [
            'voitures' => $voitures,
        ]);
    }

    #[IsGranted('ROLE_LIST_VOITURES_VIEW')]
    #[Route("/cts/voitures/list-voitures/partial", name: 'app_voitures_list_uneditable_partial')]
    public function listVoituresNonEditablePartial(Request $request, VoitureRepository $voitureRepository): Response
    {
        $q = trim((string)$request->query->get('q', ''));
        $includeActive = $request->query->getInt('active', 1) === 1;
        $includeInactive = $request->query->getInt('inactive', 0) === 1;
        if (!$includeActive && !$includeInactive) {
            $includeActive = true;
            $includeInactive = true;
        }
        $voitures = $voitureRepository->findOrderedBySocieteSearch($q, $this->getCurrentUserCentreScopeIds(), $includeActive, $includeInactive);

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
        SocieteRepository    $societeRepository,
        CentreRepository     $centreRepository,
    ): Response
    {
        $q = trim((string)$request->query->get('q', ''));
        $centreIds = $this->getCurrentUserCentreScopeIds();
        $includeActive = $request->query->getInt('active', 1) === 1;
        $includeInactive = $request->query->getInt('inactive', 0) === 1;
        if (!$includeActive && !$includeInactive) {
            $includeActive = true;
            $includeInactive = true;
        }
        $voitures = $voitureRepository->findOrderedBySocieteSearch($q, $centreIds, $includeActive, $includeInactive);
        $societes = $societeRepository->findOrderedByNomSearch(null, $centreIds);
        $centres = $centreRepository->findOrderedBySocieteVilleAgrSearch(null, $centreIds);

        return $this->render('cts/voitures/list.html.twig', [
            'voitures' => $voitures,
            'societes' => $societes,
            'centres' => $centres,
            'pagination' => null,
        ]);
    }

    #[IsGranted('ROLE_LIST_VOITURES_VIEW')]
    #[Route("/cts/voitures/list/partial", name: 'app_voitures_list_partial')]
    public function listVoituresPartial(
        Request              $request,
        VoitureRepository    $voitureRepository,
        SocieteRepository    $societeRepository,
        CentreRepository     $centreRepository,
    ): Response
    {
        $q = trim((string)$request->query->get('q', ''));
        $centreIds = $this->getCurrentUserCentreScopeIds();
        $includeActive = $request->query->getInt('active', 1) === 1;
        $includeInactive = $request->query->getInt('inactive', 0) === 1;
        if (!$includeActive && !$includeInactive) {
            $includeActive = true;
            $includeInactive = true;
        }
        $voitures = $voitureRepository->findOrderedBySocieteSearch($q, $centreIds, $includeActive, $includeInactive);
        $societes = $societeRepository->findOrderedByNomSearch(null, $centreIds);
        $centres = $centreRepository->findOrderedBySocieteVilleAgrSearch(null, $centreIds);

        return $this->render('cts/voitures/_list_results.html.twig', [
            'voitures' => $voitures,
            'societes' => $societes,
            'centres' => $centres,
            'pagination' => null,
        ]);
    }

    #[IsGranted('ROLE_LIST_VOITURES_ADD')]
    #[Route("/cts/voitures/add", name: 'app_voitures_add')]
    public function addVoiture(
        Request                         $request,
        EntityManagerInterface          $em,
        VoitureCertificatCessionStorage $storage,
    ): Response
    {
        $centreIds = $this->getCurrentUserCentreScopeIds();

        $form = $this->createForm(CreateVoitureType::class, null, [
            'centre_scope_ids' => $centreIds,
        ]);
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
                    return $this->redirectToRoute('app_voitures_list_uneditable');
                }

                // Read metadata before move(): after moving, the tmp file no longer exists.
                $originalName = $uploaded->getClientOriginalName();
                $mime = (string)($uploaded->getMimeType() ?? '');
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

            return $this->redirectToRoute('app_voitures_list_uneditable');
        }

        return $this->render('cts/voitures/add.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[IsGranted('ROLE_LIST_VOITURES_EDIT')]
    #[Route('/cts/voitures/bulk-update', name: 'app_voitures_bulk_update', methods: ['POST'])]
    public function bulkUpdateVoitures(
        Request $request,
        VoitureRepository $voitureRepository,
        SocieteRepository $societeRepository,
        CentreRepository $centreRepository,
        EntityManagerInterface $em,
    ): Response {
        if (!$this->isCsrfTokenValid('voitures_bulk', (string) $request->request->get(self::CSRF_FIELD_NAME, ''))) {
            $this->addFlash('error', 'Jeton CSRF invalide, rechargez la page.');
            return $this->redirectToRoute('app_voitures_list', [
                'q' => $request->query->get('q'),
                'active' => $request->query->getInt('active', 1),
                'inactive' => $request->query->getInt('inactive', 0),
            ]);
        }

        $centreIds = $this->getCurrentUserCentreScopeIds();
        $includeActive = $request->query->getInt('active', 1) === 1;
        $includeInactive = $request->query->getInt('inactive', 0) === 1;
        if (!$includeActive && !$includeInactive) {
            $includeActive = true;
            $includeInactive = true;
        }

        $q = trim((string)$request->query->get('q', ''));
        $voitures = $voitureRepository->findOrderedBySocieteSearch($q, $centreIds, $includeActive, $includeInactive);

        $societes = $societeRepository->findOrderedByNomSearch(null, $centreIds);
        $centres = $centreRepository->findOrderedBySocieteVilleAgrSearch(null, $centreIds);

        $allowedById = [];
        foreach ($voitures as $v) {
            $id = $v->getId();
            if ($id !== null) {
                $allowedById[(string)$id] = $v;
            }
        }

        $payload = $request->request->all('changes');
        if (!is_array($payload) || $payload === []) {
            $this->addFlash('warning', 'Aucune modification détectée.');
            return $this->redirectToRoute('app_voitures_list', [
                'q' => $request->query->get('q'),
                'active' => $includeActive ? 1 : 0,
                'inactive' => $includeInactive ? 1 : 0,
            ]);
        }

        $societeIds = array_fill_keys(array_filter(array_map(static fn($s) => $s->getId(), $societes)), true);
        $centreAllowedIds = array_fill_keys(array_filter(array_map(static fn($c) => $c->getId(), $centres)), true);

        $errors = [];
        $changed = 0;
        foreach ($payload as $id => $fields) {
            $id = trim((string)$id);
            if ($id === '' || !isset($allowedById[$id])) {
                continue;
            }
            if (!is_array($fields)) {
                continue;
            }

            $voiture = $allowedById[$id];
            $rowErrors = $this->applyManualVoitureUpdate($voiture, $fields, $societeRepository, $centreRepository, $societeIds, $centreAllowedIds);
            if ($rowErrors !== []) {
                $errors[] = sprintf('[%s] %s', (string)$voiture->getImmatriculation(), implode(' | ', $rowErrors));
                continue;
            }
            ++$changed;
        }

        if ($errors !== []) {
            foreach ($errors as $msg) {
                $this->addFlash('error', $msg);
            }
            return $this->render('cts/voitures/list.html.twig', [
                'voitures' => $voitures,
                'societes' => $societes,
                'centres' => $centres,
                'pagination' => null,
            ]);
        }

        if ($changed === 0) {
            $this->addFlash('warning', 'Aucune modification appliquée.');
            return $this->redirectToRoute('app_voitures_list', [
                'q' => $request->query->get('q'),
                'active' => $includeActive ? 1 : 0,
                'inactive' => $includeInactive ? 1 : 0,
            ]);
        }

        $em->flush();
        $this->addFlash('success', 'Modifications enregistrées.');

        return $this->redirectToRoute('app_voitures_list_uneditable', [
            'q' => $request->query->get('q'),
            'active' => $includeActive ? 1 : 0,
            'inactive' => $includeInactive ? 1 : 0,
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
        $openBasedir = (string)ini_get('open_basedir');
        $uploadTmpDir = (string)ini_get('upload_tmp_dir');

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

    #[IsGranted('ROLE_LIST_VOITURES_ADD')]
    #[Route('/cts/voitures/{id}/certificat-cession/upload', name: 'app_voitures_certificat_upload', methods: ['POST'])]
    public function uploadVoitureCertificatCession(
        Voiture                         $voiture,
        Request                         $request,
        EntityManagerInterface          $em,
        VoitureCertificatCessionStorage $storage,
    ): Response
    {
        $token = (string)$request->request->get(self::CSRF_FIELD_NAME, '');
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

        $mime = (string)($file->getMimeType() ?? '');
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

    #[IsGranted('ROLE_LIST_VOITURES_ADD')]
    #[Route('/cts/voitures/{id}/certificat-cession/download', name: 'app_voitures_certificat_download', methods: ['GET'])]
    public function downloadVoitureCertificatCession(
        Voiture                         $voiture,
        VoitureCertificatCessionStorage $storage,
    ): Response
    {
        $relative = $voiture->getCertificatCessionPath();
        if (!$relative) {
            throw $this->createNotFoundException('Aucun certificat associé à cette voiture.');
        }

        $absolute = $storage->absolutePath($relative);
        if (!is_file($absolute)) {
            throw $this->createNotFoundException('Fichier introuvable.');
        }

        $immatriculation = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string)$voiture->getImmatriculation());
        $immatriculation = trim((string)$immatriculation, '_');
        $downloadName = $immatriculation !== '' ? ('certificat_cession_' . $immatriculation) : 'certificat_cession';

        $ext = pathinfo($absolute, PATHINFO_EXTENSION);
        if ($ext) {
            $downloadName .= '.' . $ext;
        }

        $response = new BinaryFileResponse($absolute);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $downloadName);

        return $response;
    }

    #[IsGranted('ROLE_LIST_VOITURES_VIEW')]
    #[Route('/cts/voitures/{id}/certificat-cession/view', name: 'app_voitures_certificat_view', methods: ['GET'])]
    public function viewVoitureCertificatCession(
        Voiture                         $voiture,
        VoitureCertificatCessionStorage $storage,
    ): Response
    {
        $relative = $voiture->getCertificatCessionPath();
        if (!$relative) {
            throw $this->createNotFoundException('Aucun certificat associé à cette voiture.');
        }

        $absolute = $storage->absolutePath($relative);
        if (!is_file($absolute)) {
            throw $this->createNotFoundException('Fichier introuvable.');
        }

        $response = new BinaryFileResponse($absolute);

        $immatriculation = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string)$voiture->getImmatriculation());
        $immatriculation = trim((string)$immatriculation, '_');
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

    #[IsGranted('ROLE_LIST_VOITURES_ADD')]
    #[Route('/cts/voitures/{id}/certificat-cession/delete', name: 'app_voitures_certificat_delete', methods: ['POST'])]
    public function deleteVoitureCertificatCession(
        Voiture                         $voiture,
        Request                         $request,
        EntityManagerInterface          $em,
        VoitureCertificatCessionStorage $storage,
    ): Response
    {
        $token = (string)$request->request->get(self::CSRF_FIELD_NAME, '');
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
        $q = trim((string)$request->query->get('q', ''));
        $centreIds = $this->getCurrentUserCentreScopeIds();
        $includeActive = $request->query->getInt('active', 1) === 1;
        $includeInactive = $request->query->getInt('inactive', 0) === 1;
        if (!$includeActive && !$includeInactive) {
            $includeActive = true;
            $includeInactive = true;
        }
        $perPage = 30;
        $paginationData = $this->computePagination($request, $perPage, $salarieRepository->countSearch($q, $centreIds, $includeActive, $includeInactive));
        $salaries = $salarieRepository->findPaginatedOrderedBySocieteSearch($perPage, $paginationData['offset'], $q, $centreIds, $includeActive, $includeInactive);

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
        $q = trim((string)$request->query->get('q', ''));
        $centreIds = $this->getCurrentUserCentreScopeIds();
        $includeActive = $request->query->getInt('active', 1) === 1;
        $includeInactive = $request->query->getInt('inactive', 0) === 1;
        if (!$includeActive && !$includeInactive) {
            $includeActive = true;
            $includeInactive = true;
        }
        $salaries = $salarieRepository->findOrderedByNomPrenomSearch($q, $centreIds, $includeActive, $includeInactive);

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
        $q = trim((string)$request->query->get('q', ''));
        $societes = $societeRepository->findOrderedByNomSearch($q, $this->getCurrentUserCentreScopeIds());

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
        $q = trim((string)$request->query->get('q', ''));
        $centres = $centreRepository->findOrderedBySocieteVilleAgrSearch($q, $this->getCurrentUserCentreScopeIds());

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
        $q = trim((string)$request->query->get('q', ''));
        $centreIds = $this->getCurrentUserCentreScopeIds();
        $includeActive = $request->query->getInt('active', 1) === 1;
        $includeInactive = $request->query->getInt('inactive', 0) === 1;
        if (!$includeActive && !$includeInactive) {
            $includeActive = true;
            $includeInactive = true;
        }
        $perPage = 20;
        $paginationData = $this->computePagination($request, $perPage, $voitureRepository->countSearch($q, $centreIds, $includeActive, $includeInactive));
        $voitures = $voitureRepository->findPaginatedOrderedBySocieteSearch($perPage, $paginationData['offset'], $q, $centreIds, $includeActive, $includeInactive);

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
        $q = trim((string)$request->query->get('q', ''));
        $includeActive = $request->query->getInt('active', 1) === 1;
        $includeInactive = $request->query->getInt('inactive', 0) === 1;
        if (!$includeActive && !$includeInactive) {
            $includeActive = true;
            $includeInactive = true;
        }
        $voitures = $voitureRepository->findOrderedBySocieteSearch($q, $this->getCurrentUserCentreScopeIds(), $includeActive, $includeInactive);

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

    private function renderSalariesList(array $salaries, array $societes, array $centres, ?array $paginationView): Response
    {
        return $this->render('cts/salaries/list.html.twig', [
            'salaries' => $salaries,
            'societes' => $societes,
            'centres' => $centres,
            'pagination' => $paginationView,
        ]);
    }

    /**
     * @param array<int, mixed> $fields
     * @return list<string> Row errors.
     */
    private function applyManualSocieteUpdate(Societe $societe, array $fields): array
    {
        $errors = [];

        $trimOrNull = static function ($v): ?string {
            if ($v === null) return null;
            $s = trim((string)$v);
            return $s === '' ? null : $s;
        };

        $nom = $trimOrNull($fields['nom'] ?? null);
        $siege = $trimOrNull($fields['siegeSocial'] ?? null);
        $siren = $trimOrNull($fields['siren'] ?? null);
        $numTva = $trimOrNull($fields['numTva'] ?? null);

        if ($nom === null) $errors[] = 'Nom requis.';
        if ($siege === null) $errors[] = 'Siège social requis.';
        if ($siren === null) $errors[] = 'SIREN requis.';

        if ($nom !== null) $societe->setNom($nom);
        if ($siege !== null) $societe->setSiegeSocial($siege);
        if ($siren !== null) $societe->setSiren($siren);
        $societe->setNumTva($numTva);

        return $errors;
    }

    /**
     * @return array<int, \App\Entity\Reseau>
     */
    private function findReseauxForCentreScope(\App\Repository\ReseauRepository $reseauRepository, ?array $centreScopeIds): array
    {
        $qb = $reseauRepository->createQueryBuilder('r')
            ->orderBy('r.nom', 'ASC');

        if ($centreScopeIds !== null) {
            $qb->distinct();
            if ($centreScopeIds === []) {
                $qb->andWhere('1=0');
            } else {
                $qb
                    ->innerJoin('r.centres', 'c_scope')
                    ->andWhere('c_scope.id IN (:centreIds)')
                    ->setParameter('centreIds', $centreScopeIds);
            }
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @param array<int, mixed> $fields
     * @param array<int, true> $allowedSocieteIds
     * @param array<int, true> $allowedReseauIds
     * @return list<string> Row errors.
     */
    private function applyManualCentreUpdate(
        Centre $centre,
        array $fields,
        SocieteRepository $societeRepository,
        \App\Repository\ReseauRepository $reseauRepository,
        array $allowedSocieteIds,
        array $allowedReseauIds,
    ): array {
        $errors = [];

        $trimOrNull = static function ($v): ?string {
            if ($v === null) return null;
            $s = trim((string)$v);
            return $s === '' ? null : $s;
        };

        $societeId = $trimOrNull($fields['societe'] ?? null);
        if ($societeId === null || !ctype_digit($societeId)) {
            $errors[] = 'Société invalide.';
        } else {
            $sid = (int)$societeId;
            if (!isset($allowedSocieteIds[$sid])) {
                $errors[] = 'Société hors scope.';
            } else {
                $societe = $societeRepository->find($sid);
                if (!$societe instanceof \App\Entity\Societe) {
                    $errors[] = 'Société introuvable.';
                } else {
                    $centre->setSociete($societe);
                }
            }
        }

        $reseauId = $trimOrNull($fields['reseau'] ?? null);
        if ($reseauId === null || !ctype_digit($reseauId)) {
            $errors[] = 'Réseau invalide.';
        } else {
            $rid = (int)$reseauId;
            if (!isset($allowedReseauIds[$rid])) {
                $errors[] = 'Réseau hors scope.';
            } else {
                $reseau = $reseauRepository->find($rid);
                if (!$reseau instanceof \App\Entity\Reseau) {
                    $errors[] = 'Réseau introuvable.';
                } else {
                    $centre->setReseau($reseau);
                }
            }
        }

        $reseauNom = $trimOrNull($fields['reseauNom'] ?? null);
        if ($reseauNom === null) {
            $errors[] = 'Enseigne requise.';
        } else {
            $centre->setReseauNom($reseauNom);
        }

        $agrCentre = $trimOrNull($fields['agrCentre'] ?? null);
        if ($agrCentre === null) {
            $errors[] = 'Agrément VL requis.';
        } else {
            $centre->setAgrCentre($agrCentre);
        }

        $centre->setAgrClCentre($trimOrNull($fields['agrClCentre'] ?? null));
        $centre->setCoordonnees($trimOrNull($fields['coordonnees'] ?? null));

        $cp = $trimOrNull($fields['cp'] ?? null);
        if ($cp === null) {
            $errors[] = 'CP requis.';
        } else {
            $centre->setCp($cp);
        }

        $ville = $trimOrNull($fields['ville'] ?? null);
        if ($ville === null) {
            $errors[] = 'Ville requise.';
        } else {
            $centre->setVille($ville);
        }

        $tel = $trimOrNull($fields['telephone'] ?? null);
        if ($tel !== null) {
            $regex = '/^((0[1-9])|(\\+33))[ .-]?((?:[ .-]?\\d{2}){4}|\\d{8})$/';
            if (preg_match($regex, $tel) !== 1) {
                $errors[] = 'Téléphone invalide.';
            } else {
                $centre->setTelephone($tel);
            }
        } else {
            $centre->setTelephone(null);
        }

        $email = $trimOrNull($fields['email'] ?? null);
        if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = 'Email invalide.';
        } else {
            $centre->setEmail($email);
        }

        $centre->setMailPassword($trimOrNull($fields['mailPassword'] ?? null));
        $centre->setSiteWeb($trimOrNull($fields['siteWeb'] ?? null));
        $centre->setNumSiret($trimOrNull($fields['numSiret'] ?? null) ?? '');
        $centre->setDateReprise($trimOrNull($fields['dateReprise'] ?? null));

        return $errors;
    }

    /**
     * @param array<int, mixed> $fields
     * @param array<int, true> $allowedSocieteIds
     * @param array<int, true> $allowedCentreIds
     * @return list<string> Row errors.
     */
    private function applyManualVoitureUpdate(
        Voiture $voiture,
        array $fields,
        SocieteRepository $societeRepository,
        CentreRepository $centreRepository,
        array $allowedSocieteIds,
        array $allowedCentreIds,
    ): array {
        $errors = [];

        $trimOrNull = static function ($v): ?string {
            if ($v === null) return null;
            $s = trim((string)$v);
            return $s === '' ? null : $s;
        };

        $societeId = $trimOrNull($fields['societe'] ?? null);
        if ($societeId === null || !ctype_digit($societeId)) {
            $errors[] = 'Société invalide.';
        } else {
            $sid = (int)$societeId;
            if (!isset($allowedSocieteIds[$sid])) {
                $errors[] = 'Société hors scope.';
            } else {
                $societe = $societeRepository->find($sid);
                if (!$societe instanceof \App\Entity\Societe) {
                    $errors[] = 'Société introuvable.';
                } else {
                    $voiture->setSociete($societe);
                }
            }
        }

        $centreId = $trimOrNull($fields['centre'] ?? null);
        if ($centreId === null || !ctype_digit($centreId)) {
            $errors[] = 'Centre invalide.';
        } else {
            $cid = (int)$centreId;
            if (!isset($allowedCentreIds[$cid])) {
                $errors[] = 'Centre hors scope.';
            } else {
                $centre = $centreRepository->find($cid);
                if (!$centre instanceof \App\Entity\Centre) {
                    $errors[] = 'Centre introuvable.';
                } else {
                    $voiture->setCentre($centre);
                }
            }
        }

        // Non-nullable in DB: keep empty strings if not provided.
        $voiture->setImmatriculation((string)($trimOrNull($fields['immatriculation'] ?? null) ?? ''));
        $voiture->setMarque((string)($trimOrNull($fields['marque'] ?? null) ?? ''));
        $voiture->setCouleur($trimOrNull($fields['couleur'] ?? null));
        $voiture->setModele($trimOrNull($fields['modele'] ?? null));

        $flocable = $fields['flocable'] ?? null;
        if (is_array($flocable)) {
            $voiture->setFlocable(in_array('1', array_map('strval', $flocable), true));
        } else {
            $voiture->setFlocable((bool)($flocable === '1' || $flocable === 1 || $flocable === true || $flocable === 'on'));
        }

        $annee = $trimOrNull($fields['annee'] ?? null);
        if ($annee !== null) {
            if (!ctype_digit($annee) || strlen($annee) !== 4) {
                $errors[] = 'Année invalide.';
            } else {
                $voiture->setAnnee($annee);
            }
        } else {
            $voiture->setAnnee(null);
        }

        $ct = $trimOrNull($fields['controleTechnique'] ?? null);
        if ($ct !== null) {
            $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $ct);
            if (!$dt instanceof \DateTimeImmutable || $dt->format('Y-m-d') !== $ct) {
                $errors[] = 'Date contrôle technique invalide.';
            } else {
                $voiture->setControleTechnique($dt);
            }
        } else {
            $voiture->setControleTechnique(null);
        }

        $voiture->setKm($trimOrNull($fields['km'] ?? null));

        $prix = $trimOrNull($fields['prix'] ?? null);
        if ($prix !== null) {
            $prix = str_replace(',', '.', $prix);
            if (!is_numeric($prix)) {
                $errors[] = 'Prix invalide.';
            } else {
                $voiture->setPrix((string)$prix);
            }
        } else {
            $voiture->setPrix(null);
        }

        $voiture->setCarteGrise($trimOrNull($fields['carteGrise'] ?? null));
        $voiture->setLieu($trimOrNull($fields['lieu'] ?? null));
        $voiture->setUtilisateur($trimOrNull($fields['utilisateur'] ?? null));
        $voiture->setRemarques($trimOrNull($fields['remarques'] ?? null));

        $active = $fields['active'] ?? null;
        if (is_array($active)) {
            $voiture->setActive(in_array('1', array_map('strval', $active), true));
        } else {
            $voiture->setActive((bool)($active === '1' || $active === 1 || $active === true || $active === 'on'));
        }

        return $errors;
    }

    /**
     * @param array<int, mixed> $fields
     * @return list<string> Row errors.
     */
    private function applyManualSalarieUpdate(Salarie $salarie, array $fields, ?array $centreScopeIds, EntityManagerInterface $em): array
    {
        $errors = [];

        $trimOrNull = static function ($v): ?string {
            if ($v === null) return null;
            $s = trim((string)$v);
            return $s === '' ? null : $s;
        };

        // Required: societe, nom, prenom.
        $societeId = $trimOrNull($fields['societe'] ?? null);
        if ($societeId === null || !ctype_digit($societeId)) {
            $errors[] = 'Société invalide.';
        } else {
            /** @var \App\Repository\SocieteRepository $societeRepo */
            $societeRepo = $em->getRepository(\App\Entity\Societe::class);
            $societe = $societeRepo->find((int)$societeId);
            if (!$societe instanceof \App\Entity\Societe) {
                $errors[] = 'Société introuvable.';
            } else {
                $salarie->setSociete($societe);
            }
        }

        $nom = $trimOrNull($fields['nom'] ?? null);
        $prenom = $trimOrNull($fields['prenom'] ?? null);
        if ($nom === null) $errors[] = 'Nom requis.';
        if ($prenom === null) $errors[] = 'Prénom requis.';
        if ($nom !== null) $salarie->setNom($nom);
        if ($prenom !== null) $salarie->setPrenom($prenom);

        $salarie->setAgrControleur($trimOrNull($fields['agrControleur'] ?? null));
        $salarie->setAgrClControleur($trimOrNull($fields['agrClControleur'] ?? null));
        $salarie->setEmail($trimOrNull($fields['email'] ?? null) ?? '');
        // Email in entity is nullable=false currently; keep empty string if null.
        $tel = $trimOrNull($fields['telephone'] ?? null);
        if ($tel !== null) {
            $regex = '/^((0[1-9])|(\\+33))[ .-]?((?:[ .-]?\\d{2}){4}|\\d{8})$/';
            if (preg_match($regex, $tel) !== 1) {
                $errors[] = 'Téléphone invalide.';
            } else {
                $salarie->setTelephone($tel);
            }
        } else {
            $salarie->setTelephone(null);
        }

        $date = $trimOrNull($fields['dateNaissance'] ?? null);
        if ($date !== null) {
            $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $date);
            if (!$dt instanceof \DateTimeImmutable || $dt->format('Y-m-d') !== $date) {
                $errors[] = 'Date de naissance invalide.';
            } else {
                $salarie->setDateNaissance($dt);
            }
        } else {
            $salarie->setDateNaissance(null);
        }

        $echelons = $trimOrNull($fields['echelons'] ?? null);
        if ($echelons !== null) {
            if (!ctype_digit($echelons) || (int)$echelons < 1 || (int)$echelons > 12) {
                $errors[] = 'Échelons invalide.';
            } else {
                $salarie->setEchelons((int)$echelons);
            }
        } else {
            $salarie->setEchelons(null);
        }

        $salaire = $trimOrNull($fields['salaireBrut'] ?? null);
        if ($salaire !== null) {
            $salaire = str_replace(',', '.', $salaire);
            if (!is_numeric($salaire)) {
                $errors[] = 'Salaire invalide.';
            } else {
                $salarie->setSalaireBrut((string)$salaire);
            }
        } else {
            $salarie->setSalaireBrut(null);
        }

        $heures = $trimOrNull($fields['nbHeures'] ?? null);
        if ($heures !== null) {
            $heures = str_replace(',', '.', $heures);
            if (!is_numeric($heures)) {
                $errors[] = 'Heures invalide.';
            } else {
                $salarie->setNbHeures((string)$heures);
            }
        } else {
            $salarie->setNbHeures(null);
        }

        $salarie->setVesteMancheAmovible($trimOrNull($fields['vesteMancheAmovible'] ?? null));
        $salarie->setPolaire($trimOrNull($fields['polaire'] ?? null));
        $salarie->setPantalon($trimOrNull($fields['pantalon'] ?? null));
        $salarie->setTeeShirts($trimOrNull($fields['teeShirts'] ?? null));
        $salarie->setPolo($trimOrNull($fields['polo'] ?? null));

        $chaussures = $trimOrNull($fields['chaussures'] ?? null);
        if ($chaussures !== null) {
            if (!ctype_digit($chaussures) || (int)$chaussures < 36 || (int)$chaussures > 50) {
                $errors[] = 'Chaussures invalide.';
            } else {
                $salarie->setChaussures((int)$chaussures);
            }
        } else {
            $salarie->setChaussures(null);
        }

        $isActive = $fields['isActive'] ?? null;
        if (is_array($isActive)) {
            $salarie->setIsActive(in_array('1', array_map('strval', $isActive), true));
        } else {
            $salarie->setIsActive((bool)($isActive === '1' || $isActive === 1 || $isActive === true || $isActive === 'on'));
        }

        // Centres (many-to-many). Enforce scope on ids.
        $centreIds = $fields['centres'] ?? [];
        if (!is_array($centreIds)) {
            $centreIds = [];
        }
        $centreIds = array_values(array_unique(array_filter(array_map(static fn($v) => ctype_digit((string)$v) ? (int)$v : null, $centreIds))));

        if ($centreScopeIds !== null) {
            // Restrict to allowed ids only.
            $allowed = array_fill_keys($centreScopeIds, true);
            $centreIds = array_values(array_filter($centreIds, static fn(int $id): bool => isset($allowed[$id])));
        }

        /** @var \App\Repository\CentreRepository $centreRepo */
        $centreRepo = $em->getRepository(\App\Entity\Centre::class);
        $wanted = [];
        if ($centreIds !== []) {
            $wanted = $centreRepo->createQueryBuilder('c')
                ->andWhere('c.id IN (:ids)')
                ->setParameter('ids', $centreIds)
                ->getQuery()
                ->getResult();
        }

        // Replace collection.
        foreach ($salarie->getCentres() as $existing) {
            $salarie->removeCentre($existing);
        }
        foreach ($wanted as $c) {
            if ($c instanceof \App\Entity\Centre) {
                $salarie->addCentre($c);
            }
        }

        return $errors;
    }


    /**
     * Displays bank balances.
     * @throws Exception
     */
    #[IsGranted('ROLE_ENCOURS_VIEW')]
    #[Route("/encours-bancaires", name: 'app_encours_bancaires')]
    public function encours(
        Request $request,
        EncoursPageBuilder $encoursPageBuilder
    ): Response
    {
        $viewData = $encoursPageBuilder->build($request, 'exploitation', $this->getCurrentUserSocieteScopeIds());
        return $this->render('encours/encours.html.twig', $viewData);
    }

    /**
     * @throws Exception
     */
    #[IsGranted('ROLE_ENCOURS_VIEW')]
    #[Route("/encours-bancaires/print", name: 'app_encours_bancaires_print')]
    public function encoursPrint(
        Request $request,
        EncoursPageBuilder $encoursPageBuilder
    ): Response
    {
        $viewData = $encoursPageBuilder->build($request, 'exploitation', $this->getCurrentUserSocieteScopeIds());

        $societesSelected = [];
        $societeIds = $viewData['societeIds'] ?? [];
        $societes = $viewData['societes'] ?? [];
        if (is_array($societeIds) && is_array($societes)) {
            foreach ($societeIds as $id) {
                if (isset($societes[$id])) {
                    $societesSelected[] = (string) $societes[$id];
                }
            }
        }

        $type = (string) ($viewData['type'] ?? 'exploitation');
        $anneeDepuis = $viewData['anneeDepuis'] ?? null;
        $anneeJusqua = $viewData['anneeJusqua'] ?? null;

        $printFilters = [
            ['label' => 'Type', 'value' => $type === 'immobilier' ? 'Immobilier' : 'Exploitations'],
            ['label' => 'Sociétés', 'value' => $societesSelected !== [] ? implode(', ', $societesSelected) : 'Toutes'],
            ['label' => 'Année depuis', 'value' => is_int($anneeDepuis) && $anneeDepuis > 0 ? (string) $anneeDepuis : 'Toutes'],
            ['label' => 'Année jusqu\'à', 'value' => is_int($anneeJusqua) && $anneeJusqua > 0 ? (string) $anneeJusqua : 'Toutes'],
        ];

        $viewData['printFilters'] = $printFilters;

        return $this->render('encours/print/encours.html.twig', $viewData);
    }

    /**
     * @throws Exception
     */
    #[IsGranted('ROLE_ENCOURS_VIEW')]
    #[Route("/encours-bancaires/print-totals", name: 'app_encours_bancaires_print_totals')]
    public function encoursPrintTotals(
        Request $request,
        EncoursPageBuilder $encoursPageBuilder
    ): Response
    {
        $viewData = $encoursPageBuilder->build($request, 'exploitation', $this->getCurrentUserSocieteScopeIds());

        $societesSelected = [];
        $societeIds = $viewData['societeIds'] ?? [];
        $societes = $viewData['societes'] ?? [];
        if (is_array($societeIds) && is_array($societes)) {
            foreach ($societeIds as $id) {
                if (isset($societes[$id])) {
                    $societesSelected[] = (string) $societes[$id];
                }
            }
        }

        $type = (string) ($viewData['type'] ?? 'exploitation');
        $anneeDepuis = $viewData['anneeDepuis'] ?? null;
        $anneeJusqua = $viewData['anneeJusqua'] ?? null;

        $printFilters = [
            ['label' => 'Type', 'value' => $type === 'immobilier' ? 'Immobilier' : 'Exploitations'],
            ['label' => 'Sociétés', 'value' => $societesSelected !== [] ? implode(', ', $societesSelected) : 'Toutes'],
            ['label' => 'Année depuis', 'value' => is_int($anneeDepuis) && $anneeDepuis > 0 ? (string) $anneeDepuis : 'Toutes'],
            ['label' => 'Année jusqu\'à', 'value' => is_int($anneeJusqua) && $anneeJusqua > 0 ? (string) $anneeJusqua : 'Toutes'],
        ];

        $viewData['printFilters'] = $printFilters;

        return $this->render('encours/print/encours_totals.html.twig', $viewData);
    }

    #[IsGranted('ROLE_ENCOURS_ADD')]
    #[Route("/encours-bancaires/add", name: 'app_encours_bancaires_add', methods: ['GET', 'POST'])]
    public function addEncours(
        Request $request,
        EntityManagerInterface $em,
    ): Response
    {
        $societeScopeIds = $this->getCurrentUserSocieteScopeIds();

        $encours = new EncoursBancaire();
        $type = (string) $request->query->get('type', '');
        if ($type === 'exploitation' || $type === 'immobilier') {
            $encours->setType($type);
        } else {
            $encours->setType('exploitation');
        }

        $form = $this->createForm(CreateEncoursBancaireType::class, $encours, [
            'societe_scope_ids' => $societeScopeIds,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $societeId = $encours->getSociete()?->getId();
            if ($societeId === null) {
                $this->addFlash('error', 'Société invalide.');
            } elseif ($societeScopeIds !== null && !in_array($societeId, $societeScopeIds, true)) {
                throw $this->createNotFoundException();
            } else {
                $this->ensureEncoursCentreOrderView($encours, $em);

                $em->persist($encours);
                $em->flush();

                $this->addFlash('success', 'Encours créé.');

                return $this->redirectToRoute('app_encours_bancaires', [
                    'type' => $encours->getType() ?? 'exploitation',
                ]);
            }
        }

        return $this->render('encours/add.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[IsGranted('ROLE_ENCOURS_EDIT')]
    #[Route("/encours-bancaires/edit/{id}", name: 'app_encours_bancaires_update', methods: ['GET', 'POST'])]
    public function updateEncours(
        EncoursBancaire $encours,
        Request $request,
        EntityManagerInterface $em,
    ): Response
    {
        $societeScopeIds = $this->getCurrentUserSocieteScopeIds();
        $societeId = $encours->getSociete()?->getId();
        if ($societeScopeIds !== null && ($societeId === null || !in_array($societeId, $societeScopeIds, true))) {
            throw $this->createNotFoundException();
        }

        $form = $this->createForm(CreateEncoursBancaireType::class, $encours, [
            'societe_scope_ids' => $societeScopeIds,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $societeId = $encours->getSociete()?->getId();
            if ($societeId === null) {
                $this->addFlash('error', 'Société invalide.');
            } elseif ($societeScopeIds !== null && !in_array($societeId, $societeScopeIds, true)) {
                throw $this->createNotFoundException();
            } else {
                $this->ensureEncoursCentreOrderView($encours, $em);

                $em->persist($encours);
                $em->flush();

                $this->addFlash('success', 'Encours mis à jour.');

                return $this->redirectToRoute('app_encours_bancaires', [
                    'type' => $encours->getType() ?? 'exploitation',
                ]);
            }
        }

        return $this->render('encours/edit.html.twig', [
            'encours' => $encours,
            'form' => $form->createView(),
        ]);
    }

    private static function normalizeCentreLabel(?string $raw): string
    {
        $s = trim((string) ($raw ?? ''));
        if ($s === '') return '';
        $s = preg_replace('/\\s+/u', ' ', $s) ?? $s;
        if (function_exists('mb_strtolower')) {
            $s = mb_strtolower($s);
        } else {
            $s = strtolower($s);
        }
        return trim($s);
    }

    /**
     * Assign a stable per-societe ordering key for the centre label without requiring a mapping table.
     *
     * Rule:
     * - if another encours already exists with the same normalized centre for this societe, reuse its centreOrderView
     * - else assign next max+1 for this societe (append at end)
     */
    private function ensureEncoursCentreOrderView(EncoursBancaire $encours, EntityManagerInterface $em): void
    {
        if ($encours->getCentreOrderView() !== null) {
            return;
        }

        $societe = $encours->getSociete();
        if (!$societe instanceof Societe) {
            return;
        }

        $centreKey = self::normalizeCentreLabel($encours->getCentre());
        if ($centreKey === '') {
            return;
        }

        /** @var \App\Repository\EncoursBancaireRepository $repo */
        $repo = $em->getRepository(EncoursBancaire::class);

        $rows = $repo->getCentreOrdersForSociete($societe);
        foreach ($rows as $row) {
            $existingKey = self::normalizeCentreLabel($row['centre'] ?? null);
            $existingOrder = $row['order'] ?? null;
            if ($existingKey !== '' && $existingOrder !== null && $existingKey === $centreKey) {
                $encours->setCentreOrderView((int) $existingOrder);
                return;
            }
        }

        $encours->setCentreOrderView($repo->getNextCentreOrderForSociete($societe));
    }

    /**
     * @return list<int>|null Null means "no restriction" (admin).
     */
    private function getCurrentUserCentreScopeIds(): ?array
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return null;
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return [];
        }

        // Prefer societes scope (covers encours + centres) when defined.
        $ids = [];
        if (method_exists($user, 'getSocietes') && $user->getSocietes()->count() > 0) {
            foreach ($user->getSocietes() as $societe) {
                if (!$societe instanceof \App\Entity\Societe) {
                    continue;
                }
                foreach ($societe->getCentre() as $centre) {
                    if (!$centre instanceof \App\Entity\Centre) {
                        continue;
                    }
                    $id = $centre->getId();
                    if ($id !== null) {
                        $ids[] = $id;
                    }
                }
            }
        } else {
            // Backward compat: old scope stored as explicit centres.
            foreach ($user->getCentres() as $centre) {
                $id = $centre->getId();
                if ($id !== null) {
                    $ids[] = $id;
                }
            }
        }

        $ids = array_values(array_unique($ids));
        sort($ids);

        return $ids;
    }

    /**
     * @return list<int>|null Null means "no restriction" (admin).
     */
    private function getCurrentUserSocieteScopeIds(): ?array
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return null;
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return [];
        }

        $ids = [];

        if (method_exists($user, 'getSocietes') && $user->getSocietes()->count() > 0) {
            foreach ($user->getSocietes() as $societe) {
                if (!$societe instanceof \App\Entity\Societe) {
                    continue;
                }
                $id = $societe->getId();
                if ($id !== null) {
                    $ids[] = $id;
                }
            }
        } else {
            // Backward compat: derive societes from assigned centres.
            foreach ($user->getCentres() as $centre) {
                $societe = $centre->getSociete();
                $id = $societe?->getId();
                if ($id !== null) {
                    $ids[] = $id;
                }
            }
        }

        $ids = array_values(array_unique($ids));
        sort($ids);

        return $ids;
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
        iterable             $entities,
        FormFactoryInterface $formFactory,
        string               $namePrefix,
        string               $formTypeClass,
        array                $extraFormOptions = [],
        ?int                 $overrideEntityId = null,
        ?FormView            $overrideFormView = null,
    ): array
    {
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
                    array_merge(
                        $extraFormOptions,
                        [
                            'csrf_field_name' => self::CSRF_FIELD_NAME,
                            'csrf_token_id' => $namePrefix . $id,
                        ]
                    )
                )
                ->createView();
        }

        return $forms;
    }
}
