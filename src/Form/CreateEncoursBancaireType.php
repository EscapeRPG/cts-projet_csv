<?php

namespace App\Form;

use App\Entity\EncoursBancaire;
use App\Entity\Societe;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class CreateEncoursBancaireType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var list<int>|null $societeScopeIds */
        $societeScopeIds = $options['societe_scope_ids'];

        $builder
            ->add('societe', EntityType::class, [
                'class' => Societe::class,
                'label' => '*Société :',
                'placeholder' => '- Choisir -',
                'choice_label' => 'nom',
                'required' => true,
                'query_builder' => static function (EntityRepository $er) use ($societeScopeIds) {
                    $qb = $er->createQueryBuilder('s')
                        ->orderBy('s.nom', 'ASC');

                    if (is_array($societeScopeIds) && $societeScopeIds !== []) {
                        $qb
                            ->andWhere('s.id IN (:ids)')
                            ->setParameter('ids', $societeScopeIds);
                    } elseif (is_array($societeScopeIds) && $societeScopeIds === []) {
                        // User has an explicit empty scope: show nothing.
                        $qb->andWhere('1 = 0');
                    }

                    return $qb;
                },
            ])
            ->add('type', ChoiceType::class, [
                'label' => '*Type :',
                'required' => true,
                'choices' => [
                    'Exploitations' => 'exploitation',
                    'Immobilier' => 'immobilier',
                ],
            ])
            ->add('centre', TextType::class, [
                'label' => '*Centre :',
                'required' => true,
            ])
            ->add('banque', TextType::class, [
                'label' => 'Banque :',
                'required' => false,
                'empty_data' => null,
            ])
            ->add('emprunt', NumberType::class, [
                'label' => 'Emprunt :',
                'required' => false,
                'scale' => 2,
                'empty_data' => null,
            ])
            ->add('date', TextType::class, [
                'label' => 'Date d\'emprunt :',
                'required' => false,
                'empty_data' => null,
            ])
            ->add('garanties', TextType::class, [
                'label' => 'Garanties :',
                'required' => false,
                'empty_data' => null,
            ])
            ->add('montants', CollectionType::class, [
                'label' => false,
                'entry_type' => EncoursMontantType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'prototype' => true,
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => EncoursBancaire::class,
            // Null means "no restriction" (admin).
            'societe_scope_ids' => null,
        ]);

        $resolver->setAllowedTypes('societe_scope_ids', ['null', 'array']);
    }
}
