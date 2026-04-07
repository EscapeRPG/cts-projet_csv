<?php

namespace App\Form;

use App\Entity\Notification;
use App\Entity\Salarie;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class CreateNotificationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('message', TextareaType::class, [
                'label' => 'Message : ',
                'required' => true,
                'attr' => [
                    'rows' => 5,
                ],
            ])
            ->add('salarie', EntityType::class, [
                'class' => Salarie::class,
                'label' => 'Salarié concerné : ',
                'choice_label' => static fn (Salarie $salarie): string => sprintf(
                    '%s %s',
                    $salarie->getPrenom(),
                    $salarie->getNom()
                ),
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('c')
                        ->orderBy('c.nom', 'ASC');
                },
                'placeholder' => 'Aucun salarié',
                'required' => false,
            ])
            ->add('targetDate', DateType::class, [
                'label' => 'Date cible : ',
                'widget' => 'single_text',
                'required' => false,
                'input' => 'datetime_immutable',
            ])
            ->add('expiresAt', DateTimeType::class, [
                'label' => 'Expire le : ',
                'widget' => 'single_text',
                'required' => true,
                'input' => 'datetime_immutable',
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Envoyer la notification',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Notification::class,
        ]);
    }
}
