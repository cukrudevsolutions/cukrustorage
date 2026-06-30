<?php
declare(strict_types=1);

namespace Cukru;

final class SlipMailer
{
    /** Send a simple price-confirmation email once the admin approves the booking (no QR/slip yet). */
    public static function sendPriceConfirmation(array $booking): bool
    {
        $siteName = Settings::get('site_name', 'CukruStorage');
        $servisLabel = $booking['jenis_servis'] === 'pickup' ? 'Pickup by Our Team' : 'Self Drop-off by Customer';
        $nextStep = $booking['jenis_servis'] === 'pickup'
            ? 'Please ensure your items are ready for collection on the proposed date.'
            : 'Please bring your items to drop off on the proposed date.';

        $body = '<p>Dear ' . e($booking['nama']) . ',</p>'
            . '<p>Your booking with <strong>' . e($siteName) . '</strong> has been <strong>approved</strong>. Here is a summary:</p>'
            . '<table cellpadding="6" cellspacing="0" style="border-collapse:collapse;">'
            . '<tr><td>Booking Reference No.</td><td><strong>' . e($booking['booking_ref']) . '</strong></td></tr>'
            . '<tr><td>Number of Boxes</td><td>' . (int) $booking['bilangan_kotak'] . '</td></tr>'
            . '<tr><td>Service Type</td><td>' . e($servisLabel) . '</td></tr>'
            . '<tr><td>Proposed Date</td><td>' . e($booking['tarikh_dicadang']) . '</td></tr>'
            . '<tr><td>Total Payment</td><td><strong>' . rm((float) $booking['harga_total']) . '</strong></td></tr>'
            . '</table>'
            . '<p>' . e($nextStep) . ' Payment is due before or during drop-off/pickup.</p>'
            . '<p>You will receive your official storage confirmation slip and QR code by email once we have received your items.</p>'
            . '<p>You can check your status anytime via your Dashboard (log in using your phone number &amp; PIN).</p>';

        return Mailer::send($booking['email'], "Booking Approved - {$booking['booking_ref']} - {$siteName}", $body);
    }

    /** Send the full storage confirmation slip (with QR code) once the admin confirms the items are in storage. */
    public static function sendStorageSlip(array $booking): bool
    {
        $siteName = Settings::get('site_name', 'CukruStorage');
        $slipUrl = APP_URL . '/slip.php?ref=' . urlencode($booking['booking_ref']) . '&token=' . urlencode($booking['qr_token']);

        $servisLabel = $booking['jenis_servis'] === 'pickup' ? 'Pickup by Our Team' : 'Self Drop-off by Customer';

        $body = '<p>Dear ' . e($booking['nama']) . ',</p>'
            . '<p>We have received your items at <strong>' . e($siteName) . '</strong>. Here is a summary of your booking:</p>'
            . '<table cellpadding="6" cellspacing="0" style="border-collapse:collapse;">'
            . '<tr><td>Booking Reference No.</td><td><strong>' . e($booking['booking_ref']) . '</strong></td></tr>'
            . '<tr><td>Number of Boxes</td><td>' . (int) $booking['bilangan_kotak'] . '</td></tr>'
            . '<tr><td>Service Type</td><td>' . e($servisLabel) . '</td></tr>'
            . '<tr><td>Total Payment</td><td><strong>' . rm((float) $booking['harga_total']) . '</strong></td></tr>'
            . '</table>'
            . '<p>Please <a href="' . e($slipUrl) . '">click here to view/print your slip and QR code</a>. Show this QR code to the admin when you collect your items at the end of the storage period.</p>'
            . '<p>You can also check your status anytime via your Dashboard (log in using your phone number &amp; PIN).</p>';

        return Mailer::send($booking['email'], "Items Received - {$booking['booking_ref']} - {$siteName}", $body);
    }
}
