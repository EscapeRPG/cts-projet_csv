<?php

namespace App\Form;

use App\Entity\Centre;
use App\Entity\Reseau;
use App\Entity\Societe;
use App\Enum\CategorieEquipement;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Validator\Constraints\Regex;

class CreateStationLavageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var list<int>|null $centreScopeIds */
        $centreScopeIds = $options['centre_scope_ids'];

        $builder
            ->add('societe', EntityType::class, [
                'class' => Societe::class,
                'label' => '*Société : ',
                'placeholder' => '- Choisir -',
                'choice_label' => 'nom',
                'required' => true,
                'query_builder' => static function (EntityRepository $er) use ($centreScopeIds) {
                    $qb = $er->createQueryBuilder('so')
                        ->orderBy('so.nom', 'ASC');

                    if ($centreScopeIds !== null) {
                        $qb->distinct();

                        if ($centreScopeIds === []) {
                            $qb->andWhere('1=0');
                        } else {
                            $qb
                                ->innerJoin('so.centre', 'c_scope')
                                ->andWhere('c_scope.id IN (:centreIds)')
                                ->setParameter('centreIds', $centreScopeIds);
                        }
                    }

                    return $qb;
                },
            ])
            ->add('reseau', EntityType::class, [
                'class' => Reseau::class,
                'label' => '*Réseau : ',
                'placeholder' => '- Choisir -',
                'choice_label' => 'nom',
                'required' => true,
                'query_builder' => static function (EntityRepository $er) use ($centreScopeIds) {
                    $qb = $er->createQueryBuilder('r')
                        ->orderBy('r.nom', 'ASC');

                    if ($centreScopeIds !== null) {
                        $qb->distinct();

                        if ($centreScopeIds === []) {
                            $qb->andWhere('1=0');
                        } else {
                            // Restrict networks to the ones used by scoped centres.
                            $qb
                                ->innerJoin('r.centres', 'c_scope')
                                ->andWhere('c_scope.id IN (:centreIds)')
                                ->setParameter('centreIds', $centreScopeIds);
                        }
                    }

                    return $qb;
                },
            ])
            ->add('reseauNom', TextType::class, [
                'label' => '*Enseigne : ',
                'required' => true,
            ])
            ->add('coordonnees', TextType::class, [
                'label' => 'Adresse : ',
                'required' => false,
                'empty_data' => null,
            ])
            ->add('cp', TextType::class, [
                'label' => '*Code Postal : ',
                'required' => true,
            ])
            ->add('ville', TextType::class, [
                'label' => '*Ville : ',
                'required' => true,
            ])
            ->add('telephone', TextType::class, [
                'label' => 'Téléphone : ',
                'required' => false,
                'empty_data' => null,
                'constraints' => [
                    new Regex(pattern: '/^((0[1-9])|(\+33))[ .-]?((?:[ .-]?\d{2}){4}|\d{8})$/', message: "Le numéro de téléphone doit commencer par 0 ou +33."),
                ]
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email : ',
                'required' => false,
                'empty_data' => null,
            ])
            ->add('mailPassword', TextType::class, [
                'label' => 'Mot de passe email : ',
                'required' => false,
                'empty_data' => null,
            ])
            ->add('siteWeb', TextType::class, [
                'label' => 'Site web : ',
                'required' => false,
                'empty_data' => null,
            ])
            ->add('numSiret', TextType::class, [
                'label' => 'Siret : ',
                'required' => false,
                'empty_data' => null,
            ])
            ->add('dateReprise', TextType::class, [
                'label' => 'Date de reprise : ',
                'required' => false,
                'empty_data' => null,
            ]);

        $equipements = $builder->create('equipements', CollectionType::class, [
            'entry_type' => CreateEquipementType::class,
            'mapped' => false,
            'allow_add' => true,
            'allow_delete' => true,
            'prototype' => true,
        ]);

        $equipements->addEventListener(
            FormEvents::PRE_SUBMIT,
            static function (FormEvent $event): void {
                $lignes = $event->getData();
                $collectionForm = $event->getForm();

                if (!is_array($lignes)) {
                    return;
                }

                $choixPortiques = [];

                foreach ($lignes as $index => $ligne) {
                    if (!is_array($ligne)) {
                        continue;
                    }

                    if (($ligne['categorie'] ?? null) !== CategorieEquipement::PORTIQUE->value) {
                        continue;
                    }

                    $libelle = trim((string) ($ligne['libelle'] ?? ''));

                    if ($libelle === '') {
                        $libelle = sprintf('Portique %s', $index);
                    }

                    $choiceLabel = sprintf('%s [%s]', $libelle, $index);
                    $choixPortiques[$choiceLabel] = (string) $index;
                }

                foreach ($collectionForm as $index => $equipementForm) {
                    $equipementForm->add('portiqueTemporaire', ChoiceType::class, [
                        'label' => 'Portique associé :',
                        'required' => false,
                        'placeholder' => '- Aucun portique -',
                        'choices' => $choixPortiques,
                    ]);
                }
            },
            -10,
        );

        $builder->add($equipements);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Centre::class,
            // Null = no restriction (admin). [] = no centres assigned.
            'centre_scope_ids' => null,
        ]);

        $resolver->setAllowedTypes('centre_scope_ids', ['null', 'array']);
    }
}
