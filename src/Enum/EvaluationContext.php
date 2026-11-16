<?php

namespace App\Enum;

/** Lieu où la compétence a été observée : l'école évalue « école », le tuteur évalue « entreprise ». */
enum EvaluationContext: string
{
    case School = 'school';
    case Company = 'company';

    public function label(): string
    {
        return match ($this) {
            self::School => 'École',
            self::Company => 'Entreprise',
        };
    }

    /** Contexte autorisé pour un profil ; l'apprenti n'évalue pas. */
    public static function forRole(UserRole $pRole): ?self
    {
        return match ($pRole) {
            UserRole::School => self::School,
            UserRole::Tutor => self::Company,
            UserRole::Apprentice => null,
        };
    }
}
