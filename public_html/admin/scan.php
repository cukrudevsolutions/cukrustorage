<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/config.php';

use Cukru\AdminAuth;
use Cukru\Csrf;
use Cukru\BookingRepository;
use Cukru\Validation;

AdminAuth::requireLogin();

// QR scan result (token handed off from JS after the camera successfully reads the code)
$qrToken = trim((string) ($_GET['token'] ?? ''));
if ($qrToken !== '') {
    $booking = BookingRepository::findByQrToken($qrToken);
    if ($booking) {
        redirect('admin/booking-detail.php?id=' . (int) $booking['id']);
    }
    flash_set('error', 'QR code not recognised / booking not found.');
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
    'pending_approval' => 'Pending Approval',
    'approved' => 'Approved',
    'in_storage' => 'In Storage',
    'ready_for_return' => 'Ready for Return',
    'returned' => 'Returned',
    'overdue' => 'Overdue',
];

$pageTitle = 'Scan / Find Booking';
require __DIR__ . '/partials/header.php';
?>

<h1>Scan QR / Find Booking Manually</h1>
<p class="muted">Find a customer's booking to view details or update its status.</p>

<div class="card">
    <h3><i class="fa-solid fa-camera"></i> Scan QR Code</h3>
    <p class="muted">Allow camera access, then point it at the QR code on the customer's slip.</p>
    <div id="qr-reader" style="max-width:380px;border-radius:var(--radius-md);overflow:hidden;"></div>
    <div id="qr-status" class="muted" style="margin-top:var(--space-2);"></div>
</div>

<div class="card">
    <h3><i class="fa-solid fa-magnifying-glass"></i> Manual Search</h3>
    <p class="muted">If the QR code is damaged/missing, search using the Booking No., Phone Number, or Name.</p>
    <form method="post" style="display:flex;gap:var(--space-2);flex-wrap:wrap;align-items:flex-end;">
        <?= Csrf::field() ?>
        <div style="flex:1 1 240px;">
            <input type="text" name="query" placeholder="Example: CKS-20260702-AB3F / 012-3456789 / name" required>
        </div>
        <button type="submit" class="btn">Search</button>
    </form>

    <?php if ($searched && empty($matches)): ?>
        <div class="empty-state">
            <div class="icon"><i class="fa-solid fa-magnifying-glass"></i></div>
            <p>No records found.</p>
        </div>
    <?php elseif (!empty($matches)): ?>
        <div class="table-responsive" style="margin-top:var(--space-4);">
        <table>
            <thead><tr><th>Booking No.</th><th>Name</th><th>Phone</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($matches as $b): ?>
                <tr>
                    <td><?= e($b['booking_ref']) ?></td>
                    <td><?= e($b['nama']) ?></td>
                    <td><?= e(format_phone($b['no_telefon'])) ?></td>
                    <td><span class="badge badge-<?= e($b['status']) ?>"><?= e($statusLabels[$b['status']] ?? $b['status']) ?></span></td>
                    <td><a class="btn btn-sm" href="booking-detail.php?id=<?= (int) $b['id'] ?>">View</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>

<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
(function () {
    const statusEl = document.getElementById('qr-status');
    if (typeof Html5Qrcode === 'undefined') {
        statusEl.textContent = 'QR scanner failed to load (requires an internet connection). Please use manual search instead.';
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
            statusEl.textContent = 'QR code detected, looking up booking...';
            scanner.stop().finally(() => {
                window.location.href = 'scan.php?token=' + encodeURIComponent(decodedText);
            });
        },
        () => { /* ignore continuous scan errors while searching for focus */ }
    ).catch((err) => {
        statusEl.textContent = 'Unable to access camera (' + err + '). Please use manual search below.';
    });
})();
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>
