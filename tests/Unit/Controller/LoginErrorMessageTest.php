<?php

namespace App\Tests\Unit\Controller;

use App\Controller\SecurityController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;

#[CoversClass(SecurityController::class)]
final class LoginErrorMessageTest extends TestCase
{
    public function testNoErrorGivesNoMessage(): void
    {
        self::assertNull(SecurityController::loginErrorMessage(null));
    }

    public function testUnknownAccountAndWrongPasswordGiveTheSameMessage(): void
    {
        self::assertSame('Identifiants invalides.', SecurityController::loginErrorMessage(new BadCredentialsException()));
        self::assertSame('Identifiants invalides.', SecurityController::loginErrorMessage(new UserNotFoundException()));
    }

    public function testThrottlingAndExpiredFormHaveTheirOwnMessage(): void
    {
        self::assertSame('Trop de tentatives : réessaie dans quelques minutes.', SecurityController::loginErrorMessage(new TooManyLoginAttemptsAuthenticationException(15)));
        self::assertSame('Le formulaire a expiré, recommence.', SecurityController::loginErrorMessage(new InvalidCsrfTokenException()));
    }
}
