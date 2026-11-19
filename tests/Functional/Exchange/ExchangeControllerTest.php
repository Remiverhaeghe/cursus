<?php

namespace App\Tests\Functional\Exchange;

use App\Controller\ExchangeController;
use App\DataFixtures\UserFixtures;
use App\Entity\Apprenticeship;
use App\Entity\Competence;
use App\Entity\CompetenceMessage;
use App\Entity\Formation;
use App\Form\Data\MessageData;
use App\Form\MessageType;
use App\Repository\CompetenceMessageRepository;
use App\Security\Voter\ExchangeVoter;
use App\Tests\Support\AppWebTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

#[CoversClass(ExchangeController::class)]
#[CoversClass(CompetenceMessageRepository::class)]
#[CoversClass(CompetenceMessage::class)]
#[CoversClass(MessageType::class)]
#[CoversClass(MessageData::class)]
#[CoversClass(ExchangeVoter::class)]
final class ExchangeControllerTest extends AppWebTestCase
{
    use ClockSensitiveTrait;

    private const string SIO = 'BTS Services informatiques aux organisations';

    /** Contrat de Lucas : formation SIO de Claire, tuteur Marc ; quatre messages de démonstration. */
    private function contract(): Apprenticeship
    {
        return $this->find(Apprenticeship::class, ['aApprentice' => $this->user(UserFixtures::APPRENTICE)]);
    }

    private function competence(string $pCode, string $pFormation = self::SIO): Competence
    {
        foreach ($this->find(Formation::class, ['aName' => $pFormation])->getCompetences() as $vCompetence) {
            if ($pCode === $vCompetence->getCode()) {
                return $vCompetence;
            }
        }
        throw new \LogicException('Compétence absente : '.$pCode);
    }

    private function threadUrl(string $pCode = 'C1.1'): string
    {
        return '/suivi/'.$this->contract()->getId().'/echanges/'.$this->competence($pCode)->getId();
    }

    /** @return iterable<string, array{string, int, bool}> */
    public static function accessMatrix(): iterable
    {
        yield 'école responsable' => [UserFixtures::SCHOOL, 200, false];
        yield 'autre école' => [UserFixtures::SCHOOL_OTHER, 403, false];
        yield 'tuteur du contrat' => [UserFixtures::TUTOR, 200, true];
        yield 'autre tuteur' => [UserFixtures::TUTOR_OTHER, 403, false];
        yield 'apprenti concerné' => [UserFixtures::APPRENTICE, 200, true];
        yield 'autre apprenti' => [UserFixtures::APPRENTICE_SAME_TUTOR, 403, false];
    }

    #[DataProvider('accessMatrix')]
    public function testEachProfileReadsAndWritesWhatItIsAllowedTo(string $pEmail, int $pStatus, bool $pCanWrite): void
    {
        $this->loginAs($pEmail);

        $this->aClient->request('GET', '/suivi/'.$this->contract()->getId().'/echanges');
        self::assertResponseStatusCodeSame($pStatus);
        $this->aClient->request('GET', $this->threadUrl());
        self::assertResponseStatusCodeSame($pStatus);
        if (200 === $pStatus) {
            self::assertSelectorTextContains('#messages', 'Faut-il inclure les imprimantes ?');
            $pCanWrite ? self::assertSelectorExists('form[name="message"]') : self::assertSelectorNotExists('form[name="message"]');
        }
    }

    public function testIndexCountsTheMessagesOfEachCompetence(): void
    {
        $this->loginAs(UserFixtures::APPRENTICE);

        $vCrawler = $this->aClient->request('GET', '/suivi/'.$this->contract()->getId().'/echanges');

        $vRows = $vCrawler->filter('#threads tbody tr')->each(static fn ($pRow): array => [$pRow->filter('td')->eq(0)->text(), $pRow->filter('td')->eq(1)->text(), $pRow->filter('td')->eq(2)->text()]);
        self::assertSame(['C1.1 · Gérer le patrimoine informatique', '3', '12/12/2025 17:30'], $vRows[0]);
        self::assertSame('1', $vRows[1][1]);
        self::assertSame('08/01/2026 09:15', $vRows[1][2]);
        self::assertSame(['0', ''], [$vRows[2][1], $vRows[2][2]]);
    }

    public function testThreadShowsTheHistoryOldestFirst(): void
    {
        $this->loginAs(UserFixtures::TUTOR);

        $vCrawler = $this->aClient->request('GET', $this->threadUrl());

        $vAuthors = $vCrawler->filter('#messages li > p:first-child')->each(static fn ($pCell): string => $pCell->text());
        self::assertSame(['Lucas Bernard · 20/11/2025 18:05', 'Marc Lefebvre · 21/11/2025 08:40', 'Lucas Bernard · 12/12/2025 17:30'], $vAuthors);
    }

    public function testApprenticeAndTutorAnswerEachOther(): void
    {
        self::mockTime('2026-03-10 18:20');
        $this->loginAs(UserFixtures::APPRENTICE);
        $this->aClient->request('GET', $this->threadUrl('C1.2'));

        $this->aClient->submitForm('Envoyer', ['message[body]' => "  Ticket 42 résolu.\nJ'ai documenté la procédure.  "]);

        self::assertResponseRedirects($this->threadUrl('C1.2'));
        $this->aClient->followRedirect();
        self::assertSelectorTextContains('.flash-success', 'Message envoyé.');
        self::assertSelectorTextContains('#messages li:last-child', 'Lucas Bernard · 10/03/2026 18:20');
        self::assertSelectorTextContains('#messages li:last-child', 'Ticket 42 résolu.');
        self::assertSame(1, $this->aClient->getCrawler()->filter('#messages li:last-child br')->count());

        $this->loginAs(UserFixtures::TUTOR);
        $this->aClient->request('GET', $this->threadUrl('C1.2'));
        $this->aClient->submitForm('Envoyer', ['message[body]' => 'Bravo, on la partage à l\'équipe.']);

        self::assertResponseRedirects($this->threadUrl('C1.2'));
        $vThread = static::getContainer()->get(CompetenceMessageRepository::class)->findThread($this->contract(), $this->competence('C1.2'));
        self::assertSame(['Marc Lefebvre', 'Lucas Bernard', 'Marc Lefebvre'], array_map(static fn (CompetenceMessage $pMessage): string => $pMessage->getAuthor()->getFullName(), $vThread));
        self::assertSame("Ticket 42 résolu.\nJ'ai documenté la procédure.", $vThread[1]->getBody());
    }

    public function testEmptyMessageIsRefused(): void
    {
        $this->loginAs(UserFixtures::APPRENTICE);
        $this->aClient->request('GET', $this->threadUrl('C2.1'));

        $this->aClient->submitForm('Envoyer', ['message[body]' => '   ']);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('main', 'Aucun message sur cette compétence.');
        self::assertSame([], static::getContainer()->get(CompetenceMessageRepository::class)->findThread($this->contract(), $this->competence('C2.1')));
    }

    public function testSchoolCannotPost(): void
    {
        $this->loginAs(UserFixtures::SCHOOL);

        $this->aClient->request('POST', $this->threadUrl(), ['message' => ['body' => 'Message de l\'école']]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testCompetenceOfAnotherFormationHasNoThread(): void
    {
        $this->loginAs(UserFixtures::APPRENTICE);

        $this->aClient->request('GET', '/suivi/'.$this->contract()->getId().'/echanges/'.$this->competence('C1.1', 'Développeur web')->getId());

        self::assertResponseStatusCodeSame(404);
    }
}
