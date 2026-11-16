<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Competence;
use App\Entity\CompetenceBlock;
use App\Entity\Evaluation;
use App\Entity\Formation;
use App\Entity\User;
use App\Enum\CompetenceLevel;
use App\Enum\EvaluationContext;
use App\Enum\UserRole;
use App\Form\Data\EvaluationData;
use App\Tests\Support\ContractFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Règles métier d'une évaluation. */
#[CoversClass(Evaluation::class)]
#[CoversClass(EvaluationData::class)]
final class EvaluationTest extends TestCase
{
    public function testTutorEvaluatesInCompany(): void
    {
        $vData = new ContractFactory();
        $vAt = new \DateTimeImmutable('2026-10-05 14:00');

        $vEvaluation = new Evaluation($vData->aApprenticeship, $vData->aModel, $vData->aTutor, CompetenceLevel::Acquired, EvaluationContext::Company, $vAt, '  Très bien  ');

        self::assertNull($vEvaluation->getId());
        self::assertSame($vData->aApprenticeship, $vEvaluation->getApprenticeship());
        self::assertSame($vData->aModel, $vEvaluation->getCompetence());
        self::assertSame($vData->aTutor, $vEvaluation->getEvaluator());
        self::assertSame(CompetenceLevel::Acquired, $vEvaluation->getLevel());
        self::assertSame(EvaluationContext::Company, $vEvaluation->getContext());
        self::assertSame($vAt, $vEvaluation->getEvaluatedAt());
        self::assertSame('Très bien', $vEvaluation->getComment());
    }

    public function testSchoolEvaluatesAtSchoolAndBlankCommentIsDropped(): void
    {
        $vData = new ContractFactory();

        $vEvaluation = new Evaluation($vData->aApprenticeship, $vData->aDatabase, $vData->aManager, CompetenceLevel::NotStarted, EvaluationContext::School, new \DateTimeImmutable(), '   ');
        $vWithoutComment = new Evaluation($vData->aApprenticeship, $vData->aDatabase, $vData->aManager, CompetenceLevel::Discovery, EvaluationContext::School, new \DateTimeImmutable());

        self::assertSame(EvaluationContext::School, $vEvaluation->getContext());
        self::assertNull($vEvaluation->getComment());
        self::assertNull($vWithoutComment->getComment());
    }

    /** @return iterable<string, array{string, string, EvaluationContext, string}> */
    public static function refusedEvaluations(): iterable
    {
        yield 'compétence d\'une autre formation' => ['étrangère', 'tuteur', EvaluationContext::Company, 'n\'appartient pas'];
        yield 'tuteur en contexte école' => ['C1.1', 'tuteur', EvaluationContext::School, 'dans son contexte'];
        yield 'école en contexte entreprise' => ['C1.1', 'école', EvaluationContext::Company, 'dans son contexte'];
        yield 'apprenti évaluateur' => ['C1.1', 'apprenti', EvaluationContext::School, 'dans son contexte'];
        yield 'autre tuteur' => ['C1.1', 'autre tuteur', EvaluationContext::Company, 'ne suit pas'];
        yield 'autre école' => ['C1.1', 'autre école', EvaluationContext::School, 'ne suit pas'];
    }

    #[DataProvider('refusedEvaluations')]
    public function testEvaluationIsRefused(string $pCompetence, string $pEvaluator, EvaluationContext $pContext, string $pMessage): void
    {
        $vData = new ContractFactory();
        $vForeign = new Competence(new CompetenceBlock(new Formation('Autre', 'BTS', $vData->aManager), 'B1', 'Autre'), 'C1.1', 'Autre');
        $vCompetences = ['C1.1' => $vData->aModel, 'étrangère' => $vForeign];
        $vEvaluators = [
            'tuteur' => $vData->aTutor,
            'école' => $vData->aManager,
            'apprenti' => $vData->aApprentice,
            'autre tuteur' => new User('autre.tuteur@cursus.test', UserRole::Tutor, 'Sophie', 'Garnier'),
            'autre école' => new User('autre.ecole@cursus.test', UserRole::School, 'Paul', 'Roux'),
        ];
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($pMessage);

        new Evaluation($vData->aApprenticeship, $vCompetences[$pCompetence], $vEvaluators[$pEvaluator], CompetenceLevel::Acquired, $pContext, new \DateTimeImmutable());
    }

    public function testInputTakesTheContextFromTheEvaluator(): void
    {
        $vData = new ContractFactory();
        $vInput = new EvaluationData();
        $vInput->competence = $vData->aInterface;
        $vInput->level = CompetenceLevel::InProgress;
        $vInput->comment = 'À consolider';
        $vNow = new \DateTimeImmutable('2026-10-05');

        $vByTutor = $vInput->toEvaluation($vData->aApprenticeship, $vData->aTutor, $vNow);
        $vBySchool = $vInput->toEvaluation($vData->aApprenticeship, $vData->aManager, $vNow);

        self::assertSame(EvaluationContext::Company, $vByTutor->getContext());
        self::assertSame(EvaluationContext::School, $vBySchool->getContext());
        self::assertSame($vData->aInterface, $vByTutor->getCompetence());
        self::assertSame(CompetenceLevel::InProgress, $vByTutor->getLevel());
        self::assertSame('À consolider', $vByTutor->getComment());
        self::assertSame($vNow, $vByTutor->getEvaluatedAt());
    }

    /** @return iterable<string, array{bool, bool, string}> */
    public static function incompleteInputs(): iterable
    {
        yield 'sans compétence' => [false, true, 'tuteur'];
        yield 'sans niveau' => [true, false, 'tuteur'];
        yield 'par l\'apprenti' => [true, true, 'apprenti'];
    }

    #[DataProvider('incompleteInputs')]
    public function testIncompleteInputIsRefused(bool $pWithCompetence, bool $pWithLevel, string $pEvaluator): void
    {
        $vData = new ContractFactory();
        $vInput = new EvaluationData();
        $vInput->competence = $pWithCompetence ? $vData->aModel : null;
        $vInput->level = $pWithLevel ? CompetenceLevel::Acquired : null;
        $this->expectException(\LogicException::class);

        $vInput->toEvaluation($vData->aApprenticeship, 'tuteur' === $pEvaluator ? $vData->aTutor : $vData->aApprentice, new \DateTimeImmutable());
    }
}
