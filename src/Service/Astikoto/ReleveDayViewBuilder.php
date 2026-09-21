<?php

namespace App\Service\Astikoto;

use App\Entity\Centre;
use App\Entity\User;
use App\Form\CreateReleveJournalierType;
use Doctrine\DBAL\Exception;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class ReleveDayViewBuilder
{
    public function __construct(
        private ReleveJournalierInitializer $initializer,
        private ReleveJournalierMapper $mapper,
        private ReleveTotalsCalculator $totalsCalculator,
        private ReleveRevenueCalculator $revenueCalculator,
        private ReleveEquipementsViewBuilder $equipementsBuilder,
        private FormFactoryInterface $formFactory,
        private EntityManagerInterface $entityManager,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /** @return array<string, mixed>
     * @throws \DateMalformedStringException
     * @throws Exception
     */
    public function build(Centre $station, \DateTimeImmutable $date, User $user, Request $request): array
    {
        $releve = $this->initializer->loadOrCreate($station, $date, $user);
        $dto = $this->mapper->toDTO($releve);
        $formOptions = [
            'action' => $this->urlGenerator->generate('app_station_detail_day', [
                'station' => $station->getId(),
                'date' => $date->format('Y-m-d'),
            ]),
            'method' => 'POST',
        ];
        $form = $this->formFactory->create(CreateReleveJournalierType::class, $dto, $formOptions);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->mapper->mapToEntity($dto, $releve);
            $now = new \DateTimeImmutable();
            $releve->markAsModified($user, $now);
            if ($request->request->getString('intent') === 'validate') {
                $releve->markAsValidated($user, $now);
            }
            $this->entityManager->persist($releve);
            $this->entityManager->flush();

            $dto = $this->mapper->toDTO($releve);
            $form = $this->formFactory->create(CreateReleveJournalierType::class, $dto, $formOptions);
        }

        $current = $this->revenueCalculator->getMonthlyRevenue($station, $date)->getTotalCents();
        $comparisons = [];

        foreach ([1, 2] as $yearsAgo) {
            $comparisonDate = $date->modify(sprintf('-%d year', $yearsAgo));
            $revenue = $this->revenueCalculator->getMonthlyRevenue($station, $comparisonDate)->getTotalCents();
            $comparisons[] = [
                'year' => (int) $comparisonDate->format('Y'),
                'revenueCents' => $revenue,
                'evolution' => $this->revenueCalculator->compareRevenues($current, $revenue),
            ];
        }

        return [
            'form' => $form->createView(),
            'formErrors' => $this->collectFormErrors($form),
            'releve' => $releve,
            'equipements' => $this->equipementsBuilder->build($releve),
            'releveTotals' => $this->totalsCalculator->calculate($dto),
            'monthlyRevenueCents' => $current,
            'monthlyComparisons' => $comparisons,
            'editing' => !$releve->isValidated() || $request->query->getBoolean('edit'),
            'editUrl' => $this->urlGenerator->generate('app_station_detail_day', [
                'station' => $station->getId(),
                'date' => $date->format('Y-m-d'),
                'edit' => 1,
            ]),
        ];
    }

    /** @return list<string> */
    private function collectFormErrors(FormInterface $form): array
    {
        $messages = [];

        foreach ($form->getErrors(true, true) as $error) {
            $messages[] = $error->getMessage();
        }

        return array_values(array_unique($messages));
    }
}
