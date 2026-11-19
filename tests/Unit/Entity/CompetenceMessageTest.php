<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Competence;
use App\Entity\CompetenceBlock;
use App\Entity\CompetenceMessage;
use App\Entity\Formation;
use App\Entity\User;
use App\Enum\UserRole;
use App\Tests\Support\ContractFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(CompetenceMessage::class)]
final class CompetenceMessageTest extends TestCase
{
    public function testMessageIsTrimmedAndKeepsItsContext(): void
    {
        $vData = new ContractFactory();
        $vPostedAt = new \DateTimeImmutable('2026-10-06 09:30');

        $vMessage = new CompetenceMessage($vData->aApprenticeship, $vData->aModel, $vData->aTutor, "  Bonne maquette.\nReste le responsive.  ", $vPostedAt);

        self::assertNull($vMessage->getId());
        self::assertSame($vData->aApprenticeship, $vMessage->getApprenticeship());
        self::assertSame($vData->aModel, $vMessage->getCompetence());
        self::assertSame($vData->aTutor, $vMessage->getAuthor());
        self::assertSame("Bonne maquette.\nReste le responsive.", $vMessage->getBody());
        self::assertSame($vPostedAt, $vMessage->getPostedAt());
    }

    public function testApprenticeWritesToo(): void
    {
        $vData = new ContractFactory();

        $vMessage = new CompetenceMessage($vData->aApprenticeship, $vData->aDatabase, $vData->aApprentice, str_repeat('é', CompetenceMessage::MAX_LENGTH), new \DateTimeImmutable());

        self::assertSame(CompetenceMessage::MAX_LENGTH, mb_strlen($vMessage->getBody()));
    }

    /** @return iterable<string, array{string, string}> */
    public static function refusals(): iterable
    {
        yield 'école responsable' => ['école', 'Seuls le tuteur et l\'apprenti du contrat échangent sur ses compétences.'];
        yield 'autre tuteur' => ['autre tuteur', 'Seuls le tuteur et l\'apprenti du contrat échangent sur ses compétences.'];
        yield 'autre formation' => ['autre compétence', 'Cette compétence n\'appartient pas à la formation de l\'apprenti.'];
        yield 'message vide' => ['vide', 'Un message compte de 1 à 2000 caractères.'];
        yield 'message trop long' => ['long', 'Un message compte de 1 à 2000 caractères.'];
    }

    #[DataProvider('refusals')]
    public function testMessageIsRefused(string $pCase, string $pError): void
    {
        $vData = new ContractFactory();
        $vOther = new Formation('Réseaux', 'BTS', $vData->aManager);
        $vAuthor = match ($pCase) {
            'école' => $vData->aManager,
            'autre tuteur' => new User('autre.tuteur@cursus.test', UserRole::Tutor, 'Sophie', 'Garnier'),
            default => $vData->aTutor,
        };
        $vCompetence = 'autre compétence' === $pCase ? new Competence(new CompetenceBlock($vOther, 'B1', 'Réseau'), 'C1.1', 'Câbler') : $vData->aModel;
        $vBody = match ($pCase) {
            'vide' => "  \n ",
            'long' => str_repeat('a', CompetenceMessage::MAX_LENGTH + 1),
            default => 'Bonjour',
        };

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($pError);

        new CompetenceMessage($vData->aApprenticeship, $vCompetence, $vAuthor, $vBody, new \DateTimeImmutable());
    }
}
