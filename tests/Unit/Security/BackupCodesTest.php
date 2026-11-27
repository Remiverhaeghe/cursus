<?php

namespace App\Tests\Unit\Security;

use App\Security\BackupCodes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BackupCodes::class)]
final class BackupCodesTest extends TestCase
{
    public function testGeneratesDistinctCodesInTheDisplayedFormat(): void
    {
        $vCodes = (new BackupCodes())->generate();

        self::assertCount(8, $vCodes);
        self::assertCount(8, array_unique($vCodes));
        foreach ($vCodes as $vCode) {
            self::assertMatchesRegularExpression('/^[a-hjkmnp-z2-9]{5}-[a-hjkmnp-z2-9]{5}$/', $vCode);
        }
    }

    public function testCountCanBeChosen(): void
    {
        self::assertCount(3, (new BackupCodes())->generate(3));
    }

    public function testDashSpacesAndCaseDoNotChangeTheHash(): void
    {
        self::assertSame(BackupCodes::hash('abcde-fghjk'), BackupCodes::hash('  ABCDE FGHJK '));
        self::assertSame('abcdefghjk', BackupCodes::normalize(' ABCDE-fghjk '));
        self::assertNotSame(BackupCodes::hash('abcde-fghjk'), BackupCodes::hash('abcde-fghjm'));
        self::assertSame(64, \strlen(BackupCodes::hash('abcde-fghjk')));
    }
}
