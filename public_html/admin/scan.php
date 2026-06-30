<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/config.php';

use Cukru\AdminAuth;
use Cukru\Csrf;
use Cukru\BookingRepository;
use Cukru\Validation;

AdminAuth::requireLogin();

// Hasil scan QR (token diserahkan dari JS selepas kamera berjaya baca kod)
$qrToken = trim((string) ($_GET['token'] ?? ''));
if ($qrToken !== '') {
    $booking = BookingRepository::findByQrToken($qrToken);
    if ($booking) {
        redirect('admin/booking-detail.php?id=' . (int) $booking['id']);
    }
    flash_set('error', 'QR code tidak dikenali / booking tidak ditemui.');
    redirect('admin/scan.php');
}

$matches = [];
$searched = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    $q = trim($_POST['query'] ?? '');
    $searched = true;

    if ($q !== '') {
        $booking = BookingRepository::findByRef(strtoupper($q));
        if ($booking) {
            redirect('admin/booking-detail.php?id=' . (int) $booking['id']);
        }

        if (Validation::isValidMalaysianPhone($q)) {
            $matches = BookingRepository::findAllByPhone(Validation::normalizePhone($q));
        } else {
            $result = BookingRepository::listFiltered(null, $q, 1, 20);
            $matches = $result['rows'];
        }

        if (count($matches) === 1) {
            redirect('admin/booking-detail.php?id=' . (int) $matches[0]['id']);
        }
    }
}

$statusLabels = [
    'pending_approval' => 'Menunggu Kelulusan',
    'approved' => 'Diluluskan',
    'in_storage' => 'Dalam Simpanan',
    'ready_for_return' => 'Sedia Diambil',
    'returned' => 'Telah Dipulangkan',
    'overdue' => 'Overdue',
];

$pageTitle = 'Scan / Cari Booking';
require __DIR__ . '/partials/header.php';
?>

<h1>Scan QR / Cari Booking Manual</h1>

<div class="card">
    <h3>Imbas QR Code</h3>
    <p class="muted">Benarkan akses kamera, kemudian halakan ke QR code pada slip pelanggan.</p>
    <div id="qr-reader" style="max-width:380px;"></div>
    <div id="qr-status" class="muted" style="margin-top:8px;"></div>
</div>

<div class="card">
    <h3>Cari Manual</h3>
    <p class="muted">Jika QR rosak/hilang, cari guna No. Booking, No. Telefon, atau Nama.</p>
    <form method="post">
        <?= Csrf::field() ?>
        <input type="text" name="query" placeholder="Contoh: CKS-20260702-AB3F / 0123456789 / nama" required>
        <button type="submit" class="btn" style="margin-top:10px;">Cari</button>
    </form>

    <?php if ($searched && empty($matches)): ?>
        <p class="alert alert-error" style="margin-top:14px;">Tiada rekod ditemui.</p>
    <?php elseif (!empty($matches)): ?>
        <table style="margin-top:14px;">
            <thead><tr><th>No. Booking</th><th>Nama</th><th>Telefon</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($matches as $b): ?>
                <tr>
                    <td><?= e($b['booking_ref']) ?></td>
                    <td><?= e($b['nama']) ?></td>
                    <td><?= e($b['no_telefon']) ?></td>
                    <td><span class="badge badge-<?= e($b['status']) ?>"><?= e($statusLabels[$b['status']] ?? $b['status']) ?></span></td>
                    <td><a class="btn btn-sm" href="booking-detail.php?id=<?= (int) $b['id'] ?>">Lihat</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
(function () {
    const statusEl = document.getElementById('qr-status');
    if (typeof Html5Qrcode === 'undefined') {
        statusEl.textContent = 'Pengimbas QR gagal dimuatkan (perlukan sambungan internet). Sila guna carian manual.';
        return;
    }
    const scanner = new Html5Qrcode('qr-reader');
    let handled = false;

    scanner.start(
        { facingMode: 'environment' },
        { fps: 10, qrbox: 240 },
        (decodedText) => {
            if (handled) return;
            handled = true;
            statusEl.textContent = 'QR dikesan, mencari booking...';
            scanner.stop().finally(() => {
                window.location.href = 'scan.php?token=' + encodeURIComponent(decodedText);
            });
        },
        () => { /* abaikan ralat scan berterusan semasa cari fokus */ }
    ).catch((err) => {
        statusEl.textContent = 'Tidak dapat akses kamera (' + err + '). Sila guna carian manual di bawah.';
    });
})();
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>
