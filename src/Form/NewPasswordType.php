<?php

namespace App\Form;

use App\Form\Data\NewPasswordData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Saisie du nouveau mot de passe, deux fois.
 *
 * @extends AbstractType<NewPasswordData>
 */
final class NewPasswordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('password', RepeatedType::class, [
            'type' => PasswordType::class,
            'invalid_message' => 'Les deux mots de passe ne correspondent pas.',
            'first_options' => ['label' => 'Nouveau mot de passe', 'help' => '12 caractères au moins', 'attr' => ['autocomplete' => 'new-password']],
            'second_options' => ['label' => 'Confirmation', 'attr' => ['autocomplete' => 'new-password']],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => NewPasswordData::class]);
    }
}
