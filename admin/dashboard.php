<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';

use Cukru\AdminAuth;
use Cukru\BookingRepository;
use Cukru\Database;

AdminAuth::requireLogin();
BookingRepository::syncOverdueStatuses();

$pdo = Database::pdo();
$counts = [];
$statuses = ['pending_approval', 'approved', 'in_storage', 'ready_for_return', 'returned', 'overdue'];
foreach ($statuses as $status) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM bookings WHERE status = ?');
    $stmt->execute([$status]);
    $counts[$status] = (int) $stmt->fetchColumn();
}

$totalBookings = array_sum($counts);

$boxesStmt = $pdo->prepare(
    'SELECT COALESCE(SUM(bilangan_kotak), 0) FROM bookings WHERE status IN (' . implode(',', array_fill(0, count($statuses), '?')) . ')'
);
$boxesStmt->execute($statuses);
$totalBoxes = (int) $boxesStmt->fetchColumn();

// Revenue figures count storage charge only — pickup charge is a logistics
// pass-through cost, not real revenue, so it's excluded here.
$totals = [];
$stmt = $pdo->query("SELECT status, SUM(harga_storage) AS total_amount FROM bookings GROUP BY status");
while ($row = $stmt->fetch()) {
    $totals[$row['status']] = (float) $row['total_amount'];
}

$confirmedTotal = 0.0;
foreach (['approved', 'in_storage', 'ready_for_return', 'returned', 'overdue'] as $status) {
    $confirmedTotal += $totals[$status] ?? 0.0;
}
$pendingTotal = $totals['pending_approval'] ?? 0.0;
$inStorageTotal = $totals['in_storage'] ?? 0.0;
$collectedTotal = $totals['returned'] ?? 0.0;

$pending = BookingRepository::listFiltered('pending_approval', null, 1, 10);

// Upcoming schedule: drop-offs/pickups that haven't happened yet (still
// pending_approval or approved - once a booking reaches in_storage the
// drop-off/pickup already took place). Includes anything already overdue
// (proposed date has passed with no action) so staff can catch missed ones,
// plus everything due in the next 7 days.
$scheduleCutoff = date('Y-m-d', strtotime('+7 days'));
$scheduleStmt = $pdo->prepare(
    "SELECT * FROM bookings
     WHERE status IN ('pending_approval', 'approved')
       AND tarikh_dicadang <= ?
     ORDER BY tarikh_dicadang ASC"
);
$scheduleStmt->execute([$scheduleCutoff]);
$scheduleRows = $scheduleStmt->fetchAll();

$today = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));
$scheduleGroups = ['overdue' => [], 'today' => [], 'tomorrow' => [], 'later' => []];
foreach ($scheduleRows as $b) {
    $d = $b['tarikh_dicadang'];
    if ($d < $today) {
        $scheduleGroups['overdue'][] = $b;
    } elseif ($d === $today) {
        $scheduleGroups['today'][] = $b;
    } elseif ($d === $tomorrow) {
        $scheduleGroups['tomorrow'][] = $b;
    } else {
        $scheduleGroups['later'][] = $b;
    }
}
$scheduleSections = [
    'overdue'  => ['label' => 'Overdue — Not Yet Actioned', 'class' => 'schedule-overdue'],
    'today'    => ['label' => 'Today', 'class' => 'schedule-today'],
    'tomorrow' => ['label' => 'Tomorrow', 'class' => 'schedule-tomorrow'],
    'later'    => ['label' => 'Later This Week', 'class' => 'schedule-later'],
];

$pageTitle = 'Dashboard';
require __DIR__ . '/partials/header.php';
?>

<h1>Admin Dashboard</h1>
<p class="muted">Summary of all current booking statuses.</p>

<?php if ($counts['pending_approval'] > 0): ?>
    <div class="alert alert-info">
        <span><strong><?= $counts['pending_approval'] ?> booking request(s)</strong> are awaiting your approval. <a href="bookings.php?status=pending_approval">View all &rarr;</a></span>
    </div>
<?php endif; ?>

