<?php
declare(strict_types=1);

namespace Cukru;

final class OwnerAuth
{
    public static function isLoggedIn(): bool
    {
        return !empty($_SESSION['owner_booking_ids']);
    }

    public static function requireLogin(): void
    {
        if (!self::isLoggedIn()) {
            redirect('login.php');
        }
    }

    /** @return int[] */
    public static function bookingIds(): array
    {
        return $_SESSION['owner_booking_ids'] ?? [];
    }

    public static function logout(): void
    {
        unset($_SESSION['owner_booking_ids']);
        session_regenerate_id(true);
    }

    private static function throttleKey(string $phone): string
    {
        return 'owner:' . $phone;
    }

    private static function isLocked(string $phone): ?int
    {
        $stmt = Database::pdo()->prepare('SELECT locked_until FROM login_throttle WHERE identifier = ?');
        $stmt->execute([self::throttleKey($phone)]);
        $row = $stmt->fetch();

        if ($row && !empty($row['locked_until']) && strtotime($row['locked_until']) > time()) {
            return (int) ceil((strtotime($row['locked_until']) - time()) / 60);
        }

        return null;
    }

    private static function registerFailure(string $phone): void
    {
        $pdo = Database::pdo();
        $maxAttempts = Settings::getInt('owner_lockout_attempts', 5);
        $lockoutMinutes = Settings::getInt('owner_lockout_minutes', 15);
        $key = self::throttleKey($phone);

        $stmt = $pdo->prepare('SELECT attempts FROM login_throttle WHERE identifier = ?');
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        $attempts = ($row['attempts'] ?? 0) + 1;

        if ($attempts >= $maxAttempts) {
            $lockedUntil = date('Y-m-d H:i:s', time() + $lockoutMinutes * 60);
            $upd = $pdo->prepare(
                'INSERT INTO login_throttle (identifier, attempts, locked_until) VALUES (?, 0, ?)
                 ON DUPLICATE KEY UPDATE attempts = 0, locked_until = VALUES(locked_until)'
            );
            $upd->execute([$key, $lockedUntil]);
            return;
        }

        $upd = $pdo->prepare(
            'INSERT INTO login_throttle (identifier, attempts) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE attempts = VALUES(attempts)'
        );
        $upd->execute([$key, $attempts]);
    }

    private static function clearThrottle(string $phone): void
    {
        $stmt = Database::pdo()->prepare('DELETE FROM login_throttle WHERE identifier = ?');
        $stmt->execute([self::throttleKey($phone)]);
    }

    /**
     * @return array{success: bool, message: string}
     */
    public static function attempt(string $rawPhone, string $pin): array
    {
        $phone = Validation::normalizePhone($rawPhone);

        if ($locked = self::isLocked($phone)) {
            return ['success' => false, 'message' => "Terlalu banyak percubaan gagal. Sila cuba lagi dalam {$locked} minit."];
        }

        $bookings = BookingRepository::findAllByPhone($phone);
        $matched = [];

        foreach ($bookings as $booking) {
            if (password_verify($pin, $booking['pin_hash'])) {
                $matched[] = (int) $booking['id'];
            }
        }

        if (empty($matched)) {
            self::registerFailure($phone);
            return ['success' => false, 'message' => 'No. telefon atau PIN tidak sah.'];
        }

        self::clearThrottle($phone);
        session_regenerate_id(true);
        $_SESSION['owner_booking_ids'] = $matched;
        $_SESSION['owner_phone'] = $phone;

        return ['success' => true, 'message' => 'Berjaya log masuk.'];
    }
}
