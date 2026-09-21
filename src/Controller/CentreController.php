<?php

namespace App\Controller;

use App\Entity\Centre;
use App\Entity\Reseau;
use App\Enum\TypeCentre;
use App\Form\CreateCentreType;
use App\Repository\CentreRepository;
use App\Repository\ReseauRepository;
use App\Repository\SocieteRepository;
use App\Service\Centre\CentreRowUpdater;
use App\Service\List\BulkUpdateProcessor;
use App\Service\Security\UserScopeResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class CentreController extends AbstractController
{
    private const string CSRF_FIELD_NAME = '_token';

    public function __construct(private readonly UserScopeResolver $scopeResolver)
    {
    }

    /**
     * Displays centers with one inline edit form per row.
     */
    #[IsGranted('ROLE_LIST_CENTRES_VIEW')]
    #[Route("/cts/centres/list", name: 'app_centres_list')]
    public function listCentres(
        Request                          $request,
        CentreRepository                 $centreRepository,
        SocieteRepository                $societeRepository,
        \App\Repository\ReseauRepository $reseauRepository,
    ): Response
    {
        $q = trim((string)$request->query->get('q', ''));
        $centreIds = $this->scopeResolver->centreIds(TypeCentre::CONTROLE_TECHNIQUE);
        $centres = $centreRepository->findCtsOrderedBySocieteVilleAgrSearch($q, $centreIds);
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
        Request                          $request,
        CentreRepository                 $centreRepository,
        SocieteRepository                $societeRepository,
        \App\Repository\ReseauRepository $reseauRepository,
    ): Response
    {
        $q = trim((string)$request->query->get('q', ''));
        $centreIds = $this->scopeResolver->centreIds(TypeCentre::CONTROLE_TECHNIQUE);
        $centres = $centreRepository->findCtsOrderedBySocieteVilleAgrSearch($q, $centreIds);
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
        $centres = $centreRepository->findCtsOrderedBySocieteVilleAgrSearch($q, $this->scopeResolver->centreIds(TypeCentre::CONTROLE_TECHNIQUE));

        return $this->render('cts/centres/list_uneditable.html.twig', [
            'centres' => $centres,
        ]);
    }

    #[IsGranted('ROLE_LIST_CENTRES_VIEW')]
    #[Route("/cts/centres/list-centres/partial", name: 'app_centres_list_uneditable_partial')]
    public function listCentresNonEditablePartial(Request $request, CentreRepository $centreRepository): Response
    {
        $q = trim((string)$request->query->get('q', ''));
        $centres = $centreRepository->findCtsOrderedBySocieteVilleAgrSearch($q, $this->scopeResolver->centreIds(TypeCentre::CONTROLE_TECHNIQUE));

        return $this->render('cts/centres/_list_results_uneditable.html.twig', [
            'centres' => $centres,
        ]);
    }

    #[IsGranted('ROLE_LIST_CENTRES_ADD')]
    #[Route("/cts/centres/add", name: 'app_centres_add')]
    public function addCentre(Request $request, EntityManagerInterface $em): Response
    {
        $centreIds = $this->scopeResolver->centreIds(TypeCentre::CONTROLE_TECHNIQUE);

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
        Request                $request,
        CentreRepository       $centreRepository,
        SocieteRepository      $societeRepository,
        ReseauRepository       $reseauRepository,
        CentreRowUpdater       $centreRowUpdater,
        BulkUpdateProcessor    $bulkUpdateProcessor,
        EntityManagerInterface $em,
    ): Response
    {
        if (!$this->isCsrfTokenValid('centres_bulk', (string)$request->request->get(self::CSRF_FIELD_NAME, ''))) {
            $this->addFlash('error', 'Jeton CSRF invalide, rechargez la page.');
            return $this->redirectToRoute('app_centres_list', [
                'q' => $request->query->get('q'),
            ]);
        }

        $q = trim((string)$request->query->get('q', ''));
        $centreIds = $this->scopeResolver->centreIds(TypeCentre::CONTROLE_TECHNIQUE);
        $centres = $centreRepository->findCtsOrderedBySocieteVilleAgrSearch($q, $centreIds);
        $societes = $societeRepository->findOrderedByNomSearch(null, $centreIds);
        $reseaux = $this->findReseauxForCentreScope($reseauRepository, $centreIds);

        $payload = $request->request->all('changes');
        if ($payload === []) {
            $this->addFlash('warning', 'Aucune modification détectée.');
            return $this->redirectToRoute('app_centres_list', [
                'q' => $request->query->get('q'),
            ]);
        }

        $societeIds = array_fill_keys(array_filter(array_map(static fn($s) => $s->getId(), $societes)), true);
        $reseauIds = array_fill_keys(array_filter(array_map(static fn($r) => $r->getId(), $reseaux)), true);

        $result = $bulkUpdateProcessor->process(
            $centres,
            $payload,
            fn(Centre $centre, array $fields): array => $centreRowUpdater->updateControleTechnique(
                $centre,
                $fields,
                $societeIds,
                $reseauIds,
            ),
            static fn(Centre $centre): string => (string) $centre->getVille(),
        );

        if ($result->errors !== []) {
            foreach ($result->errors as $msg) {
                $this->addFlash('error', $msg);
            }
            return $this->render('cts/centres/list.html.twig', [
                'centres' => $centres,
                'societes' => $societes,
                'reseaux' => $reseaux,
            ]);
        }

        if ($result->changedCount === 0) {
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
     * @return array<int, Reseau>
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

    #[IsGranted('ROLE_LIST_CENTRES_VIEW')]
    #[Route('/cts/centres/list/print', name: 'app_centres_list_print')]
    public function listCentresPrint(Request $request, CentreRepository $centreRepository): Response
    {
        $q = trim((string)$request->query->get('q', ''));
        $centres = $centreRepository->findCtsOrderedBySocieteVilleAgrSearch($q, $this->scopeResolver->centreIds(TypeCentre::CONTROLE_TECHNIQUE));

        return $this->render('cts/lists/print/centres.html.twig', [
            'centres' => $centres,
            'q' => $q,
            'printTitle' => 'Liste des centres',
            'printVariant' => 'lists',
            'printOrientation' => 'landscape',
            'autoPrint' => true,
        ]);
    }
}
