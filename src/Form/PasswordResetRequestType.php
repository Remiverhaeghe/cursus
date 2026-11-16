<?php

namespace App\Form;

use App\Form\Data\PasswordResetRequestData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Formulaire de demande de nouveau mot de passe.
 *
 * @extends AbstractType<PasswordResetRequestData>
 */
final class PasswordResetRequestType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('email', EmailType::class, ['label' => 'Adresse e-mail', 'empty_data' => '']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => PasswordResetRequestData::class]);
    }
}
