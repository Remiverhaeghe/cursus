<?php

namespace App\Tests\Unit\Evaluation;

use App\Audit\AuditLog;
use App\Entity\Apprenticeship;
use App\Entity\Evaluation;
use App\Entity\User;
use App\Enum\CompetenceLevel;
use App\Enum\EvaluationContext;
use App\Evaluation\EvaluationRecorder;
use App\Form\Data\EvaluationData;
use App\Tests\Support\ContractFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\MockClock;

#[CoversClass(EvaluationRecorder::class)]
final class EvaluationRecorderTest extends TestCase
{
    public function testEvaluationIsSavedDatedAndLoggedWithIdsOnly(): void
    {
        $vData = new ContractFactory();
        (new \ReflectionProperty(Apprenticeship::class, 'aId'))->setValue($vData->aApprenticeship, 5);
        (new \ReflectionProperty(User::class, 'aId'))->setValue($vData->aTutor, 9);
        $vInput = new EvaluationData();
        $vInput->competence = $vData->aModel;
        $vInput->level = CompetenceLevel::Mastered;
        $vEntityManager = $this->createMock(EntityManagerInterface::class);
        $vEntityManager->expects(self::once())->method('persist')->with(self::isInstanceOf(Evaluation::class))
            ->willReturnCallback(static function (Evaluation $pEvaluation): void {
                (new \ReflectionProperty(Evaluation::class, 'aId'))->setValue($pEvaluation, 31);
            });
        $vEntityManager->expects(self::once())->method('flush');
        $vLogger = $this->createMock(LoggerInterface::class);
        $vLogger->expects(self::once())->method('info')->with('Évaluation enregistrée', ['evaluation_id' => 31, 'apprenticeship_id' => 5, 'evaluator_id' => 9]);

        $vEvaluation = (new EvaluationRecorder($vEntityManager, new MockClock('2026-11-15 09:30:00'), $vLogger, $this->createStub(AuditLog::class)))->record($vInput, $vData->aApprenticeship, $vData->aTutor);

        self::assertSame('2026-11-15 09:30:00', $vEvaluation->getEvaluatedAt()->format('Y-m-d H:i:s'));
        self::assertSame(EvaluationContext::Company, $vEvaluation->getContext());
        self::assertSame(CompetenceLevel::Mastered, $vEvaluation->getLevel());
    }
}
