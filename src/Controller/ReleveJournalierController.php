<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\TypeCentre;
use App\Repository\StationLavageRepository;
use App\Service\Astikoto\ReleveDayViewBuilder;
use App\Service\Astikoto\ReleveRevenueCalculator;
use App\Service\Security\UserScopeResolver;
use DateMalformedStringException;
use Doctrine\DBAL\Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted("ROLE_ASTIKOTO")]
final class ReleveJournalierController extends AbstractController
{
    private const string ERROR = "Station introuvable";

    public function __construct(private readonly UserScopeResolver $scopeResolver)
    {
    }

    /**
     * Displays car wash details for a specific date.
     * @throws DateMalformedStringException
     * @throws Exception
     */
    #[Route("/astikoto/stations/releves", name: 'app_station_releves')]
    public function releves(
        Request                 $request,
        StationLavageRepository $repo,
    ): Response
    {
        $centreIds = $this->scopeResolver->centreIds(TypeCentre::STATION_LAVAGE);

        $stations = $repo->findStationsLavage(null, $centreIds);

        $selectedDate = $this->resolveSelectedDate($request->query->get('date'));
        $stationId = $request->query->getInt('station');

        if ($stationId <= 0) {
            return $this->render('astikoto/stations/releves.html.twig', [
                'stations' => $stations,
                'selectedStation' => null,
                'selectedDate' => $selectedDate,
                'details' => null,
            ]);
        }

        $station = $repo->findOneStationInScope($stationId, $centreIds);

        if ($station === null) {
            throw $this->createNotFoundException(self::ERROR);
        }

        $viewData = [
            'stations' => $stations,
            'selectedStation' => $station,
            'selectedDate' => $selectedDate,
        ];

        if ($request->isXmlHttpRequest()) {
            return $this->render('astikoto/stations/_station_results.html.twig', $viewData);
        }

        return $this->render('astikoto/stations/releves.html.twig', $viewData);
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     */
    #[Route('/astikoto/stations/releves/jour', name: 'app_station_releves_day', methods: ['GET', 'POST'])]
    public function releveJour(
        Request                 $request,
        StationLavageRepository $repo,
        ReleveDayViewBuilder    $viewBuilder,
        ReleveRevenueCalculator $revenueCalculator,
    ): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $stationId = $request->query->getInt('station');
        if ($stationId <= 0) {
            throw $this->createNotFoundException(self::ERROR);
        }

        $station = $repo->findOneStationInScope(
            $stationId,
            $this->scopeResolver->centreIds(TypeCentre::STATION_LAVAGE),
        );

        if ($station === null) {
            throw $this->createNotFoundException(self::ERROR);
        }

        $selectedDate = $this->resolveSelectedDate($request->query->get('date'));

        return $this->render('astikoto/stations/_day_details.html.twig', [
            'selectedStation' => $station,
            'selectedDate' => $selectedDate,
            'annualRevenues' => $revenueCalculator->getAnnualRevenues($station, new \DateTimeImmutable()),
            ...$viewBuilder->build($station, $selectedDate, $user, $request),
        ]);
    }

    private function resolveSelectedDate(mixed $value): \DateTimeImmutable
    {
        if (!is_string($value) || $value === '') {
            return new \DateTimeImmutable('today');
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = \DateTimeImmutable::getLastErrors();

        if (
            $date === false
            || ($errors !== false
                && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format('Y-m-d') !== $value
        ) {
            throw $this->createNotFoundException('Date invalide.');
        }

        return $date;
    }
}
