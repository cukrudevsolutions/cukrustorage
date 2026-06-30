<?php
declare(strict_types=1);

/** Escape output untuk paparan HTML (elak XSS). */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): never
{
    $url = str_starts_with($path, 'http') ? $path : rtrim(base_path(), '/') . '/' . ltrim($path, '/');
    header('Location: ' . $url);
    exit;
}

/**
 * Path web (bukan filesystem) ke root public_html semasa, contoh "/CUKRUSTORE/public_html"
 * supaya link berfungsi sama ada di subfolder XAMPP atau root domain Hostinger.
 */
function base_path(): string
{
    static $base = null;
    if ($base === null) {
        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        // Jika berada dalam /admin, naik satu tahap supaya base sentiasa root public_html
        $base = preg_replace('#/admin$#', '', $scriptDir);
        $base = rtrim($base, '/');
    }
    return $base;
}

function asset(string $path): string
{
    return base_path() . '/assets/' . ltrim($path, '/');
}

function flash_set(string $key, string $message): void
{
    $_SESSION['_flash'][$key] = $message;
}

function flash_get(string $key): ?string
{
    if (!isset($_SESSION['_flash'][$key])) {
        return null;
    }
    $message = $_SESSION['_flash'][$key];
    unset($_SESSION['_flash'][$key]);
    return $message;
}

/** Format RMxx.xx */
function rm(float $amount): string
{
    return 'RM' . number_format($amount, 2);
}

function old(string $field, string $default = ''): string
{
    return e($_SESSION['_old'][$field] ?? $default);
}

/** Format no. telefon disimpan (digit sahaja) kepada paparan 01X-XXXXXXX yang konsisten. */
function format_phone(string $digits): string
{
    if (!preg_match('/^01\d{8,9}$/', $digits)) {
        return $digits;
    }
    return substr($digits, 0, 3) . '-' . substr($digits, 3);
}

/** Papar nama jenama dengan akhiran "Storage" diwarnakan biru (padan logo), jika ada. */
function brand_name_html(string $name): string
{
    if (preg_match('/^(.*)(Storage)$/i', $name, $m)) {
        return e($m[1]) . '<span class="brand-accent">' . e($m[2]) . '</span>';
    }
    return e($name);
}
