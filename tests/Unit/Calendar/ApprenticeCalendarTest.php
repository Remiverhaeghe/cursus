<?php

namespace App\Tests\Unit\Calendar;

use App\Calendar\ApprenticeCalendar;
use App\Entity\CalendarPeriod;
use App\Entity\PlannedAssessment;
use App\Enum\EvaluationContext;
use App\Tests\Support\ContractFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/** Contrat fictif du 01/09/2026 au 31/08/2027. */
#[CoversClass(ApprenticeCalendar::class)]
final class ApprenticeCalendarTest extends TestCase
{
    private ContractFactory $aData;

    /** @var array<string, CalendarPeriod> */
    private array $aPeriods;

    /** @var array<string, PlannedAssessment> */
    private array $aAssessments;

    protected function setUp(): void
    {
        $this->aData = new ContractFactory();
        $vCohort = $this->aData->aApprenticeship->getCohort();
        $vSchool = EvaluationContext::School;
        $vCompany = EvaluationContext::Company;
        $this->aPeriods = [
            'avant' => new CalendarPeriod($vCohort, $vCompany, new \DateTimeImmutable('2026-08-17'), new \DateTimeImmutable('2026-08-31')),
            'rentrée' => new CalendarPeriod($vCohort, $vSchool, new \DateTimeImmutable('2026-08-31'), new \DateTimeImmutable('2026-09-06')),
            'entreprise' => new CalendarPeriod($vCohort, $vCompany, new \DateTimeImmutable('2026-09-07'), new \DateTimeImmutable('2026-09-20')),
            'école' => new CalendarPeriod($vCohort, $vSchool, new \DateTimeImmutable('2026-09-28'), new \DateTimeImmutable('2026-10-04')),
            'dernière' => new CalendarPeriod($vCohort, $vCompany, new \DateTimeImmutable('2027-08-31'), new \DateTimeImmutable('2027-09-12')),
            'après' => new CalendarPeriod($vCohort, $vSchool, new \DateTimeImmutable('2027-09-13'), new \DateTimeImmutable('2027-09-19')),
        ];
        $this->aAssessments = [
            'passée' => new PlannedAssessment($vCohort, 'Positionnement', $vSchool, new \DateTimeImmutable('2026-09-14')),
            'aujourd’hui' => new PlannedAssessment($vCohort, 'Point d\'étape', $vCompany, new \DateTimeImmutable('2026-09-15')),
            'fin du contrat' => new PlannedAssessment($vCohort, 'Soutenance', $vSchool, new \DateTimeImmutable('2027-08-31')),
            'après le contrat' => new PlannedAssessment($vCohort, 'Rattrapage', $vSchool, new \DateTimeImmutable('2027-09-01')),
        ];
    }

    public function testCalendarKeepsTheContractPeriodsAndTheComingAssessments(): void
    {
        $vCalendar = ApprenticeCalendar::build($this->aData->aApprenticeship, new \DateTimeImmutable('2026-09-15 16:45'));

        self::assertSame($this->periods('rentrée', 'entreprise', 'école', 'dernière'), $vCalendar->periods);
        self::assertSame($this->aPeriods['entreprise'], $vCalendar->current);
        self::assertSame($this->aPeriods['école'], $vCalendar->next);
        self::assertSame([$this->aAssessments['aujourd’hui'], $this->aAssessments['fin du contrat']], $vCalendar->upcoming);
    }

    public function testBetweenTwoPeriodsThereIsNoCurrentOne(): void
    {
        $vCalendar = ApprenticeCalendar::build($this->aData->aApprenticeship, new \DateTimeImmutable('2026-09-21'));

        self::assertNull($vCalendar->current);
        self::assertSame($this->aPeriods['école'], $vCalendar->next);
    }

    public function testOnTheFirstDayThePeriodIsCurrentAndTheNextOneFollows(): void
    {
        $vCalendar = ApprenticeCalendar::build($this->aData->aApprenticeship, new \DateTimeImmutable('2026-09-28'));

        self::assertSame($this->aPeriods['école'], $vCalendar->current);
        self::assertSame($this->aPeriods['dernière'], $vCalendar->next);
    }

    public function testAfterTheLastPeriodNothingIsAhead(): void
    {
        $vCalendar = ApprenticeCalendar::build($this->aData->aApprenticeship, new \DateTimeImmutable('2027-09-01'));

        self::assertSame($this->aPeriods['dernière'], $vCalendar->current);
        self::assertNull($vCalendar->next);
        self::assertSame([], $vCalendar->upcoming);
    }

    /** @return list<CalendarPeriod> */
    private function periods(string ...$pNames): array
    {
        $rPeriods = array_values(array_map(fn (string $pName): CalendarPeriod => $this->aPeriods[$pName], $pNames));

        return $rPeriods;
    }
}
