<?php

namespace App\Form;

use App\Form\Model\ReleveJournalierDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class CreateReleveJournalierType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('commentaire', TextareaType::class, [
                'required' => false,
            ])
            ->add('relevesEquipements', CollectionType::class, [
                'entry_type' => ReleveEquipementType::class,
                'label' => false,
                'allow_add' => false,
                'allow_delete' => false,
                'by_reference' => false,
                'prototype' => false,
            ])
            ->add('relevesProduits', CollectionType::class, [
                'entry_type' => ReleveProduitType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'prototype' => true,
            ])
            ->add('relevesPrestations', CollectionType::class, [
                'entry_type' => RelevePrestationType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'prototype' => true,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ReleveJournalierDTO::class,
        ]);
    }
}