<div class="card">
    <h2><i class="fa-solid fa-calendar-check"></i> Upcoming Drop-off / Pickup Schedule</h2>
    <p class="muted" style="margin-bottom:var(--space-4);">Confirmed and pending bookings due within the next 7 days, so you know what's happening today, tomorrow, and beyond.</p>

    <?php if (empty($scheduleRows)): ?>
        <div class="empty-state">
            <div class="icon"><i class="fa-solid fa-calendar-xmark"></i></div>
            <p>Nothing due in the next 7 days.</p>
        </div>
    <?php else: ?>
        <?php foreach ($scheduleSections as $key => $meta): ?>
            <?php if (empty($scheduleGroups[$key])) continue; ?>
            <div class="schedule-group <?= e($meta['class']) ?>">
                <p class="eyebrow schedule-group-label"><?= e($meta['label']) ?> (<?= count($scheduleGroups[$key]) ?>)</p>
                <?php foreach ($scheduleGroups[$key] as $b): ?>
                    <a href="booking-detail.php?id=<?= (int) $b['id'] ?>" class="schedule-item">
                        <div class="schedule-item-icon">
                            <i class="fa-solid <?= $b['jenis_servis'] === 'pickup' ? 'fa-truck' : 'fa-store' ?>"></i>
                        </div>
                        <div class="schedule-item-body">
                            <strong><?= e($b['nama']) ?></strong>
                            <span class="muted"><?= e($b['booking_ref']) ?> &middot; <?= (int) $b['bilangan_kotak'] ?> box(es) &middot; <?= $b['jenis_servis'] === 'pickup' ? 'Team Pickup' : 'Self Drop-off' ?></span>
                        </div>
                        <div class="schedule-item-date">
                            <span><?= date('j M', strtotime($b['tarikh_dicadang'])) ?></span>
                            <span class="badge badge-<?= e($b['status']) ?>"><?= $b['status'] === 'pending_approval' ? 'Pending' : 'Approved' ?></span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<div class="rev-grid">
    <div class="card stat-card">
        <div class="stat-num"><?= $totalBookings ?></div>
        <div class="muted">Total Bookings</div>
        <p class="field-hint">All bookings recorded in the system.</p>
    </div>
    <div class="card stat-card">
        <div class="stat-num"><?= $totalBoxes ?></div>
        <div class="muted">Total Boxes</div>
        <p class="field-hint">Total boxes across all bookings.</p>
    </div>
    <div class="card stat-card">
        <div class="stat-num"><?= rm($pendingTotal) ?></div>
        <div class="muted">Pending Approval</div>
        <p class="field-hint">Storage value waiting for admin approval.</p>
    </div>
    <div class="card stat-card">
        <div class="stat-num"><?= rm($confirmedTotal) ?></div>
        <div class="muted">Confirmed Revenue</div>
        <p class="field-hint">Storage revenue from approved/in-storage/returned bookings.</p>
    </div>
    <div class="card stat-card">
        <div class="stat-num"><?= rm($inStorageTotal) ?></div>
        <div class="muted">In Storage Value</div>
        <p class="field-hint">Storage value currently held, expected once collected.</p>
    </div>
    <div class="card stat-card">
        <div class="stat-num"><?= rm($collectedTotal) ?></div>
        <div class="muted">Collected Revenue</div>
        <p class="field-hint">Storage revenue already received from returned bookings.</p>
    </div>
</div>
<p class="field-hint" style="margin-top:var(--space-2);">Revenue figures show storage charge only — pickup charges are excluded as they're a logistics cost, not store revenue.</p>

<div class="dashboard-graph card">
    <h2>Booking Status Breakdown</h2>
    <?php
    $labels = [
        'pending_approval' => 'Pending Approval',
        'approved' => 'Approved',
        'in_storage' => 'In Storage',
        'ready_for_return' => 'Ready for Return',
        'returned' => 'Returned',
        'overdue' => 'Overdue',
    ];
    foreach ($labels as $key => $label):
        $value = $counts[$key] ?? 0;
        $percent = $totalBookings ? round($value / $totalBookings * 100) : 0;
    ?>
    <div class="graph-row">
        <div class="graph-label"><span><?= e($label) ?></span><span><?= $value ?> (<?= $percent ?>%)</span></div>
        <div class="graph-bar"><div class="graph-fill status-<?= e($key) ?>" style="width:<?= $percent ?>%"></div></div>
    </div>
    <?php endforeach; ?>
</div>

<h2>Latest Requests Awaiting Approval</h2>
<div class="card">
    <?php if (empty($pending['rows'])): ?>
        <div class="empty-state">
            <div class="icon"><i class="fa-solid fa-inbox"></i></div>
            <p>No requests are awaiting approval at this time.</p>
        </div>
    <?php else: ?>
    <div class="table-responsive">
    <table>
        <thead><tr><th>Booking No.</th><th>Name</th><th>Phone</th><th>Boxes</th><th>Service</th><th>Date</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($pending['rows'] as $b): ?>
            <tr>
                <td data-label="Booking No."><?= e($b['booking_ref']) ?></td>
                <td data-label="Name"><?= e($b['nama']) ?></td>
                <td data-label="Phone"><?= e(format_phone($b['no_telefon'])) ?></td>
                <td data-label="Boxes"><?= (int) $b['bilangan_kotak'] ?></td>
                <td data-label="Service"><?= $b['jenis_servis'] === 'pickup' ? 'Pickup' : 'Drop-off' ?></td>
                <td data-label="Date"><?= e($b['tarikh_dicadang']) ?></td>
                <td><a class="btn btn-sm" href="booking-detail.php?id=<?= (int) $b['id'] ?>">Review</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
