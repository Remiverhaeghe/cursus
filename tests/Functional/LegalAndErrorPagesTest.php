<?php

namespace App\Tests\Functional;

use App\Controller\LegalController;
use App\DataFixtures\UserFixtures;
use App\Tests\Support\AppWebTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/** Pages légales publiques et pages d'erreur, vues comme en production (noyau hors debug). */
#[CoversClass(LegalController::class)]
final class LegalAndErrorPagesTest extends AppWebTestCase
{
    protected static function clientOptions(): array
    {
        return ['debug' => false];
    }

    /** @return iterable<string, array{string, string}> */
    public static function legalPages(): iterable
    {
        yield 'mentions légales' => ['/mentions-legales', 'Mentions légales'];
        yield 'confidentialité' => ['/confidentialite', 'Politique de confidentialité'];
        yield 'conditions' => ['/conditions-utilisation', 'Conditions d\'utilisation'];
    }

    #[DataProvider('legalPages')]
    public function testLegalPagesArePublicAndInventNothing(string $pPath, string $pTitle): void
    {
        $this->aClient->request('GET', $pPath);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', $pTitle);
        self::assertSelectorTextContains('main', '(à compléter avant la mise en ligne)');
    }

    public function testEveryPageLinksToTheLegalPages(): void
    {
        $vCrawler = $this->aClient->request('GET', '/connexion');

        $vLinks = $vCrawler->filter('footer a')->each(static fn ($pLink): string => (string) $pLink->attr('href'));
        self::assertSame(['/mentions-legales', '/confidentialite', '/conditions-utilisation'], $vLinks);
        self::assertSame('noindex', $vCrawler->filter('meta[name="robots"]')->attr('content'));
        self::assertNotEmpty($vCrawler->filter('meta[name="description"]')->attr('content'));
    }

    public function testUnknownPageShowsTheCustom404(): void
    {
        $this->aClient->request('GET', '/page-qui-n-existe-pas');

        self::assertResponseStatusCodeSame(404);
        self::assertSelectorTextContains('h1', 'Page introuvable');
        self::assertSelectorExists('a[href="/"]');
    }

    public function testForbiddenPageShowsTheCustom403(): void
    {
        $this->loginAs(UserFixtures::TUTOR);

        $this->aClient->request('GET', '/ecole');

        self::assertResponseStatusCodeSame(403);
        self::assertSelectorTextContains('h1', 'Accès refusé');
    }

    public function testGenericErrorPageRevealsNothingTechnical(): void
    {
        $vHtml = static::getContainer()->get('twig')->render('@Twig/Exception/error.html.twig', ['status_code' => 500, 'status_text' => 'Internal Server Error', 'exception' => new \RuntimeException('détail secret')]);

        self::assertStringContainsString('Une erreur est survenue', $vHtml);
        self::assertStringContainsString('Code : 500', $vHtml);
        self::assertStringNotContainsString('détail secret', $vHtml);
    }
}
