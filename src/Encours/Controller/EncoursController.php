<?php

namespace App\Encours\Controller;

use App\Organisation\Entity\Centre;
use App\Encours\Entity\EncoursBancaire;
use App\Personnel\Entity\Salarie;
use App\Organisation\Entity\Societe;
use App\Utilisateur\Entity\User;
use App\ParcAutomobile\Entity\Voiture;
use App\Organisation\Enum\TypeCentre;
use App\Organisation\Form\CreateCentreType;
use App\Encours\Form\CreateEncoursBancaireType;
use App\Personnel\Form\CreateSalarieType;
use App\Organisation\Form\CreateSocieteType;
use App\ParcAutomobile\Form\CreateVoitureType;
use App\Organisation\Repository\CentreRepository;
use App\Encours\Repository\EncoursBancaireRepository;
use App\Personnel\Repository\SalarieRepository;
use App\Organisation\Repository\SocieteRepository;
use App\ParcAutomobile\Repository\VoitureRepository;
use App\Organisation\Service\CentreRowUpdater;
use App\Encours\Service\EncoursPageBuilder;
use App\Utilisateur\Security\UserScopeResolver;
use App\ParcAutomobile\Service\VoitureCertificatCessionStorage;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Exception\DriverException as DbalDriverException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
final class EncoursController extends AbstractController
{
    public function __construct(private readonly UserScopeResolver $scopeResolver)
    {
    }

    /**
     * Displays bank balances.
     * @throws Exception
     */
    #[IsGranted('ROLE_ENCOURS_VIEW')]
    #[Route("/encours-bancaires", name: 'app_encours_bancaires')]
    public function encours(
        Request            $request,
        EncoursPageBuilder $encoursPageBuilder
    ): Response
    {
        $viewData = $encoursPageBuilder->build($request, 'exploitation', $this->scopeResolver->societeIds());
        return $this->render('encours/encours.html.twig', $viewData);
    }

    /**
     * @throws Exception
     */
    #[IsGranted('ROLE_ENCOURS_VIEW')]
    #[Route("/encours-bancaires/print", name: 'app_encours_bancaires_print')]
    public function encoursPrint(
        Request            $request,
        EncoursPageBuilder $encoursPageBuilder
    ): Response
    {
        $viewData = $encoursPageBuilder->build($request, 'exploitation', $this->scopeResolver->societeIds());

        $societesSelected = [];
        $societeIds = $viewData['societeIds'] ?? [];
        $societes = $viewData['societes'] ?? [];
        if (is_array($societeIds) && is_array($societes)) {
            foreach ($societeIds as $id) {
                if (isset($societes[$id])) {
                    $societesSelected[] = (string)$societes[$id];
                }
            }
        }

        $type = (string)($viewData['type'] ?? 'exploitation');
        $anneeDepuis = $viewData['anneeDepuis'] ?? null;
        $anneeJusqua = $viewData['anneeJusqua'] ?? null;

        $printFilters = [
            ['label' => 'Type', 'value' => $type === 'immobilier' ? 'Immobilier' : 'Exploitations'],
            ['label' => 'Sociétés', 'value' => $societesSelected !== [] ? implode(', ', $societesSelected) : 'Toutes'],
            ['label' => 'Année depuis', 'value' => is_int($anneeDepuis) && $anneeDepuis > 0 ? (string)$anneeDepuis : 'Toutes'],
            ['label' => 'Année jusqu\'à', 'value' => is_int($anneeJusqua) && $anneeJusqua > 0 ? (string)$anneeJusqua : 'Toutes'],
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
        Request            $request,
        EncoursPageBuilder $encoursPageBuilder
    ): Response
    {
        $viewData = $encoursPageBuilder->build($request, 'exploitation', $this->scopeResolver->societeIds());

        $societesSelected = [];
        $societeIds = $viewData['societeIds'] ?? [];
        $societes = $viewData['societes'] ?? [];
        if (is_array($societeIds) && is_array($societes)) {
            foreach ($societeIds as $id) {
                if (isset($societes[$id])) {
                    $societesSelected[] = (string)$societes[$id];
                }
            }
        }

        $type = (string)($viewData['type'] ?? 'exploitation');
        $anneeDepuis = $viewData['anneeDepuis'] ?? null;
        $anneeJusqua = $viewData['anneeJusqua'] ?? null;

        $printFilters = [
            ['label' => 'Type', 'value' => $type === 'immobilier' ? 'Immobilier' : 'Exploitations'],
            ['label' => 'Sociétés', 'value' => $societesSelected !== [] ? implode(', ', $societesSelected) : 'Toutes'],
            ['label' => 'Année depuis', 'value' => is_int($anneeDepuis) && $anneeDepuis > 0 ? (string)$anneeDepuis : 'Toutes'],
            ['label' => 'Année jusqu\'à', 'value' => is_int($anneeJusqua) && $anneeJusqua > 0 ? (string)$anneeJusqua : 'Toutes'],
        ];

        $viewData['printFilters'] = $printFilters;

        return $this->render('encours/print/encours_totals.html.twig', $viewData);
    }

    #[IsGranted('ROLE_ENCOURS_ADD')]
    #[Route("/encours-bancaires/add", name: 'app_encours_bancaires_add', methods: ['GET', 'POST'])]
    public function addEncours(
        Request                $request,
        EntityManagerInterface $em,
    ): Response
    {
        $societeScopeIds = $this->scopeResolver->societeIds();

        $encours = new EncoursBancaire();
        $type = (string)$request->query->get('type', '');
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
        EncoursBancaire        $encours,
        Request                $request,
        EntityManagerInterface $em,
    ): Response
    {
        $societeScopeIds = $this->scopeResolver->societeIds();
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
        $s = trim((string)($raw ?? ''));
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

        /** @var EncoursBancaireRepository $repo */
        $repo = $em->getRepository(EncoursBancaire::class);

        $rows = $repo->getCentreOrdersForSociete($societe);
        foreach ($rows as $row) {
            $existingKey = self::normalizeCentreLabel($row['centre'] ?? null);
            $existingOrder = $row['order'] ?? null;
            if ($existingKey !== '' && $existingOrder !== null && $existingKey === $centreKey) {
                $encours->setCentreOrderView((int)$existingOrder);
                return;
            }
        }

        $encours->setCentreOrderView($repo->getNextCentreOrderForSociete($societe));
    }
}
