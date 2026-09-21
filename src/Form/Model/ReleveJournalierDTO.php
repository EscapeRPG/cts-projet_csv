<?php

namespace App\Form\Model;

use App\Entity\RelevePrestation;
use App\Entity\ReleveProduit;

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
