<?php
declare(strict_types=1);

namespace Cukru;

use PDO;

final class AdminAuth
{
    public static function isLoggedIn(): bool
    {
        return !empty($_SESSION['admin_id']);
    }

    public static function requireLogin(): void
    {
        if (!self::isLoggedIn()) {
            redirect('admin/login.php');
        }
    }

    public static function username(): ?string
    {
        return $_SESSION['admin_username'] ?? null;
    }

    public static function logout(): void
    {
        unset($_SESSION['admin_id'], $_SESSION['admin_username']);
        session_regenerate_id(true);
    }

    /**
     * @return array{success: bool, message: string}
     */
    public static function attempt(string $username, string $password): array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT * FROM admins WHERE username = ?');
        $stmt->execute([$username]);
        $admin = $stmt->fetch();

        $maxAttempts = Settings::getInt('admin_lockout_attempts', 5);
        $lockoutMinutes = Settings::getInt('admin_lockout_minutes', 15);

        if (!$admin) {
            // Still run password_verify against a dummy hash to avoid a timing attack
            password_verify($password, '$2y$10$abcdefghijklmnopqrstuuVx1y2z3A4b5C6d7E8f9G0h1I2j3K4l5');
            return ['success' => false, 'message' => 'Invalid username or password.'];
        }

        if (!empty($admin['locked_until']) && strtotime($admin['locked_until']) > time()) {
            $minsLeft = (int) ceil((strtotime($admin['locked_until']) - time()) / 60);
            return ['success' => false, 'message' => "Account temporarily locked due to too many failed attempts. Try again in {$minsLeft} minute(s)."];
        }

        if (!password_verify($password, $admin['password_hash'])) {
            $attempts = $admin['failed_attempts'] + 1;

            if ($attempts >= $maxAttempts) {
                $lockedUntil = date('Y-m-d H:i:s', time() + $lockoutMinutes * 60);
                $upd = $pdo->prepare('UPDATE admins SET failed_attempts = 0, locked_until = ? WHERE id = ?');
                $upd->execute([$lockedUntil, $admin['id']]);
                return ['success' => false, 'message' => "Too many failed attempts. Account locked for {$lockoutMinutes} minute(s)."];
            }

            $upd = $pdo->prepare('UPDATE admins SET failed_attempts = ? WHERE id = ?');
            $upd->execute([$attempts, $admin['id']]);
            $remaining = $maxAttempts - $attempts;
            return ['success' => false, 'message' => "Invalid username or password. ({$remaining} attempt(s) left before account lockout)"];
        }

        $upd = $pdo->prepare('UPDATE admins SET failed_attempts = 0, locked_until = NULL, last_login_at = NOW() WHERE id = ?');
        $upd->execute([$admin['id']]);

        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int) $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];

        return ['success' => true, 'message' => 'Login successful.'];
    }
}
