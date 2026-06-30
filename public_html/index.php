<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';

use Cukru\OwnerAuth;
use Cukru\Settings;

if (OwnerAuth::isLoggedIn()) {
    redirect('dashboard.php');
}

$pageTitle = 'Selamat Datang';
require __DIR__ . '/partials/header.php';
?>

<div class="card" style="text-align:center;">
    <h1><?= e(Settings::get('site_name', 'CukruStorage')) ?></h1>
    <p class="muted">Servis simpan barang student semasa cuti semester.</p>
    <p style="margin-top:18px;display:flex;gap:10px;justify-content:center;flex-wrap:wrap;">
        <a class="btn" href="booking.php">Buat Booking Baharu</a>
        <a class="btn btn-secondary" href="login.php">Log Masuk Semak Status</a>
    </p>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
