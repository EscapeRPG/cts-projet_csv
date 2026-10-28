<?php

namespace App\ReleveLavage\Service;

use App\Organisation\Entity\Centre;
use App\ReleveLavage\Entity\ReleveEquipement;
use App\ReleveLavage\Entity\ReleveJournalier;
use App\Utilisateur\Entity\User;
use App\Organisation\Enum\CategorieEquipement;
use App\ReleveLavage\Repository\PortiqueImporteRepository;
use App\ReleveLavage\Repository\ReleveJournalierRepository;
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
