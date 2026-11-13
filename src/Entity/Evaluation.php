<?php

namespace App\Entity;

use App\Enum\CompetenceLevel;
use App\Enum\EvaluationContext;
use App\Enum\UserRole;
use App\Repository\EvaluationRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Évaluation datée d'une compétence pour un contrat. Une évaluation n'est jamais modifiée :
 * le niveau courant est la dernière en date, l'historique donne la courbe de progression.
 */
#[ORM\Entity(repositoryClass: EvaluationRepository::class)]
class Evaluation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $aId = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Apprenticeship $aApprenticeship;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Competence $aCompetence;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $aEvaluator;

    #[ORM\Column(type: 'smallint', enumType: CompetenceLevel::class)]
    private CompetenceLevel $aLevel;

    #[ORM\Column(length: 20, enumType: EvaluationContext::class)]
    private EvaluationContext $aContext;

    #[ORM\Column]
    private \DateTimeImmutable $aEvaluatedAt;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $aComment;

    public function __construct(
        Apprenticeship $pApprenticeship,
        Competence $pCompetence,
        User $pEvaluator,
        CompetenceLevel $pLevel,
        EvaluationContext $pContext,
        \DateTimeImmutable $pEvaluatedAt,
        ?string $pComment = null,
    ) {
        if (!$pApprenticeship->getFormation()->hasCompetence($pCompetence)) {
            throw new \InvalidArgumentException('Cette compétence n\'appartient pas à la formation de l\'apprenti.');
        }
        if (EvaluationContext::forRole($pEvaluator->getRole()) !== $pContext) {
            throw new \InvalidArgumentException('Chaque évaluateur note dans son contexte : l\'école à l\'école, le tuteur en entreprise.');
        }
        if (!self::isPartyTo($pEvaluator, $pApprenticeship)) {
            throw new \InvalidArgumentException('L\'évaluateur ne suit pas ce contrat.');
        }
        $this->aApprenticeship = $pApprenticeship;
        $this->aCompetence = $pCompetence;
        $this->aEvaluator = $pEvaluator;
        $this->aLevel = $pLevel;
        $this->aContext = $pContext;
        $this->aEvaluatedAt = $pEvaluatedAt;
        $vComment = null === $pComment ? '' : trim($pComment);
        $this->aComment = '' === $vComment ? null : $vComment;
    }

    private static function isPartyTo(User $pEvaluator, Apprenticeship $pApprenticeship): bool
    {
        return $pEvaluator->hasRole(UserRole::School)
            ? $pApprenticeship->isManagedBy($pEvaluator)
            : $pApprenticeship->isTutor($pEvaluator);
    }

    public function getId(): ?int
    {
        return $this->aId;
    }

    public function getApprenticeship(): Apprenticeship
    {
        return $this->aApprenticeship;
    }

    public function getCompetence(): Competence
    {
        return $this->aCompetence;
    }

    public function getEvaluator(): User
    {
        return $this->aEvaluator;
    }

    public function getLevel(): CompetenceLevel
    {
        return $this->aLevel;
    }

    public function getContext(): EvaluationContext
    {
        return $this->aContext;
    }

    public function getEvaluatedAt(): \DateTimeImmutable
    {
        return $this->aEvaluatedAt;
    }

    public function getComment(): ?string
    {
        return $this->aComment;
    }
}
