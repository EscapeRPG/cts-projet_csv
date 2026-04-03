<?php

namespace App\Form;

use App\Entity\Centre;
use App\Entity\Societe;
use App\Entity\Voiture;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CreateVoitureType extends AbstractType
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
            ->add('centre', EntityType::class, [
                'class' => Centre::class,
                'label' => '*Centre : ',
                'placeholder' => '- Choisir -',
                'choice_label' => function($centre) {
                    return $centre->getReseauNom() . ' ' . $centre->getVille();
                },
                'group_by' => function($centre) {
                    return $centre->getReseauNom();
                },
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('c')
                        ->orderBy('c.reseauNom', 'ASC')
                        ->addOrderBy('c.ville', 'ASC');
                },
                'required' => true,
            ])
            ->add('immatriculation',
            )
            ->add('marque')
            ->add('couleur')
            ->add('modele')
            ->add('flocable')
            ->add('annee', null)
            ->add('controleTechnique', null, [
                'widget' => 'single_text',
            ])
            ->add('km')
            ->add('prix')
            ->add('carteGrise')
            ->add('lieu')
            ->add('utilisateur')
            ->add('remarques')
            ->add('active')
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Voiture::class,
        ]);
    }
}
