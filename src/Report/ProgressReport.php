<?php

namespace App\Report;

use App\Progress\ApprenticeProgress;
use App\Progress\CompetenceProgress;

/** Bilan d'un contrat en PDF : progression globale, puis bloc par bloc, et ce qui reste à valider. */
final class ProgressReport
{
    public function render(ApprenticeProgress $pProgress, \DateTimeImmutable $pToday): string
    {
        $vContract = $pProgress->aApprenticeship;
        $vName = $vContract->getApprentice()->getFullName();
        $vPdf = new SimplePdf('Bilan de compétences : '.$vName);

        $vPdf->text('Bilan de compétences', 18, true);
        $vPdf->text($vName, 14, true);
        $vPdf->text($vContract->getFormation()->getName().' · '.$vContract->getCohort()->getName());
        $vPdf->text('Tuteur : '.$vContract->getTutor()->getFullName().' · '.$vContract->getCompany()->getName());
        $vPdf->text(\sprintf('Contrat du %s au %s', $vContract->getStartDate()->format('d/m/Y'), $vContract->getEndDate()->format('d/m/Y')));
        $vPdf->text('Bilan établi le '.$pToday->format('d/m/Y'));
        $vPdf->space(10);

        $vTotal = \count($pProgress->competences());
        $vPdf->text(\sprintf('Progression globale : %d %% (moyenne %s sur 4)', $pProgress->percentage(), $this->decimal($pProgress->average())), 12, true);
        $vPdf->bar($pProgress->percentage() / 100, 300);
        $vPdf->text(\sprintf(
            '%d compétence(s) validée(s) sur %d, %d pas encore évaluée(s).',
            $pProgress->validatedCount(),
            $vTotal,
            $pProgress->unevaluatedCount(),
        ));

        foreach ($pProgress->aBlocks as $vBlock) {
            $vPdf->space(12);
            $vPdf->text($vBlock->aBlock->label().' : '.$vBlock->percentage().' %', 12, true);
            $vPdf->bar($vBlock->percentage() / 100, 200);
            foreach ($vBlock->aCompetences as $vCompetence) {
                $vPdf->text($this->competenceLine($vCompetence), 10, false, 10);
            }
        }

        $vRemaining = $pProgress->remaining();
        $vPdf->space(12);
        $vPdf->text('Reste à valider', 12, true);
        if ([] === $vRemaining) {
            $vPdf->text('Toutes les compétences sont validées.');
        }
        foreach ($vRemaining as $vCompetence) {
            $vPdf->text('- '.$vCompetence->aCompetence->getCode().' '.$vCompetence->aCompetence->getLabel(), 10, false, 10);
        }

        return $vPdf->render();
    }

    private function competenceLine(CompetenceProgress $pProgress): string
    {
        $vCompetence = $pProgress->aCompetence;
        $vLevel = $pProgress->current()?->label() ?? 'Pas encore évaluée';
        $vDate = null === $pProgress->aLastEvaluatedAt ? '' : ' (le '.$pProgress->aLastEvaluatedAt->format('d/m/Y').')';

        return \sprintf('%s %s : %s%s', $vCompetence->getCode(), $vCompetence->getLabel(), $vLevel, $vDate);
    }

    private function decimal(float $pValue): string
    {
        return number_format($pValue, 2, ',', '');
    }
}
