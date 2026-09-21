<?php

namespace App\Service\Astikoto;

use App\Entity\ReleveEquipement;
use App\Entity\ReleveJournalier;
use App\Form\Model\ReleveEquipementDTO;
use App\Form\Model\ReleveJournalierDTO;
use App\Utils\MoneyToCents;

final readonly class ReleveJournalierMapper
{
    public function toDTO(ReleveJournalier $releve): ReleveJournalierDTO
    {
        $dto = new ReleveJournalierDTO();
        $dto->commentaire = $releve->getCommentaire();
        $equipmentLines = $releve->getRelevesEquipements()->toArray();
        usort($equipmentLines, self::compareEquipmentLines(...));
        $lines = [];

        foreach ($equipmentLines as $line) {
            if (($equipment = $line->getEquipement()) !== null) {
                $lines[spl_object_id($equipment)] = $line;
            }
        }

        foreach ($equipmentLines as $line) {
            $portique = $line->getEquipement()?->getPortiqueAssocie();
            $portiqueLine = $portique !== null ? ($lines[spl_object_id($portique)] ?? null) : null;
            $dto->relevesEquipements[] = $this->equipmentToDTO($line, $portiqueLine);
        }

        $dto->relevesProduits = $releve->getRelevesProduits()->toArray();
        $dto->relevesPrestations = $releve->getRelevesPrestations()->toArray();

        return $dto;
    }

    public function mapToEntity(ReleveJournalierDTO $dto, ReleveJournalier $releve): void
    {
        $releve->setCommentaire($dto->commentaire);
        $dtoByEquipmentId = [];
        foreach ($dto->relevesEquipements as $lineDTO) {
            if ($lineDTO->equipementId !== null) {
                $dtoByEquipmentId[$lineDTO->equipementId] = $lineDTO;
            }
        }

        foreach ($releve->getRelevesEquipements() as $line) {
            $id = $line->getEquipement()?->getId();
            $lineDTO = $id !== null ? ($dtoByEquipmentId[$id] ?? null) : null;
            if (!$lineDTO instanceof ReleveEquipementDTO) {
                throw new \DomainException('Une ligne d’équipement est absente du formulaire.');
            }

            $portiqueId = $line->getEquipement()?->getPortiqueAssocie()?->getId();
            $portiqueDTO = $portiqueId !== null ? ($dtoByEquipmentId[$portiqueId] ?? null) : null;
            $this->applyEquipmentDTO($lineDTO, $line, $portiqueDTO);
        }

        foreach ($releve->getRelevesProduits()->toArray() as $product) {
            if (!in_array($product, $dto->relevesProduits, true)) {
                $releve->removeReleveProduit($product);
            }
        }

        foreach ($dto->relevesProduits as $product) {
            $releve->addReleveProduit($product);
        }

        foreach ($releve->getRelevesPrestations()->toArray() as $prestation) {
            if (!in_array($prestation, $dto->relevesPrestations, true)) {
                $releve->removeRelevePrestation($prestation);
            }
        }

        foreach ($dto->relevesPrestations as $prestation) {
            $releve->addRelevePrestation($prestation);
        }
    }

    private function equipmentToDTO(ReleveEquipement $line, ?ReleveEquipement $portique): ReleveEquipementDTO
    {
        $dto = new ReleveEquipementDTO();
        $dto->equipementId = $line->getEquipement()?->getId();
        $dto->categorie = $line->getEquipement()?->getCategorie();
        $dto->cb = $line->getCb();
        $dto->especes = $line->getEspeces();
        $dto->cheque = $line->getCheque();
        $dto->jetons = $line->getJetons();
        $dto->bl = $line->getBl();
        $dto->totalCb = $this->addMoney($dto->cb, $portique?->getCb() ?? '0');
        $dto->totalEspeces = $this->addMoney($dto->especes, $portique?->getEspeces() ?? '0');
        $dto->totalCheque = $this->addMoney($dto->cheque, $portique?->getCheque() ?? '0');
        $dto->totalJetons = $dto->jetons + ($portique?->getJetons() ?? 0);
        $dto->totalBl = $this->addMoney($dto->bl, $portique?->getBl() ?? '0');

        return $dto;
    }

    private function applyEquipmentDTO(ReleveEquipementDTO $dto, ReleveEquipement $line, ?ReleveEquipementDTO $portique): void
    {
        $line
            ->setCb($dto->isBorne() ? $this->subtractMoney($dto->totalCb, $portique?->cb ?? '0') : $dto->cb)
            ->setEspeces($dto->isBorne() ? $this->subtractMoney($dto->totalEspeces, $portique?->especes ?? '0') : $dto->especes)
            ->setCheque($dto->isBorne() ? $this->subtractMoney($dto->totalCheque, $portique?->cheque ?? '0') : $dto->cheque)
            ->setJetons($dto->isBorne() ? $dto->totalJetons - ($portique?->jetons ?? 0) : $dto->jetons)
            ->setBl($dto->isBorne() ? $this->subtractMoney($dto->totalBl, $portique?->bl ?? '0') : $dto->bl);
    }

    private function addMoney(string $left, string $right): string
    {
        return $this->centsToMoney(MoneyToCents::moneyToCents($left) + MoneyToCents::moneyToCents($right));
    }

    private function subtractMoney(string $left, string $right): string
    {
        return $this->centsToMoney(MoneyToCents::moneyToCents($left) - MoneyToCents::moneyToCents($right));
    }

    private function centsToMoney(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $absolute = abs($cents);

        return sprintf('%s%d.%02d', $sign, intdiv($absolute, 100), $absolute % 100);
    }

    private static function compareEquipmentLines(ReleveEquipement $left, ReleveEquipement $right): int
    {
        return ($left->getEquipement()?->getId() ?? PHP_INT_MAX)
            <=> ($right->getEquipement()?->getId() ?? PHP_INT_MAX);
    }
}
