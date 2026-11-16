<?php

namespace App\Form;

use App\Form\Data\CompetenceBlockData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Formulaire d'ajout d'un bloc de compétences.
 *
 * @extends AbstractType<CompetenceBlockData>
 */
final class CompetenceBlockType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('code', TextType::class, ['label' => 'Code du bloc', 'empty_data' => ''])
            ->add('title', TextType::class, ['label' => 'Intitulé', 'empty_data' => '']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => CompetenceBlockData::class]);
    }
}
