<?php

namespace App\Form;

use App\Entity\OutilsReseaux;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Image;

class OutilsReseauxType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => '*Nom :',
                'required' => true,
                'help' => '18 caractères maximum',
                'constraints' => [
                    new Length(max: 18, maxMessage: 'Le nom de l\'outil doit faire moins de 18 caractères')
                ]

            ])
            ->add('logo', FileType::class, [
                'label' => 'Logo :',
                'mapped' => false,
                'required' => false,
                'help' => 'Si laissé vide, conserve l\'ancienne image (si existante)',
                'attr' => ['accept' => 'image/png,image/jpeg,image/webp,image/gif'],
                'constraints' => [
                    new Image(
                        maxSize: '2M',
                        mimeTypes: ['image/png', 'image/jpeg', 'image/webp', 'image/gif'],
                        mimeTypesMessage: 'Veuillez sélectionner une image PNG, JPEG, WebP ou GIF.',
                    ),
                ],
            ])
            ->add('lien', TextType::class, [
                'label' => '*Lien :',
                'required' => true,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => OutilsReseaux::class,
        ]);
    }
}
