<?php

namespace App\Form;

use App\Enum\EvaluationContext;
use App\Form\Data\CalendarPeriodData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Ajout d'une période au rythme d'alternance d'une promotion.
 *
 * @extends AbstractType<CalendarPeriodData>
 */
final class CalendarPeriodType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('place', EnumType::class, [
                'label' => 'Lieu',
                'class' => EvaluationContext::class,
                'choice_label' => static fn (EvaluationContext $pPlace): string => $pPlace->label(),
                'expanded' => true,
            ])
            ->add('startDate', DateType::class, ['label' => 'Premier jour', 'widget' => 'single_text', 'input' => 'datetime_immutable'])
            ->add('endDate', DateType::class, ['label' => 'Dernier jour', 'widget' => 'single_text', 'input' => 'datetime_immutable']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => CalendarPeriodData::class]);
    }
}
