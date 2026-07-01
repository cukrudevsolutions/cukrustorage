<?php
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';

use Cukru\Validation;
use Cukru\BookingRepository;

header('Content-Type: application/json');

$raw = trim($_GET['phone'] ?? '');

if (!Validation::isValidMalaysianPhone($raw)) {
    echo json_encode(['status' => 'invalid']);
    exit;
}

$phone = Validation::normalizePhone($raw);
$exists = !empty(BookingRepository::findAllByPhone($phone));

echo json_encode(['status' => $exists ? 'exists' : 'available']);
