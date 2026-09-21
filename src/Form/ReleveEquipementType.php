<?php

namespace App\Form;

use App\Form\Model\ReleveEquipementDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ReleveEquipementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('cb', NumberType::class, [
                'required' => false,
                'scale' => 2,
                'input' => 'string',
                'empty_data' => '0',
                'html5' => false,
            ])
            ->add('especes', NumberType::class, [
                'required' => false,
                'scale' => 2,
                'input' => 'string',
                'empty_data' => '0',
                'html5' => false,
            ])
            ->add('cheque', NumberType::class, [
                'required' => false,
                'scale' => 2,
                'input' => 'string',
                'empty_data' => '0',
                'html5' => false,
            ])
            ->add('jetons', IntegerType::class, [
                'required' => false,
                'empty_data' => '0',
            ])
            ->add('bl', NumberType::class, [
                'required' => false,
                'scale' => 2,
                'input' => 'string',
                'empty_data' => '0',
                'html5' => false,
            ]);

        $builder->addEventListener(FormEvents::PRE_SET_DATA, static function (FormEvent $event): void {
            $data = $event->getData();
            if (!$data instanceof ReleveEquipementDTO || !$data->isBorne()) {
                return;
            }

            $form = $event->getForm();
            foreach (['totalCb', 'totalEspeces', 'totalCheque', 'totalBl'] as $field) {
                $form->add($field, NumberType::class, [
                    'required' => false,
                    'scale' => 2,
                    'input' => 'string',
                    'empty_data' => '0',
                    'html5' => false,
                ]);
            }
            $form->add('totalJetons', IntegerType::class, [
                'required' => false,
                'empty_data' => '0',
            ]);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ReleveEquipementDTO::class,
        ]);
    }
}
