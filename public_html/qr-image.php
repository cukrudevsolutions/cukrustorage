<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';

use Cukru\OwnerAuth;
use Cukru\AdminAuth;
use Cukru\BookingRepository;
use Cukru\QrCode;

$ref = trim((string) ($_GET['ref'] ?? ''));
$booking = $ref !== '' ? BookingRepository::findByRef($ref) : null;

$allowed = AdminAuth::isLoggedIn()
    || (OwnerAuth::isLoggedIn() && $booking && in_array((int) $booking['id'], OwnerAuth::bookingIds(), true));

if (!$booking || !$booking['qr_token'] || !$allowed) {
    http_response_code(404);
    exit;
}

header('Content-Type: image/png');
header('Cache-Control: private, max-age=3600');
echo QrCode::pngBinary($booking['qr_token'], 300);
