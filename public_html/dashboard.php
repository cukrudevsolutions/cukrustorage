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
    'pending_approval' => 'Pending Approval',
    'approved' => 'Approved',
    'in_storage' => 'In Storage',
    'ready_for_return' => 'Ready for Return',
    'returned' => 'Returned',
    'overdue' => 'Overdue',
];

$bookings = [];
foreach (OwnerAuth::bookingIds() as $id) {
    $b = BookingRepository::findById($id);
    if ($b) {
        $bookings[] = $b;
    }
}

$returnWindowEnd = new DateTimeImmutable(Settings::get('return_window_end', '2026-10-09'));

$pageTitle = 'My Dashboard';
require __DIR__ . '/partials/header.php';
?>

<h1>My Tracking Dashboard</h1>
<p class="muted">The current status of your item storage booking(s).</p>

<?php if (empty($bookings)): ?>
    <div class="card empty-state">
        <div class="icon"><i class="fa-solid fa-inbox"></i></div>
        <p>No booking records found.</p>
        <a class="btn" href="booking.php">Make a New Booking</a>
    </div>
<?php endif; ?>

<?php foreach ($bookings as $booking): ?>
    <?php
    $isPending = $booking['status'] === 'pending_approval';
    // The slip/QR is only released to the customer once their items are confirmed in storage (not right at approval).
    $slipAvailable = in_array($booking['status'], ['in_storage', 'ready_for_return', 'returned', 'overdue'], true) && $booking['qr_token'];
    $referenceDate = $booking['returned_at'] ? new DateTimeImmutable($booking['returned_at']) : null;
    $overdue = RateCard::calculateOverdue($returnWindowEnd, $referenceDate);
    ?>
    <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:var(--space-2);margin-bottom:var(--space-3);">
            <div>
                <h2 style="margin-bottom:var(--space-2);">Booking #<?= e($booking['booking_ref']) ?></h2>
                <span class="badge badge-<?= e($booking['status']) ?>"><?= e($statusLabels[$booking['status']] ?? $booking['status']) ?></span>
            </div>
            <?php if ($slipAvailable): ?>
                <a class="btn btn-sm btn-secondary" href="slip.php?ref=<?= urlencode($booking['booking_ref']) ?>" target="_blank"><i class="fa-solid fa-receipt"></i> Slip</a>
            <?php endif; ?>
        </div>

        <div class="kv"><span class="k">Number of Boxes</span><span class="v"><?= (int) $booking['bilangan_kotak'] ?></span></div>
        <div class="kv"><span class="k">Service Type</span><span class="v"><?= $booking['jenis_servis'] === 'pickup' ? 'Team Pickup' : 'Self Drop-off' ?></span></div>
        <div class="kv"><span class="k">Proposed Date</span><span class="v"><?= e($booking['tarikh_dicadang']) ?></span></div>
        <div class="kv"><span class="k">Return Period</span><span class="v"><?= e(Settings::get('return_window_start')) ?> - <?= e(Settings::get('return_window_end')) ?></span></div>
        <div class="kv">
            <span class="k">Price</span>
            <span class="v"><?= $isPending ? '<em style="font-style:italic;color:var(--color-warning);font-weight:600;">Pending admin approval</em>' : rm((float) $booking['harga_total']) ?></span>
        </div>

        <?php if ($booking['status'] === 'overdue' || ($overdue['days'] > 0 && $booking['status'] !== 'returned')): ?>
            <div class="alert alert-error" style="margin-top:var(--space-4);margin-bottom:0;">
                <span>Your items are past the Return Period by <strong><?= $overdue['days'] ?> day(s)</strong>.
                Outstanding charge: <strong><?= rm($overdue['amount']) ?></strong> (RM<?= number_format(Settings::getFloat('overdue_rate_per_day', 10), 2) ?>/day).
                Please contact the admin to arrange collection.</span>
            </div>
        <?php endif; ?>

        <?php
        $fotos = array_filter([$booking['foto_storan_1'], $booking['foto_storan_2'], $booking['foto_storan_3']]);
        if (!empty($fotos)):
        ?>
            <hr class="section-divider">
            <h3 class="eyebrow" style="margin-bottom:var(--space-3);"><i class="fa-solid fa-camera"></i> Items at Storage Location</h3>
            <div class="photo-grid">
                <?php foreach ($fotos as $i => $foto): ?>
                    <a href="<?= e($foto) ?>" target="_blank" class="photo-slot" style="display:block;">
                        <img src="<?= e($foto) ?>" alt="Storage photo <?= $i + 1 ?>">
                    </a>
                <?php endforeach; ?>
            </div>
            <p class="field-hint" style="margin-top:var(--space-3);">These photos were taken by the admin for reference. Please verify your items against these photos at the time of collection.</p>
        <?php endif; ?>

        <?php if ($booking['status'] === 'approved'): ?>
            <div class="alert alert-info" style="margin-top:var(--space-4);margin-bottom:0;">
                <span>Your booking slip & QR code will be available here once we've confirmed your items are in storage.</span>
            </div>
        <?php elseif ($slipAvailable): ?>
            <hr class="section-divider">
            <div class="qr-box">
                <img src="qr-image.php?ref=<?= urlencode($booking['booking_ref']) ?>" alt="Booking QR Code">
                <p class="field-hint" style="margin-top:var(--space-3);">Show this QR code to the admin when collecting your items.</p>
            </div>
        <?php endif; ?>
    </div>
<?php endforeach; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
