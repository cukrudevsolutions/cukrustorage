<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/config.php';

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
    'all' => 'Semua',
    'pending_approval' => 'Menunggu Kelulusan',
    'approved' => 'Diluluskan',
    'in_storage' => 'Dalam Simpanan',
    'ready_for_return' => 'Sedia Diambil',
    'returned' => 'Telah Dipulangkan',
    'overdue' => 'Overdue',
];

$pageTitle = 'Semua Booking';
require __DIR__ . '/partials/header.php';
?>

<h1>Semua Booking</h1>

<form method="get" class="filter-bar">
    <select name="status" onchange="this.form.submit()">
        <?php foreach ($statusLabels as $key => $label): ?>
            <option value="<?= e($key) ?>" <?= $status === $key ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
    <input type="text" name="q" placeholder="Cari nama / telefon / no. booking" value="<?= e($search) ?>">
    <button type="submit" class="btn btn-sm">Cari</button>
    <?php if ($search || $status !== 'all'): ?><a class="btn btn-sm btn-secondary" href="bookings.php">Reset</a><?php endif; ?>
</form>

<div class="card">
    <?php if (empty($result['rows'])): ?>
        <p class="muted">Tiada rekod ditemui.</p>
    <?php else: ?>
    <table>
        <thead><tr><th>No. Booking</th><th>Nama</th><th>Telefon</th><th>Kotak</th><th>Servis</th><th>Status</th><th>Jumlah</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($result['rows'] as $b): ?>
            <tr>
                <td><?= e($b['booking_ref']) ?></td>
                <td><?= e($b['nama']) ?></td>
                <td><?= e($b['no_telefon']) ?></td>
                <td><?= (int) $b['bilangan_kotak'] ?></td>
                <td><?= $b['jenis_servis'] === 'pickup' ? 'Pickup' : 'Drop-off' ?></td>
                <td><span class="badge badge-<?= e($b['status']) ?>"><?= e($statusLabels[$b['status']] ?? $b['status']) ?></span></td>
                <td><?= $b['harga_total'] !== null ? rm((float) $b['harga_total']) : '-' ?></td>
                <td><a class="btn btn-sm" href="booking-detail.php?id=<?= (int) $b['id'] ?>">Lihat</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ($totalPages > 1): ?>
        <div style="margin-top:14px;display:flex;gap:6px;flex-wrap:wrap;">
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <a class="btn btn-sm <?= $p === $page ? '' : 'btn-secondary' ?>"
                   href="?status=<?= e($status) ?>&q=<?= urlencode($search) ?>&page=<?= $p ?>"><?= $p ?></a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
