<?php

namespace App\Form;

use App\Form\Data\CompanyData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Formulaire d'ajout d'une entreprise d'accueil.
 *
 * @extends AbstractType<CompanyData>
 */
final class CompanyType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'Raison sociale', 'empty_data' => ''])
            ->add('city', TextType::class, ['label' => 'Ville', 'empty_data' => '']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => CompanyData::class]);
    }
}
