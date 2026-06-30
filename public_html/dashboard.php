<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';

use Cukru\OwnerAuth;
use Cukru\BookingRepository;
use Cukru\RateCard;
use Cukru\Settings;

OwnerAuth::requireLogin();
BookingRepository::syncOverdueStatuses();

$statusLabels = [
    'pending_approval' => 'Menunggu Kelulusan',
    'approved' => 'Diluluskan',
    'in_storage' => 'Dalam Simpanan',
    'ready_for_return' => 'Sedia untuk Diambil',
    'returned' => 'Telah Dipulangkan',
    'overdue' => 'Tertunggak (Overdue)',
];

$bookings = [];
foreach (OwnerAuth::bookingIds() as $id) {
    $b = BookingRepository::findById($id);
    if ($b) {
        $bookings[] = $b;
    }
}

$returnWindowEnd = new DateTimeImmutable(Settings::get('return_window_end', '2026-10-09'));

$pageTitle = 'Dashboard Saya';
require __DIR__ . '/partials/header.php';
?>

<h1>Dashboard Tracking Saya</h1>

<?php if (empty($bookings)): ?>
    <div class="card">Tiada rekod booking ditemui.</div>
<?php endif; ?>

<?php foreach ($bookings as $booking): ?>
    <?php
    $isPending = $booking['status'] === 'pending_approval';
    $referenceDate = $booking['returned_at'] ? new DateTimeImmutable($booking['returned_at']) : null;
    $overdue = RateCard::calculateOverdue($returnWindowEnd, $referenceDate);
    ?>
    <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:8px;">
            <div>
                <h2 style="margin-bottom:2px;">Booking #<?= e($booking['booking_ref']) ?></h2>
                <span class="badge badge-<?= e($booking['status']) ?>"><?= e($statusLabels[$booking['status']] ?? $booking['status']) ?></span>
            </div>
            <?php if (!$isPending && $booking['qr_token']): ?>
                <a class="btn btn-sm btn-secondary" href="slip.php?ref=<?= urlencode($booking['booking_ref']) ?>" target="_blank">Lihat / Cetak Slip</a>
            <?php endif; ?>
        </div>

        <div class="kv"><span class="k">Bilangan Kotak</span><span class="v"><?= (int) $booking['bilangan_kotak'] ?></span></div>
        <div class="kv"><span class="k">Jenis Servis</span><span class="v"><?= $booking['jenis_servis'] === 'pickup' ? 'Pickup oleh Team' : 'Drop-off Sendiri' ?></span></div>
        <div class="kv"><span class="k">Tarikh Dicadang</span><span class="v"><?= e($booking['tarikh_dicadang']) ?></span></div>
        <div class="kv"><span class="k">Return Window</span><span class="v"><?= e(Settings::get('return_window_start')) ?> - <?= e(Settings::get('return_window_end')) ?></span></div>
        <div class="kv">
            <span class="k">Harga</span>
            <span class="v"><?= $isPending ? 'Menunggu kelulusan admin' : rm((float) $booking['harga_total']) ?></span>
        </div>

        <?php if ($booking['status'] === 'overdue' || ($overdue['days'] > 0 && $booking['status'] !== 'returned')): ?>
            <div class="alert alert-error" style="margin-top:14px;">
                Barang anda telah melepasi Return Window selama <strong><?= $overdue['days'] ?> hari</strong>.
                Caj tertunggak: <strong><?= rm($overdue['amount']) ?></strong> (RM<?= number_format(Settings::getFloat('overdue_rate_per_day', 10), 2) ?>/hari).
                Sila hubungi admin untuk aturan pengambilan.
            </div>
        <?php endif; ?>

        <?php if (!$isPending && $booking['qr_token']): ?>
            <div class="qr-box">
                <img src="qr-image.php?ref=<?= urlencode($booking['booking_ref']) ?>" alt="QR Code Booking">
                <p class="muted">Tunjukkan QR ni kepada admin semasa drop-off / ambil semula barang.</p>
            </div>
        <?php endif; ?>
    </div>
<?php endforeach; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
