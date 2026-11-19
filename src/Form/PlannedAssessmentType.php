<?php

namespace App\Form;

use App\Enum\EvaluationContext;
use App\Form\Data\PlannedAssessmentData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Ajout d'une évaluation prévue au calendrier d'une promotion.
 *
 * @extends AbstractType<PlannedAssessmentData>
 */
final class PlannedAssessmentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, ['label' => 'Intitulé'])
            ->add('place', EnumType::class, [
                'label' => 'Lieu',
                'class' => EvaluationContext::class,
                'choice_label' => static fn (EvaluationContext $pPlace): string => $pPlace->label(),
                'expanded' => true,
            ])
            ->add('date', DateType::class, ['label' => 'Date', 'widget' => 'single_text', 'input' => 'datetime_immutable']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => PlannedAssessmentData::class]);
    }
}
