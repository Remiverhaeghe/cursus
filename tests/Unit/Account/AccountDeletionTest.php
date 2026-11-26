<?php

namespace App\Tests\Unit\Account;

use App\Account\AccountDeletion;
use App\Audit\AuditLog;
use App\Entity\User;
use App\Enum\UserRole;
use App\Repository\ApprenticeshipRepository;
use App\Repository\FormationRepository;
use App\Repository\PasswordResetTokenRepository;
use App\Tests\Support\ContractFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(AccountDeletion::class)]
final class AccountDeletionTest extends TestCase
{
    public function testSchoolManagingAFormationIsBlockedFirst(): void
    {
        $vData = new ContractFactory();

        $vBlocker = $this->deletion([$vData->aFormation], true)->blocker($vData->aManager, new \DateTimeImmutable());

        self::assertSame('Tu gères encore au moins une formation : confie-la à un autre responsable avant de supprimer ton compte.', $vBlocker);
    }

    public function testFormationsOnlyMatterForSchoolAccounts(): void
    {
        $vData = new ContractFactory();

        self::assertNull($this->deletion([$vData->aFormation], false)->blocker($vData->aTutor, new \DateTimeImmutable()));
        self::assertNull($this->deletion([], false)->blocker($vData->aManager, new \DateTimeImmutable()));
    }

    public function testCurrentContractBlocks(): void
    {
        $vUser = new User('apprenti@cursus.test', UserRole::Apprentice, 'Lucas', 'Blanc');

        self::assertSame(
            'Un contrat en cours te concerne : la suppression sera possible à sa fin, ou après accord de l\'école.',
            $this->deletion([], true)->blocker($vUser, new \DateTimeImmutable()),
        );
    }

    public function testDeletionAnonymizesForgetsTokensAndLogs(): void
    {
        $vUser = new User('apprenti@cursus.test', UserRole::Apprentice, 'Lucas', 'Blanc');
        (new \ReflectionProperty(User::class, 'aId'))->setValue($vUser, 12);
        $vTokens = $this->createMock(PasswordResetTokenRepository::class);
        $vTokens->expects(self::once())->method('removeForUser')->with($vUser);
        $vEntityManager = $this->createMock(EntityManagerInterface::class);
        $vEntityManager->expects(self::once())->method('flush');
        $vLogger = $this->createMock(LoggerInterface::class);
        $vLogger->expects(self::once())->method('info')->with('Compte supprimé et anonymisé', ['user_id' => 12]);

        (new AccountDeletion($this->createStub(FormationRepository::class), $this->createStub(ApprenticeshipRepository::class), $vTokens, $vEntityManager, $vLogger, $this->createStub(AuditLog::class)))->delete($vUser);

        self::assertSame('compte-supprime-12@cursus.invalid', $vUser->getEmail());
    }

    /** @param list<\App\Entity\Formation> $pFormations */
    private function deletion(array $pFormations, bool $pCurrentContract): AccountDeletion
    {
        $vFormations = $this->createStub(FormationRepository::class);
        $vFormations->method('findManagedBy')->willReturn($pFormations);
        $vApprenticeships = $this->createStub(ApprenticeshipRepository::class);
        $vApprenticeships->method('hasCurrentContract')->willReturn($pCurrentContract);

        return new AccountDeletion(
            $vFormations,
            $vApprenticeships,
            $this->createStub(PasswordResetTokenRepository::class),
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(LoggerInterface::class),
            $this->createStub(AuditLog::class),
        );
    }
}
