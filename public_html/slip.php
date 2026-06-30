<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';

use Cukru\OwnerAuth;
use Cukru\AdminAuth;
use Cukru\BookingRepository;
use Cukru\Settings;

$ref = trim((string) ($_GET['ref'] ?? ''));
$booking = $ref !== '' ? BookingRepository::findByRef($ref) : null;

$isAdmin = AdminAuth::isLoggedIn();
$allowed = $isAdmin
    || (OwnerAuth::isLoggedIn() && $booking && in_array((int) $booking['id'], OwnerAuth::bookingIds(), true));

if (!$booking || !$booking['qr_token'] || !$allowed) {
    http_response_code(404);
    exit('Slip tidak ditemui.');
}

$siteName = Settings::get('site_name', 'CukruStorage');
$unclaimedDays = Settings::getInt('unclaimed_days', 30);
$overdueRate = Settings::getFloat('overdue_rate_per_day', 10);
?>
<!DOCTYPE html>
<html lang="ms">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Slip Booking <?= e($booking['booking_ref']) ?> - <?= e($siteName) ?></title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="container">

<div class="card no-print" style="text-align:center;">
    <button class="btn" onclick="window.print()">Cetak / Simpan PDF</button>
    <?php if ($isAdmin): ?><a class="btn btn-secondary" href="admin/booking-detail.php?id=<?= (int) $booking['id'] ?>">Kembali ke Butiran Booking</a><?php endif; ?>
</div>

<div class="card">
    <div style="text-align:center;margin-bottom:10px;">
        <h1 style="margin-bottom:2px;"><?= e($siteName) ?></h1>
        <p class="muted">Slip Pengesahan Booking</p>
    </div>

    <div class="qr-box">
        <img src="qr-image.php?ref=<?= urlencode($booking['booking_ref']) ?>" alt="QR Code">
        <p style="font-weight:700;"><?= e($booking['booking_ref']) ?></p>
    </div>

    <h3>Maklumat Pelanggan</h3>
    <div class="kv"><span class="k">Nama</span><span class="v"><?= e($booking['nama']) ?></span></div>
    <div class="kv"><span class="k">No. Telefon</span><span class="v"><?= e($booking['no_telefon']) ?></span></div>

    <h3 style="margin-top:16px;">Maklumat Simpanan</h3>
    <div class="kv"><span class="k">Bilangan Kotak</span><span class="v"><?= (int) $booking['bilangan_kotak'] ?></span></div>
    <div class="kv"><span class="k">Jenis Servis</span><span class="v"><?= $booking['jenis_servis'] === 'pickup' ? 'Pickup oleh Team' : 'Drop-off Sendiri' ?></span></div>
    <?php if ($booking['jenis_servis'] === 'pickup'): ?>
        <div class="kv"><span class="k">Alamat Pickup</span><span class="v"><?= e($booking['alamat_pickup']) ?></span></div>
    <?php endif; ?>
    <div class="kv"><span class="k">Tarikh Dicadang</span><span class="v"><?= e($booking['tarikh_dicadang']) ?></span></div>
    <div class="kv"><span class="k">Return Window</span><span class="v"><?= e(Settings::get('return_window_start')) ?> - <?= e(Settings::get('return_window_end')) ?></span></div>

    <h3 style="margin-top:16px;">Harga</h3>
    <div class="kv"><span class="k">Caj Storan</span><span class="v"><?= rm((float) $booking['harga_storage']) ?></span></div>
    <?php if ($booking['harga_pickup'] !== null): ?>
        <div class="kv"><span class="k">Caj Pickup (jarak + upah)</span><span class="v"><?= rm((float) $booking['harga_pickup']) ?></span></div>
    <?php endif; ?>
    <div class="kv"><span class="k"><strong>Jumlah</strong></span><span class="v"><strong><?= rm((float) $booking['harga_total']) ?></strong></span></div>

    <h3 style="margin-top:16px;">Ringkasan Terma Penting</h3>
    <ul style="font-size:0.88rem;padding-left:18px;">
        <li>Pelanggan bertanggungjawab mengambil semula barang dalam tempoh Return Window di atas (Klausa 6.1).</li>
        <li>Pengambilan selepas Return Window dikenakan caj lewat <strong><?= rm($overdueRate) ?>/hari</strong> (Klausa 6.2).</li>
        <li>Barang tidak dituntut selepas <strong><?= $unclaimedDays ?> hari</strong> dari tamat Return Window berhak dilupuskan/dijual/disumbang (Klausa 7).</li>
        <li>Jangan simpan barang berharga tinggi, dokumen penting, atau wang tunai (Klausa 8.2).</li>
        <li>Barang dilarang: bahan mudah terbakar/letupan, dadah, senjata, haiwan hidup, dll (Klausa 9).</li>
    </ul>
    <p class="muted">Terma &amp; Syarat penuh: lihat <a href="terms.php" target="_blank">halaman Terma &amp; Syarat</a> atau Dashboard Tracking anda.</p>

    <p class="muted" style="margin-top:18px;text-align:center;">Dicetak pada <?= date('d/m/Y H:i') ?></p>
</div>

</div>
</body>
</html>
