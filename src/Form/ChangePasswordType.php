<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class ChangePasswordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'options' => [
                    'attr' => [
                        'autocomplete' => 'new-password',
                    ]
                ],
                'first_options' => [
                    'label' => 'Mot de passe',
                    'constraints' => [
                        new NotBlank(message: 'Veuillez saisir un mot de passe'),
                        new Length(min: 12, max: 4096, minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères'),
                        new Regex(pattern: '/^(?=.*[0-9])(?=.*[\W_]).+$/', message: 'Le mot de passe doit contenir au moins un chiffre et un caractère spécial.'),
                    ],
                ],
                'second_options' => [
                    'label' => 'Confirmation',
                ],
                'invalid_message' => 'Les mots de passe doivent correspondre.',
                'required' => true,
                'mapped' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
