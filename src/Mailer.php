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
        $siteName = Settings::get('site_name', 'CukruStorage');
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            sprintf('From: %s <noreply@%s>', $siteName, parse_url(APP_URL, PHP_URL_HOST) ?: 'localhost'),
        ];

        return @mail($to, $subject, $htmlBody, implode("\r\n", $headers));
    }
}
