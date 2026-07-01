<?php
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';

use Cukru\BookingRepository;
use Cukru\OwnerAuth;

$ref = trim((string) ($_GET['ref'] ?? ''));
$slot = (int) ($_GET['slot'] ?? 0);
$slotMap = [1 => 'foto_storan_1', 2 => 'foto_storan_2', 3 => 'foto_storan_3'];

if ($ref === '' || !isset($slotMap[$slot])) {
    http_response_code(404);
    exit('Photo not found.');
}

$booking = BookingRepository::findByRef($ref);
if (!$booking) {
    http_response_code(404);
    exit('Photo not found.');
}

if (!OwnerAuth::isLoggedIn() || !in_array((int) $booking['id'], OwnerAuth::bookingIds(), true)) {
    http_response_code(403);
    exit('Access denied.');
}

$photoKey = $slotMap[$slot];
$photoData = $booking[$photoKey] ?? null;
if ($photoData === null || $photoData === '') {
    http_response_code(404);
    exit('Photo not found.');
}

if (!preg_match('#^data:image/([a-zA-Z0-9+]+);base64,#', $photoData, $matches)) {
    http_response_code(415);
    exit('Unsupported photo format.');
}

[$prefix, $base64Data] = explode(',', $photoData, 2);
$binary = base64_decode($base64Data, true);
if ($binary === false) {
    http_response_code(400);
    exit('Invalid photo data.');
}

$mimeType = 'image/jpeg';
if (stripos($photoData, 'data:image/png;base64,') === 0) {
    $mimeType = 'image/png';
} elseif (stripos($photoData, 'data:image/webp;base64,') === 0) {
    $mimeType = 'image/webp';
}

header('Content-Type: ' . $mimeType);
header('Content-Length: ' . strlen($binary));
header('Cache-Control: private, max-age=3600');
echo $binary;
