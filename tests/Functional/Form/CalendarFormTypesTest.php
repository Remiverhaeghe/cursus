<?php

namespace App\Tests\Functional\Form;

use App\Enum\EvaluationContext;
use App\Form\CalendarPeriodType;
use App\Form\Data\CalendarPeriodData;
use App\Form\Data\MessageData;
use App\Form\Data\PlannedAssessmentData;
use App\Form\MessageType;
use App\Form\PlannedAssessmentType;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;

/** Formulaires du calendrier et des échanges : libellés, lieux proposés, dates reçues en objets immuables. */
#[CoversClass(CalendarPeriodType::class)]
#[CoversClass(PlannedAssessmentType::class)]
#[CoversClass(MessageType::class)]
#[CoversClass(CalendarPeriodData::class)]
#[CoversClass(PlannedAssessmentData::class)]
#[CoversClass(MessageData::class)]
final class CalendarFormTypesTest extends KernelTestCase
{
    public function testPeriodForm(): void
    {
        $vForm = $this->form(CalendarPeriodType::class);

        self::assertSame(CalendarPeriodData::class, $vForm->getConfig()->getDataClass());
        self::assertSame(['place' => 'Lieu', 'startDate' => 'Premier jour', 'endDate' => 'Dernier jour'], self::labels($vForm));
        self::assertSame(['École', 'Entreprise'], self::placeLabels($vForm));

        $vForm->submit(['place' => 'company', 'startDate' => '2026-09-14', 'endDate' => '2026-09-27']);

        self::assertTrue($vForm->isValid());
        $vData = $vForm->getData();
        self::assertInstanceOf(CalendarPeriodData::class, $vData);
        self::assertSame(EvaluationContext::Company, $vData->place);
        self::assertInstanceOf(\DateTimeImmutable::class, $vData->startDate);
        self::assertSame('2026-09-14', $vData->startDate->format('Y-m-d'));
        self::assertInstanceOf(\DateTimeImmutable::class, $vData->endDate);
    }

    public function testPeriodMayLastOneDayButNotEndBeforeItStarts(): void
    {
        $vOneDay = $this->form(CalendarPeriodType::class);
        $vOneDay->submit(['place' => 'school', 'startDate' => '2026-09-14', 'endDate' => '2026-09-14']);
        self::assertTrue($vOneDay->isValid());

        $vBackwards = $this->form(CalendarPeriodType::class);
        $vBackwards->submit(['place' => 'school', 'startDate' => '2026-09-14', 'endDate' => '2026-09-13']);
        self::assertFalse($vBackwards->isValid());
        self::assertSame('La période doit finir après son début.', $vBackwards->get('endDate')->getErrors()->current()?->getMessage());
    }

    public function testAssessmentForm(): void
    {
        $vForm = $this->form(PlannedAssessmentType::class);

        self::assertSame(PlannedAssessmentData::class, $vForm->getConfig()->getDataClass());
        self::assertSame(['title' => 'Intitulé', 'place' => 'Lieu', 'date' => 'Date'], self::labels($vForm));
        self::assertSame(['École', 'Entreprise'], self::placeLabels($vForm));

        $vForm->submit(['title' => 'Épreuve blanche', 'place' => 'school', 'date' => '2027-01-18']);

        self::assertTrue($vForm->isValid());
        $vData = $vForm->getData();
        self::assertInstanceOf(PlannedAssessmentData::class, $vData);
        self::assertInstanceOf(\DateTimeImmutable::class, $vData->date);
        self::assertSame('2027-01-18', $vData->date->format('Y-m-d'));

        $vLong = $this->form(PlannedAssessmentType::class);
        $vLong->submit(['title' => str_repeat('a', 151), 'place' => 'school', 'date' => '2027-01-18']);
        self::assertFalse($vLong->isValid());
    }

    public function testMessageForm(): void
    {
        $vForm = $this->form(MessageType::class);

        self::assertSame(MessageData::class, $vForm->getConfig()->getDataClass());
        self::assertSame(['body' => 'Message'], self::labels($vForm));

        $vForm->submit(['body' => '  ']);
        self::assertFalse($vForm->isValid());

        $vLong = $this->form(MessageType::class);
        $vLong->submit(['body' => ' '.str_repeat('a', 2000).' ']);
        self::assertTrue($vLong->isValid());

        $vTooLong = $this->form(MessageType::class);
        $vTooLong->submit(['body' => str_repeat('a', 2001)]);
        self::assertFalse($vTooLong->isValid());
    }

    /** @param class-string $pType */
    private function form(string $pType): FormInterface
    {
        $rForm = static::getContainer()->get(FormFactoryInterface::class)->create($pType, null, ['csrf_protection' => false]);

        return $rForm;
    }

    /** @return array<string, string> */
    private static function labels(FormInterface $pForm): array
    {
        $rLabels = [];
        foreach ($pForm as $vName => $vChild) {
            $rLabels[(string) $vName] = (string) $vChild->getConfig()->getOption('label');
        }

        return $rLabels;
    }

    /** @return list<string> */
    private static function placeLabels(FormInterface $pForm): array
    {
        self::assertTrue($pForm->get('place')->getConfig()->getOption('expanded'));
        $rLabels = array_values(array_map(static fn ($pChoice): string => $pChoice->label, $pForm->get('place')->createView()->vars['choices']));

        return $rLabels;
    }
}
