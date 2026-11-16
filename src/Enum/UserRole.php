<?php

namespace App\Enum;

/** Profil d'un compte : il fixe les droits, l'espace d'accueil et l'obligation de double authentification. */
enum UserRole: string
{
    case School = 'ROLE_SCHOOL';
    case Tutor = 'ROLE_TUTOR';
    case Apprentice = 'ROLE_APPRENTICE';

    public function label(): string
    {
        return match ($this) {
            self::School => 'École',
            self::Tutor => 'Tuteur',
            self::Apprentice => 'Apprenti',
        };
    }

    public function requiresTotp(): bool
    {
        return self::Apprentice !== $this;
    }

    public function homeRoute(): string
    {
        return match ($this) {
            self::School => 'app_school_home',
            self::Tutor => 'app_tutor_home',
            self::Apprentice => 'app_apprentice_home',
        };
    }
}
