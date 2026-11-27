<?php

namespace App\Tests\Functional\School;

use App\Audit\AuditLog;
use App\Controller\School\AuditController;
use App\DataFixtures\UserFixtures;
use App\Entity\AuditEntry;
use App\Tests\Support\AppWebTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/** Journal des actions : un test par profil. */
#[CoversClass(AuditController::class)]
final class AuditControllerTest extends AppWebTestCase
{
    public function testSchoolReadsTheJournalNewestFirst(): void
    {
        $vSchool = $this->loginAs(UserFixtures::SCHOOL);
        $vAudit = static::getContainer()->get(AuditLog::class);
        $vAudit->record(AuditLog::EVALUATION, $vSchool, 'Première action');
        $vAudit->record(AuditLog::PASSWORD_RESET, $vSchool, 'Seconde action');
        $this->entityManager()->flush();

        $vCrawler = $this->aClient->request('GET', '/ecole/journal');

        self::assertResponseStatusCodeSame(200);
        $vRows = $vCrawler->filter('tbody tr');
        self::assertGreaterThanOrEqual(2, $vRows->count());
        $vTexts = $vRows->each(static fn ($pRow): string => $pRow->text());
        $vFirst = array_search(true, array_map(static fn (string $pText): bool => str_contains($pText, 'Première action'), $vTexts), true);
        $vSecond = array_search(true, array_map(static fn (string $pText): bool => str_contains($pText, 'Seconde action'), $vTexts), true);
        self::assertGreaterThan($vSecond, $vFirst);
        self::assertStringContainsString('Évaluation', $vTexts[$vFirst]);
        self::assertStringContainsString('Claire Fontaine', $vTexts[$vFirst]);
    }

    public function testTutorAndApprenticeAreRefused(): void
    {
        foreach ([UserFixtures::TUTOR, UserFixtures::APPRENTICE] as $vEmail) {
            $this->loginAs($vEmail);
            $this->aClient->request('GET', '/ecole/journal');
            self::assertResponseStatusCodeSame(403, $vEmail);
        }
    }

    public function testAnonymousVisitorIsSentToLogin(): void
    {
        $this->aClient->request('GET', '/ecole/journal');

        self::assertResponseRedirects('/connexion');
    }

    public function testMenuOffersTheJournalToTheSchoolOnly(): void
    {
        $this->loginAs(UserFixtures::SCHOOL);
        $vSchool = $this->aClient->request('GET', '/ecole');
        self::assertCount(1, $vSchool->filter('nav.menu a[href="/ecole/journal"]'));

        $this->loginAs(UserFixtures::TUTOR);
        $vTutor = $this->aClient->request('GET', '/tuteur');
        self::assertCount(0, $vTutor->filter('nav.menu a[href="/ecole/journal"]'));
    }

    public function testALoginLeavesALine(): void
    {
        $this->aClient->request('GET', '/connexion');
        $this->aClient->submitForm('Se connecter', ['email' => UserFixtures::APPRENTICE, 'password' => UserFixtures::PASSWORD]);

        $vEntries = $this->entityManager()->getRepository(AuditEntry::class)->findBy(['aAction' => AuditLog::LOGIN]);
        self::assertNotSame([], $vEntries);
        self::assertSame(UserFixtures::APPRENTICE, $vEntries[array_key_last($vEntries)]->getActor()?->getEmail());
    }
}
