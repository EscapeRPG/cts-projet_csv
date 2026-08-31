<?php

namespace App\Form;

use App\Entity\EncoursMontant;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class EncoursMontantType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('annee', IntegerType::class, [
                'label' => 'Année',
                'required' => true,
            ])
            ->add('montant', NumberType::class, [
                'label' => 'Montant',
                'required' => true,
                'scale' => 2,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => EncoursMontant::class,
        ]);
    }
}

