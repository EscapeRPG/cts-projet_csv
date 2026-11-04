<?php

namespace App\ReleveLavage\Controller;

use App\Utilisateur\Entity\User;
use App\Organisation\Enum\TypeCentre;
use App\Organisation\Repository\StationLavageRepository;
use App\ReleveLavage\Service\ReleveDayViewBuilder;
use App\ReleveLavage\Service\ReleveRevenueCalculator;
use App\ReleveLavage\Service\RelevePeriodSummary;
use App\ReleveLavage\Repository\ReleveJournalierRepository;
use App\Utilisateur\Security\UserScopeResolver;
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
            return $this->render('releve_lavage/releves.html.twig', [
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
            return $this->render('releve_lavage/_station_results.html.twig', $viewData);
        }

        return $this->render('releve_lavage/releves.html.twig', $viewData);
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

        return $this->render('releve_lavage/_day_details.html.twig', [
            'selectedStation' => $station,
            'selectedDate' => $selectedDate,
            'annualRevenues' => $revenueCalculator->getAnnualRevenues($station, new \DateTimeImmutable()),
            ...$viewBuilder->build($station, $selectedDate, $user, $request),
        ]);
    }

    #[Route('/astikoto/stations/releves/imprimer', name: 'app_station_releves_print', methods: ['GET'])]
    public function printPeriod(
        Request $request,
        StationLavageRepository $stations,
        ReleveJournalierRepository $releves,
        RelevePeriodSummary $summary,
    ): Response {
        $station = $stations->findOneStationInScope(
            $request->query->getInt('station'),
            $this->scopeResolver->centreIds(TypeCentre::STATION_LAVAGE),
        );
        if ($station === null) {
            throw $this->createNotFoundException(self::ERROR);
        }

        $start = $request->query->getString('debut');
        $end = $request->query->getString('fin');
        $error = null;
        if ($request->query->has('debut') || $request->query->has('fin')) {
            $from = \DateTimeImmutable::createFromFormat('!Y-m-d', $start);
            $to = \DateTimeImmutable::createFromFormat('!Y-m-d', $end);
            if (!$from || !$to || $from->format('Y-m-d') !== $start || $to->format('Y-m-d') !== $end) {
                $error = 'Veuillez renseigner deux dates valides.';
            } elseif ($from > $to) {
                $error = 'La date de fin doit être égale ou postérieure à la date de début.';
            } else {
                return $this->render('releve_lavage/print/period.html.twig', [
                    'selectedStation' => $station,
                    'from' => $from,
                    'to' => $to,
                    ...$summary->build($releves->findForPeriod($station, $from, $to->modify('+1 day'))),
                ]);
            }
        }

        $referenceDate = $this->resolveSelectedDate($request->query->get('date'));
        return $this->render('releve_lavage/print/select_period.html.twig', [
            'selectedStation' => $station,
            'start' => $start ?: $referenceDate->modify('first day of this month')->format('Y-m-d'),
            'end' => $end ?: $referenceDate->modify('last day of this month')->format('Y-m-d'),
            'error' => $error,
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
