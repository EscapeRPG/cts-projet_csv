<?php

namespace App\Form;

use App\Entity\Centre;
use App\Entity\Salarie;
use App\Entity\Societe;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Regex;

class CreateSalarieType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var list<int>|null $centreScopeIds */
        $centreScopeIds = $options['centre_scope_ids'];

        $tailles = [];
        $chaussures = [];

        for ($i = 36; $i <= 52; $i += 2) {
            $tailles[(string) $i] = (string) $i;

            if ($i < 52) {
                $inter = $i . '/' . ($i + 2);
                $tailles[$inter] = $inter;
            }
        }

        for ($y = 36; $y <= 50; $y++) {
            $chaussures[$y] = $y;
        }

        $builder
            ->add('societe', EntityType::class, [
                'class' => Societe::class,
                'label' => '*Société : ',
                'placeholder' => '- Choisir -',
                'choice_label' => 'nom',
                'required' => true,
                'query_builder' => static function (EntityRepository $er) use ($centreScopeIds) {
                    $qb = $er->createQueryBuilder('so')
                        ->orderBy('so.nom', 'ASC');

                    if ($centreScopeIds !== null) {
                        $qb->distinct();

                        if ($centreScopeIds === []) {
                            $qb->andWhere('1=0');
                        } else {
                            // Restrict companies to the ones owning at least one scoped centre.
                            $qb
                                ->innerJoin('so.centre', 'c_scope')
                                ->andWhere('c_scope.id IN (:centreIds)')
                                ->setParameter('centreIds', $centreScopeIds);
                        }
                    }

                    return $qb;
                },
            ])
            ->add('agrControleur', TextType::class, [
                'label' => 'Agrément : ',
                'required' => false,
                'empty_data' => null,
            ])
            ->add('agrClControleur', TextType::class, [
                'label' => 'Agrément CL : ',
                'required' => false,
                'empty_data' => null,
            ])
            ->add('centres', EntityType::class, [
                'class' => Centre::class,
                'label' => 'Centre(s) : ',
                'required' => false,
                'multiple' => true,
                'expanded' => false,
                'choice_label' => static function (Centre $centre): string {
                    $ville = (string) ($centre->getVille() ?? '');
                    $agr = (string) ($centre->getAgrCentre() ?? '');

                    return trim($agr === '' ? $ville : "{$ville} ({$agr})");
                },
                'group_by' => static fn (Centre $centre): string => (string) ($centre->getReseauNom() ?? ''),
                'attr' => [
                    // Used by JS to transform this <select multiple> into a select-like checkbox dropdown.
                    'data-centres-selectlike' => '1',
                    // Keep the native widget compact when JS is disabled.
                    'size' => 1,
                ],
                'query_builder' => static function (EntityRepository $er) use ($centreScopeIds) {
                    $qb = $er->createQueryBuilder('c')
                        ->orderBy('c.reseauNom', 'ASC')
                        ->addOrderBy('c.ville', 'ASC');

                    if ($centreScopeIds !== null) {
                        if ($centreScopeIds === []) {
                            $qb->andWhere('1=0');
                        } else {
                            $qb
                                ->andWhere('c.id IN (:centreIds)')
                                ->setParameter('centreIds', $centreScopeIds);
                        }
                    }

                    return $qb;
                },
            ])
            ->add('nom', TextType::class, [
                'label' => '*Nom : ',
                'required' => true,
            ])
            ->add('prenom', TextType::class, [
                'label' => '*Prénom : ',
                'required' => true,
            ])
            ->add('dateNaissance', DateType::class, [
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'html5' => true,
                'label' => 'Date de naissance : ',
                'required' => false,
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email : ',
                'required' => false,
                'empty_data' => '',
            ])
            ->add('telephone', TextType::class, [
                'label' => 'Téléphone : ',
                'required' => false,
                'empty_data' => null,
                'constraints' => [
                    new Regex(pattern: '/^((0[1-9])|(\+33))[ .-]?((?:[ .-]?\d{2}){4}|\d{8})$/', message: "Le numéro de téléphone doit commencer par 0 ou +33."),
                ]
            ])
            ->add('echelons', ChoiceType::class, [
                'label' => 'Échelons : ',
                'required' => false,
                'empty_data' => null,
                'placeholder' => '- Choisir -',
                'choices' => [
                    '1' => 1,
                    '2' => 2,
                    '3' => 3,
                    '4' => 4,
                    '5' => 5,
                    '6' => 6,
                    '7' => 7,
                    '8' => 8,
                    '9' => 9,
                    '10' => 10,
                    '11' => 11,
                    '12' => 12,
                ]
            ])
            ->add('salaireBrut', NumberType::class, [
                'label' => 'Salaire Brut',
                'required' => false,
                'empty_data' => null,
                'scale' => 2,
            ])
            ->add('nbHeures', NumberType::class, [
                'label' => 'Nombre d\'heures : ',
                'help' => 'Au format numérique uniquement (ex: 35,15)',
                'required' => false,
                'empty_data' => null,
                'scale' => 2,
            ])
            ->add('vesteMancheAmovible', ChoiceType::class, [
                'label' => 'Veste : ',
                'required' => false,
                'empty_data' => null,
                'placeholder' => '- Choisir -',
                'choices' => [
                    'XXS' => 'XXS',
                    'XS' => 'XS',
                    'S' => 'S',
                    'M' => 'M',
                    'L' => 'L',
                    'XL' => 'XL',
                    'XXL' => 'XXL',
                    'XXXL' => 'XXXL',
                    'XXXXL' => 'XXXXL',
                ]
            ])
            ->add('polaire', ChoiceType::class, [
                'label' => 'Polaire : ',
                'required' => false,
                'empty_data' => null,
                'placeholder' => '- Choisir -',
                'choices' => [
                    'XXS' => 'XXS',
                    'XS' => 'XS',
                    'S' => 'S',
                    'M' => 'M',
                    'L' => 'L',
                    'XL' => 'XL',
                    'XXL' => 'XXL',
                    'XXXL' => 'XXXL',
                    'XXXXL' => 'XXXXL',
                ]
            ])
            ->add('pantalon', ChoiceType::class, [
                'label' => 'Pantalon : ',
                'required' => false,
                'empty_data' => null,
                'placeholder' => '- Choisir -',
                'choices' => $tailles
            ])
            ->add('teeShirts', ChoiceType::class, [
                'label' => 'Tee-shirt : ',
                'required' => false,
                'empty_data' => null,
                'placeholder' => '- Choisir -',
                'choices' => [
                    'XXS' => 'XXS',
                    'XS' => 'XS',
                    'S' => 'S',
                    'M' => 'M',
                    'L' => 'L',
                    'XL' => 'XL',
                    'XXL' => 'XXL',
                    'XXXL' => 'XXXL',
                    'XXXXL' => 'XXXXL',
                ]
            ])
            ->add('polo', ChoiceType::class, [
                'label' => 'Polo : ',
                'required' => false,
                'empty_data' => null,
                'placeholder' => '- Choisir -',
                'choices' => [
                    'XXS' => 'XXS',
                    'XS' => 'XS',
                    'S' => 'S',
                    'M' => 'M',
                    'L' => 'L',
                    'XL' => 'XL',
                    'XXL' => 'XXL',
                    'XXXL' => 'XXXL',
                    'XXXXL' => 'XXXXL',
                ]
            ])
            ->add('chaussures', ChoiceType::class, [
                'label' => 'Chaussures : ',
                'required' => false,
                'empty_data' => null,
                'placeholder' => '- Choisir -',
                'choices' => $chaussures
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Actif ? ',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Salarie::class,
            // Null = no restriction (admin). [] = no centres assigned.
            'centre_scope_ids' => null,
        ]);

        $resolver->setAllowedTypes('centre_scope_ids', ['null', 'array']);
    }
}
