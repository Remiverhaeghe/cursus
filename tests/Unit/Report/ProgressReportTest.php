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

    public function testContentStreamIsExactForTheStandardContract(): void
    {
        $vFile = $this->render([
            $this->evaluation($this->aData->aModel, CompetenceLevel::Mastered, '2026-03-01'),
            $this->evaluation($this->aData->aInterface, CompetenceLevel::InProgress, '2026-02-10'),
        ]);

        preg_match('/stream\n(.*)\nendstream/s', $vFile, $vMatch);

        self::assertSame(
            $this->line(2, 18, 50, '766.8', 'Bilan de compétences')
            .$this->line(2, 14, 50, '747.2', 'Lucas Blanc')
            .$this->line(1, 10, 50, '733.2', 'Développeur web · Promotion 2026')
            .$this->line(1, 10, 50, '719.2', 'Tuteur : Marc Petit · Atelier Lumen')
            .$this->line(1, 10, 50, '705.2', 'Contrat du 01/09/2026 au 31/08/2027')
            .$this->line(1, 10, 50, '691.2', 'Bilan établi le 23/11/2026')
            .$this->line(2, 12, 50, '664.4', 'Progression globale : 50 % (moyenne 2,00 sur 4)')
            ."0.85 0.85 0.85 rg 50 652.4 300 8 re f\n0.18 0.45 0.30 rg 50 652.4 150 8 re f\n"
            .$this->line(1, 10, 50, '638.4', '1 compétence(s) validée(s) sur 3, 1 pas encore évaluée(s).')
            .$this->line(2, 12, 50, '609.6', 'B1 · Front-end : 75 %')
            ."0.85 0.85 0.85 rg 50 597.6 200 8 re f\n0.18 0.45 0.30 rg 50 597.6 150 8 re f\n"
            .$this->line(1, 10, 60, '583.6', 'C1.1 Maquetter : Maîtrisée (le 01/03/2026)')
            .$this->line(1, 10, 60, '569.6', 'C1.2 Intégrer : En cours d\'acquisition (le 10/02/2026)')
            .$this->line(2, 12, 50, '540.8', 'B2 · Back-end : 0 %')
            ."0.85 0.85 0.85 rg 50 528.8 200 8 re f\n"
            .$this->line(1, 10, 60, '514.8', 'C2.1 Base de données : Pas encore évaluée')
            .$this->line(2, 12, 50, '486', 'Reste à valider')
            .$this->line(1, 10, 60, '472', '- C1.2 Intégrer')
            .$this->line(1, 10, 60, '458', '- C2.1 Base de données'),
            $vMatch[1],
        );
    }

    private function line(int $pFont, int $pSize, int $pX, string $pY, string $pText): string
    {
        return \sprintf("BT /F%d %d Tf %d %s Td (%s) Tj ET\n", $pFont, $pSize, $pX, $pY, $this->win($pText));
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

    /** Le PDF est écrit en Windows-1252 : un texte accentué s'y cherche dans cet encodage ; texte tel qu'il figure dans le flux PDF : CP1252, parenthèses protégées par une barre oblique. */
    private function win(string $pText): string
    {
        return str_replace(['(', ')'], ['\\(', '\\)'], (string) iconv('UTF-8', 'CP1252', $pText));
    }
}
