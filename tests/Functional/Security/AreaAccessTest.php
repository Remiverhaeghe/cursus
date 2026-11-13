<?php

namespace App\Tests\Functional\Security;

use App\Controller\DashboardController;
use App\EventSubscriber\SecurityHeadersSubscriber;
use App\DataFixtures\UserFixtures;
use App\Tests\Support\AppWebTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/** Cloisonnement des espaces : chaque profil n'entre que dans le sien. */
#[CoversClass(DashboardController::class)]
#[CoversClass(SecurityHeadersSubscriber::class)]
final class AreaAccessTest extends AppWebTestCase
{
    /** @return iterable<string, array{string, string, int}> */
    public static function matrix(): iterable
    {
        $vAccounts = ['école' => UserFixtures::SCHOOL, 'tuteur' => UserFixtures::TUTOR, 'apprenti' => UserFixtures::APPRENTICE];
        $vAreas = ['école' => '/ecole', 'tuteur' => '/tuteur', 'apprenti' => '/apprenti'];
        foreach ($vAccounts as $vProfile => $vEmail) {
            foreach ($vAreas as $vArea => $vPath) {
                yield $vProfile.' vers espace '.$vArea => [$vEmail, $vPath, $vProfile === $vArea ? 200 : 403];
            }
        }
    }

    #[DataProvider('matrix')]
    public function testEachProfileOnlyEntersItsOwnArea(string $pEmail, string $pPath, int $pExpectedStatus): void
    {
        $this->loginAs($pEmail);

        $this->aClient->request('GET', $pPath);

        self::assertResponseStatusCodeSame($pExpectedStatus);
    }

    /** @return iterable<string, array{string}> */
    public static function areas(): iterable
    {
        yield 'école' => ['/ecole'];
        yield 'tuteur' => ['/tuteur'];
        yield 'apprenti' => ['/apprenti'];
    }

    #[DataProvider('areas')]
    public function testAnonymousVisitorIsSentToLogin(string $pPath): void
    {
        $this->aClient->request('GET', $pPath);

        self::assertResponseRedirects('/connexion');
    }

    public function testPagesCarryTheCspNonceOnTheirScripts(): void
    {
        $vCrawler = $this->aClient->request('GET', '/connexion');

        $vPolicy = (string) $this->aClient->getResponse()->headers->get('Content-Security-Policy');
        $vNonce = $vCrawler->filter('script[type="importmap"]')->attr('nonce');
        self::assertNotNull($vNonce);
        self::assertStringContainsString("'nonce-".$vNonce."'", $vPolicy);
        self::assertResponseHeaderSame('X-Frame-Options', 'DENY');
    }

    public function testHealthStaysPublic(): void
    {
        $this->aClient->request('GET', '/health');

        self::assertResponseIsSuccessful();
    }
}
