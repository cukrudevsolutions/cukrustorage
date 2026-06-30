<?php
declare(strict_types=1);

namespace Cukru;

final class PinReset
{
    public static function request(string $rawPhone, string $email): void
    {
        $phone = Validation::normalizePhone($rawPhone);
        $bookings = BookingRepository::findAllByPhone($phone);

        $match = null;
        foreach ($bookings as $booking) {
            if (strcasecmp($booking['email'], $email) === 0) {
                $match = $booking;
                break;
            }
        }

        // Sentiasa "berjaya" secara visual (elak attacker imbas no. telefon/emel sah)
        if (!$match) {
            return;
        }

        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + 3600);

        $stmt = Database::pdo()->prepare(
            'INSERT INTO pin_reset_tokens (booking_id, token, expires_at) VALUES (?, ?, ?)'
        );
        $stmt->execute([$match['id'], $token, $expiresAt]);

        $resetUrl = APP_URL . base_path() . '/reset-pin.php?token=' . urlencode($token);
        $siteName = e(Settings::get('site_name', 'CukruStorage'));

        $body = "<p>Salam {$match['nama']},</p>"
            . "<p>Kami terima permintaan untuk reset PIN akaun {$siteName} anda (No. Booking: {$match['booking_ref']}).</p>"
            . "<p><a href=\"{$resetUrl}\">Klik di sini untuk set PIN baharu</a> (pautan ini sah selama 1 jam sahaja).</p>"
            . "<p>Jika anda tidak membuat permintaan ini, sila abaikan emel ini.</p>";

        Mailer::send($match['email'], "Reset PIN - {$siteName}", $body);
    }

    public static function validateToken(string $token): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM pin_reset_tokens WHERE token = ? AND used = 0 AND expires_at > NOW()'
        );
        $stmt->execute([$token]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function reset(string $token, string $newPin): bool
    {
        $tokenRow = self::validateToken($token);
        if (!$tokenRow) {
            return false;
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $hash = password_hash($newPin, PASSWORD_DEFAULT);
            $upd = $pdo->prepare('UPDATE bookings SET pin_hash = ? WHERE id = ?');
            $upd->execute([$hash, $tokenRow['booking_id']]);

            $markUsed = $pdo->prepare('UPDATE pin_reset_tokens SET used = 1 WHERE id = ?');
            $markUsed->execute([$tokenRow['id']]);

            $pdo->commit();
            return true;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
