<?php

namespace App\Security;

/** Codes de secours de la double authentification : dix caractères tirés au hasard, affichés « xxxxx-xxxxx ». */
final class BackupCodes
{
    public const int COUNT = 8;
    private const string ALPHABET = 'abcdefghjkmnpqrstuvwxyz23456789';

    /** @return list<string> */
    public function generate(int $pCount = self::COUNT): array
    {
        $rCodes = [];
        while (\count($rCodes) < $pCount) {
            $vCode = '';
            for ($vIndex = 0; $vIndex < 10; ++$vIndex) {
                $vCode .= self::ALPHABET[random_int(0, \strlen(self::ALPHABET) - 1)];
            }
            $rCodes[$vCode] = substr($vCode, 0, 5).'-'.substr($vCode, 5);
        }

        return array_values($rCodes);
    }

    /** Forme comparée et hachée : sans tiret ni espace, en minuscules. */
    public static function normalize(string $pCode): string
    {
        return mb_strtolower(str_replace(['-', ' '], '', trim($pCode)));
    }

    public static function hash(string $pCode): string
    {
        return hash('sha256', self::normalize($pCode));
    }
}
