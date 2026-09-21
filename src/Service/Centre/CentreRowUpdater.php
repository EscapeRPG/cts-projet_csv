<?php

namespace App\Service\Centre;

use App\Entity\Centre;
use App\Entity\Reseau;
use App\Entity\Societe;
use App\Repository\ReseauRepository;
use App\Repository\SocieteRepository;

final readonly class CentreRowUpdater
{
    public function __construct(
        private SocieteRepository $societeRepository,
        private ReseauRepository $reseauRepository,
    ) {
    }

    /**
     * @param array<string, mixed> $fields
     * @param array<int, true> $allowedSocieteIds
     * @return list<string>
     */
    public function updateStation(Centre $station, array $fields, array $allowedSocieteIds): array
    {
        $errors = $this->updateCommonFields($station, $fields, $allowedSocieteIds);

        $reseauNom = self::trimOrNull($fields['reseauNom'] ?? null);
        if ($reseauNom === null) {
            $errors[] = 'Enseigne requise.';
        } else {
            $station->setReseauNom($reseauNom);
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $fields
     * @param array<int, true> $allowedSocieteIds
     * @param array<int, true> $allowedReseauIds
     * @return list<string>
     */
    public function updateControleTechnique(
        Centre $centre,
        array $fields,
        array $allowedSocieteIds,
        array $allowedReseauIds,
    ): array {
        $errors = $this->updateCommonFields($centre, $fields, $allowedSocieteIds);

        $reseauId = self::trimOrNull($fields['reseau'] ?? null);
        if ($reseauId === null || !ctype_digit($reseauId)) {
            $errors[] = 'Réseau invalide.';
        } elseif (!isset($allowedReseauIds[(int) $reseauId])) {
            $errors[] = 'Réseau hors scope.';
        } else {
            $reseau = $this->reseauRepository->find((int) $reseauId);
            if (!$reseau instanceof Reseau) {
                $errors[] = 'Réseau introuvable.';
            } else {
                $centre->setReseau($reseau);
            }
        }

        $reseauNom = self::trimOrNull($fields['reseauNom'] ?? null);
        if ($reseauNom === null) {
            $errors[] = 'Enseigne requise.';
        } else {
            $centre->setReseauNom($reseauNom);
        }

        $agrCentre = self::trimOrNull($fields['agrCentre'] ?? null);
        if ($agrCentre === null) {
            $errors[] = 'Agrément VL requis.';
        } else {
            $centre->setAgrCentre($agrCentre);
        }

        $centre->setAgrClCentre(self::trimOrNull($fields['agrClCentre'] ?? null));
        $centre->setMailPassword(self::trimOrNull($fields['mailPassword'] ?? null));

        $emailOrange = self::trimOrNull($fields['emailOrange'] ?? null);
        if ($emailOrange !== null && filter_var($emailOrange, FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = 'Email Orange invalide.';
        } else {
            $centre->setEmailOrange($emailOrange);
        }

        $centre->setMailOrangePassword(self::trimOrNull($fields['mailOrangePassword'] ?? null));
        $centre->setDateReprise(self::trimOrNull($fields['dateReprise'] ?? null));

        return $errors;
    }

    /**
     * @param array<string, mixed> $fields
     * @param array<int, true> $allowedSocieteIds
     * @return list<string>
     */
    private function updateCommonFields(Centre $centre, array $fields, array $allowedSocieteIds): array
    {
        $errors = [];
        $societeId = self::trimOrNull($fields['societe'] ?? null);
        if ($societeId === null || !ctype_digit($societeId)) {
            $errors[] = 'Société invalide.';
        } elseif (!isset($allowedSocieteIds[(int) $societeId])) {
            $errors[] = 'Société hors scope.';
        } else {
            $societe = $this->societeRepository->find((int) $societeId);
            if (!$societe instanceof Societe) {
                $errors[] = 'Société introuvable.';
            } else {
                $centre->setSociete($societe);
            }
        }

        $centre->setCoordonnees(self::trimOrNull($fields['coordonnees'] ?? null));

        $cp = self::trimOrNull($fields['cp'] ?? null);
        if ($cp === null) {
            $errors[] = 'CP requis.';
        } else {
            $centre->setCp($cp);
        }

        $ville = self::trimOrNull($fields['ville'] ?? null);
        if ($ville === null) {
            $errors[] = 'Ville requise.';
        } else {
            $centre->setVille($ville);
        }

        $telephone = self::trimOrNull($fields['telephone'] ?? null);
        if ($telephone !== null && preg_match('/^((0[1-9])|(\\+33))[ .-]?((?:[ .-]?\\d{2}){4}|\\d{8})$/', $telephone) !== 1) {
            $errors[] = 'Téléphone invalide.';
        } else {
            $centre->setTelephone($telephone);
        }

        $email = self::trimOrNull($fields['email'] ?? null);
        if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = 'Email invalide.';
        } else {
            $centre->setEmail($email);
        }

        $centre->setSiteWeb(self::trimOrNull($fields['siteWeb'] ?? null));
        $centre->setNumSiret(self::trimOrNull($fields['numSiret'] ?? null) ?? '');

        return $errors;
    }

    private static function trimOrNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
