<?php

namespace App\Controller;

use App\Entity\Centre;
use App\Entity\Salarie;
use App\Entity\Societe;
use App\Enum\TypeCentre;
use App\Form\CreateSalarieType;
use App\Repository\CentreRepository;
use App\Repository\SalarieRepository;
use App\Repository\SocieteRepository;
use App\Service\List\ActiveStatusFilter;
use App\Service\List\BulkUpdateProcessor;
use App\Service\Pagination\PaginationResolver;
use App\Service\Security\UserScopeResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class SalarieController extends AbstractController
{
    private const string CSRF_FIELD_NAME = '_token';

    public function __construct(
        private readonly UserScopeResolver $scopeResolver,
        private readonly PaginationResolver $paginationResolver
    )
    {
    }

    /**
     * Displays employees with one inline edit form per row.
     */
    #[IsGranted('ROLE_LIST_SALARIES_VIEW')]
    #[Route("/cts/salaries/list", name: 'app_salaries_list')]
    public function listSalaries(
        Request           $request,
        SalarieRepository $salarieRepository,
        SocieteRepository $societeRepository,
        CentreRepository  $centreRepository,
    ): Response
    {
        $q = trim((string)$request->query->get('q', ''));
        $centreIds = $this->scopeResolver->centreIds(TypeCentre::CONTROLE_TECHNIQUE);
        $activeStatus = ActiveStatusFilter::fromRequest($request);

        // Bulk-edit: load the full result set (no pagination).
        $salaries = $salarieRepository->findOrderedBySocieteSearch($q, $centreIds, $activeStatus->includeActive, $activeStatus->includeInactive);
        $societes = $societeRepository->findOrderedByNomSearch(null, $centreIds);
        $centres = $centreRepository->findCtsOrderedBySocieteVilleAgrSearch(null, $centreIds);

        return $this->renderSalariesList($salaries, $societes, $centres, null);
    }

    #[IsGranted('ROLE_LIST_SALARIES_VIEW')]
    #[Route("/cts/salaries/list/partial", name: 'app_salaries_list_partial')]
    public function listSalariesPartial(
        Request           $request,
        SalarieRepository $salarieRepository,
        SocieteRepository $societeRepository,
        CentreRepository  $centreRepository,
    ): Response
    {
        $q = trim((string)$request->query->get('q', ''));
        $centreIds = $this->scopeResolver->centreIds(TypeCentre::CONTROLE_TECHNIQUE);
        $activeStatus = ActiveStatusFilter::fromRequest($request);
        $salaries = $salarieRepository->findOrderedBySocieteSearch($q, $centreIds, $activeStatus->includeActive, $activeStatus->includeInactive);
        $societes = $societeRepository->findOrderedByNomSearch(null, $centreIds);
        $centres = $centreRepository->findCtsOrderedBySocieteVilleAgrSearch(null, $centreIds);

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
        $activeStatus = ActiveStatusFilter::fromRequest($request);
        $salaries = $salarieRepository->findOrderedByNomPrenomSearch($q, $this->scopeResolver->centreIds(TypeCentre::CONTROLE_TECHNIQUE), $activeStatus->includeActive, $activeStatus->includeInactive);

        return $this->render('cts/salaries/list_uneditable.html.twig', [
            'salaries' => $salaries,
        ]);
    }

    #[IsGranted('ROLE_LIST_SALARIES_VIEW')]
    #[Route("/cts/salaries/list-salaries/partial", name: 'app_salaries_list_uneditable_partial')]
    public function listSalariesNonEditablePartial(Request $request, SalarieRepository $salarieRepository): Response
    {
        $q = trim((string)$request->query->get('q', ''));
        $activeStatus = ActiveStatusFilter::fromRequest($request);
        $salaries = $salarieRepository->findOrderedByNomPrenomSearch($q, $this->scopeResolver->centreIds(TypeCentre::CONTROLE_TECHNIQUE), $activeStatus->includeActive, $activeStatus->includeInactive);

        return $this->render('cts/salaries/_list_results_uneditable.html.twig', [
            'salaries' => $salaries,
        ]);
    }

    #[IsGranted('ROLE_LIST_SALARIES_ADD')]
    #[Route("/cts/salaries/add", name: 'app_salaries_add')]
    public function addSalarie(Request $request, EntityManagerInterface $em): Response
    {
        $centreIds = $this->scopeResolver->centreIds(TypeCentre::CONTROLE_TECHNIQUE);

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
        Request                $request,
        SalarieRepository      $salarieRepository,
        SocieteRepository      $societeRepository,
        CentreRepository       $centreRepository,
        BulkUpdateProcessor    $bulkUpdateProcessor,
        EntityManagerInterface $em,
    ): Response
    {
        $centreIds = $this->scopeResolver->centreIds(TypeCentre::CONTROLE_TECHNIQUE);
        $activeStatus = ActiveStatusFilter::fromRequest($request);

        $q = trim((string)$request->query->get('q', ''));
        $salaries = $salarieRepository->findOrderedBySocieteSearch($q, $centreIds, $activeStatus->includeActive, $activeStatus->includeInactive);
        $payload = $request->request->all('changes');
        if (!$this->isCsrfTokenValid('salaries_bulk', (string)$request->request->get(self::CSRF_FIELD_NAME, ''))) {
            $this->addFlash('error', 'Jeton CSRF invalide, rechargez la page.');
            return $this->redirectToRoute('app_salaries_list', [
                'page' => max(1, $request->query->getInt('page', 1)),
                'q' => $request->query->get('q'),
                ...$activeStatus->queryParameters(),
            ]);
        }
        if ($payload === []) {
            $this->addFlash('warning', 'Aucune modification détectée.');
            return $this->redirectToRoute('app_salaries_list', [
                'page' => max(1, $request->query->getInt('page', 1)),
                'q' => $request->query->get('q'),
                ...$activeStatus->queryParameters(),
            ]);
        }

        $result = $bulkUpdateProcessor->process(
            $salaries,
            $payload,
            fn(Salarie $salarie, array $fields): array => $this->applyManualSalarieUpdate($salarie, $fields, $centreIds, $em),
            static fn(Salarie $salarie): string => trim(sprintf('%s %s', $salarie->getNom(), $salarie->getPrenom())),
        );

        if ($result->errors !== []) {
            foreach ($result->errors as $msg) {
                $this->addFlash('error', $msg);
            }
            $societes = $societeRepository->findOrderedByNomSearch(null, $centreIds);
            $centres = $centreRepository->findCtsOrderedBySocieteVilleAgrSearch(null, $centreIds);
            return $this->renderSalariesList($salaries, $societes, $centres, null);
        }

        if ($result->changedCount === 0) {
            $this->addFlash('warning', 'Aucune modification appliquée.');
            return $this->redirectToRoute('app_salaries_list', [
                'page' => max(1, $request->query->getInt('page', 1)),
                'q' => $request->query->get('q'),
                ...$activeStatus->queryParameters(),
            ]);
        }

        $em->flush();
        $this->addFlash('success', 'Modifications enregistrées.');

        return $this->redirectToRoute('app_salaries_list_uneditable', [
            'q' => $request->query->get('q'),
            ...$activeStatus->queryParameters(),
        ]);
    }

    /**
     * @param array<int, mixed> $fields
     * @return list<string> Row errors.
     */
    private function applyManualSalarieUpdate(Salarie $salarie, array $fields, ?array $centreScopeIds, EntityManagerInterface $em): array
    {
        $errors = [];

        $trimOrNull = static function ($v): ?string {
            if ($v === null) {
                return null;
            }

            $s = trim((string)$v);
            return $s === '' ? null : $s;
        };

        // Required: societe, nom, prenom.
        $societeId = $trimOrNull($fields['societe'] ?? null);
        if ($societeId === null || !ctype_digit($societeId)) {
            $errors[] = 'Société invalide.';
        } else {
            /** @var SocieteRepository $societeRepo */
            $societeRepo = $em->getRepository(Societe::class);
            $societe = $societeRepo->find((int)$societeId);
            if (!$societe instanceof Societe) {
                $errors[] = 'Société introuvable.';
            } else {
                $salarie->setSociete($societe);
            }
        }

        $nom = $trimOrNull($fields['nom'] ?? null);
        $prenom = $trimOrNull($fields['prenom'] ?? null);
        if ($nom === null) {
            $errors[] = 'Nom requis.';
        }
        if ($prenom === null) {
            $errors[] = 'Prénom requis.';
        }
        if ($nom !== null) {
            $salarie->setNom($nom);
        }
        if ($prenom !== null) {
            $salarie->setPrenom($prenom);
        }

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

        /** @var CentreRepository $centreRepo */
        $centreRepo = $em->getRepository(Centre::class);
        $wanted = [];
        if ($centreIds !== []) {
            $wanted = $centreRepo->createQueryBuilder('c')
                ->andWhere('c.id IN (:ids)')
                ->andWhere('c.type = :centreType')
                ->setParameter('ids', $centreIds)
                ->setParameter('centreType', \App\Enum\TypeCentre::CONTROLE_TECHNIQUE)
                ->getQuery()
                ->getResult();
        }

        // Replace collection.
        foreach ($salarie->getCentres() as $existing) {
            $salarie->removeCentre($existing);
        }
        foreach ($wanted as $c) {
            if ($c instanceof Centre) {
                $salarie->addCentre($c);
            }
        }

        return $errors;
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

    #[IsGranted('ROLE_LIST_SALARIES_VIEW')]
    #[Route('/cts/salaries/list/print', name: 'app_salaries_list_print')]
    public function listSalariesPrint(Request $request, SalarieRepository $salarieRepository): Response
    {
        $q = trim((string)$request->query->get('q', ''));
        $centreIds = $this->scopeResolver->centreIds(TypeCentre::CONTROLE_TECHNIQUE);
        $activeStatus = ActiveStatusFilter::fromRequest($request);
        $perPage = 30;
        $paginationData = $this->paginationResolver->computePagination($request, $perPage, $salarieRepository->countSearch($q, $centreIds, $activeStatus->includeActive, $activeStatus->includeInactive));
        $salaries = $salarieRepository->findPaginatedOrderedBySocieteSearch($perPage, $paginationData['offset'], $q, $centreIds, $activeStatus->includeActive, $activeStatus->includeInactive);

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
        $centreIds = $this->scopeResolver->centreIds(TypeCentre::CONTROLE_TECHNIQUE);
        $activeStatus = ActiveStatusFilter::fromRequest($request);
        $salaries = $salarieRepository->findOrderedByNomPrenomSearch($q, $centreIds, $activeStatus->includeActive, $activeStatus->includeInactive);

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


}
