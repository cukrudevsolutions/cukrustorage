<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';

use Cukru\AdminAuth;
use Cukru\BookingRepository;
use Cukru\RateCard;
use Cukru\Settings;

AdminAuth::requireLogin();

$id = (int) ($_GET['id'] ?? 0);
$booking = $id > 0 ? BookingRepository::findById($id) : null;

if (!$booking) {
    http_response_code(404);
    flash_set('error', 'Booking not found.');
    redirect('bookings.php');
}

$statusLabels = [
    'pending_approval' => 'Waiting for Approval',
    'approved' => 'Approved',
    'in_storage' => 'Items in Storage',
    'ready_for_return' => 'Ready to Collect',
    'returned' => 'Collected',
    'overdue' => 'Overdue — Please Collect',
    'cancelled' => 'Cancelled',
];

$returnWindowEnd = new DateTimeImmutable(Settings::get('return_window_end', '2026-10-09'));
$referenceDate = $booking['returned_at'] ? new DateTimeImmutable($booking['returned_at']) : null;
$overdue = RateCard::calculateOverdue($returnWindowEnd, $referenceDate);

$slipAvailable = in_array($booking['status'], ['in_storage', 'ready_for_return', 'returned', 'overdue'], true) && $booking['qr_token'];

$cancelReason = null;
if ($booking['status'] === 'cancelled') {
    foreach (array_reverse(BookingRepository::getLogs($id)) as $log) {
        if ($log['status_baru'] === 'cancelled') {
            $cancelReason = $log['notes'];
            break;
        }
    }
}

$pageTitle = 'Owner Preview — ' . $booking['booking_ref'];
require __DIR__ . '/partials/header.php';
?>

<div style="display:flex;align-items:center;gap:var(--space-2);margin-bottom:var(--space-3);">
    <a href="bookings.php" style="color:var(--color-muted);font-size:0.88rem;"><i class="fa-solid fa-arrow-left"></i> All Bookings</a>
    <span class="field-hint" style="margin-left:auto;font-size:0.84rem;color:var(--color-muted);">Admin preview of owner storage view</span>
</div>

<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:var(--space-2);margin-bottom:var(--space-3);">
        <div>
            <h2 style="margin-bottom:var(--space-2);">Booking #<?= e($booking['booking_ref']) ?></h2>
            <span class="badge badge-<?= e($booking['status']) ?>"><?= e($statusLabels[$booking['status']] ?? $booking['status']) ?></span>
        </div>
        <?php if ($slipAvailable): ?>
            <a class="btn btn-sm btn-secondary" href="../slip.php?ref=<?= urlencode($booking['booking_ref']) ?>" target="_blank"><i class="fa-solid fa-receipt"></i> Slip</a>
        <?php endif; ?>
    </div>

    <div class="kv"><span class="k">Customer</span><span class="v"><?= e($booking['nama']) ?></span></div>
    <div class="kv"><span class="k">Boxes</span><span class="v"><?= (int) $booking['bilangan_kotak'] ?></span></div>
    <div class="kv"><span class="k">Service</span><span class="v"><?= $booking['jenis_servis'] === 'pickup' ? 'Team Pickup' : 'Self Drop-off' ?></span></div>
    <div class="kv"><span class="k">Drop-off/Pickup Date</span><span class="v"><?= e($booking['tarikh_dicadang']) ?></span></div>
    <div class="kv"><span class="k">Collection Period</span><span class="v"><?= e(Settings::get('return_window_start')) ?> – <?= e(Settings::get('return_window_end')) ?></span></div>
    <div class="kv">
        <span class="k">Price</span>
        <span class="v"><?= $booking['harga_total'] !== null ? rm((float) $booking['harga_total']) : '<em style="font-style:italic;color:var(--color-warning);font-weight:600;">Pending admin approval</em>' ?></span>
    </div>

    <?php if ($booking['status'] === 'cancelled'): ?>
        <div class="alert alert-error" style="margin-top:var(--space-4);margin-bottom:0;">
            <span>This booking has been cancelled.<?= $cancelReason ? ' Reason: ' . e($cancelReason) : '' ?></span>
        </div>
    <?php endif; ?>

    <?php if ($booking['status'] === 'overdue' || ($overdue['days'] > 0 && $booking['status'] !== 'returned')): ?>
        <div class="alert alert-error" style="margin-top:var(--space-4);margin-bottom:0;">
            <span>Your items are past the Return Period by <strong><?= $overdue['days'] ?> day(s)</strong>.
            Outstanding charge: <strong><?= rm($overdue['amount']) ?></strong>.</span>
        </div>
    <?php endif; ?>

    <?php
    $photoSlots = array_filter([
        1 => $booking['foto_storan_1'],
        2 => $booking['foto_storan_2'],
        3 => $booking['foto_storan_3'],
    ], static fn (?string $photo): bool => $photo !== null && $photo !== '');
    if (!empty($photoSlots)):
    ?>
        <hr class="section-divider">
        <h3 class="eyebrow" style="margin-bottom:var(--space-3);"><i class="fa-solid fa-camera"></i> Items at Storage Location</h3>
        <div class="photo-grid">
            <?php foreach ($photoSlots as $slot => $foto): ?>
                <?php $photoUrl = '../storage-photo.php?ref=' . urlencode($booking['booking_ref']) . '&slot=' . $slot; ?>
                <a href="<?= e($photoUrl) ?>" target="_blank" class="photo-slot" style="display:block;">
                    <img src="<?= e($photoUrl) ?>" alt="Storage photo <?= $slot ?>">
                </a>
            <?php endforeach; ?>
        </div>
        <p class="field-hint" style="margin-top:var(--space-3);">These photos were taken by the admin for the customer to verify their items at collection.</p>
    <?php endif; ?>

    <?php if ($booking['status'] === 'approved'): ?>
        <div class="alert alert-info" style="margin-top:var(--space-4);margin-bottom:0;">
            <span>The slip & QR code will be available here once the items are confirmed in storage.</span>
        </div>
    <?php elseif ($slipAvailable): ?>
        <hr class="section-divider">
        <div class="qr-box">
            <img src="../qr-image.php?ref=<?= urlencode($booking['booking_ref']) ?>" alt="Booking QR Code">
            <p class="field-hint" style="margin-top:var(--space-3);">Show this QR code to the admin when collecting the items.</p>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/partials/footer.php';
