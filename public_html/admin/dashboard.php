<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/config.php';

use Cukru\AdminAuth;
use Cukru\BookingRepository;
use Cukru\Database;

AdminAuth::requireLogin();
BookingRepository::syncOverdueStatuses();

$pdo = Database::pdo();
$counts = [];
foreach (['pending_approval', 'approved', 'in_storage', 'ready_for_return', 'returned', 'overdue'] as $status) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM bookings WHERE status = ?');
    $stmt->execute([$status]);
    $counts[$status] = (int) $stmt->fetchColumn();
}

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

<div class="stats-grid">
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
    ?>
    <a href="bookings.php?status=<?= $key ?>" class="card stat-card" style="display:block;">
        <div class="stat-num"><?= $counts[$key] ?></div>
        <div class="muted"><?= e($label) ?></div>
    </a>
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
