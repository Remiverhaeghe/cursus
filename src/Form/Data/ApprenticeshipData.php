<?php

namespace App\Form\Data;

use App\Entity\Apprenticeship;
use App\Entity\Cohort;
use App\Entity\Company;
use App\Entity\User;
use Symfony\Component\Validator\Constraints as Assert;

/** Valeurs saisies pour rattacher un apprenti à un tuteur, une entreprise et une promotion. */
final class ApprenticeshipData
{
    #[Assert\NotNull]
    public ?User $apprentice = null;

    #[Assert\NotNull]
    public ?User $tutor = null;

    #[Assert\NotNull]
    public ?Company $company = null;

    #[Assert\NotNull]
    public ?\DateTimeImmutable $startDate = null;

    #[Assert\NotNull]
    #[Assert\GreaterThan(propertyPath: 'startDate', message: 'La fin du contrat doit suivre son début.')]
    public ?\DateTimeImmutable $endDate = null;

    public function toApprenticeship(Cohort $pCohort): Apprenticeship
    {
        if (null === $this->apprentice || null === $this->tutor || null === $this->company || null === $this->startDate || null === $this->endDate) {
            throw new \LogicException('Contrat non validé.');
        }

        return new Apprenticeship($this->apprentice, $this->tutor, $this->company, $pCohort, $this->startDate, $this->endDate);
    }
}
