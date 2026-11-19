<?php

namespace App\Tests\Functional\Account;

use App\Account\AccountDeletion;
use App\Account\AccountExporter;
use App\Controller\AccountController;
use App\DataFixtures\UserFixtures;
use App\Entity\Apprenticeship;
use App\Entity\Cohort;
use App\Entity\Company;
use App\Entity\User;
use App\Enum\UserRole;
use App\Repository\CompetenceMessageRepository;
use App\Tests\Support\AppWebTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[CoversClass(AccountController::class)]
#[CoversClass(AccountExporter::class)]
#[CoversClass(AccountDeletion::class)]
#[CoversClass(CompetenceMessageRepository::class)]
final class AccountTest extends AppWebTestCase
{
    private const string FREE_ACCOUNT = 'compte.libre@cursus.test';
    private const string PASSWORD = 'mot-de-passe-du-test';

    /** @return iterable<string, array{string, string}> */
    public static function blockedAccounts(): iterable
    {
        yield 'école qui gère une formation' => [UserFixtures::SCHOOL, 'Tu gères encore au moins une formation'];
        yield 'tuteur avec un contrat en cours' => [UserFixtures::TUTOR, 'Un contrat en cours te concerne'];
        yield 'apprenti avec un contrat en cours' => [UserFixtures::APPRENTICE, 'Un contrat en cours te concerne'];
    }

    #[DataProvider('blockedAccounts')]
    public function testAccountWithOngoingFollowUpCannotBeDeleted(string $pEmail, string $pReason): void
    {
        $this->loginAs($pEmail);

        $this->aClient->request('GET', '/compte');

        self::assertResponseStatusCodeSame(200);
        self::assertSelectorTextContains('.flash', $pReason);
        self::assertSelectorNotExists('form[action="/compte/supprimer"]');

        $this->aClient->request('POST', '/compte/supprimer', ['password' => UserFixtures::PASSWORD, '_token' => 'peu-importe']);
        self::assertResponseStatusCodeSame(422);
        self::assertSame($pEmail, $this->user($pEmail)->getEmail());
    }

