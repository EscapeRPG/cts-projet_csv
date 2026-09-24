<?php

namespace App\ViewModel\Astikoto;

use App\Entity\ReleveEquipement;

final readonly class ReleveEquipementRow
{
    public function __construct(
        public int $formIndex,
        public ReleveEquipement $releve,
        public ?int $portiqueFormIndex = null,
        public ?ReleveEquipement $portique = null,
    ) {
    }

    public function getLibelle(): string
    {
        return $this->releve->getEquipement()?->getLibelle() ?? '';
    }

    public function getTotal(): float
    {
        return (float) $this->releve->getCb()
            + (float) $this->releve->getEspeces()
            + (float) $this->releve->getCheque()
            + (float) $this->releve->getBl();
    }

    public function getTotalBorne(string $field): float|int
    {
        $getter = 'getTotal'.ucfirst($field);
        $value = $this->releve->$getter();

        return $field === 'jetons'
            ? (int) $value
            : (float) $value;
    }
}
