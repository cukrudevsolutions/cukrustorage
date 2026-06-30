<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';

use Cukru\OwnerAuth;
use Cukru\AdminAuth;
use Cukru\BookingRepository;
use Cukru\QrCode;

$ref = trim((string) ($_GET['ref'] ?? ''));
$token = trim((string) ($_GET['token'] ?? ''));
$booking = $ref !== '' ? BookingRepository::findByRef($ref) : null;

// The QR is only released to the customer once the items are confirmed in storage (see slip.php).
$slipReleasedToCustomer = $booking && in_array($booking['status'], ['in_storage', 'ready_for_return', 'returned', 'overdue'], true);

$allowed = AdminAuth::isLoggedIn()
    || (OwnerAuth::isLoggedIn() && $booking && in_array((int) $booking['id'], OwnerAuth::bookingIds(), true) && $slipReleasedToCustomer)
    || ($booking && $booking['qr_token'] && $token !== '' && hash_equals($booking['qr_token'], $token) && $slipReleasedToCustomer);

if (!$booking || !$booking['qr_token'] || !$allowed) {
    http_response_code(404);
    exit;
}

header('Content-Type: image/png');
header('Cache-Control: private, max-age=3600');
echo QrCode::pngBinary($booking['qr_token'], 300);
