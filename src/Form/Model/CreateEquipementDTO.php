<?php

namespace App\Form\Model;

use App\Enum\CategorieEquipement;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class CreateEquipementDTO
{
    public ?string $id = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 50)]
    public ?string $code = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    public ?string $libelle = null;

    #[Assert\NotNull]
    public ?CategorieEquipement $categorie = null;

    public bool $isActive = true;

    public ?string $portiqueTemporaire = null;

    #[Assert\Positive(message: 'Le numéro de portique importé doit être strictement positif.')]
    public ?int $numeroPortiqueImport = null;

    #[Assert\Callback]
    public function validateNumeroPortiqueImport(ExecutionContextInterface $context): void
    {
        if (
            $this->numeroPortiqueImport !== null
            && $this->categorie !== CategorieEquipement::PORTIQUE
        ) {
            $context
                ->buildViolation('Ce numéro ne peut être renseigné que pour un portique.')
                ->atPath('numeroPortiqueImport')
                ->addViolation();
        }
    }
}
