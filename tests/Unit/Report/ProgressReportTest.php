<?php

namespace App\Tests\Unit\Report;

use App\Entity\Competence;
use App\Entity\Evaluation;
use App\Enum\CompetenceLevel;
use App\Enum\EvaluationContext;
use App\Progress\ProgressCalculator;
use App\Report\ProgressReport;
use App\Report\SimplePdf;
use App\Tests\Support\ContractFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/** Contenu du bilan : identité du contrat, progression, niveau de chaque compétence, ce qui reste à valider. */
#[CoversClass(ProgressReport::class)]
#[CoversClass(SimplePdf::class)]
final class ProgressReportTest extends TestCase
{
    private ContractFactory $aData;

    protected function setUp(): void
    {
        $this->aData = new ContractFactory();
    }

    public function testReportNamesTheContractAndEachBlock(): void
    {
        $vFile = $this->render([]);

        self::assertStringStartsWith('%PDF-1.4', $vFile);
        self::assertStringContainsString('(Lucas Blanc) Tj', $vFile);
        self::assertStringContainsString('Tuteur : Marc Petit', $vFile);
        self::assertStringContainsString($this->win('Bilan établi le 23/11/2026'), $vFile);
        self::assertStringContainsString('Front-end', $vFile);
        self::assertStringContainsString('Back-end', $vFile);
    }

    public function testEveryCompetenceShowsItsLevelAndLastDate(): void
    {
        $vFile = $this->render([
            $this->evaluation($this->aData->aModel, CompetenceLevel::Mastered, '2026-03-01'),
            $this->evaluation($this->aData->aInterface, CompetenceLevel::InProgress, '2026-02-10'),
        ]);

        self::assertStringContainsString($this->win('C1.1 Maquetter : Maîtrisée (le 01/03/2026)'), $vFile);
        self::assertStringContainsString($this->win('C1.2 Intégrer : En cours d\'acquisition (le 10/02/2026)'), $vFile);
        self::assertStringContainsString($this->win('C2.1 Base de données : Pas encore évaluée'), $vFile);
    }

    public function testRemainingListsOnlyWhatIsNotValidated(): void
    {
        $vFile = $this->render([
            $this->evaluation($this->aData->aModel, CompetenceLevel::Acquired, '2026-03-01'),
            $this->evaluation($this->aData->aInterface, CompetenceLevel::Discovery, '2026-03-02'),
        ]);

        $vRemaining = substr($vFile, (int) strrpos($vFile, 'Reste'));
        self::assertStringContainsString('- C1.2 '.$this->win('Intégrer'), $vRemaining);
        self::assertStringContainsString('- C2.1 Base', $vRemaining);
        self::assertStringNotContainsString('- C1.1', $vRemaining);
    }

    public function testNothingRemainingWhenEverythingIsValidated(): void
    {
        $vFile = $this->render([
            $this->evaluation($this->aData->aModel, CompetenceLevel::Acquired, '2026-03-01'),
            $this->evaluation($this->aData->aInterface, CompetenceLevel::Mastered, '2026-03-02'),
            $this->evaluation($this->aData->aDatabase, CompetenceLevel::Acquired, '2026-03-03'),
        ]);

        self::assertStringContainsString($this->win('Toutes les compétences sont validées.'), $vFile);
        self::assertStringContainsString($this->win('3 compétence(s) validée(s) sur 3, 0 pas encore évaluée(s).'), $vFile);
    }

    public function testGlobalProgressUsesTheAverageOnFour(): void
    {
        // Niveaux 4, 2 et non évalué : moyenne 2 sur 4, soit 50 %
        $vFile = $this->render([
            $this->evaluation($this->aData->aModel, CompetenceLevel::Mastered, '2026-03-01'),
            $this->evaluation($this->aData->aInterface, CompetenceLevel::InProgress, '2026-03-02'),
        ]);

        self::assertStringContainsString('Progression globale : 50 %', $vFile);
        self::assertStringContainsString('moyenne 2,00 sur 4', $vFile);
    }

    /** @param list<Evaluation> $pEvaluations */
    private function render(array $pEvaluations): string
    {
        $vProgress = (new ProgressCalculator())->forApprenticeship($this->aData->aApprenticeship, $pEvaluations);

        return (new ProgressReport())->render($vProgress, new \DateTimeImmutable('2026-11-23'));
    }

    private function evaluation(Competence $pCompetence, CompetenceLevel $pLevel, string $pDate): Evaluation
    {
        return new Evaluation($this->aData->aApprenticeship, $pCompetence, $this->aData->aTutor, $pLevel, EvaluationContext::Company, new \DateTimeImmutable($pDate));
    }

    /** Le PDF est écrit en Windows-1252 : un texte accentué s'y cherche dans cet encodage. */
    /** Texte tel qu'il figure dans le flux PDF : CP1252, parenthèses protégées par une barre oblique. */
    private function win(string $pText): string
    {
        return str_replace(['(', ')'], ['\\(', '\\)'], (string) iconv('UTF-8', 'CP1252', $pText));
    }
}
