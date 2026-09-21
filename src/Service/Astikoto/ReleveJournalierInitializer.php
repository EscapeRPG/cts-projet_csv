<?php

namespace App\Service\Astikoto;

use App\Entity\Centre;
use App\Entity\ReleveEquipement;
use App\Entity\ReleveJournalier;
use App\Entity\User;
use App\Enum\CategorieEquipement;
use App\Repository\PortiqueImporteRepository;
use App\Repository\ReleveJournalierRepository;
use Doctrine\DBAL\Exception;

final readonly class ReleveJournalierInitializer
{
    public function __construct(
        private ReleveJournalierRepository $releveRepository,
        private PortiqueImporteRepository  $portiqueRepository,
    ) {
    }

    /**
     * @throws Exception
     */
    public function loadOrCreate(
        Centre $station,
        \DateTimeImmutable $date,
        User $user,
    ): ReleveJournalier {
        $existing = $this->releveRepository->findOneByCentreAndDate($station, $date);

        if ($existing instanceof ReleveJournalier) {
            return $existing;
        }

        $releve = ReleveJournalier::create(
            $station,
            $date,
            $user,
            new \DateTimeImmutable(),
        );

        $imports = $this->portiqueRepository
            ->aggregateByPortiqueForStationAndDate($station, $date);

        foreach ($station->getEquipementsStation() as $equipement) {
            if (!$equipement->isActive()) {
                continue;
            }

            $ligne = new ReleveEquipement();
            $ligne->setEquipement($equipement);

            if ($equipement->getCategorie() === CategorieEquipement::PORTIQUE) {
                $numero = $equipement->getNumeroPortiqueImport();
                $import = $numero !== null
                    ? ($imports[$numero] ?? null)
                    : null;

                if ($import !== null) {
                    $ligne
                        ->setCb($import['cb'])
                        ->setEspeces($import['especes'])
                        ->setJetons($import['jetons']);
                }
            }

            $releve->addReleveEquipement($ligne);
        }

        return $releve;
    }
}
