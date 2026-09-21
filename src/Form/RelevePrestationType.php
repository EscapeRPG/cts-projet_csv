<?php

namespace App\Form;

use App\Entity\RelevePrestation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class RelevePrestationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'required' => true,
            ])
            ->add('cb', NumberType::class, [
                'required' => false,
                'scale' => 2,
                'input' => 'string',
                'empty_data' => '0',
            ])
            ->add('especes', NumberType::class, [
                'required' => false,
                'scale' => 2,
                'input' => 'string',
                'empty_data' => '0',
            ])
            ->add('cheque', NumberType::class, [
                'required' => false,
                'scale' => 2,
                'input' => 'string',
                'empty_data' => '0',
            ])
            ->add('enCompte', NumberType::class, [
                'required' => false,
                'scale' => 2,
                'input' => 'string',
                'empty_data' => '0',
            ])
            ->add('contrat', NumberType::class, [
                'required' => false,
                'scale' => 2,
                'input' => 'string',
                'empty_data' => '0',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RelevePrestation::class,
        ]);
    }
}
