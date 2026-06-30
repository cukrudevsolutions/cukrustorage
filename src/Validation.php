<?php
declare(strict_types=1);

namespace Cukru;

final class Validation
{
    /** Buang semua aksara bukan digit, untuk simpan/cari no. telefon secara konsisten. */
    public static function normalizePhone(string $raw): string
    {
        return preg_replace('/\D+/', '', $raw) ?? '';
    }

    /** Format Malaysia: 01X-XXXXXXX (10 digit) atau 011-XXXXXXXX (11 digit). */
    public static function isValidMalaysianPhone(string $raw): bool
    {
        $digits = self::normalizePhone($raw);
        return (bool) preg_match('/^01\d{8,9}$/', $digits);
    }

    public static function isValidPin(string $pin): bool
    {
        return (bool) preg_match('/^\d{4,6}$/', $pin);
    }

    public static function isValidEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function isValidName(string $name): bool
    {
        $name = trim($name);
        return mb_strlen($name) >= 2 && mb_strlen($name) <= 100;
    }
}
