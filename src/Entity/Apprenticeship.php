<?php

namespace App\Entity;

use App\Enum\UserRole;
use App\Repository\ApprenticeshipRepository;
use Doctrine\ORM\Mapping as ORM;

/** Contrat d'apprentissage : relie un apprenti, son tuteur, l'entreprise d'accueil et la promotion. */
#[ORM\Entity(repositoryClass: ApprenticeshipRepository::class)]
class Apprenticeship
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $aId = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $aApprentice;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $aTutor;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Company $aCompany;

    #[ORM\ManyToOne(inversedBy: 'aApprenticeships')]
    #[ORM\JoinColumn(nullable: false)]
    private Cohort $aCohort;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $aStartDate;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $aEndDate;

    public function __construct(
        User $pApprentice,
        User $pTutor,
        Company $pCompany,
        Cohort $pCohort,
        \DateTimeImmutable $pStartDate,
        \DateTimeImmutable $pEndDate,
    ) {
        if (!$pApprentice->hasRole(UserRole::Apprentice)) {
            throw new \InvalidArgumentException('Le contrat doit concerner un compte apprenti.');
        }
        if (!$pTutor->hasRole(UserRole::Tutor)) {
            throw new \InvalidArgumentException('Le tuteur doit avoir un compte tuteur.');
        }
        if ($pEndDate <= $pStartDate) {
            throw new \InvalidArgumentException('La fin du contrat doit suivre son début.');
        }
        $this->aApprentice = $pApprentice;
        $this->aTutor = $pTutor;
        $this->aCompany = $pCompany;
        $this->aCohort = $pCohort;
        $this->aStartDate = $pStartDate;
        $this->aEndDate = $pEndDate;
        $pCohort->attachApprenticeship($this);
    }

    public function getId(): ?int
    {
        return $this->aId;
    }

    public function getApprentice(): User
    {
        return $this->aApprentice;
    }

    public function getTutor(): User
    {
        return $this->aTutor;
    }

    public function getCompany(): Company
    {
        return $this->aCompany;
    }

    public function getCohort(): Cohort
    {
        return $this->aCohort;
    }

    public function getFormation(): Formation
    {
        return $this->aCohort->getFormation();
    }

    public function getStartDate(): \DateTimeImmutable
    {
        return $this->aStartDate;
    }

    public function getEndDate(): \DateTimeImmutable
    {
        return $this->aEndDate;
    }

    public function isApprentice(User $pUser): bool
    {
        return $pUser === $this->aApprentice;
    }

    public function isTutor(User $pUser): bool
    {
        return $pUser === $this->aTutor;
    }

    public function isManagedBy(User $pUser): bool
    {
        return $this->getFormation()->isManagedBy($pUser);
    }
}
