<?php

namespace App\ReleveLavage\Form\Model;

use App\ReleveLavage\Entity\RelevePrestation;
use App\ReleveLavage\Entity\ReleveProduit;

final class ReleveJournalierDTO
{
    public ?string $commentaire = null;

    /** @var list<ReleveEquipementDTO> */
    public array $relevesEquipements = [];

    /** @var list<ReleveProduit> */
    public array $relevesProduits = [];

    /** @var list<RelevePrestation> */
    public array $relevesPrestations = [];

}
