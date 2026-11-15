<?php

namespace App\Form;

use App\Entity\Company;
use App\Entity\User;
use App\Enum\UserRole;
use App\Form\Data\ApprenticeshipData;
use App\Repository\CompanyRepository;
use App\Repository\UserRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Formulaire de rattachement d'un apprenti : seuls les comptes du bon profil sont proposés.
 *
 * @extends AbstractType<ApprenticeshipData>
 */
final class ApprenticeshipType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('apprentice', EntityType::class, [
                'label' => 'Apprenti',
                'class' => User::class,
                'choice_label' => 'fullName',
                'query_builder' => static fn (UserRepository $pRepository) => $pRepository->queryByRole(UserRole::Apprentice),
                'placeholder' => 'Choisir un apprenti',
            ])
            ->add('tutor', EntityType::class, [
                'label' => 'Tuteur',
                'class' => User::class,
                'choice_label' => 'fullName',
                'query_builder' => static fn (UserRepository $pRepository) => $pRepository->queryByRole(UserRole::Tutor),
                'placeholder' => 'Choisir un tuteur',
            ])
            ->add('company', EntityType::class, [
                'label' => 'Entreprise',
                'class' => Company::class,
                'choice_label' => static fn (Company $pCompany): string => (string) $pCompany,
                'query_builder' => static fn (CompanyRepository $pRepository) => $pRepository->createQueryBuilder('c')->orderBy('c.aName'),
                'placeholder' => 'Choisir une entreprise',
            ])
            ->add('startDate', DateType::class, ['label' => 'Début du contrat', 'widget' => 'single_text', 'input' => 'datetime_immutable'])
            ->add('endDate', DateType::class, ['label' => 'Fin du contrat', 'widget' => 'single_text', 'input' => 'datetime_immutable']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => ApprenticeshipData::class]);
    }
}
