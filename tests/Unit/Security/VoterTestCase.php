<?php

namespace App\Tests\Unit\Security;

use App\Entity\User;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/** Outils communs aux tests de voters : un jeton par profil. */
abstract class VoterTestCase extends TestCase
{
    protected static function token(?User $pUser): TokenInterface
    {
        if (null === $pUser) {
            return new NullToken();
        }

        return new UsernamePasswordToken($pUser, 'main', $pUser->getRoles());
    }
}
