<?php

namespace App\Enum;

/** Niveau d'une compétence, de 0 à 4 : l'énumération rend tout autre niveau impossible. */
enum CompetenceLevel: int
{
    case NotStarted = 0;
    case Discovery = 1;
    case InProgress = 2;
    case Acquired = 3;
    case Mastered = 4;

    /** Niveau à partir duquel une compétence est considérée comme validée. */
    public const int VALIDATION_THRESHOLD = 3;

    public function label(): string
    {
        return match ($this) {
            self::NotStarted => 'Non abordée',
            self::Discovery => 'Découverte',
            self::InProgress => 'En cours d\'acquisition',
            self::Acquired => 'Acquise',
            self::Mastered => 'Maîtrisée',
        };
    }

    public function isValidated(): bool
    {
        return $this->value >= self::VALIDATION_THRESHOLD;
    }
}
