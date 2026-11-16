<?php

namespace App\Tests\Unit\Enum;

use App\Enum\CompetenceLevel;
use App\Enum\EvaluationContext;
use App\Enum\UserRole;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(CompetenceLevel::class)]
#[CoversClass(EvaluationContext::class)]
final class EvaluationEnumsTest extends TestCase
{
    /** @return iterable<string, array{CompetenceLevel, string, bool}> */
    public static function levels(): iterable
    {
        yield '0' => [CompetenceLevel::NotStarted, 'Non abordée', false];
        yield '1' => [CompetenceLevel::Discovery, 'Découverte', false];
        yield '2' => [CompetenceLevel::InProgress, 'En cours d\'acquisition', false];
        yield '3' => [CompetenceLevel::Acquired, 'Acquise', true];
        yield '4' => [CompetenceLevel::Mastered, 'Maîtrisée', true];
    }

    #[DataProvider('levels')]
    public function testLevel(CompetenceLevel $pLevel, string $pLabel, bool $pValidated): void
    {
        self::assertSame($pLabel, $pLevel->label());
        self::assertSame($pValidated, $pLevel->isValidated());
    }

    /** @return iterable<string, array{int, bool}> */
    public static function rawLevels(): iterable
    {
        foreach ([-1 => false, 0 => true, 4 => true, 5 => false] as $vValue => $vExists) {
            yield (string) $vValue => [$vValue, $vExists];
        }
    }

    #[DataProvider('rawLevels')]
    public function testLevelIsBoundedFromZeroToFour(int $pValue, bool $pExists): void
    {
        self::assertSame($pExists, null !== CompetenceLevel::tryFrom($pValue));
        self::assertSame([0, 1, 2, 3, 4], array_map(static fn (CompetenceLevel $pLevel): int => $pLevel->value, CompetenceLevel::cases()));
    }

    public function testContextFollowsTheEvaluatorProfile(): void
    {
        self::assertSame(EvaluationContext::School, EvaluationContext::forRole(UserRole::School));
        self::assertSame(EvaluationContext::Company, EvaluationContext::forRole(UserRole::Tutor));
        self::assertNull(EvaluationContext::forRole(UserRole::Apprentice));
        self::assertSame('École', EvaluationContext::School->label());
        self::assertSame('Entreprise', EvaluationContext::Company->label());
    }
}
