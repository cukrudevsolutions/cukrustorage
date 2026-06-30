<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/config.php';

use Cukru\AdminAuth;
use Cukru\Csrf;
use Cukru\BookingRepository;
use Cukru\RateCard;
use Cukru\Settings;

AdminAuth::requireLogin();

$id = (int) ($_GET['id'] ?? 0);
$booking = $id > 0 ? BookingRepository::findById($id) : null;

if (!$booking) {
    http_response_code(404);
    flash_set('error', 'Booking tidak ditemui.');
    redirect('admin/bookings.php');
}

$updatableStatuses = [
    'in_storage' => 'Dalam Simpanan (IN_STORAGE)',
    'ready_for_return' => 'Sedia untuk Diambil (READY_FOR_RETURN)',
    'returned' => 'Telah Dipulangkan (RETURNED)',
    'overdue' => 'Overdue',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    $action = $_POST['action'] ?? '';

    if ($action === 'approve' && $booking['status'] === 'pending_approval') {
        $hargaStorage = (float) ($_POST['harga_storage'] ?? 0);
        $hargaPickup = $booking['jenis_servis'] === 'pickup' ? (float) ($_POST['harga_pickup'] ?? 0) : null;
        $hargaTotal = $hargaStorage + ($hargaPickup ?? 0);

        if ($hargaStorage < 0 || ($hargaPickup !== null && $hargaPickup < 0)) {
            flash_set('error', 'Harga tidak sah.');
        } else {
            $qrToken = BookingRepository::generateQrToken();
            BookingRepository::approve($id, $hargaStorage, $hargaPickup, $hargaTotal, $qrToken, AdminAuth::username());
            flash_set('success', 'Booking diluluskan. Slip & QR code kini sedia.');
        }
        redirect('admin/booking-detail.php?id=' . $id);
    }

    if ($action === 'update_status' && $booking['status'] !== 'pending_approval') {
        $newStatus = $_POST['new_status'] ?? '';
        $notes = trim($_POST['notes'] ?? '') ?: null;

        if (!array_key_exists($newStatus, $updatableStatuses)) {
            flash_set('error', 'Status tidak sah.');
        } else {
            BookingRepository::updateStatus($id, $newStatus, AdminAuth::username(), $notes);
            flash_set('success', 'Status booking dikemaskini.');
        }
        redirect('admin/booking-detail.php?id=' . $id);
    }
}

$booking = BookingRepository::findById($id);
$logs = BookingRepository::getLogs($id);
$returnWindowEnd = new DateTimeImmutable(Settings::get('return_window_end', '2026-10-09'));
$referenceDate = $booking['returned_at'] ? new DateTimeImmutable($booking['returned_at']) : null;
$overdue = RateCard::calculateOverdue($returnWindowEnd, $referenceDate);

$statusLabels = [
    'pending_approval' => 'Menunggu Kelulusan',
    'approved' => 'Diluluskan',
    'in_storage' => 'Dalam Simpanan',
    'ready_for_return' => 'Sedia untuk Diambil',
    'returned' => 'Telah Dipulangkan',
    'overdue' => 'Tertunggak (Overdue)',
];

$pageTitle = 'Butiran Booking ' . $booking['booking_ref'];
require __DIR__ . '/partials/header.php';
?>

<p><a href="bookings.php">&larr; Kembali ke Senarai Booking</a></p>

<div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:10px;">
    <div>
        <h1 style="margin-bottom:4px;">Booking #<?= e($booking['booking_ref']) ?></h1>
        <span class="badge badge-<?= e($booking['status']) ?>"><?= e($statusLabels[$booking['status']] ?? $booking['status']) ?></span>
    </div>
    <?php if ($booking['qr_token']): ?>
        <a class="btn btn-secondary" href="../slip.php?ref=<?= urlencode($booking['booking_ref']) ?>" target="_blank">Lihat / Cetak Slip</a>
    <?php endif; ?>
</div>

