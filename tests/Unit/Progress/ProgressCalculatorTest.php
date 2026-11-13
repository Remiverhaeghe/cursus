<?php

namespace App\Tests\Unit\Progress;

use App\Entity\Evaluation;
use App\Enum\CompetenceLevel;
use App\Enum\EvaluationContext;
use App\Progress\ApprenticeProgress;
use App\Progress\Average;
use App\Progress\BlockProgress;
use App\Progress\CohortProgress;
use App\Progress\CompetenceProgress;
use App\Progress\ProgressCalculator;
use App\Tests\Support\ContractFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/** Règles de progression : dernier niveau retenu, compétences non évaluées à 0 et signalées. */
#[CoversClass(ProgressCalculator::class)]
#[CoversClass(ApprenticeProgress::class)]
#[CoversClass(BlockProgress::class)]
#[CoversClass(CompetenceProgress::class)]
#[CoversClass(CohortProgress::class)]
#[CoversClass(Average::class)]
final class ProgressCalculatorTest extends TestCase
{
    private ContractFactory $aData;

    protected function setUp(): void
    {
        $this->aData = new ContractFactory();
    }

    public function testCurrentLevelIsTheLatestEvaluationWhateverTheInputOrder(): void
    {
        $vEvaluations = [
            $this->tutorEvaluation('C1.1', CompetenceLevel::Mastered, '2026-03-01'),
            $this->tutorEvaluation('C1.1', CompetenceLevel::Discovery, '2025-10-01'),
            $this->tutorEvaluation('C1.1', CompetenceLevel::InProgress, '2026-01-01'),
        ];

        $vModel = $this->progress($vEvaluations)->aBlocks[0]->aCompetences[0];

        self::assertSame(CompetenceLevel::Mastered, $vModel->current());
        self::assertSame([CompetenceLevel::Discovery, CompetenceLevel::InProgress, CompetenceLevel::Mastered], $vModel->aHistory);
        self::assertSame('2026-03-01', $vModel->aLastEvaluatedAt?->format('Y-m-d'));
        self::assertSame(4, $vModel->value());
        self::assertTrue($vModel->isEvaluated());
        self::assertTrue($vModel->isValidated());
    }

    public function testSameDayEvaluationsKeepTheirRecordingOrder(): void
    {
        $vEvaluations = [
            $this->tutorEvaluation('C1.1', CompetenceLevel::InProgress, '2026-03-01'),
            $this->tutorEvaluation('C1.1', CompetenceLevel::Acquired, '2026-03-01'),
        ];

        self::assertSame(CompetenceLevel::Acquired, $this->progress($vEvaluations)->aBlocks[0]->aCompetences[0]->current());
    }

    public function testUnevaluatedCompetencesCountAsZeroAndAreFlagged(): void
    {
        $vEvaluations = [
            $this->tutorEvaluation('C1.1', CompetenceLevel::Acquired, '2026-01-01'),
            $this->tutorEvaluation('C2.1', CompetenceLevel::InProgress, '2026-01-01'),
        ];

        $vProgress = $this->progress($vEvaluations);
        [$vFront, $vBack] = $vProgress->aBlocks;
        $vInterface = $vFront->aCompetences[1];

        self::assertSame($this->aData->aFrontBlock, $vFront->aBlock);
        self::assertSame(1.5, $vFront->average());
        self::assertSame(38, $vFront->percentage());
        self::assertSame([$vInterface], $vFront->unevaluated());
        self::assertNull($vInterface->current());
        self::assertSame(0, $vInterface->value());
        self::assertFalse($vInterface->isEvaluated());
        self::assertFalse($vInterface->isValidated());
        self::assertNull($vInterface->aLastEvaluatedAt);
        self::assertSame(2.0, $vBack->average());
        self::assertSame([], $vBack->unevaluated());
        self::assertSame(round(5 / 3, 2), $vProgress->average());
        self::assertSame(42, $vProgress->percentage());
        self::assertSame(1, $vProgress->validatedCount());
        self::assertSame(1, $vProgress->unevaluatedCount());
        self::assertCount(3, $vProgress->competences());
        self::assertSame(['C1.2', 'C2.1'], array_map(static fn (CompetenceProgress $pProgress): string => $pProgress->aCompetence->getCode(), $vProgress->remaining()));
        self::assertSame($this->aData->aApprenticeship, $vProgress->aApprenticeship);
    }

    public function testNoEvaluationGivesZeroEverywhere(): void
    {
        $vProgress = $this->progress([]);

        self::assertSame(0.0, $vProgress->average());
        self::assertSame(0, $vProgress->percentage());
        self::assertSame(0, $vProgress->validatedCount());
        self::assertSame(3, $vProgress->unevaluatedCount());
        self::assertCount(3, $vProgress->remaining());
    }

    public function testSparklinePointsDrawLevelFourAtTheTop(): void
    {
        $vRising = new CompetenceProgress($this->aData->aModel, [CompetenceLevel::NotStarted, CompetenceLevel::InProgress, CompetenceLevel::Mastered], null);
        $vSingle = new CompetenceProgress($this->aData->aModel, [CompetenceLevel::Discovery], null);
        $vEmpty = new CompetenceProgress($this->aData->aModel, [], null);

        self::assertSame('0,32 60,16 120,0', $vRising->sparklinePoints(120, 32));
        self::assertSame('60,24', $vSingle->sparklinePoints(120, 32));
        self::assertSame('', $vEmpty->sparklinePoints(120, 32));
        self::assertSame('0,30 50,15 100,0', $vRising->sparklinePoints(100, 30));
        self::assertSame('0,22.5 10,22.5', (new CompetenceProgress($this->aData->aModel, [CompetenceLevel::Discovery, CompetenceLevel::Discovery], null))->sparklinePoints(10, 30));
    }

    public function testAveragesAndCohort(): void
    {
        $vGood = $this->progress([$this->tutorEvaluation('C1.1', CompetenceLevel::Mastered, '2026-01-01'), $this->tutorEvaluation('C1.2', CompetenceLevel::Mastered, '2026-01-01')]);
        $vWeak = $this->progress([]);
        $vCohort = new CohortProgress($this->aData->aApprenticeship->getCohort(), [$vGood, $vWeak]);

        self::assertSame(0.0, Average::of([]));
        self::assertSame(2.5, Average::of([2, 3]));
        self::assertSame(0.67, Average::of([0, 1, 1]));
        self::assertSame(100, Average::percentage(4.0));
        self::assertSame(13, Average::percentage(0.5));
        self::assertSame(1.34, $vCohort->average());
        self::assertSame(Average::percentage($vCohort->average()), $vCohort->percentage());
        self::assertSame([$vGood, $vWeak], $vCohort->aApprentices);
    }

    /** @param list<Evaluation> $pEvaluations */
    private function progress(array $pEvaluations): ApprenticeProgress
    {
        return (new ProgressCalculator())->forApprenticeship($this->aData->aApprenticeship, $pEvaluations);
    }

    private function tutorEvaluation(string $pCode, CompetenceLevel $pLevel, string $pDate): Evaluation
    {
        $vCompetence = match ($pCode) {
            'C1.1' => $this->aData->aModel,
            'C1.2' => $this->aData->aInterface,
            default => $this->aData->aDatabase,
        };

        return new Evaluation($this->aData->aApprenticeship, $vCompetence, $this->aData->aTutor, $pLevel, EvaluationContext::Company, new \DateTimeImmutable($pDate));
    }
}
