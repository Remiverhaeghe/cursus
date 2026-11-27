<?php

namespace App\Tests\Unit\Security;

use App\Entity\AuditEntry;
use App\Entity\User;
use App\Enum\UserRole;
use App\Security\Voter\AuditVoter;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/** Un test par profil pour le journal des actions : seule l'école le lit. */
#[CoversClass(AuditVoter::class)]
final class AuditVoterTest extends VoterTestCase
{
    public function testEachProfile(): void
    {
        $vVoter = new AuditVoter();
        $vCases = [
            [UserRole::School, VoterInterface::ACCESS_GRANTED],
            [UserRole::Tutor, VoterInterface::ACCESS_DENIED],
            [UserRole::Apprentice, VoterInterface::ACCESS_DENIED],
        ];
        foreach ($vCases as [$vRole, $vExpected]) {
            $vUser = new User('x@cursus.test', $vRole, 'X', 'Y');
            self::assertSame($vExpected, $vVoter->vote(self::token($vUser), AuditEntry::class, [AuditVoter::VIEW]), $vRole->label());
        }
        self::assertSame(VoterInterface::ACCESS_DENIED, $vVoter->vote(self::token(null), AuditEntry::class, [AuditVoter::VIEW]));
    }

    public function testOtherSubjectsAndAttributesAreNotHandled(): void
    {
        $vVoter = new AuditVoter();
        $vSchool = new User('x@cursus.test', UserRole::School, 'X', 'Y');

        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $vVoter->vote(self::token($vSchool), 'autre chose', [AuditVoter::VIEW]));
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $vVoter->vote(self::token($vSchool), AuditEntry::class, ['AUTRE']));
    }
}
