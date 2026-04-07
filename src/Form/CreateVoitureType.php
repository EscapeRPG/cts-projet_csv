<?php

namespace App\Form;

use App\Entity\Centre;
use App\Entity\Societe;
use App\Entity\Voiture;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class CreateVoitureType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $annees = [];

        for ($i = 1970; $i <= date('Y'); $i++) {
            $annees[$i] = $i;
        }

        $builder
            ->add('societe', EntityType::class, [
                'class' => Societe::class,
                'label' => '*Société :',
                'placeholder' => '- Choisir -',
                'choice_label' => 'nom',
                'required' => true,
            ])
            ->add('centre', EntityType::class, [
                'class' => Centre::class,
                'label' => '*Centre :',
                'placeholder' => '- Choisir -',
                'choice_label' => function ($centre) {
                    return $centre->getReseauNom() . ' ' . $centre->getVille();
                },
                'group_by' => function ($centre) {
                    return $centre->getReseauNom();
                },
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('c')
                        ->orderBy('c.reseauNom', 'ASC')
                        ->addOrderBy('c.ville', 'ASC');
                },
                'required' => true,
            ])
            ->add('immatriculation',TextType::class, [
                'label' => 'Immatriculation :',
                'required' => false,
            ])
            ->add('marque',TextType::class, [
                'label' => 'Marque :',
                'required' => false,
            ])
            ->add('couleur',TextType::class, [
                'label' => 'Couleur :',
                'required' => false,
            ])
            ->add('modele',TextType::class, [
                'label' => 'Modèle :',
                'required' => false,
            ])
            ->add('flocable', CheckboxType::class, [
                'label' => 'Flocable :',
                'required' => false,

            ])
            ->add('annee', ChoiceType::class, [
                'label' => 'Année :',
                'placeholder' => '- Choisir -',
                'choices' => $annees,
                'multiple' => false,
                'required' => false,
                'empty_data' => null,
            ])
            ->add('controleTechnique', null, [
                'label' => 'Contrôle technique :',
                'widget' => 'single_text',
            ])
            ->add('km',TextType::class, [
                'label' => 'Kilométrage :',
                'required' => false,
            ])
            ->add('prix',TextType::class, [
                'label' => 'Prix :',
                'required' => false,
            ])
            ->add('carteGrise',TextType::class, [
                'label' => 'Carte grise :',
                'required' => false,
            ])
            ->add('lieu',TextType::class, [
                'label' => 'Lieu :',
                'required' => false,
            ])
            ->add('utilisateur',TextType::class, [
                'label' => 'Utilisateur :',
                'required' => false,
            ])
            ->add('remarques',TextareaType::class, [
                'label' => 'Remarques :',
                'attr' => ['rows' => 5],
                'required' => false,
            ])
            ->add('certificatCessionFile', FileType::class, [
                'label' => 'Certificat de cession :',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File(maxSize:'15M', mimeTypes: [
                        'application/pdf',
                        'image/jpeg',
                        'image/png',
                    ], mimeTypesMessage:'Formats acceptés: PDF, JPG, PNG.'),
                ],
            ])
            ->add('active', CheckboxType::class, [
                'label' => 'Active :',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Voiture::class,
        ]);
    }
}
