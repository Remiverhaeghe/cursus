<?php

namespace App\Tests\Unit\Audit;

use App\Audit\AuditLog;
use App\Entity\AuditEntry;
use App\Entity\User;
use App\Enum\UserRole;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(AuditLog::class)]
#[CoversClass(AuditEntry::class)]
final class AuditLogTest extends TestCase
{
    public function testRecordAddsADatedLineWithoutFlushing(): void
    {
        $vUser = new User('lea@cursus.test', UserRole::Tutor, 'Léa', 'Martin');
        $vSaved = null;
        $vEntityManager = $this->createMock(EntityManagerInterface::class);
        $vEntityManager->expects(self::once())->method('persist')->willReturnCallback(static function (AuditEntry $pEntry) use (&$vSaved): void {
            $vSaved = $pEntry;
        });
        $vEntityManager->expects(self::never())->method('flush');

        (new AuditLog($vEntityManager, new MockClock('2026-11-26 10:15:00')))->record(AuditLog::LOGIN, $vUser, 'Connexion réussie');

        self::assertInstanceOf(AuditEntry::class, $vSaved);
        self::assertSame('connexion', $vSaved->getAction());
        self::assertSame('Connexion réussie', $vSaved->getSummary());
        self::assertSame($vUser, $vSaved->getActor());
        self::assertSame('2026-11-26 10:15:00', $vSaved->getOccurredAt()->format('Y-m-d H:i:s'));
        self::assertNull($vSaved->getId());
    }

    public function testEverySavedActionHasALabel(): void
    {
        foreach ([AuditLog::LOGIN, AuditLog::EVALUATION, AuditLog::PASSWORD_RESET, AuditLog::ACCOUNT_DELETED, AuditLog::ACCOUNT_CREATED, AuditLog::TOTP_ENABLED, AuditLog::BACKUP_CODES] as $vAction) {
            self::assertArrayHasKey($vAction, AuditLog::LABELS);
        }
    }

    public function testALongSummaryIsCutToTheColumnSize(): void
    {
        $vEntry = new AuditEntry('x', str_repeat('é', 300), null, new \DateTimeImmutable());

        self::assertSame(255, mb_strlen($vEntry->getSummary()));
        self::assertNull($vEntry->getActor());
    }
}
