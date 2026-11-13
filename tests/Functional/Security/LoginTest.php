<?php

namespace App\Tests\Functional\Security;

use App\Controller\HomeController;
use App\Controller\SecurityController;
use App\DataFixtures\UserFixtures;
use App\EventSubscriber\LoginAuditSubscriber;
use App\Tests\Support\AppWebTestCase;
use OTPHP\TOTP;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(SecurityController::class)]
#[CoversClass(HomeController::class)]
#[CoversClass(LoginAuditSubscriber::class)]
final class LoginTest extends AppWebTestCase
{
    public function testLoginPageIsPublic(): void
    {
        $this->aClient->request('GET', '/connexion');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Connexion');
        self::assertSelectorNotExists('.flash-error');
    }

    public function testAnonymousVisitorIsSentToLogin(): void
    {
        $this->aClient->request('GET', '/');

        self::assertResponseRedirects('/connexion');
    }

    public function testApprenticeLogsInAndLandsInTheirSpace(): void
    {
        $this->submitLogin(UserFixtures::APPRENTICE, UserFixtures::PASSWORD);

        self::assertResponseRedirects('/');
        $this->aClient->followRedirect();
        self::assertResponseRedirects('/apprenti');
        $this->aClient->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Lucas');
    }

    public function testWrongPasswordShowsAGenericError(): void
    {
        $this->submitLogin(UserFixtures::APPRENTICE, 'mauvais-mot-de-passe');

        self::assertResponseRedirects('/connexion');
        $this->aClient->followRedirect();
        self::assertSelectorTextContains('.flash-error', 'Identifiants invalides.');
        self::assertInputValueSame('email', UserFixtures::APPRENTICE);
    }

    public function testUnknownAccountShowsTheSameError(): void
    {
        $this->submitLogin('inconnu@cursus.test', UserFixtures::PASSWORD);

        $this->aClient->followRedirect();
        self::assertSelectorTextContains('.flash-error', 'Identifiants invalides.');
    }

    public function testConnectedUserIsSentAwayFromTheLoginPage(): void
    {
        $this->loginAs(UserFixtures::APPRENTICE);

        $this->aClient->request('GET', '/connexion');

        self::assertResponseRedirects('/');
    }

    public function testSchoolWithoutTotpMustEnableItFirst(): void
    {
        $this->submitLogin(UserFixtures::SCHOOL, UserFixtures::PASSWORD);
        $this->aClient->followRedirect();

        self::assertResponseRedirects('/double-authentification');
    }

    public function testSchoolWithTotpMustGiveACode(): void
    {
        $vUser = $this->user(UserFixtures::SCHOOL);
        $vUser->enableTotp(self::TOTP_SECRET);
        $this->entityManager()->flush();

        $this->submitLogin(UserFixtures::SCHOOL, UserFixtures::PASSWORD);
        $this->followRedirectsUntil('/2fa');
        self::assertSelectorTextContains('h1', 'Double authentification');

        $this->aClient->request('GET', '/ecole');
        self::assertResponseRedirects();
        $this->followRedirectsUntil('/2fa');

        $this->aClient->submitForm('Valider', ['_auth_code' => '000000']);
        $this->followRedirectsUntil('/2fa');
        self::assertSelectorTextContains('.flash-error', 'Code incorrect.');

        $this->aClient->submitForm('Valider', ['_auth_code' => TOTP::createFromSecret(self::TOTP_SECRET)->now()]);
        $this->followRedirectsUntil('/ecole');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Claire');
    }

    public function testLogoutEndsTheSession(): void
    {
        $this->loginAs(UserFixtures::APPRENTICE);
        $vCrawler = $this->aClient->request('GET', '/apprenti');

        $this->aClient->click($vCrawler->selectLink('Déconnexion')->link());
        self::assertResponseRedirects('/connexion');

        $this->aClient->request('GET', '/apprenti');
        self::assertResponseRedirects('/connexion');
    }

    /** Suit les redirections jusqu'à la page attendue, sans dépendre du nombre d'étapes intermédiaires. */
    private function followRedirectsUntil(string $pPath): void
    {
        for ($vStep = 0; $vStep < 5 && $this->aClient->getResponse()->isRedirection(); ++$vStep) {
            $this->aClient->followRedirect();
        }
        self::assertResponseIsSuccessful();
        self::assertSame($pPath, parse_url((string) $this->aClient->getRequest()->getUri(), \PHP_URL_PATH));
    }

    private function submitLogin(string $pEmail, string $pPassword): void
    {
        $this->aClient->request('GET', '/connexion');
        $this->aClient->submitForm('Se connecter', ['email' => $pEmail, 'password' => $pPassword]);
    }
}
