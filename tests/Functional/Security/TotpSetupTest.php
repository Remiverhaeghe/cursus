<?php

namespace App\Tests\Functional\Security;

use App\Controller\SecurityController;
use App\DataFixtures\UserFixtures;
use App\EventSubscriber\TotpEnrollmentSubscriber;
use App\Tests\Support\AppWebTestCase;
use OTPHP\TOTP;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(SecurityController::class)]
#[CoversClass(TotpEnrollmentSubscriber::class)]
final class TotpSetupTest extends AppWebTestCase
{
    public function testTutorWithoutTotpIsBlockedUntilActivation(): void
    {
        $this->aClient->loginUser($this->user(UserFixtures::TUTOR));

        $this->aClient->request('GET', '/tuteur');

        self::assertResponseRedirects('/double-authentification');
    }

    public function testActivationShowsAQrCodeAndKeepsTheSameSecret(): void
    {
        $this->aClient->loginUser($this->user(UserFixtures::TUTOR));

        $vFirst = $this->aClient->request('GET', '/double-authentification');
        self::assertResponseIsSuccessful();
        self::assertStringStartsWith('data:image/svg+xml', (string) $vFirst->filter('img.qr')->attr('src'));
        $vSecret = $vFirst->filter('#totp-secret')->text();

        $vSecond = $this->aClient->request('GET', '/double-authentification');
        self::assertSame($vSecret, $vSecond->filter('#totp-secret')->text());
        self::assertFalse($this->refreshed($this->user(UserFixtures::TUTOR))->isTotpAuthenticationEnabled());
    }

    public function testWrongCodeIsRefused(): void
    {
        $this->aClient->loginUser($this->user(UserFixtures::TUTOR));
        $this->aClient->request('GET', '/double-authentification');

        $this->aClient->submitForm('Activer', ['code' => '000000']);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.flash-error', 'Code incorrect');
        self::assertFalse($this->refreshed($this->user(UserFixtures::TUTOR))->isTotpAuthenticationEnabled());
    }

    public function testExpiredFormIsRefused(): void
    {
        $this->aClient->loginUser($this->user(UserFixtures::TUTOR));
        $vCrawler = $this->aClient->request('GET', '/double-authentification');
        $vSecret = $vCrawler->filter('#totp-secret')->text();

        $this->aClient->request('POST', '/double-authentification', ['code' => TOTP::createFromSecret($vSecret)->now(), '_token' => 'faux']);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.flash-error', 'Le formulaire a expiré');
        self::assertFalse($this->refreshed($this->user(UserFixtures::TUTOR))->isTotpAuthenticationEnabled());
    }

    public function testRightCodeEnablesTotp(): void
    {
        $this->aClient->loginUser($this->user(UserFixtures::TUTOR));
        $vCrawler = $this->aClient->request('GET', '/double-authentification');
        $vSecret = $vCrawler->filter('#totp-secret')->text();

        $this->aClient->submitForm('Activer', ['code' => TOTP::createFromSecret($vSecret)->now()]);

        self::assertResponseRedirects('/');
        $vUser = $this->refreshed($this->user(UserFixtures::TUTOR));
        self::assertTrue($vUser->isTotpAuthenticationEnabled());
        self::assertSame($vSecret, $vUser->getTotpAuthenticationConfiguration()?->getSecret());
        $this->aClient->followRedirect();
        $this->aClient->followRedirect();
        self::assertSelectorTextContains('.flash-success', 'Double authentification activée.');

        // Un nouveau passage repart d'un nouveau secret : l'ancien a quitté la session
        $vUser->disableTotp();
        $this->entityManager()->flush();
        $vNext = $this->aClient->request('GET', '/double-authentification');
        self::assertNotSame($vSecret, $vNext->filter('#totp-secret')->text());
    }

    public function testAlreadyEnabledAccountIsSentHome(): void
    {
        $this->loginAs(UserFixtures::SCHOOL);

        $this->aClient->request('GET', '/double-authentification');

        self::assertResponseRedirects('/');
        $this->aClient->followRedirect();
        $this->aClient->followRedirect();
        self::assertSelectorTextContains('.flash-info', 'déjà active');
    }

    public function testApprenticeMayEnableItWithoutBeingForced(): void
    {
        $this->aClient->loginUser($this->user(UserFixtures::APPRENTICE));

        $this->aClient->request('GET', '/apprenti');
        self::assertResponseIsSuccessful();

        $this->aClient->request('GET', '/double-authentification');
        self::assertResponseIsSuccessful();
    }
}
