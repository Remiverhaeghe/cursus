<?php

namespace App\Tests\Unit\Entity;

use App\Entity\CalendarPeriod;
use App\Entity\Cohort;
use App\Entity\PlannedAssessment;
use App\Enum\EvaluationContext;
use App\Tests\Support\ContractFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/** Périodes d'alternance et évaluations prévues d'une promotion. */
#[CoversClass(CalendarPeriod::class)]
#[CoversClass(PlannedAssessment::class)]
#[CoversClass(Cohort::class)]
final class CalendarEntitiesTest extends TestCase
{
    public function testPeriodKeepsWholeDaysAndJoinsItsCohort(): void
    {
        $vCohort = (new ContractFactory())->aApprenticeship->getCohort();

        $vPeriod = new CalendarPeriod($vCohort, EvaluationContext::Company, new \DateTimeImmutable('2026-09-07 15:30'), new \DateTimeImmutable('2026-09-07 08:00'));

        self::assertNull($vPeriod->getId());
        self::assertSame($vCohort, $vPeriod->getCohort());
        self::assertSame($vCohort->getFormation(), $vPeriod->getFormation());
        self::assertSame(EvaluationContext::Company, $vPeriod->getPlace());
        self::assertSame('2026-09-07 00:00', $vPeriod->getStartDate()->format('Y-m-d H:i'));
        self::assertSame('2026-09-07 00:00', $vPeriod->getEndDate()->format('Y-m-d H:i'));
        self::assertSame([$vPeriod], $vCohort->getPeriods());
    }

    public function testPeriodCannotEndBeforeItStarts(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La période doit finir après son début.');

        new CalendarPeriod((new ContractFactory())->aApprenticeship->getCohort(), EvaluationContext::School, new \DateTimeImmutable('2026-09-08'), new \DateTimeImmutable('2026-09-07'));
    }

    public function testOverlapCountsBothEndsAndIgnoresTheTime(): void
    {
        $vPeriod = new CalendarPeriod((new ContractFactory())->aApprenticeship->getCohort(), EvaluationContext::School, new \DateTimeImmutable('2026-09-07'), new \DateTimeImmutable('2026-09-13'));

        self::assertTrue($vPeriod->overlaps(new \DateTimeImmutable('2026-09-01'), new \DateTimeImmutable('2026-09-07 09:00')));
        self::assertTrue($vPeriod->overlaps(new \DateTimeImmutable('2026-09-13 18:00'), new \DateTimeImmutable('2026-09-20')));
        self::assertTrue($vPeriod->overlaps(new \DateTimeImmutable('2026-09-09'), new \DateTimeImmutable('2026-09-10')));
        self::assertFalse($vPeriod->overlaps(new \DateTimeImmutable('2026-09-01'), new \DateTimeImmutable('2026-09-06 23:00')));
        self::assertFalse($vPeriod->overlaps(new \DateTimeImmutable('2026-09-14'), new \DateTimeImmutable('2026-09-20')));
    }

    public function testCohortSortsItsCalendarAndDetectsOverlaps(): void
    {
        $vCohort = (new ContractFactory())->aApprenticeship->getCohort();
        $vLater = new CalendarPeriod($vCohort, EvaluationContext::Company, new \DateTimeImmutable('2026-09-14'), new \DateTimeImmutable('2026-09-27'));
        $vEarlier = new CalendarPeriod($vCohort, EvaluationContext::School, new \DateTimeImmutable('2026-09-07'), new \DateTimeImmutable('2026-09-13'));
        $vExam = new PlannedAssessment($vCohort, 'Épreuve blanche', EvaluationContext::School, new \DateTimeImmutable('2027-01-18'));
        $vReview = new PlannedAssessment($vCohort, 'Bilan', EvaluationContext::Company, new \DateTimeImmutable('2026-12-07'));

        self::assertSame([$vEarlier, $vLater], $vCohort->getPeriods());
        self::assertSame([$vReview, $vExam], $vCohort->getAssessments());
        self::assertTrue($vCohort->hasPeriodOverlapping(new \DateTimeImmutable('2026-09-27'), new \DateTimeImmutable('2026-10-03')));
        self::assertFalse($vCohort->hasPeriodOverlapping(new \DateTimeImmutable('2026-09-28'), new \DateTimeImmutable('2026-10-03')));

        $vCohort->detachPeriod($vEarlier);
        $vCohort->detachAssessment($vExam);

        self::assertSame([$vLater], $vCohort->getPeriods());
        self::assertSame([$vReview], $vCohort->getAssessments());
    }

    public function testAssessmentIsTrimmedAndDated(): void
    {
        $vCohort = (new ContractFactory())->aApprenticeship->getCohort();

        $vAssessment = new PlannedAssessment($vCohort, '  Soutenance  ', EvaluationContext::Company, new \DateTimeImmutable('2027-05-17 14:00'));

        self::assertNull($vAssessment->getId());
        self::assertSame($vCohort, $vAssessment->getCohort());
        self::assertSame($vCohort->getFormation(), $vAssessment->getFormation());
        self::assertSame('Soutenance', $vAssessment->getTitle());
        self::assertSame(EvaluationContext::Company, $vAssessment->getPlace());
        self::assertSame('2027-05-17 00:00', $vAssessment->getDate()->format('Y-m-d H:i'));
    }

    public function testAssessmentNeedsATitle(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Une évaluation prévue porte un intitulé.');

        new PlannedAssessment((new ContractFactory())->aApprenticeship->getCohort(), '   ', EvaluationContext::School, new \DateTimeImmutable('2027-01-18'));
    }
}
