<?php

namespace App\Tests\Functional\Account;

use App\Account\PasswordReset;
use App\Controller\PasswordResetController;
use App\DataFixtures\UserFixtures;
use App\Entity\PasswordResetToken;
use App\Form\NewPasswordType;
use App\Form\PasswordResetRequestType;
use App\Repository\PasswordResetTokenRepository;
use App\Tests\Support\AppWebTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Mime\Email;

#[CoversClass(PasswordResetController::class)]
#[CoversClass(PasswordReset::class)]
#[CoversClass(PasswordResetTokenRepository::class)]
#[CoversClass(PasswordResetRequestType::class)]
#[CoversClass(NewPasswordType::class)]
final class PasswordResetTest extends AppWebTestCase
{
    private const string NEW_PASSWORD = 'nouveau-mot-de-passe-solide';

    public function testLoginPageLinksToTheForm(): void
    {
        $vCrawler = $this->aClient->request('GET', '/connexion');

        $this->aClient->click($vCrawler->selectLink('Mot de passe oublié ?')->link());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Mot de passe oublié');
    }

    public function testKnownAddressReceivesALinkThatWorksOnce(): void
    {
        $this->requestLink(' Lucas.Bernard@cursus.test ');

        self::assertResponseRedirects('/connexion');
        self::assertEmailCount(1);
        $vEmail = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $vEmail);
        self::assertSame(UserFixtures::APPRENTICE, $vEmail->getTo()[0]->getAddress());
        self::assertSame('no-reply@cursus.test', $vEmail->getFrom()[0]->getAddress());
        self::assertEmailTextBodyContains($vEmail, 'valable 30 minutes');
        $this->aClient->followRedirect();
        self::assertSelectorTextContains('.flash-info', PasswordResetController::SENT_MESSAGE);

        $vLink = $this->linkFrom($vEmail);
        $this->aClient->request('GET', $vLink);
        self::assertResponseIsSuccessful();
        $this->aClient->submitForm('Enregistrer le mot de passe', [
            'new_password[password][first]' => self::NEW_PASSWORD,
            'new_password[password][second]' => self::NEW_PASSWORD,
        ]);
        self::assertResponseRedirects('/connexion');
        $this->aClient->followRedirect();
        self::assertSelectorTextContains('.flash-success', 'Mot de passe changé.');

        $this->aClient->submitForm('Se connecter', ['email' => UserFixtures::APPRENTICE, 'password' => self::NEW_PASSWORD]);
        self::assertResponseRedirects('/');

        $this->aClient->request('GET', $vLink);
        self::assertResponseRedirects('/mot-de-passe-oublie');
        $this->aClient->followRedirect();
        self::assertSelectorTextContains('.flash-error', 'n\'est plus valable');
    }

    public function testUnknownAddressGetsTheSameAnswerAndNoEmail(): void
    {
        $this->requestLink('inconnu@cursus.test');

        self::assertResponseRedirects('/connexion');
        self::assertEmailCount(0);
        $this->aClient->followRedirect();
        self::assertSelectorTextContains('.flash-info', PasswordResetController::SENT_MESSAGE);
    }

    public function testNewRequestReplacesThePreviousLink(): void
    {
        $this->requestLink(UserFixtures::APPRENTICE);
        $vFirst = $this->linkFrom(self::getMailerMessage());
        $this->requestLink(UserFixtures::APPRENTICE);

        $this->aClient->request('GET', $vFirst);

        self::assertResponseRedirects('/mot-de-passe-oublie');
    }

    public function testInvalidAddressIsRefused(): void
    {
        $this->requestLink('pas-une-adresse');

        self::assertResponseStatusCodeSame(422);
        self::assertEmailCount(0);
    }

    public function testRequestsAreLimited(): void
    {
        // Le cache des limites est en mémoire : garder le même noyau pour qu'elles s'accumulent
        $this->aClient->disableReboot();
        for ($vAttempt = 0; $vAttempt < 3; ++$vAttempt) {
            $this->requestLink('inconnu@cursus.test');
            self::assertResponseRedirects('/connexion');
        }

        $this->requestLink('inconnu@cursus.test');

        self::assertResponseStatusCodeSame(429);
        self::assertSelectorTextContains('.flash-error', 'Trop de demandes');
    }

    /** @return iterable<string, array{string, string}> */
    public static function refusedPasswords(): iterable
    {
        yield 'trop court' => ['court-11car', 'court-11car'];
        yield 'confirmation différente' => [self::NEW_PASSWORD, self::NEW_PASSWORD.'!'];
        yield 'vide' => ['', ''];
    }

    #[DataProvider('refusedPasswords')]
    public function testWeakOrMismatchedPasswordIsRefused(string $pFirst, string $pSecond): void
    {
        $this->requestLink(UserFixtures::APPRENTICE);
        $this->aClient->request('GET', $this->linkFrom(self::getMailerMessage()));

        $this->aClient->submitForm('Enregistrer le mot de passe', [
            'new_password[password][first]' => $pFirst,
            'new_password[password][second]' => $pSecond,
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    public function testExpiredOrUnknownLinkIsRefused(): void
    {
        $vToken = str_repeat('a', 64);
        $this->entityManager()->persist(new PasswordResetToken($this->user(UserFixtures::APPRENTICE), $vToken, new \DateTimeImmutable('-31 minutes')));
        $this->entityManager()->flush();

        $this->aClient->request('GET', '/mot-de-passe-oublie/'.$vToken);
        self::assertResponseRedirects('/mot-de-passe-oublie');

        $this->aClient->request('GET', '/mot-de-passe-oublie/'.str_repeat('b', 64));
        self::assertResponseRedirects('/mot-de-passe-oublie');
    }

    public function testChangingThePasswordClosesOtherSessions(): void
    {
        $vUser = $this->loginAs(UserFixtures::APPRENTICE);
        $this->aClient->request('GET', '/apprenti');
        self::assertResponseIsSuccessful();

        $vToken = str_repeat('c', 64);
        $this->entityManager()->persist(new PasswordResetToken($this->user(UserFixtures::APPRENTICE), $vToken, new \DateTimeImmutable()));
        $this->entityManager()->flush();
        $vReset = static::getContainer()->get(PasswordReset::class);
        self::assertTrue($vReset->reset($vToken, self::NEW_PASSWORD));
        self::assertFalse($vReset->reset($vToken, self::NEW_PASSWORD));

        $this->aClient->request('GET', '/apprenti');

        self::assertResponseRedirects('/connexion');
        self::assertNotSame($vUser->getPassword(), $this->refreshed($vUser)->getPassword());
    }

    private function requestLink(string $pEmail): void
    {
        $this->aClient->request('GET', '/mot-de-passe-oublie');
        $this->aClient->submitForm('Recevoir le lien', ['password_reset_request[email]' => $pEmail]);
    }

    private function linkFrom(?object $pEmail): string
    {
        self::assertInstanceOf(Email::class, $pEmail);
        $vMatches = [];
        self::assertSame(1, preg_match('#https?://[^/\s]+(/mot-de-passe-oublie/[0-9a-f]{64})#', (string) $pEmail->getTextBody(), $vMatches));

        return $vMatches[1];
    }
}
