<?php

namespace App\DataFixtures;

use App\Entity\User;
use App\Enum\UserRole;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/** Comptes de démonstration, tous fictifs, avec le même mot de passe. */
final class UserFixtures extends Fixture
{
    public const string PASSWORD = 'demo-cursus';

    public const string SCHOOL = 'claire.fontaine@cursus.test';
    public const string SCHOOL_OTHER = 'paul.renard@cursus.test';
    public const string TUTOR = 'marc.lefebvre@cursus.test';
    public const string TUTOR_OTHER = 'sophie.garnier@cursus.test';
    public const string APPRENTICE = 'lucas.bernard@cursus.test';
    public const string APPRENTICE_SAME_TUTOR = 'hugo.moreau@cursus.test';
    public const string APPRENTICE_OTHER = 'emma.petit@cursus.test';
    public const string APPRENTICE_OTHER_SCHOOL = 'nina.roux@cursus.test';

    /** @var list<array{string, UserRole, string, string}> */
    private const array USERS = [
        [self::SCHOOL, UserRole::School, 'Claire', 'Fontaine'],
        [self::SCHOOL_OTHER, UserRole::School, 'Paul', 'Renard'],
        [self::TUTOR, UserRole::Tutor, 'Marc', 'Lefebvre'],
        [self::TUTOR_OTHER, UserRole::Tutor, 'Sophie', 'Garnier'],
        [self::APPRENTICE, UserRole::Apprentice, 'Lucas', 'Bernard'],
        [self::APPRENTICE_SAME_TUTOR, UserRole::Apprentice, 'Hugo', 'Moreau'],
        [self::APPRENTICE_OTHER, UserRole::Apprentice, 'Emma', 'Petit'],
        [self::APPRENTICE_OTHER_SCHOOL, UserRole::Apprentice, 'Nina', 'Roux'],
    ];

    public function __construct(private readonly UserPasswordHasherInterface $aPasswordHasher)
    {
    }

    public function load(ObjectManager $manager): void
    {
        foreach (self::USERS as [$vEmail, $vRole, $vFirstName, $vLastName]) {
            $vUser = new User($vEmail, $vRole, $vFirstName, $vLastName);
            $vUser->setPassword($this->aPasswordHasher->hashPassword($vUser, self::PASSWORD));
            $manager->persist($vUser);
            $this->addReference($vEmail, $vUser);
        }
        $manager->flush();
    }
}
