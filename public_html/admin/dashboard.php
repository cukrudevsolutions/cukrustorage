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

<h1>Dashboard Admin</h1>

<?php if ($counts['pending_approval'] > 0): ?>
    <div class="alert alert-info">
        <strong><?= $counts['pending_approval'] ?> permohonan booking</strong> sedang menunggu kelulusan anda.
        <a href="bookings.php?status=pending_approval">Lihat semua</a>
    </div>
<?php endif; ?>

<div class="grid-2" style="grid-template-columns:repeat(3,1fr);gap:14px;">
    <?php
    $labels = [
        'pending_approval' => 'Menunggu Kelulusan',
        'approved' => 'Diluluskan',
        'in_storage' => 'Dalam Simpanan',
        'ready_for_return' => 'Sedia Diambil',
        'returned' => 'Telah Dipulangkan',
        'overdue' => 'Overdue',
    ];
    foreach ($labels as $key => $label):
    ?>
    <div class="card" style="text-align:center;">
        <div style="font-size:1.8rem;font-weight:700;"><?= $counts[$key] ?></div>
        <div class="muted"><?= e($label) ?></div>
        <a class="btn btn-sm btn-secondary" style="margin-top:8px;" href="bookings.php?status=<?= $key ?>">Lihat</a>
    </div>
    <?php endforeach; ?>
</div>

<h2 style="margin-top:24px;">Permohonan Terbaharu Menunggu Kelulusan</h2>
<div class="card">
    <?php if (empty($pending['rows'])): ?>
        <p class="muted">Tiada permohonan menunggu kelulusan buat masa ini.</p>
    <?php else: ?>
    <table>
        <thead><tr><th>No. Booking</th><th>Nama</th><th>Telefon</th><th>Kotak</th><th>Servis</th><th>Tarikh</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($pending['rows'] as $b): ?>
            <tr>
                <td><?= e($b['booking_ref']) ?></td>
                <td><?= e($b['nama']) ?></td>
                <td><?= e($b['no_telefon']) ?></td>
                <td><?= (int) $b['bilangan_kotak'] ?></td>
                <td><?= $b['jenis_servis'] === 'pickup' ? 'Pickup' : 'Drop-off' ?></td>
                <td><?= e($b['tarikh_dicadang']) ?></td>
                <td><a class="btn btn-sm" href="booking-detail.php?id=<?= (int) $b['id'] ?>">Semak</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
