<?php

namespace App\Calendar;

use App\Entity\Apprenticeship;
use App\Entity\CalendarPeriod;
use App\Entity\PlannedAssessment;

/** Calendrier d'un apprenti : les périodes de sa promotion pendant son contrat, où il est aujourd'hui, et les évaluations à venir. */
final readonly class ApprenticeCalendar
{
    /**
     * @param list<CalendarPeriod>    $periods
     * @param list<PlannedAssessment> $upcoming
     */
    public function __construct(
        public array $periods,
        public ?CalendarPeriod $current,
        public ?CalendarPeriod $next,
        public array $upcoming,
    ) {
    }

    public static function build(Apprenticeship $pApprenticeship, \DateTimeImmutable $pToday): self
    {
        $vToday = $pToday->setTime(0, 0);
        $vCohort = $pApprenticeship->getCohort();
        $vPeriods = array_values(array_filter(
            $vCohort->getPeriods(),
            static fn (CalendarPeriod $pPeriod): bool => $pPeriod->overlaps($pApprenticeship->getStartDate(), $pApprenticeship->getEndDate()),
        ));
        $vCurrent = null;
        $vNext = null;
        foreach ($vPeriods as $vPeriod) {
            if ($vPeriod->overlaps($vToday, $vToday)) {
                $vCurrent = $vPeriod;
            } elseif (null === $vNext && $vPeriod->getStartDate() > $vToday) {
                $vNext = $vPeriod;
            }
        }
        // Une évaluation après la fin du contrat ne concerne plus l'apprenti
        $vUpcoming = array_values(array_filter(
            $vCohort->getAssessments(),
            static fn (PlannedAssessment $pAssessment): bool => $pAssessment->getDate() >= $vToday && $pAssessment->getDate() <= $pApprenticeship->getEndDate(),
        ));

        $rCalendar = new self($vPeriods, $vCurrent, $vNext, $vUpcoming);

        return $rCalendar;
    }
}
