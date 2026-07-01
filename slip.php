<?php
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';

use Cukru\OwnerAuth;
use Cukru\AdminAuth;
use Cukru\BookingRepository;
use Cukru\Settings;

$ref = trim((string) ($_GET['ref'] ?? ''));
$token = trim((string) ($_GET['token'] ?? ''));
$booking = $ref !== '' ? BookingRepository::findByRef($ref) : null;

// The slip is only released once the items are confirmed in storage - not right at approval
// (the admin can still view/print it earlier via their own access, e.g. to label the box).
$slipReleasedToCustomer = $booking && in_array($booking['status'], ['in_storage', 'ready_for_return', 'returned', 'overdue'], true);

$isAdmin = AdminAuth::isLoggedIn();
$allowed = $isAdmin
    || (OwnerAuth::isLoggedIn() && $booking && in_array((int) $booking['id'], OwnerAuth::bookingIds(), true) && $slipReleasedToCustomer)
    || ($booking && $booking['qr_token'] && $token !== '' && hash_equals($booking['qr_token'], $token) && $slipReleasedToCustomer);

if (!$booking || !$booking['qr_token'] || !$allowed) {
    http_response_code(404);
    exit('Slip not found.');
}

$siteName = Settings::get('site_name', 'CukruStorage');
$unclaimedDays = Settings::getInt('unclaimed_days', 30);
$overdueRate = Settings::getFloat('overdue_rate_per_day', 10);
$servisLabel = $booking['jenis_servis'] === 'pickup' ? 'Pickup by Our Team' : 'Self Drop-off by Customer';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Booking Confirmation Slip <?= e($booking['booking_ref']) ?> - <?= e($siteName) ?></title>
<link rel="icon" type="image/png" href="assets/images/favicon.png">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
<link rel="stylesheet" href="assets/css/style.css">
<style>
    .slip-wrap { max-width: 980px; }
    .slip-doc { padding: var(--space-6); }
    .slip-header { text-align: center; padding-bottom: var(--space-4); margin-bottom: var(--space-5); border-bottom: 2px solid var(--color-text); }
    .slip-header h1 { margin-bottom: 2px; }
    .slip-ref { font-weight: 800; font-size: 1.15rem; letter-spacing: .03em; margin-top: var(--space-2); }
    .slip-grid { display: block; }
    .slip-terms { background: var(--color-bg); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: var(--space-4); height: 100%; }
    .slip-terms ul { font-size: 0.82rem; padding-left: 18px; margin: 0; line-height: 1.65; }
    .slip-terms li { margin-bottom: var(--space-2); }
    .slip-footer { text-align: center; margin-top: var(--space-5); padding-top: var(--space-3); border-top: 1px dashed var(--color-border); }

    @media screen and (min-width: 820px), print {
        .slip-grid { display: grid; grid-template-columns: 1.15fr 1fr; gap: var(--space-6); align-items: start; }
        .qr-box { padding-top: 0; }
    }

    @media print {
        @page { size: landscape; margin: 8mm; }
        body { background: #fff; font-size: 12px; }
        .slip-wrap { max-width: none; }
        .slip-doc {
            box-shadow: none; border: none; padding: 0;
            --space-1: 2px; --space-2: 3px; --space-3: 5px;
            --space-4: 7px; --space-5: 8px; --space-6: 10px;
        }
        .slip-doc h1 { font-size: 1.15rem; }
        .slip-doc .slip-header { padding-bottom: var(--space-2); border-bottom-width: 1px; }
        .slip-doc .qr-box { padding: 0; }
        .slip-doc .qr-box img { width: 85px; height: 85px; }
        .slip-doc .slip-ref { font-size: 0.92rem; }
        .slip-doc .kv { font-size: 0.8rem; }
        .slip-doc .slip-terms ul { font-size: 0.74rem; line-height: 1.35; }
        .slip-doc .slip-terms li { margin-bottom: var(--space-1); }
        .slip-doc .field-hint { font-size: 0.68rem; }
        .slip-doc .eyebrow { font-size: 0.65rem; }
    }
</style>
</head>
<body>
<div class="container slip-wrap">

<div class="card no-print" style="text-align:center;display:flex;gap:var(--space-2);justify-content:center;flex-wrap:wrap;">
    <button class="btn" onclick="window.print()"><i class="fa-solid fa-print"></i> Print / Save as PDF</button>
    <?php if ($isAdmin): ?><a class="btn btn-secondary" href="admin/booking-detail.php?id=<?= (int) $booking['id'] ?>">&larr; Back to Booking Details</a><?php endif; ?>
</div>

<div class="card slip-doc">
    <div class="slip-header">
        <img src="assets/images/favicon.png" alt="" style="width:40px;height:40px;border-radius:var(--radius-sm);display:block;margin:0 auto var(--space-2);">
        <h1><?= brand_name_html($siteName) ?></h1>
        <p class="muted">Booking Confirmation Slip</p>
    </div>

    <div class="slip-grid">
        <div>
            <div class="qr-box" style="padding-top:0;">
                <img src="qr-image.php?ref=<?= urlencode($booking['booking_ref']) ?>&token=<?= urlencode($token) ?>" alt="Booking QR Code">
                <p class="field-hint" style="margin-top:var(--space-2);margin-bottom:0;">Booking Reference No.</p>
                <p class="slip-ref"><?= e($booking['booking_ref']) ?></p>
            </div>

            <hr class="section-divider">
            <h3 class="eyebrow">Customer Details</h3>
            <div class="kv"><span class="k">Full Name</span><span class="v"><?= e($booking['nama']) ?></span></div>
            <div class="kv"><span class="k">Phone Number</span><span class="v"><?= e(format_phone($booking['no_telefon'])) ?></span></div>

            <hr class="section-divider">
            <h3 class="eyebrow">Booking Details</h3>
            <div class="kv"><span class="k">Number of Boxes</span><span class="v"><?= (int) $booking['bilangan_kotak'] ?></span></div>
            <div class="kv"><span class="k">Service Type</span><span class="v"><?= e($servisLabel) ?></span></div>
            <?php if ($booking['jenis_servis'] === 'pickup'): ?>
                <div class="kv"><span class="k">Pickup Address</span><span class="v"><?= e($booking['alamat_pickup']) ?></span></div>
            <?php endif; ?>
            <div class="kv"><span class="k">Proposed Date</span><span class="v"><?= e($booking['tarikh_dicadang']) ?></span></div>
            <div class="kv"><span class="k">Return Period</span><span class="v"><?= e(Settings::get('return_window_start')) ?> to <?= e(Settings::get('return_window_end')) ?></span></div>

            <hr class="section-divider">
            <h3 class="eyebrow">Payment Details</h3>
            <div class="kv"><span class="k">Storage Charge</span><span class="v"><?= rm((float) $booking['harga_storage']) ?></span></div>
            <?php if ($booking['harga_pickup'] !== null): ?>
                <div class="kv"><span class="k">Pickup Charge (Distance + Labour)</span><span class="v"><?= rm((float) $booking['harga_pickup']) ?></span></div>
            <?php endif; ?>
            <div class="kv"><span class="k"><strong>Grand Total</strong></span><span class="v"><strong style="font-size:1.05rem;"><?= rm((float) $booking['harga_total']) ?></strong></span></div>
        </div>

        <div class="slip-terms">
            <h3 class="eyebrow">Summary of Key Terms &amp; Conditions</h3>
            <ul>
                <li>The Customer is responsible for collecting their items within the Return Period stated above (Clause 6.1).</li>
                <li>Collection after the end of that period incurs a late charge of <strong><?= rm($overdueRate) ?> per day</strong> (Clause 6.2).</li>
                <li>Items not claimed within <strong><?= $unclaimedDays ?> days</strong> after the end of the Return Period may be disposed of, sold, or donated by us (Clause 7).</li>
                <li>Customers are advised not to store high-value items, important documents, or cash (Clause 8.2).</li>
                <li>Items strictly prohibited from storage: flammable/explosive materials, illegal substances, weapons, live animals, and the like (Clause 9).</li>
            </ul>
            <p class="field-hint" style="margin-top:var(--space-3);margin-bottom:0;">The full Terms &amp; Conditions can be found on the <a href="terms.php" target="_blank">Terms &amp; Conditions</a> page or via the customer's Status Dashboard.</p>
        </div>
    </div>

    <div class="slip-footer">
        <p class="field-hint" style="margin:0;">This document is generated automatically by the system and is valid without a signature. Printed on <?= date('d/m/Y, h:i A') ?>.</p>
    </div>
</div>

</div>
</body>
</html>
