<?php

namespace App\Form;

use App\Entity\User;
use App\Form\Type\SocietesScopeType;
use App\Repository\CentreRepository;
use App\Repository\SocieteRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CreateUserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('username', TextType::class, [
                'label' => 'Nom :',
                'required' => true,
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email :',
                'required' => true,
            ])
            ->add('isAdmin', CheckboxType::class, [
                'label' => 'Administrateur ?',
                'required' => false,
                'mapped' => false,
            ])
            ->add('organigrammes', ChoiceType::class, [
                'label' => 'Organigrammes :',
                'required' => false,
                'mapped' => false,
                'expanded' => true,
                'multiple' => true,
                'choices' => [
                    'Consulter' => 'ROLE_ORGANIGRAM_VIEW',
                    'Éditer' => 'ROLE_ORGANIGRAM_EDIT',
                    'Ajouter' => 'ROLE_ORGANIGRAM_ADD',
                ],
            ])
            ->add('encours', ChoiceType::class, [
                'label' => 'Encours bancaires :',
                'required' => false,
                'mapped' => false,
                'expanded' => true,
                'multiple' => true,
                'choices' => [
                    'Consulter' => 'ROLE_ENCOURS_VIEW',
                    'Éditer' => 'ROLE_ENCOURS_EDIT',
                    'Ajouter' => 'ROLE_ENCOURS_ADD',
                ],
            ])
            ->add('entreprises', ChoiceType::class, [
                'label' => 'Entreprise(s) :',
                'required' => false,
                'mapped' => false,
                'expanded' => true,
                'multiple' => true,
                'choices' => [
                    'CTS' => 'ROLE_CTS',
                    'Astikoto' => 'ROLE_ASTIKOTO',
                ],
            ])
            ->add('societe', ChoiceType::class, [
                'label' => 'Sociétés :',
                'required' => false,
                'mapped' => false,
                'expanded' => true,
                'multiple' => true,
                'choices' => [
                    'Consulter' => 'ROLE_LIST_SOCIETES_VIEW',
                    'Éditer' => 'ROLE_LIST_SOCIETES_EDIT',
                    'Ajouter' => 'ROLE_LIST_SOCIETES_ADD',
                ],
            ])
            ->add('centre', ChoiceType::class, [
                'label' => 'Centres :',
                'required' => false,
                'mapped' => false,
                'expanded' => true,
                'multiple' => true,
                'choices' => [
                    'Consulter' => 'ROLE_LIST_CENTRES_VIEW',
                    'Éditer' => 'ROLE_LIST_CENTRES_EDIT',
                    'Ajouter' => 'ROLE_LIST_CENTRES_ADD',
                ],
            ])
            ->add('voiture', ChoiceType::class, [
                'label' => 'Voitures :',
                'required' => false,
                'mapped' => false,
                'expanded' => true,
                'multiple' => true,
                'choices' => [
                    'Consulter' => 'ROLE_LIST_VOITURES_VIEW',
                    'Éditer' => 'ROLE_LIST_VOITURES_EDIT',
                    'Ajouter' => 'ROLE_LIST_VOITURES_ADD',
                ],
            ])
            ->add('salaries', ChoiceType::class, [
                'label' => 'Salariés :',
                'required' => false,
                'mapped' => false,
                'expanded' => true,
                'multiple' => true,
                'choices' => [
                    'Consulter' => 'ROLE_LIST_SALARIES_VIEW',
                    'Éditer' => 'ROLE_LIST_SALARIES_EDIT',
                    'Ajouter' => 'ROLE_LIST_SALARIES_ADD',
                ],
            ])
            ->add('centres', SocietesScopeType::class, [
                'label' => 'Périmètre autorisé :',
                'mapped' => false,
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Valider',
            ]);

        // POST_SET_DATA: PRE_SET_DATA can be overwritten by the default data-mapping phase,
        // especially for mapped=false fields. POST_SET_DATA runs after mapping.
        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event): void {
            $user = $event->getData();
            $form = $event->getForm();
            if (!$user instanceof User) {
                return;
            }

            $values = [];
            foreach ($user->getSocietes() as $societe) {
                $id = $societe->getId();
                if ($id !== null) {
                    $values[] = 'societe:' . (string) $id;
                }
            }
            foreach ($user->getCentres() as $centre) {
                $id = $centre->getId();
                if ($id !== null) {
                    $values[] = 'centre:' . (string) $id;
                }
            }

            if ($form->has('centres')) {
                $form->get('centres')->setData($values);
            }
        });

        $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event) use ($options): void {
            $user = $event->getData();
            $form = $event->getForm();
            if (!$user instanceof User) {
                return;
            }

            $raw = $form->has('centres') ? ($form->get('centres')->getData() ?? []) : [];
            if (!is_array($raw)) {
                $raw = [];
            }

            $societeIds = [];
            $centreIds = [];
            foreach ($raw as $v) {
                if (!is_scalar($v)) {
                    continue;
                }
                $s = trim((string) $v);
                if (str_starts_with($s, 'societe:')) {
                    $id = (int) substr($s, 8);
                    if ($id > 0) $societeIds[] = $id;
                } elseif (str_starts_with($s, 'centre:')) {
                    $id = (int) substr($s, 7);
                    if ($id > 0) $centreIds[] = $id;
                }
            }
            $societeIds = array_values(array_unique($societeIds));
            $centreIds = array_values(array_unique($centreIds));

            // Reset collections then re-add.
            foreach ($user->getSocietes()->toArray() as $societe) {
                $user->removeSociete($societe);
            }
            foreach ($user->getCentres()->toArray() as $centre) {
                $user->removeCentre($centre);
            }

            /** @var SocieteRepository $societeRepo */
            $societeRepo = $options['societe_repository'];
            /** @var CentreRepository $centreRepo */
            $centreRepo = $options['centre_repository'];

            foreach ($societeRepo->findBy(['id' => $societeIds]) as $societe) {
                $user->addSociete($societe);
            }
            foreach ($centreRepo->findBy(['id' => $centreIds]) as $centre) {
                $user->addCentre($centre);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);

        $resolver->setRequired(['societe_repository', 'centre_repository']);
        $resolver->setAllowedTypes('societe_repository', SocieteRepository::class);
        $resolver->setAllowedTypes('centre_repository', CentreRepository::class);
    }
}
