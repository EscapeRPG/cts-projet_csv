<?php

namespace App\Form;

use App\Entity\Societe;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CreateSocieteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => '*Nom : ',
                'required' => true,
            ])
            ->add('siegeSocial', TextType::class, [
                'label' => 'Siège social : ',
                'required' => true,
            ])
            ->add('siren', TextType::class, [
                'label' => 'SIREN : ',
                'required' => true,
            ])
            ->add('numTva', TextType::class, [
                'label' => 'Numéro de TVA : ',
                'required' => false,
                'empty_data' => null,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Societe::class,
        ]);
    }
}
