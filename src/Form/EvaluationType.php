<?php

namespace App\Form;

use App\Entity\Competence;
use App\Entity\Formation;
use App\Enum\CompetenceLevel;
use App\Form\Data\EvaluationData;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** Formulaire d'évaluation : seules les compétences de la formation de l'apprenti sont proposées. */
final class EvaluationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Formation $vFormation */
        $vFormation = $options['formation'];
        $builder
            ->add('competence', EntityType::class, [
                'label' => 'Compétence',
                'class' => Competence::class,
                'choices' => $vFormation->getCompetences(),
                'choice_label' => static fn (Competence $pCompetence): string => (string) $pCompetence,
                'group_by' => static fn (Competence $pCompetence): string => (string) $pCompetence->getBlock(),
                'placeholder' => 'Choisir une compétence',
            ])
            ->add('level', EnumType::class, [
                'label' => 'Niveau',
                'class' => CompetenceLevel::class,
                'choice_label' => static fn (CompetenceLevel $pLevel): string => $pLevel->value.' · '.$pLevel->label(),
                'expanded' => true,
            ])
            ->add('comment', TextareaType::class, ['label' => 'Commentaire', 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => EvaluationData::class]);
        $resolver->setRequired('formation');
        $resolver->setAllowedTypes('formation', Formation::class);
    }
}
