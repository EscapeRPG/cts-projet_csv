<?php

namespace App\Form;

use App\Entity\Centre;
use App\Entity\User;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
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
                    'Ajouter' => 'ROLE_LIST_SALARIES_ADD',
                ],
            ])
            ->add('centres', EntityType::class, [
                'class' => Centre::class,
                'label' => 'Centre(s) :',
                'required' => false,
                'multiple' => true,
                'expanded' => false,
                'choice_label' => static function (Centre $centre): string {
                    $reseau = (string)($centre->getReseauNom() ?? '');
                    $ville = (string)($centre->getVille() ?? '');
                    $agr = (string)($centre->getAgrCentre() ?? '');
                    $agrCl = (string)($centre->getAgrClCentre() ?? '');

                    return trim($agr === '' ? "{$reseau} {$ville} ({$agrCl})" : "{$reseau} {$ville} ({$agr})");
                },
                'group_by' => static function (Centre $centre): string {
                    $name = trim((string)($centre->getSociete()?->getNom() ?? ''));
                    return $name !== '' ? $name : '(Sans societe)';
                },
                'attr' => [
                    'data-centres-selectlike' => '1',
                    'size' => 1,
                ],
                'query_builder' => static function (EntityRepository $er) {
                    return $er->createQueryBuilder('c')
                        ->leftJoin('c.societe', 's')
                        ->addSelect('s')
                        ->orderBy('s.nom', 'ASC')
                        ->addOrderBy('c.ville', 'ASC');
                },
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Valider',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
