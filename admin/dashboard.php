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
$totals = [];
$stmt = $pdo->query("SELECT status, SUM(COALESCE(harga_total, harga_storage + COALESCE(harga_pickup, 0))) AS total_amount FROM bookings GROUP BY status");
while ($row = $stmt->fetch()) {
    $totals[$row['status']] = (float) $row['total_amount'];
}

$allTotal = array_sum($totals);
$confirmedTotal = 0.0;
foreach (['approved', 'in_storage', 'ready_for_return', 'returned', 'overdue'] as $status) {
    $confirmedTotal += $totals[$status] ?? 0.0;
}
$pendingTotal = $totals['pending_approval'] ?? 0.0;
$inStorageTotal = $totals['in_storage'] ?? 0.0;
$allTotal = max($allTotal, $confirmedTotal + $pendingTotal);

$pending = BookingRepository::listFiltered('pending_approval', null, 1, 10);

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

<div class="rev-grid">
    <div class="card stat-card">
        <div class="stat-num"><?= $totalBookings ?></div>
        <div class="muted">Total Bookings</div>
        <p class="field-hint">All bookings recorded in the system.</p>
    </div>
    <div class="card stat-card">
        <div class="stat-num"><?= rm($allTotal) ?></div>
        <div class="muted">All Booking Value</div>
        <p class="field-hint">Sum of all booking values, including pending and confirmed.</p>
    </div>
    <div class="card stat-card">
        <div class="stat-num"><?= rm($confirmedTotal) ?></div>
        <div class="muted">Confirmed Revenue</div>
        <p class="field-hint">Revenue from approved/in-storage/returned bookings.</p>
    </div>
    <div class="card stat-card">
        <div class="stat-num"><?= rm($pendingTotal) ?></div>
        <div class="muted">Pending Approval</div>
        <p class="field-hint">Value waiting for admin approval.</p>
    </div>
</div>

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
                <td><?= e($b['booking_ref']) ?></td>
                <td><?= e($b['nama']) ?></td>
                <td><?= e(format_phone($b['no_telefon'])) ?></td>
                <td><?= (int) $b['bilangan_kotak'] ?></td>
                <td><?= $b['jenis_servis'] === 'pickup' ? 'Pickup' : 'Drop-off' ?></td>
                <td><?= e($b['tarikh_dicadang']) ?></td>
                <td><a class="btn btn-sm" href="booking-detail.php?id=<?= (int) $b['id'] ?>">Review</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
