<?php

namespace App\Service\Encours;

class EncoursService
{
    public function getEncours(array $encours): array
    {
        $groupes = [];

        foreach ($encours as $ligne) {
            $societeNom = $ligne->getSociete()->getNom();

            $key = $societeNom . '|' . $ligne->getCentre() . '|' . $ligne->getBanque() . '|' . $ligne->getEmprunt();

            if (!isset($groupes[$societeNom])) {
                $groupes[$societeNom] = [
                    'lignes' => [],
                    'totauxParAnnee' => [],
                ];
            }

            if (!isset($groupes[$societeNom]['lignes'][$key])) {
                $groupes[$societeNom]['lignes'][$key] = [
                    'id' => $ligne->getId(),
                    'centre' => $ligne->getCentre(),
                    'banque' => $ligne->getBanque(),
                    'emprunt' => $ligne->getEmprunt(),
                    'date' => $ligne->getDate(),
                    'garanties' => $ligne->getGaranties(),
                    'montants' => [],
                ];
            }

            foreach ($ligne->getMontants() as $montant) {
                $annee = $montant->getAnnee();
                $valeur = (float) $montant->getMontant();

                $groupes[$societeNom]['lignes'][$key]['montants'][$annee] = $valeur;

                if (!isset($groupes[$societeNom]['totauxParAnnee'][$annee])) {
                    $groupes[$societeNom]['totauxParAnnee'][$annee] = 0;
                }

                $groupes[$societeNom]['totauxParAnnee'][$annee] += $valeur;
            }
        }

        return $groupes;
    }

    public function buildGlobals(array $groupes): array
    {
        $globalTotalsParAnnee = [];
        foreach ($groupes as $groupe) {
            $totauxParAnnee = $groupe['totauxParAnnee'] ?? [];
            foreach ($totauxParAnnee as $annee => $total) {
                if (!isset($globalTotalsParAnnee[$annee])) {
                    $globalTotalsParAnnee[$annee] = 0.0;
                }
                $globalTotalsParAnnee[$annee] += (float) $total;
            }
        }

        return $globalTotalsParAnnee;
    }
}
