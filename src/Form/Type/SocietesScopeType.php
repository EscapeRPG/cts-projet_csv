<?php

namespace App\Form\Type;

use App\Entity\Centre;
use App\Entity\Societe;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Selectlike-compatible scope selector:
 * - each Societe is a selectable option (value = societe id)
 * - its Centres are shown as disabled options for context (value = "centre:<id>")
 *
 * This keeps the exact selectlike UX (optgroup titles + toggles) that was used for centres,
 * while allowing selecting societes even when they have no centre.
 */
final class SocietesScopeType extends AbstractType
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'required' => false,
            'multiple' => true,
            'expanded' => false,
            'attr' => [
                'data-centres-selectlike' => '1',
                'data-selectlike-item-singular' => 'élément',
                'data-selectlike-item-plural' => 'éléments',
                'size' => 1,
            ],
            'choices' => function (Options $options): array {
                $societes = $this->em->getRepository(Societe::class)
                    ->createQueryBuilder('s')
                    ->leftJoin('s.centre', 'c')
                    ->addSelect('c')
                    ->orderBy('s.nom', 'ASC')
                    ->addOrderBy('c.ville', 'ASC')
                    ->getQuery()
                    ->getResult();

                $choices = [];

                foreach ($societes as $societe) {
                    if (!$societe instanceof Societe) {
                        continue;
                    }

                    $societeName = trim((string) ($societe->getNom() ?? ''));
                    if ($societeName === '') {
                        $societeName = '(Sans nom)';
                    }

                    $group = [
                        $societeName => 'societe:' . (string) $societe->getId(),
                    ];

                    foreach ($societe->getCentre() as $centre) {
                        if (!$centre instanceof Centre) {
                            continue;
                        }

                        $reseau = trim((string) ($centre->getReseauNom() ?? ''));
                        $ville = trim((string) ($centre->getVille() ?? ''));
                        $agr = trim((string) ($centre->getAgrCentre() ?? ''));
                        $agrCl = trim((string) ($centre->getAgrClCentre() ?? ''));
                        $key = $agr !== '' ? $agr : ($agrCl !== '' ? $agrCl : '');
                        $label = trim($key !== '' ? "{$reseau} {$ville} ({$key})" : $ville);
                        if ($label === '') {
                            continue;
                        }

                        $group[$label] = 'centre:' . (string) ($centre->getId() ?? '');
                    }

                    $choices[$societeName] = $group;
                }

                return $choices;
            },
            // Hide the selectable societe option line: the group title checkbox already represents it.
            'choice_attr' => static function (?string $choice, string $key, string $value): array {
                if (str_starts_with($value, 'societe:')) {
                    return ['data-selectlike-hidden' => '1'];
                }
                return [];
            },
        ]);
    }

    public function getParent(): string
    {
        return ChoiceType::class;
    }
}
