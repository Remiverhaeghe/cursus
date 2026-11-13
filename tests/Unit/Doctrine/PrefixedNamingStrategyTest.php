<?php

namespace App\Tests\Unit\Doctrine;

use App\Doctrine\PrefixedNamingStrategy;
use App\Entity\User;
use App\Enum\UserRole;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(PrefixedNamingStrategy::class)]
final class PrefixedNamingStrategyTest extends TestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function properties(): iterable
    {
        yield 'attribut préfixé' => ['aFirstName', 'first_name'];
        yield 'identifiant' => ['aId', 'id'];
        yield 'sans préfixe' => ['amount', 'amount'];
        yield 'a minuscule seul' => ['a', 'a'];
        yield 'préfixe au milieu ignoré' => ['dataValue', 'data_value'];
    }

    #[DataProvider('properties')]
    public function testColumnNameDropsThePrefix(string $pProperty, string $pColumn): void
    {
        self::assertSame($pColumn, (new PrefixedNamingStrategy())->propertyToColumnName($pProperty, User::class));
    }

    public function testTableAndJoinNames(): void
    {
        $vStrategy = new PrefixedNamingStrategy();

        self::assertSame('user', $vStrategy->classToTableName(User::class));
        self::assertSame('user_role', $vStrategy->classToTableName(UserRole::class));
        self::assertSame('formation_id', $vStrategy->joinColumnName('aFormation', User::class));
        self::assertSame('id', $vStrategy->referenceColumnName());
        self::assertSame('user_user_role', $vStrategy->joinTableName(User::class, UserRole::class, 'aRoles'));
        self::assertSame('user_id', $vStrategy->joinKeyColumnName(User::class, null));
        self::assertSame('period_start_date', $vStrategy->embeddedFieldToColumnName('aPeriod', 'start_date', User::class, UserRole::class));
    }
}
