<?php

namespace App\Controller;

use App\Entity\EquipementStation;
use App\Enum\TypeCentre;
use App\Form\CreateStationLavageType;
use App\Repository\SocieteRepository;
use App\Repository\StationLavageRepository;
use App\Service\Centre\CentreRowUpdater;
use App\Service\List\BulkUpdateProcessor;
use App\Service\Security\UserScopeResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Exception\LogicException;

#[IsGranted('ROLE_ASTIKOTO')]
final class StationLavageController extends AbstractController
{
    private const string CSRF_FIELD_NAME = '_token';

    public function __construct(private readonly UserScopeResolver $scopeResolver)
    {
    }

    /**
     * Displays car wash stations with one inline edit form per row.
     */
    #[Route("/astikoto/stations/list", name: 'app_stations_list')]
    public function listStations(
        Request                 $request,
        StationLavageRepository $repo,
        SocieteRepository       $societeRepository,
    ): Response
    {
        $q = trim((string)$request->query->get('q', ''));
        $centreIds = $this->scopeResolver->centreIds(TypeCentre::STATION_LAVAGE);

        $stations = $repo->findStationsLavage($q, $centreIds);
        $societes = $societeRepository->findOrderedByNomSearch(null, $centreIds);

        return $this->render('astikoto/stations/list.html.twig', [
            'stations' => $stations,
            'societes' => $societes,
        ]);
    }

    /**
     * Displays car wash stations.
     */
    #[Route("/astikoto/stations/list_uneditable", name: 'app_stations_list_uneditable')]
    public function listStationsUneditable(
        Request                 $request,
        StationLavageRepository $repo,
    ): Response
    {
        $q = trim((string)$request->query->get('q', ''));
        $centreIds = $this->scopeResolver->centreIds(TypeCentre::STATION_LAVAGE);

        $stations = $repo->findStationsLavage($q, $centreIds);

        return $this->render('astikoto/stations/list_uneditable.html.twig', [
            'stations' => $stations,
        ]);
    }

    #[Route("/astikoto/stations/add", name: 'app_stations_add')]
    public function addStation(Request $request, EntityManagerInterface $em): Response
    {
        $centreIds = $this->scopeResolver->centreIds(TypeCentre::STATION_LAVAGE);

        $form = $this->createForm(CreateStationLavageType::class, null, [
            'centre_scope_ids' => $centreIds,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $station = $form->getData();
            $station->setType(TypeCentre::STATION_LAVAGE);

            $equipements = [];
            $donneesEquipements = [];

            foreach ($form->get('equipements') as $index => $equipementForm) {
                $data = $equipementForm->getData();

                $equipement = new EquipementStation(
                    $station,
                    $data->code,
                    $data->libelle,
                    $data->categorie,
                );

                $equipement->setIsActive($data->isActive);
                $equipement->setNumeroPortiqueImport($data->numeroPortiqueImport);

                $station->addEquipementStation($equipement);

                $equipements[(string)$index] = $equipement;
                $donneesEquipements[(string)$index] = $data;
            }

            foreach ($donneesEquipements as $index => $data) {
                if ($data->portiqueTemporaire === null) {
                    continue;
                }

                $portique = $equipements[$data->portiqueTemporaire] ?? null;

                if ($portique === null) {
                    throw new LogicException('Portique temporaire inexistant');
                }

                $equipements[$index]->associerPortique($portique);
            }

            $em->persist($station);
            $em->flush();

            $this->addFlash('success', 'Station créée.');

            return $this->redirectToRoute('app_stations_list');
        }

        return $this->render('astikoto/stations/add.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route("/astikoto/stations/bulk-update", name: 'app_stations_bulk_update', methods: ['POST'])]
    public function bulkUpdateStations(
        Request                 $request,
        StationLavageRepository $repository,
        SocieteRepository       $societeRepository,
        CentreRowUpdater        $centreRowUpdater,
        BulkUpdateProcessor     $bulkUpdateProcessor,
        EntityManagerInterface  $em
    ): Response
    {
        if (!$this->isCsrfTokenValid('stations_bulk', (string)$request->request->get(self::CSRF_FIELD_NAME, ''))) {
            $this->addFlash('error', 'Jeton CSRF invalide, rechargez la page.');
            return $this->redirectToRoute('app_stations_list', [
                'q' => $request->query->get('q'),
            ]);
        }

        $q = trim((string)$request->query->get('q', ''));
        $stationIds = $this->scopeResolver->centreIds(TypeCentre::STATION_LAVAGE);
        $stations = $repository->findStationsLavage($q, $stationIds);

        $payload = $request->request->all('changes');
        if ($payload === []) {
            $this->addFlash('warning', 'Aucune modification détectée.');
            return $this->redirectToRoute('app_stations_list', [
                'q' => $request->query->get('q'),
            ]);
        }

        $societes = $societeRepository->findOrderedByNomSearch(null, $stationIds);
        $societeIds = array_fill_keys(array_filter(array_map(static fn($s) => $s->getId(), $societes)), true);

        $result = $bulkUpdateProcessor->process(
            $stations,
            $payload,
            fn($station, array $fields): array => $centreRowUpdater->updateStation($station, $fields, $societeIds),
            static fn($station): string => (string) $station->getNom(),
        );

        if ($result->errors !== []) {
            foreach ($result->errors as $msg) {
                $this->addFlash('error', $msg);
            }
            return $this->render('astikoto/stations/list.html.twig', [
                'stations' => $stations,
                'societes' => $societes,
            ]);
        }

        if ($result->changedCount === 0) {
            $this->addFlash('warning', 'Aucune modification appliquée.');
            return $this->redirectToRoute('app_stations_list', [
                'q' => $request->query->get('q'),
            ]);
        }

        $em->flush();
        $this->addFlash('success', 'Modifications enregistrées.');

        return $this->redirectToRoute('app_stations_list_uneditable', [
            'q' => $request->query->get('q'),
        ]);
    }
}
