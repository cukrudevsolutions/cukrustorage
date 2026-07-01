<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';

use Cukru\AdminAuth;
use Cukru\Database;

AdminAuth::requireLogin();

$pdo = Database::pdo();

// All pickup bookings, sorted ascending by proposed date, exclude already returned
$stmt = $pdo->query(
    "SELECT * FROM bookings
     WHERE jenis_servis = 'pickup'
     ORDER BY
         CASE status
             WHEN 'pending_approval' THEN 1
             WHEN 'approved' THEN 2
             WHEN 'in_storage' THEN 3
             WHEN 'ready_for_return' THEN 4
             WHEN 'returned' THEN 5
             WHEN 'overdue' THEN 6
         END ASC,
         tarikh_dicadang ASC"
);
$pickups = $stmt->fetchAll();

$statusLabels = [
    'pending_approval' => 'Waiting for Approval',
    'approved'         => 'Approved',
    'in_storage'       => 'Items in Storage',
    'ready_for_return' => 'Ready to Collect',
    'returned'         => 'Collected',
    'overdue'          => 'Overdue',
];

function wa_link(string $phone): string
{
    $digits = preg_replace('/\D/', '', $phone);
    if (str_starts_with($digits, '0')) {
        $digits = '6' . $digits;
    }
    return 'https://wa.me/' . $digits;
}

$pageTitle = 'Pickup List';
require __DIR__ . '/partials/header.php';
?>

<h1><i class="fa-solid fa-truck"></i> Pickup List</h1>
<p class="muted">All bookings requiring team pickup, sorted by date. Tap the WhatsApp icon to contact the customer directly.</p>

<?php if (empty($pickups)): ?>
    <div class="card empty-state">
        <div class="icon"><i class="fa-solid fa-truck"></i></div>
        <p>No pickup bookings found.</p>
    </div>
<?php else: ?>

<?php
$grouped = [];
foreach ($pickups as $p) {
    $grouped[$p['tarikh_dicadang']][] = $p;
}
?>

<?php foreach ($grouped as $date => $items): ?>
    <div style="margin-bottom:var(--space-2);">
        <p class="eyebrow" style="margin:var(--space-4) 0 var(--space-2);"><i class="fa-solid fa-calendar-day"></i> <?= date('j F Y (l)', strtotime($date)) ?></p>
    </div>
    <?php foreach ($items as $b): ?>
    <div class="card" style="margin-bottom:var(--space-3);">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:var(--space-2);flex-wrap:wrap;">
            <div>
                <div style="font-weight:700;font-size:0.88rem;"><?= e($b['booking_ref']) ?></div>
                <span class="badge badge-<?= e($b['status']) ?>" style="margin-top:4px;"><?= e($statusLabels[$b['status']] ?? $b['status']) ?></span>
            </div>
            <div style="display:flex;gap:var(--space-2);align-items:center;flex-shrink:0;">
                <a href="<?= e(wa_link($b['no_telefon'])) ?>" target="_blank" rel="noopener"
                   class="btn btn-sm" style="background:#25D366;border:none;padding:10px 14px;">
                    <svg style="width:16px;height:16px;fill:#fff;display:block;" viewBox="0 0 32 32"><circle cx="16" cy="16" r="16" fill="#25D366"/><path fill="#fff" d="M22.7 9.3a8.9 8.9 0 0 0-14 10.7L7 25l5.2-1.6a8.9 8.9 0 0 0 12.6-8 8.8 8.8 0 0 0-2.1-6.1zm-6.6 13.6a7.4 7.4 0 0 1-3.8-1l-.3-.2-2.8.9.9-2.7-.2-.3a7.4 7.4 0 1 1 13.8-3.7 7.4 7.4 0 0 1-7.6 7zm4-5.5c-.2-.1-1.3-.6-1.5-.7-.2-.1-.3-.1-.5.1l-.7.9c-.1.1-.3.2-.5.1-.2-.1-1-.4-1.9-1.2-.7-.6-1.2-1.4-1.3-1.6-.1-.2 0-.3.1-.5l.4-.4.2-.4v-.4c-.1-.1-.5-1.3-.7-1.8-.2-.4-.4-.4-.5-.4h-.5c-.1 0-.4.1-.6.3-.2.2-.8.8-.8 1.9s.8 2.2 1 2.4c.1.1 1.7 2.6 4 3.6.6.2 1 .4 1.4.5.6.2 1.1.1 1.5.1.5-.1 1.3-.5 1.5-1 .2-.5.2-.9.1-1l-.4-.2z"/></svg>
                </a>
                <a href="booking-detail.php?id=<?= (int) $b['id'] ?>" class="btn btn-sm btn-secondary"><i class="fa-solid fa-eye"></i></a>
            </div>
        </div>

        <div style="margin-top:var(--space-3);">
            <div class="kv"><span class="k">Name</span><span class="v"><?= e($b['nama']) ?></span></div>
            <div class="kv"><span class="k">Phone</span><span class="v"><?= e(format_phone($b['no_telefon'])) ?></span></div>
            <?php if ($b['alamat_pickup']): ?>
            <div class="kv" style="flex-wrap:wrap;gap:4px;">
                <span class="k">Address</span>
                <span class="v" style="font-size:0.82rem;text-align:right;"><?= e($b['alamat_pickup']) ?></span>
            </div>
            <?php endif; ?>
            <?php if ($b['jarak_anggaran']): ?>
            <div class="kv"><span class="k">Est. Distance</span><span class="v"><?= e($b['jarak_anggaran']) ?></span></div>
            <?php endif; ?>
            <div class="kv"><span class="k">Boxes</span><span class="v"><?= (int) $b['bilangan_kotak'] ?></span></div>
            <?php if ($b['harga_total']): ?>
            <div class="kv"><span class="k">Price</span><span class="v"><?= rm((float) $b['harga_total']) ?></span></div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
<?php endforeach; ?>

<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
