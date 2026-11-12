<?php

namespace App\Support;

final class TemporaryResponderPassword
{
    public static function generate(string $firstName, int $year): string
    {
        $letters = strtoupper((string) preg_replace('/[^A-Za-z]/', '', $firstName));
        $consonants = str_replace(['A', 'E', 'I', 'O', 'U'], '', $letters);

        return $consonants.'@'.$year.'!';
    }

    public static function matches(string $password, string $firstName): bool
    {
        $letters = strtoupper((string) preg_replace('/[^A-Za-z]/', '', $firstName));
        $consonants = preg_quote(str_replace(['A', 'E', 'I', 'O', 'U'], '', $letters), '/');

        return preg_match('/^'.$consonants.'@[0-9]{4}!$/D', $password) === 1;
    }
}
