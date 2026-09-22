<?php

namespace App\Form;

use App\Enum\CategorieEquipement;
use App\Form\Model\CreateEquipementDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class CreateEquipementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('id', HiddenType::class)
            ->add('libelle', TextType::class, [
                'label' => '*Nom :',
                'required' => true,
            ])
            ->add('code', TextType::class, [
                'label' => '*Code (unique) :',
                'required' => true,
            ])
            ->add('categorie', EnumType::class, [
                'class' => CategorieEquipement::class,
                'label' => '*Catégorie :',
                'required' => true,
            ])
            ->add('numeroPortiqueImport', IntegerType::class, [
                'label' => 'Numéro du portique dans les imports :',
                'required' => false,
                'attr' => [
                    'min' => 1,
                    'inputmode' => 'numeric',
                ],
                'help' => 'Numéro extrait du nom des fichiers CSV, par exemple 4 ou 5.',
            ])
            ->add('portiqueTemporaire', ChoiceType::class, [
                'label' => 'Portique associé :',
                'required' => false,
                'placeholder' => '- Aucun portique -',
                'choices' => $options['portique_choices'],
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Actif ?',
                'required' => false,
                'attr' => [
                    'checked' => true,
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CreateEquipementDTO::class,
            'portique_choices' => [],
        ]);

        $resolver->setAllowedTypes('portique_choices', 'array');
    }
}
