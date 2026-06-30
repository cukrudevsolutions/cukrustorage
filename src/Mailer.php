<?php
declare(strict_types=1);

namespace Cukru;

final class Mailer
{
    /**
     * Hantar emel guna fungsi mail() bawaan PHP (berfungsi di Hostinger shared hosting
     * tanpa konfigurasi tambahan). Untuk deliverability lebih baik, boleh ganti dengan
     * SMTP/PHPMailer kemudian - struktur fungsi ni kekal sama.
     */
    public static function send(string $to, string $subject, string $htmlBody): bool
    {
        $from = $_ENV['MAIL_FROM'] ?? 'noreply@example.com';
        $fromName = $_ENV['MAIL_FROM_NAME'] ?? 'CukruStorage';

        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            sprintf('From: %s <%s>', $fromName, $from),
        ];

        return @mail($to, $subject, $htmlBody, implode("\r\n", $headers));
    }
}
