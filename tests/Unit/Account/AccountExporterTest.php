<?php

namespace App\Tests\Unit\Account;

use App\Account\AccountExporter;
use App\Entity\Evaluation;
use App\Enum\CompetenceLevel;
use App\Enum\EvaluationContext;
use App\Repository\ApprenticeshipRepository;
use App\Repository\EvaluationRepository;
use App\Tests\Support\ContractFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/** Contenu exact de l'export : chaque clé et chaque valeur, rien de plus. */
#[CoversClass(AccountExporter::class)]
final class AccountExporterTest extends TestCase
{
    public function testExportListsProfileContractsAndEvaluations(): void
    {
        $vData = new ContractFactory();
        $vEvaluation = new Evaluation($vData->aApprenticeship, $vData->aModel, $vData->aTutor, CompetenceLevel::Acquired, EvaluationContext::Company, new \DateTimeImmutable('2026-10-05 14:00:00+00:00'), 'Très bien');
        $vApprenticeships = $this->createStub(ApprenticeshipRepository::class);
        $vApprenticeships->method('findInvolving')->willReturn([$vData->aApprenticeship]);
        $vEvaluations = $this->createStub(EvaluationRepository::class);
        $vEvaluations->method('findInvolving')->willReturn([$vEvaluation]);

        $vExport = (new AccountExporter($vApprenticeships, $vEvaluations))->export($vData->aApprentice, new \DateTimeImmutable('2026-11-15 10:00:00+00:00'));

        self::assertSame([
            'exported_at' => '2026-11-15T10:00:00+00:00',
            'account' => [
                'email' => 'apprenti@cursus.test',
                'first_name' => 'Lucas',
                'last_name' => 'Blanc',
                'role' => 'Apprenti',
                'two_factor_enabled' => false,
            ],
            'apprenticeships' => [[
                'formation' => 'Développeur web',
                'cohort' => 'Promotion 2026',
                'apprentice' => 'Lucas Blanc',
                'tutor' => 'Marc Petit',
                'company' => 'Atelier Lumen (Lille)',
                'start_date' => '2026-09-01',
                'end_date' => '2027-08-31',
            ]],
            'evaluations' => [[
                'date' => '2026-10-05T14:00:00+00:00',
                'apprentice' => 'Lucas Blanc',
                'competence' => 'C1.1 · Maquetter',
                'level' => 3,
                'context' => 'Entreprise',
                'evaluator' => 'Marc Petit',
                'comment' => 'Très bien',
            ]],
        ], $vExport);
    }
}
