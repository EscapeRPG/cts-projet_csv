<?php

namespace App\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\File;

final class OrganigramUploadType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('pdf', FileType::class, [
            'label' => 'PDF',
            'mapped' => false,
            'required' => true,
            'constraints' => [
                new File([
                    'maxSize' => '25M',
                    'mimeTypes' => [
                        'application/pdf',
                        'application/x-pdf',
                    ],
                    'mimeTypesMessage' => 'Veuillez fournir un fichier PDF valide.',
                ]),
            ],
        ]);
    }
}

