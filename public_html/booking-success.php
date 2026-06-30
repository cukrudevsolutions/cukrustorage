<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';

use Cukru\BookingRepository;

$ref = trim((string) ($_GET['ref'] ?? ''));
$booking = $ref !== '' ? BookingRepository::findByRef($ref) : null;

$pageTitle = 'Permohonan Dihantar';
require __DIR__ . '/partials/header.php';
?>

<div class="card" style="text-align:center;">
    <h1>Permohonan Anda Telah Dihantar</h1>
    <p>Sila tunggu kelulusan admin untuk harga akhir. Anda akan dapat semak status bila-bila masa melalui Dashboard Saya.</p>

    <?php if ($booking): ?>
        <div class="kv"><span class="k">No. Booking</span><span class="v"><?= e($booking['booking_ref']) ?></span></div>
        <div class="kv"><span class="k">Status</span><span class="v"><span class="badge badge-<?= e($booking['status']) ?>">Menunggu Kelulusan</span></span></div>
    <?php endif; ?>

    <p style="margin-top:18px;">
        <a class="btn" href="login.php">Log Masuk untuk Semak Status</a>
    </p>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