<div class="grid-2" style="margin-top:16px;">
    <div class="card">
        <h3>Maklumat Pelanggan</h3>
        <div class="kv"><span class="k">Nama</span><span class="v"><?= e($booking['nama']) ?></span></div>
        <div class="kv"><span class="k">No. Telefon</span><span class="v"><?= e($booking['no_telefon']) ?></span></div>
        <div class="kv"><span class="k">Emel</span><span class="v"><?= e($booking['email']) ?></span></div>
    </div>
    <div class="card">
        <h3>Maklumat Simpanan</h3>
        <div class="kv"><span class="k">Bilangan Kotak</span><span class="v"><?= (int) $booking['bilangan_kotak'] ?></span></div>
        <div class="kv"><span class="k">Jenis Servis</span><span class="v"><?= $booking['jenis_servis'] === 'pickup' ? 'Pickup oleh Team' : 'Drop-off Sendiri' ?></span></div>
        <?php if ($booking['jenis_servis'] === 'pickup'): ?>
            <div class="kv"><span class="k">Alamat</span><span class="v"><?= nl2br(e($booking['alamat_pickup'])) ?></span></div>
            <div class="kv"><span class="k">Jarak Anggaran</span><span class="v"><?= e($booking['jarak_anggaran'] ?? '-') ?></span></div>
        <?php endif; ?>
        <div class="kv"><span class="k">Tarikh Dicadang</span><span class="v"><?= e($booking['tarikh_dicadang']) ?></span></div>
    </div>
</div>

<?php if ($overdue['days'] > 0 && $booking['status'] !== 'returned'): ?>
    <div class="alert alert-error">
        Booking ni telah overdue selama <strong><?= $overdue['days'] ?> hari</strong> - caj tertunggak: <strong><?= rm($overdue['amount']) ?></strong>.
    </div>
<?php endif; ?>

<?php if ($booking['status'] === 'pending_approval'): ?>
    <div class="card">
        <h2>Kelulusan Booking</h2>
        <p class="muted">Sahkan harga akhir. Caj storan dikira automatik ikut rate card, tapi anda boleh override jika perlu.</p>
        <form method="post">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="approve">

            <label class="required" for="harga_storage">Caj Storan (RM)</label>
            <input type="number" step="0.01" min="0" id="harga_storage" name="harga_storage"
                   value="<?= e((string) RateCard::calculateStorage((int) $booking['bilangan_kotak'])) ?>" required>

            <?php if ($booking['jenis_servis'] === 'pickup'): ?>
                <label class="required" for="harga_pickup">Caj Pickup - Jarak + Upah Angkat (RM)</label>
                <input type="number" step="0.01" min="0" id="harga_pickup" name="harga_pickup" value="0" required>
                <p class="muted" style="margin:4px 0 0;">Sila kira berdasarkan jarak sebenar ke alamat pelanggan di atas.</p>
            <?php endif; ?>

            <button type="submit" class="btn" style="margin-top:16px;" onclick="return confirm('Lulus booking ni dengan harga yang ditetapkan?')">Lulus Booking & Jana QR</button>
        </form>
    </div>
<?php else: ?>
    <div class="card">
        <h2>Harga</h2>
        <div class="kv"><span class="k">Caj Storan</span><span class="v"><?= rm((float) $booking['harga_storage']) ?></span></div>
        <?php if ($booking['harga_pickup'] !== null): ?>
            <div class="kv"><span class="k">Caj Pickup</span><span class="v"><?= rm((float) $booking['harga_pickup']) ?></span></div>
        <?php endif; ?>
        <div class="kv"><span class="k"><strong>Jumlah</strong></span><span class="v"><strong><?= rm((float) $booking['harga_total']) ?></strong></span></div>
    </div>

    <div class="card">
        <h2>Kemaskini Status</h2>
        <form method="post">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="update_status">

            <label class="required" for="new_status">Status Baharu</label>
            <select id="new_status" name="new_status" required>
                <option value="">- Pilih -</option>
                <?php foreach ($updatableStatuses as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= $booking['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>

            <label for="notes">Nota (pilihan)</label>
            <textarea id="notes" name="notes" placeholder="Contoh: barang diserahkan kepada pelanggan sendiri"></textarea>

            <button type="submit" class="btn" style="margin-top:16px;">Kemaskini Status</button>
        </form>
    </div>
<?php endif; ?>

<div class="card">
    <h2>Sejarah Status (Log)</h2>
    <?php if (empty($logs)): ?>
        <p class="muted">Tiada log lagi.</p>
    <?php else: ?>
    <table>
        <thead><tr><th>Tarikh/Masa</th><th>Status Lama</th><th>Status Baru</th><th>Oleh</th><th>Nota</th></tr></thead>
        <tbody>
        <?php foreach ($logs as $log): ?>
            <tr>
                <td><?= e(date('d/m/Y H:i', strtotime($log['created_at']))) ?></td>
                <td><?= e($log['status_lama'] ?? '-') ?></td>
                <td><?= e($log['status_baru']) ?></td>
                <td><?= e($log['updated_by']) ?></td>
                <td><?= e($log['notes'] ?? '') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
