<?php

namespace App\Controller;

use App\Entity\Societe;
use App\Enum\TypeCentre;
use App\Form\CreateSocieteType;
use App\Repository\SocieteRepository;
use App\Service\List\BulkUpdateProcessor;
use App\Service\Security\UserScopeResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class SocieteController extends AbstractController
{
    private const string CSRF_FIELD_NAME = '_token';

    public function __construct(private readonly UserScopeResolver $scopeResolver)
    {
    }

    /**
     * Displays companies with one inline edit form per row.
     */
    #[IsGranted('ROLE_LIST_SOCIETES_VIEW')]
    #[Route('/cts/societes/list', name: 'app_societes_list')]
    public function listSocietes(Request $request, SocieteRepository $societeRepository): Response
    {
        $q = trim((string)$request->query->get('q', ''));
        $societes = $societeRepository->findOrderedByNomSearch($q, $this->scopeResolver->centreIds(TypeCentre::CONTROLE_TECHNIQUE));

        return $this->render('cts/societes/list.html.twig', [
            'societes' => $societes,
        ]);
    }

    #[IsGranted('ROLE_LIST_SOCIETES_VIEW')]
    #[Route('/cts/societes/list/partial', name: 'app_societes_list_partial')]
    public function listSocietesPartial(Request $request, SocieteRepository $societeRepository): Response
    {
        $q = trim((string)$request->query->get('q', ''));
        $societes = $societeRepository->findOrderedByNomSearch($q, $this->scopeResolver->centreIds(TypeCentre::CONTROLE_TECHNIQUE));

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
        $societes = $societeRepository->findOrderedByNomSearch($q, $this->scopeResolver->centreIds(TypeCentre::CONTROLE_TECHNIQUE));

        return $this->render('cts/societes/list_uneditable.html.twig', [
            'societes' => $societes,
        ]);
    }

    #[IsGranted('ROLE_LIST_SOCIETES_VIEW')]
    #[Route("/cts/societes/list-societes/partial", name: 'app_societes_list_uneditable_partial')]
    public function listSocietesNonEditablePartial(Request $request, SocieteRepository $societeRepository): Response
    {
        $q = trim((string)$request->query->get('q', ''));
        $societes = $societeRepository->findOrderedByNomSearch($q, $this->scopeResolver->centreIds(TypeCentre::CONTROLE_TECHNIQUE));

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
        Request                $request,
        SocieteRepository      $societeRepository,
        BulkUpdateProcessor    $bulkUpdateProcessor,
        EntityManagerInterface $em,
    ): Response
    {
        if (!$this->isCsrfTokenValid('societes_bulk', (string)$request->request->get(self::CSRF_FIELD_NAME, ''))) {
            $this->addFlash('error', 'Jeton CSRF invalide, rechargez la page.');
            return $this->redirectToRoute('app_societes_list', [
                'q' => $request->query->get('q'),
            ]);
        }

        $q = trim((string)$request->query->get('q', ''));
        $centreIds = $this->scopeResolver->centreIds(TypeCentre::CONTROLE_TECHNIQUE);
        $societes = $societeRepository->findOrderedByNomSearch($q, $centreIds);

        $payload = $request->request->all('changes');
        if ($payload === []) {
            $this->addFlash('warning', 'Aucune modification détectée.');
            return $this->redirectToRoute('app_societes_list', [
                'q' => $request->query->get('q'),
            ]);
        }

        $result = $bulkUpdateProcessor->process(
            $societes,
            $payload,
            fn(Societe $societe, array $fields): array => $this->applyManualSocieteUpdate($societe, $fields),
            static fn(Societe $societe): string => (string) $societe->getNom(),
        );

        if ($result->errors !== []) {
            foreach ($result->errors as $msg) {
                $this->addFlash('error', $msg);
            }
            return $this->render('cts/societes/list.html.twig', [
                'societes' => $societes,
            ]);
        }

        if ($result->changedCount === 0) {
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

    #[IsGranted('ROLE_LIST_SOCIETES_VIEW')]
    #[Route('/cts/societes/list/print', name: 'app_societes_list_print')]
    public function listSocietesPrint(Request $request, SocieteRepository $societeRepository): Response
    {
        $q = trim((string)$request->query->get('q', ''));
        $societes = $societeRepository->findOrderedByNomSearch($q, $this->scopeResolver->centreIds(TypeCentre::CONTROLE_TECHNIQUE));

        return $this->render('cts/lists/print/societes.html.twig', [
            'societes' => $societes,
            'q' => $q,
            'printTitle' => 'Liste des sociétés',
            'printVariant' => 'lists',
            'printOrientation' => 'portrait',
            'autoPrint' => true,
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
            if ($v === null) {
                return null;
            }

            $s = trim((string)$v);
            return $s === '' ? null : $s;
        };

        $nom = $trimOrNull($fields['nom'] ?? null);
        $siege = $trimOrNull($fields['siegeSocial'] ?? null);
        $siren = $trimOrNull($fields['siren'] ?? null);
        $numTva = $trimOrNull($fields['numTva'] ?? null);

        if ($nom === null) {
            $errors[] = 'Nom requis.';
        }
        if ($siege === null) {
            $errors[] = 'Siège social requis.';
        }
        if ($siren === null) {
            $errors[] = 'SIREN requis.';
        }

        if ($nom !== null) {
            $societe->setNom($nom);
        }
        if ($siege !== null) {
            $societe->setSiegeSocial($siege);
        }
        if ($siren !== null) {
            $societe->setSiren($siren);
        }
        $societe->setNumTva($numTva);

        return $errors;
    }
}
