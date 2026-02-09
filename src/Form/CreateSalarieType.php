<?php

namespace App\Form;

use App\Entity\Salarie;
use App\Entity\Societe;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Regex;

class CreateSalarieType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('societe', EntityType::class, [
                'class' => Societe::class,
                'label' => '*Société : ',
                'placeholder' => '- Choisir -',
                'choice_label' => 'nom',
                'required' => true,
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
            ->add('nom', TextType::class, [
                'label' => '*Nom : ',
                'required' => true,
            ])
            ->add('prenom', TextType::class, [
                'label' => '*Prénom : ',
                'required' => true,
            ])
            ->add('dateNaissance', null, [
                'widget' => 'single_text',
                'label' => 'Date de naissance : ',
                'required' => false,
                'empty_data' => null,
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email : ',
                'required' => false,
                'empty_data' => null,
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
                    'XXXL' => 'XXXL',
                    'XXXXL' => 'XXXXL',
                ]
            ])
            ->add('pantalon', TextType::class, [
                'label' => 'Pantalon : ',
                'required' => false,
                'empty_data' => null,
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
                    'XXXL' => 'XXXL',
                    'XXXXL' => 'XXXXL',
                ]
            ])
            ->add('chaussures', IntegerType::class, [
                'label' => 'Chaussures : ',
                'required' => false,
                'empty_data' => null,
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
        ]);
    }
}
