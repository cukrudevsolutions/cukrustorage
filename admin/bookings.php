<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';

use Cukru\AdminAuth;
use Cukru\BookingRepository;

AdminAuth::requireLogin();
BookingRepository::syncOverdueStatuses();

$status = trim((string) ($_GET['status'] ?? 'all'));
$search = trim((string) ($_GET['q'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;

$result = BookingRepository::listFiltered($status === 'all' ? null : $status, $search ?: null, $page, $perPage);
$totalPages = max(1, (int) ceil($result['total'] / $perPage));

$statusLabels = [
    'all' => 'All',
    'pending_approval' => 'Pending Approval',
    'approved' => 'Approved',
    'in_storage' => 'In Storage',
    'ready_for_return' => 'Ready for Return',
    'returned' => 'Returned',
    'overdue' => 'Overdue',
];

$pageTitle = 'All Bookings';
require __DIR__ . '/partials/header.php';
?>

<h1>All Bookings</h1>
<p class="muted">Full list, filterable by status or searchable.</p>

<form method="get" class="filter-bar">
    <select name="status" onchange="this.form.submit()">
        <?php foreach ($statusLabels as $key => $label): ?>
            <option value="<?= e($key) ?>" <?= $status === $key ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
    <input type="text" name="q" placeholder="Search name / phone / booking no." value="<?= e($search) ?>" style="flex:2 1 220px;">
    <button type="submit" class="btn btn-sm">Search</button>
    <?php if ($search || $status !== 'all'): ?><a class="btn btn-sm btn-secondary" href="bookings.php">Reset</a><?php endif; ?>
</form>

<div class="card">
    <?php if (empty($result['rows'])): ?>
        <div class="empty-state">
            <div class="icon"><i class="fa-solid fa-magnifying-glass"></i></div>
            <p>No records found.</p>
        </div>
    <?php else: ?>
    <div class="table-responsive">
    <table>
        <thead><tr><th>Booking No.</th><th>Name</th><th>Phone</th><th>Boxes</th><th>Service</th><th>Status</th><th>Total</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($result['rows'] as $b): ?>
            <tr>
                <td><a href="booking-detail.php?id=<?= (int) $b['id'] ?>" class="btn-link" style="font-weight:700;"><?= e($b['booking_ref']) ?></a></td>
                <td>
                    <a href="owner-view.php?id=<?= (int) $b['id'] ?>" class="btn-link"><?= e($b['nama']) ?></a>
                    <div class="field-hint" style="margin-top:4px;font-size:0.75rem;color:var(--color-muted);">Admin preview of owner view</div>
                </td>
                <td><?= e(format_phone($b['no_telefon'])) ?></td>
                <td><?= (int) $b['bilangan_kotak'] ?></td>
                <td><?= $b['jenis_servis'] === 'pickup' ? 'Pickup' : 'Drop-off' ?></td>
                <td><span class="badge badge-<?= e($b['status']) ?>"><?= e($statusLabels[$b['status']] ?? $b['status']) ?></span></td>
                <td><?= $b['harga_total'] !== null ? rm((float) $b['harga_total']) : '-' ?></td>
                <td><a class="btn btn-sm" href="booking-detail.php?id=<?= (int) $b['id'] ?>">View</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>

    <?php if ($totalPages > 1): ?>
        <div style="margin-top:var(--space-4);display:flex;gap:6px;flex-wrap:wrap;">
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <a class="btn btn-sm <?= $p === $page ? '' : 'btn-secondary' ?>"
                   href="?status=<?= e($status) ?>&q=<?= urlencode($search) ?>&page=<?= $p ?>"><?= $p ?></a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
