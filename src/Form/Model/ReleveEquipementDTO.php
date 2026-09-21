<?php

namespace App\Form\Model;

use App\Enum\CategorieEquipement;

final class ReleveEquipementDTO
{
    public ?int $equipementId = null;
    public ?CategorieEquipement $categorie = null;
    public string $cb = '0.00';
    public string $especes = '0.00';
    public string $cheque = '0.00';
    public int $jetons = 0;
    public string $bl = '0.00';

    public string $totalCb = '0.00';
    public string $totalEspeces = '0.00';
    public string $totalCheque = '0.00';
    public int $totalJetons = 0;
    public string $totalBl = '0.00';

    public function isBorne(): bool
    {
        return $this->categorie === CategorieEquipement::BORNE;
    }

}
