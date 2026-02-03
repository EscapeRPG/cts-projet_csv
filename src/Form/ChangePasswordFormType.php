<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotCompromisedPassword;
use Symfony\Component\Validator\Constraints\PasswordStrength;
use Symfony\Component\Validator\Constraints\Regex;

class ChangePasswordFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'options' => [
                    'attr' => [
                        'autocomplete' => 'new-password',
                    ],
                ],
                'first_options' => [
                    'help' => 'Au moins 12 caractères alphanumériques dont un caractère spécial',
                    'constraints' => [
                        new NotBlank(message: 'Veuillez indiquer votre mot de passe'),
                        new Length(min: 12, max: 4096, minMessage: 'Le mot de passe doit au moins contenir {{ limit }} caractères'),
                        new Regex(pattern: '/^(?=.*[a-zA-Z])(?=.*\d)(?=.*[\W_]).{12,}$/', message: 'Le mot de passe doit contenir au moins 12 caractères avec une lettre, un chiffre et un caractère spécial.'),
                        new PasswordStrength(),
                        new NotCompromisedPassword(),

                    ],
                    'label' => 'Votre mot de passe',
                ],
                'second_options' => [
                    'label' => 'Retapez votre mot de passe',
                ],
                'invalid_message' => 'Les deux mots de passe ne correspondent pas.',
                'mapped' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
