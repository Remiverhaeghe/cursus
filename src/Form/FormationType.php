<?php

namespace App\Form;

use App\Form\Data\FormationData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Formulaire de création d'une formation.
 *
 * @extends AbstractType<FormationData>
 */
final class FormationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'Nom de la formation', 'empty_data' => ''])
            ->add('diploma', TextType::class, [
                'label' => 'Diplôme préparé',
                'empty_data' => '',
                'help' => 'Par exemple : BTS, titre professionnel, licence professionnelle',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => FormationData::class]);
    }
}
