<?php
declare(strict_types=1);

namespace Cukru;

final class Settings
{
    private static ?array $cache = null;

    private static function loadAll(): array
    {
        if (self::$cache === null) {
            $stmt = Database::pdo()->query('SELECT setting_key, setting_value FROM settings');
            self::$cache = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
        }
        return self::$cache;
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $all = self::loadAll();
        return $all[$key] ?? $default;
    }

    public static function getFloat(string $key, float $default = 0.0): float
    {
        $value = self::get($key);
        return $value !== null ? (float) $value : $default;
    }

    public static function getInt(string $key, int $default = 0): int
    {
        $value = self::get($key);
        return $value !== null ? (int) $value : $default;
    }

    public static function set(string $key, string $value): void
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO settings (setting_key, setting_value, updated_at) VALUES (:k, :v, NOW())
             ON DUPLICATE KEY UPDATE setting_value = :v2, updated_at = NOW()'
        );
        $stmt->execute(['k' => $key, 'v' => $value, 'v2' => $value]);
        self::$cache = null;
    }

    public static function all(): array
    {
        return self::loadAll();
    }
}
