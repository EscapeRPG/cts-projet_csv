<?php

namespace App\Form;

use App\Entity\Salarie;
use App\Entity\User;
use App\Repository\SalarieRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
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
            ->add('roles', ChoiceType::class, [
                'label' => 'Rôles :',
                'required' => true,
                'mapped' => false,
                'choices' => [
                    'Utilisateur' => 'ROLE_USER',
                    'Salarié CTS' => 'ROLE_CTS',
                    'Astikoto' => 'ROLE_ASTIKOTO',
                    'Administrateur' => 'ROLE_ADMIN',
                ]
            ])
            ->add('salarie', EntityType::class, [
                'label' => 'Salarié associé :',
                'class' => Salarie::class,
                'required' => false,
                'placeholder' => 'Aucun',
                'choice_label' => static fn (Salarie $salarie): string => trim($salarie->getNom() . ' ' . $salarie->getPrenom()),
                'query_builder' => static fn (SalarieRepository $repo) => $repo
                    ->createQueryBuilder('s')
                    ->orderBy('s.nom', 'ASC')
                    ->addOrderBy('s.prenom', 'ASC'),
            ])
            ->add('sumbit', SubmitType::class, [
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