    public function testApprenticeExportsItsOwnDataOnly(): void
    {
        $this->loginAs(UserFixtures::APPRENTICE);

        $this->aClient->request('GET', '/compte/export');

        self::assertResponseStatusCodeSame(200);
        self::assertResponseHeaderSame('Content-Disposition', 'attachment; filename=cursus-mes-donnees.json');
        $vData = json_decode((string) $this->aClient->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($vData);
        self::assertSame(['email' => UserFixtures::APPRENTICE, 'first_name' => 'Lucas', 'last_name' => 'Bernard', 'role' => 'Apprenti', 'two_factor_enabled' => false], $vData['account']);
        self::assertCount(1, $vData['apprenticeships']);
        self::assertSame('Marc Lefebvre', $vData['apprenticeships'][0]['tutor']);
        self::assertSame('Atelier Lumen (Lille)', $vData['apprenticeships'][0]['company']);
        self::assertSame('2027-08-31', $vData['apprenticeships'][0]['end_date']);
        self::assertCount(6, $vData['evaluations']);
        self::assertSame(['C1.1 · Gérer le patrimoine informatique', 1, 'École'], [$vData['evaluations'][0]['competence'], $vData['evaluations'][0]['level'], $vData['evaluations'][0]['context']]);
        self::assertCount(4, $vData['messages']);
        self::assertSame(['Lucas Bernard', 'Marc Lefebvre'], array_values(array_unique(array_column($vData['messages'], 'author'))));
        $vRaw = (string) $this->aClient->getResponse()->getContent();
        self::assertStringContainsString("{\n    \"exported_at\"", $vRaw);
        self::assertStringContainsString('Gérer le patrimoine informatique', $vRaw);
        self::assertStringNotContainsString('Hugo', $vRaw);
        self::assertStringNotContainsString('password', $vRaw);
        self::assertStringNotContainsString('totp_secret', $vRaw);
    }

    public function testTutorExportListsTheEvaluationsItGave(): void
    {
        $this->loginAs(UserFixtures::TUTOR);

        $this->aClient->request('GET', '/compte/export');

        $vData = json_decode((string) $this->aClient->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($vData);
        self::assertTrue($vData['account']['two_factor_enabled']);
        self::assertCount(2, $vData['apprenticeships']);
        self::assertCount(4, $vData['evaluations']);
        self::assertSame(['Marc Lefebvre'], array_values(array_unique(array_column($vData['evaluations'], 'evaluator'))));
        self::assertCount(2, $vData['messages']);
        self::assertSame(['Marc Lefebvre'], array_values(array_unique(array_column($vData['messages'], 'author'))));
    }

    public function testFreeAccountIsDeletedAfterPasswordCheck(): void
    {
        $vUser = $this->freeAccount();
        $vId = $vUser->getId();
        $this->loginAs(self::FREE_ACCOUNT);
        $this->aClient->request('GET', '/compte');

        $vToken = $this->aClient->getCrawler()->filter('input[name="_token"]')->attr('value');
        $this->aClient->request('POST', '/compte/supprimer', ['_token' => (string) $vToken]);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.flash-error', 'Mot de passe incorrect.');

        $this->aClient->submitForm('Supprimer définitivement mon compte', ['password' => 'mauvais']);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.flash-error', 'Mot de passe incorrect.');

        $this->aClient->submitForm('Supprimer définitivement mon compte', ['password' => self::PASSWORD]);
        self::assertResponseRedirects('/connexion');
        $this->aClient->followRedirect();
        self::assertSelectorTextContains('.flash-success', 'Ton compte a été supprimé.');

        $vDeleted = $this->find(User::class, ['aId' => $vId]);
        $this->entityManager()->refresh($vDeleted);
        self::assertSame('compte-supprime-'.$vId.'@cursus.invalid', $vDeleted->getEmail());
        self::assertSame('Compte supprimé', $vDeleted->getFullName());

        $this->aClient->request('GET', '/connexion');
        $this->aClient->submitForm('Se connecter', ['email' => self::FREE_ACCOUNT, 'password' => self::PASSWORD]);
        self::assertResponseRedirects('/connexion');
    }

    public function testExpiredFormDoesNotDeleteTheAccount(): void
    {
        $this->freeAccount();
        $this->loginAs(self::FREE_ACCOUNT);
        $this->aClient->request('GET', '/compte');

        $this->aClient->request('POST', '/compte/supprimer', ['password' => self::PASSWORD, '_token' => 'faux']);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.flash-error', 'Le formulaire a expiré');
        self::assertSame(self::FREE_ACCOUNT, $this->user(self::FREE_ACCOUNT)->getEmail());
    }

    /** @return iterable<string, array{string, bool}> */
    public static function contractEnds(): iterable
    {
        yield 'contrat fini hier' => ['-1 day', true];
        yield 'contrat qui finit aujourd\'hui' => ['today', false];
    }

    #[DataProvider('contractEnds')]
    public function testOnlyAFinishedContractAllowsDeletion(string $pEnd, bool $pAllowed): void
    {
        $vApprentice = $this->freeAccount();
        $this->entityManager()->persist(new Apprenticeship(
            $vApprentice,
            $this->user(UserFixtures::TUTOR),
            $this->find(Company::class, ['aName' => 'Atelier Lumen']),
            $this->find(Cohort::class, ['aName' => 'Promotion 2025-2027']),
            new \DateTimeImmutable('2024-09-01'),
            new \DateTimeImmutable($pEnd),
        ));
        $this->entityManager()->flush();
        $this->loginAs(self::FREE_ACCOUNT);

        $this->aClient->request('GET', '/compte');

        $pAllowed
            ? self::assertSelectorExists('form[action="/compte/supprimer"]')
            : self::assertSelectorTextContains('.flash', 'Un contrat en cours te concerne');
    }

    public function testAccountPageNeedsALogin(): void
    {
        $this->aClient->request('GET', '/compte');

        self::assertResponseRedirects('/connexion');
    }

    private function freeAccount(): User
    {
        $rUser = new User(self::FREE_ACCOUNT, UserRole::Apprentice, 'Inès', 'Faure');
        $vHasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $rUser->setPassword($vHasher->hashPassword($rUser, self::PASSWORD));
        $this->entityManager()->persist($rUser);
        $this->entityManager()->flush();

        return $rUser;
    }
}
