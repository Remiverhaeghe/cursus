<?php

namespace App\Tests\Unit\Form;

use App\Entity\CalendarPeriod;
use App\Enum\EvaluationContext;
use App\Form\Data\CalendarPeriodData;
use App\Form\Data\MessageData;
use App\Form\Data\PlannedAssessmentData;
use App\Tests\Support\ContractFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CalendarPeriodData::class)]
#[CoversClass(PlannedAssessmentData::class)]
#[CoversClass(MessageData::class)]
final class CalendarInputDataTest extends TestCase
{
    public function testPeriodInputBuildsThePeriodAndChecksOverlaps(): void
    {
        $vCohort = (new ContractFactory())->aApprenticeship->getCohort();
        new CalendarPeriod($vCohort, EvaluationContext::School, new \DateTimeImmutable('2026-09-07'), new \DateTimeImmutable('2026-09-13'));
        $vData = new CalendarPeriodData();
        $vData->place = EvaluationContext::Company;
        $vData->startDate = new \DateTimeImmutable('2026-09-14');
        $vData->endDate = new \DateTimeImmutable('2026-09-27');

        self::assertFalse($vData->overlapsAPeriodOf($vCohort));
        $vData->startDate = new \DateTimeImmutable('2026-09-13');
        self::assertTrue($vData->overlapsAPeriodOf($vCohort));

        $vPeriod = $vData->toPeriod($vCohort);
        self::assertSame(EvaluationContext::Company, $vPeriod->getPlace());
        self::assertSame('2026-09-13', $vPeriod->getStartDate()->format('Y-m-d'));
        self::assertSame('2026-09-27', $vPeriod->getEndDate()->format('Y-m-d'));
        self::assertSame($vCohort, $vPeriod->getCohort());
    }

    public function testIncompletePeriodIsRefused(): void
    {
        $vCohort = (new ContractFactory())->aApprenticeship->getCohort();
        foreach (['place' => [false, true], 'startDate' => [true, true], 'endDate' => [true, true]] as $vMissing => $vRefused) {
            $vData = new CalendarPeriodData();
            $vData->place = EvaluationContext::School;
            $vData->startDate = new \DateTimeImmutable('2026-09-07');
            $vData->endDate = new \DateTimeImmutable('2026-09-13');
            $vData->{$vMissing} = null;
            $vCalls = [static fn (): bool => $vData->overlapsAPeriodOf($vCohort), static fn (): CalendarPeriod => $vData->toPeriod($vCohort)];
            foreach ($vCalls as $vIndex => $vCall) {
                try {
                    $vCall();
                    self::assertFalse($vRefused[$vIndex], 'Période incomplète acceptée : '.$vMissing);
                } catch (\LogicException $vError) {
                    self::assertSame('Période non validée.', $vError->getMessage());
                }
            }
        }
    }

    public function testAssessmentInputBuildsTheAssessment(): void
    {
        $vCohort = (new ContractFactory())->aApprenticeship->getCohort();
        $vData = new PlannedAssessmentData();
        $vData->title = 'Épreuve blanche';
        $vData->place = EvaluationContext::School;
        $vData->date = new \DateTimeImmutable('2027-01-18');

        $vAssessment = $vData->toAssessment($vCohort);

        self::assertSame('Épreuve blanche', $vAssessment->getTitle());
        self::assertSame(EvaluationContext::School, $vAssessment->getPlace());
        self::assertSame('2027-01-18', $vAssessment->getDate()->format('Y-m-d'));
        self::assertSame([$vAssessment], $vCohort->getAssessments());
    }

    public function testIncompleteAssessmentIsRefused(): void
    {
        foreach (['title', 'place', 'date'] as $vMissing) {
            $vData = new PlannedAssessmentData();
            $vData->title = 'Bilan';
            $vData->place = EvaluationContext::Company;
            $vData->date = new \DateTimeImmutable('2027-01-18');
            $vData->{$vMissing} = null;
            try {
                $vData->toAssessment((new ContractFactory())->aApprenticeship->getCohort());
                self::fail('Évaluation prévue incomplète acceptée : '.$vMissing);
            } catch (\LogicException $vError) {
                self::assertSame('Évaluation prévue non validée.', $vError->getMessage());
            }
        }
    }

    public function testMessageInputBuildsTheMessage(): void
    {
        $vData = new ContractFactory();
        $vInput = new MessageData();
        $vInput->body = 'Maquette envoyée.';
        $vNow = new \DateTimeImmutable('2026-10-06 09:30');

        $vMessage = $vInput->toMessage($vData->aApprenticeship, $vData->aModel, $vData->aApprentice, $vNow);

        self::assertSame('Maquette envoyée.', $vMessage->getBody());
        self::assertSame($vData->aApprentice, $vMessage->getAuthor());
        self::assertSame($vData->aModel, $vMessage->getCompetence());
        self::assertSame($vNow, $vMessage->getPostedAt());

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Message non validé.');
        (new MessageData())->toMessage($vData->aApprenticeship, $vData->aModel, $vData->aApprentice, $vNow);
    }
}
