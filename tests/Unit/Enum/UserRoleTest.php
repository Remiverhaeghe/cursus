<?php

namespace App\Tests\Unit\Enum;

use App\Enum\UserRole;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(UserRole::class)]
final class UserRoleTest extends TestCase
{
    /** @return iterable<string, array{UserRole, string, bool, string}> */
    public static function roles(): iterable
    {
        yield 'école' => [UserRole::School, 'École', true, 'app_school_home'];
        yield 'tuteur' => [UserRole::Tutor, 'Tuteur', true, 'app_tutor_home'];
        yield 'apprenti' => [UserRole::Apprentice, 'Apprenti', false, 'app_apprentice_home'];
    }

    #[DataProvider('roles')]
    public function testRoleDescribesItsRights(UserRole $pRole, string $pLabel, bool $pRequiresTotp, string $pHomeRoute): void
    {
        self::assertSame($pLabel, $pRole->label());
        self::assertSame($pRequiresTotp, $pRole->requiresTotp());
        self::assertSame($pHomeRoute, $pRole->homeRoute());
    }
}
