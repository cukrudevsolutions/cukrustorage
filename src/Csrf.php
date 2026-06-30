<?php
declare(strict_types=1);

namespace Cukru;

final class Csrf
{
    private const SESSION_KEY = '_csrf_token';

    public static function token(): string
    {
        if (empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::SESSION_KEY];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . e(self::token()) . '">';
    }

    public static function verify(?string $token): bool
    {
        if (!is_string($token) || empty($_SESSION[self::SESSION_KEY])) {
            return false;
        }
        return hash_equals($_SESSION[self::SESSION_KEY], $token);
    }

    /** Panggil di awal setiap handler POST. Hentikan request jika token tak sah. */
    public static function requireValid(): void
    {
        $token = $_POST['csrf_token'] ?? null;
        if (!self::verify($token)) {
            http_response_code(419);
            exit('Sesi tamat tempoh atau permintaan tidak sah. Sila muat semula halaman dan cuba lagi.');
        }
    }
}
