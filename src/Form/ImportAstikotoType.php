<?php

namespace App\Form;

use App\Entity\Centre;
use App\Enum\TypeCentre;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;

final class ImportAstikotoType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('centre', EntityType::class, [
                'class' => Centre::class,
                'label' => 'Station de lavage',
                'placeholder' => '- Choisir une station -',
                'choice_label' => static function (Centre $centre): string {
                    $ville = trim((string) $centre->getVille());
                    $societe = trim((string) $centre->getSociete()?->getNom());

                    return $societe === '' ? $ville : sprintf('%s — %s', $societe, $ville);
                },
                'query_builder' => static fn (EntityRepository $repository) => $repository
                    ->createQueryBuilder('c')
                    ->leftJoin('c.societe', 's')
                    ->andWhere('c.type = :type')
                    ->setParameter('type', TypeCentre::STATION_LAVAGE)
                    ->orderBy('s.nom', 'ASC')
                    ->addOrderBy('c.ville', 'ASC'),
                'constraints' => [
                    new NotBlank(message: 'Veuillez sélectionner la station concernée.'),
                ],
            ])
            ->add('files', FileType::class, [
                'label' => 'Fichiers CSV',
                'multiple' => true,
                'mapped' => false,
                'constraints' => [
                    new NotBlank(message: 'Veuillez sélectionner au moins un fichier.'),
                    new All([
                        new File(
                            extensions: ['csv'],
                            extensionsMessage: 'Seuls les fichiers CSV sont acceptés.',
                        ),
                    ]),
                ],
            ])
            ->add('import', SubmitType::class, [
                'label' => 'Importer',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}
