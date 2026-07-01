<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';

use Cukru\AdminAuth;
use Cukru\Csrf;
use Cukru\BookingRepository;
use Cukru\RateCard;
use Cukru\Settings;
use Cukru\SlipMailer;
use Cukru\PhotoUpload;

AdminAuth::requireLogin();

$id = (int) ($_GET['id'] ?? 0);
$booking = $id > 0 ? BookingRepository::findById($id) : null;

if (!$booking) {
    http_response_code(404);
    flash_set('error', 'Booking not found.');
    redirect('admin/bookings.php');
}

$updatableStatuses = [
    'in_storage' => 'In Storage (IN_STORAGE)',
    'ready_for_return' => 'Ready for Return (READY_FOR_RETURN)',
    'returned' => 'Returned (RETURNED)',
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
            flash_set('error', 'Invalid price.');
        } else {
            $qrToken = BookingRepository::generateQrToken();
            BookingRepository::approve($id, $hargaStorage, $hargaPickup, $hargaTotal, $qrToken, AdminAuth::username());

            $approved = BookingRepository::findById($id);
            $emailSent = SlipMailer::sendPriceConfirmation($approved);

            flash_set('success', 'Booking approved. The slip & QR code are ready for printing, but will only be sent to the customer once their items are confirmed in storage.'
                . ($emailSent ? ' A price confirmation email has been sent to the customer.' : ' (Email failed to send - please check the mail server configuration.)'));
        }
        redirect('admin/booking-detail.php?id=' . $id);
    }

    if ($action === 'resend_email' && $booking['qr_token']) {
        $isInStorageOrLater = $booking['status'] !== 'approved';
        $emailSent = $isInStorageOrLater ? SlipMailer::sendStorageSlip($booking) : SlipMailer::sendPriceConfirmation($booking);
        flash_set($emailSent ? 'success' : 'error', $emailSent ? 'Email resent successfully.' : 'Failed to send email. Please check the mail server configuration.');
        redirect('admin/booking-detail.php?id=' . $id);
    }

    if ($action === 'reset_pin') {
        $newPin = BookingRepository::resetPin($id, AdminAuth::username());
        flash_set('success', "Owner's PIN has been reset to: {$newPin}. Please inform the customer immediately (this PIN is only shown once).");
        redirect('admin/booking-detail.php?id=' . $id);
    }

    if ($action === 'update_photos') {
        $slots = [1 => $booking['foto_storan_1'], 2 => $booking['foto_storan_2'], 3 => $booking['foto_storan_3']];
        $error = null;

        foreach ($slots as $n => $current) {
            if (isset($_POST["remove_foto_{$n}"])) {
                $slots[$n] = null;
                continue;
            }
            $file = $_FILES["foto_{$n}"] ?? null;
            if ($file && $file['error'] !== UPLOAD_ERR_NO_FILE) {
                try {
                    $slots[$n] = PhotoUpload::processUploadedFile($file);
                } catch (\RuntimeException $e) {
                    $error = "Photo {$n}: " . $e->getMessage();
                    break;
                }
            }
        }

        if ($error) {
            flash_set('error', $error);
        } else {
            BookingRepository::updatePhotos($id, $slots[1], $slots[2], $slots[3], AdminAuth::username());
            flash_set('success', 'Storage photos updated successfully.');
        }
        redirect('admin/booking-detail.php?id=' . $id);
    }

    if ($action === 'update_status' && $booking['status'] !== 'pending_approval') {
        $newStatus = $_POST['new_status'] ?? '';
        $notes = trim($_POST['notes'] ?? '') ?: null;

        if (!array_key_exists($newStatus, $updatableStatuses)) {
            flash_set('error', 'Invalid status.');
        } else {
            $isFirstTimeInStorage = $booking['status'] === 'approved' && $newStatus === 'in_storage';
            BookingRepository::updateStatus($id, $newStatus, AdminAuth::username(), $notes);

            $successMsg = 'Status updated to: ' . ($updatableStatuses[$newStatus] ?? $newStatus) . '.';
            if ($isFirstTimeInStorage) {
                $updated = BookingRepository::findById($id);
                $emailSent = SlipMailer::sendStorageSlip($updated);
                $successMsg .= $emailSent ? ' Confirmation slip emailed to the customer.' : ' (Slip email failed.)';
            }
            flash_set('success', $successMsg);
        }
        redirect('admin/booking-detail.php?id=' . $id);
    }

    if ($action === 'edit_booking') {
        $fields = [];
        $newBoxes = (int) ($_POST['bilangan_kotak'] ?? $booking['bilangan_kotak']);
        if ($newBoxes >= 1 && $newBoxes <= 50) $fields['bilangan_kotak'] = $newBoxes;

        $newDate = trim($_POST['tarikh_dicadang'] ?? '');
        if ($newDate && DateTime::createFromFormat('Y-m-d', $newDate)) $fields['tarikh_dicadang'] = $newDate;

        if ($booking['jenis_servis'] === 'pickup') {
            $newAddr = trim($_POST['alamat_pickup'] ?? '');
            if ($newAddr) $fields['alamat_pickup'] = $newAddr;
            $newJarak = trim($_POST['jarak_anggaran'] ?? '');
            $fields['jarak_anggaran'] = $newJarak ?: null;
        }

        if (!empty($fields)) {
            $setClauses = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($fields)));
            $fields['id'] = $id;
            $pdo = \Cukru\Database::pdo();
            $pdo->prepare("UPDATE bookings SET $setClauses, updated_at = NOW() WHERE id = :id")->execute($fields);
            flash_set('success', 'Booking details updated.');
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
    'pending_approval' => 'Pending Approval',
    'approved' => 'Approved',
    'in_storage' => 'In Storage',
    'ready_for_return' => 'Ready for Return',
    'returned' => 'Returned',
    'overdue' => 'Overdue',
];

$pageTitle = 'Booking Details ' . $booking['booking_ref'];
require __DIR__ . '/partials/header.php';
?>

<p><a href="bookings.php">&larr; Back to Booking List</a></p>

<div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:var(--space-3);margin-bottom:var(--space-2);">
    <div>
        <h1 style="margin-bottom:var(--space-2);">Booking #<?= e($booking['booking_ref']) ?></h1>
        <span class="badge badge-<?= e($booking['status']) ?>"><?= e($statusLabels[$booking['status']] ?? $booking['status']) ?></span>
    </div>
    <?php if ($booking['qr_token']): ?>
        <div style="display:flex;gap:var(--space-2);flex-wrap:wrap;">
            <a class="btn btn-secondary btn-sm" href="../slip.php?ref=<?= urlencode($booking['booking_ref']) ?>" target="_blank"><i class="fa-solid fa-receipt"></i> View / Print Slip</a>
            <form method="post" style="display:inline;">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="resend_email">
                <button type="submit" class="btn btn-secondary btn-sm"><i class="fa-solid fa-envelope"></i> Resend Email</button>
            </form>
        </div>
        <?php if ($booking['status'] === 'approved'): ?>
            <p class="field-hint" style="width:100%;margin-top:var(--space-2);">The slip/QR is ready for printing (e.g. to label the box), but the customer will only be emailed their confirmation slip once you mark this booking as <strong>In Storage</strong>.</p>
        <?php endif; ?>
    <?php endif; ?>
</div>

<div class="grid-2">
    <div class="card">
        <h3>Customer Details</h3>
        <div class="kv"><span class="k">Name</span><span class="v"><?= e($booking['nama']) ?></span></div>
        <div class="kv"><span class="k">Phone Number</span><span class="v"><?= e(format_phone($booking['no_telefon'])) ?></span></div>
        <div class="kv"><span class="k">Email</span><span class="v"><?= e($booking['email']) ?></span></div>
        <form method="post" style="margin-top:var(--space-4);" onsubmit="return confirm('Reset this customer\'s PIN to a new random PIN? The old PIN will immediately become invalid.')">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="reset_pin">
            <button type="submit" class="btn btn-sm btn-secondary btn-block"><i class="fa-solid fa-key"></i> Reset Customer PIN</button>
        </form>
    </div>
    <div class="card">
        <h3>Storage Details</h3>
        <div class="kv"><span class="k">Number of Boxes</span><span class="v"><?= (int) $booking['bilangan_kotak'] ?></span></div>
        <div class="kv"><span class="k">Service Type</span><span class="v"><?= $booking['jenis_servis'] === 'pickup' ? 'Team Pickup' : 'Self Drop-off' ?></span></div>
        <?php if ($booking['jenis_servis'] === 'pickup'): ?>
            <div class="kv"><span class="k">Address</span><span class="v"><?= nl2br(e($booking['alamat_pickup'])) ?></span></div>
            <div class="kv"><span class="k">Estimated Distance</span><span class="v"><?= e($booking['jarak_anggaran'] ?? '-') ?></span></div>
        <?php endif; ?>
        <div class="kv"><span class="k">Proposed Date</span><span class="v"><?= e($booking['tarikh_dicadang']) ?></span></div>
    </div>
</div>

<?php if ($booking['status'] !== 'pending_approval'): ?>
<div class="card">
    <h3><i class="fa-solid fa-camera"></i> Storage Photos (Proof of Item Location)</h3>
    <p class="field-hint" style="margin-bottom:var(--space-3);">Take photos of the items at the storage location so the owner can verify their status, and as a reference to confirm during collection. Maximum 3 photos.</p>

    <form method="post" enctype="multipart/form-data">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="update_photos">

        <div class="photo-grid">
        <?php for ($n = 1; $n <= PhotoUpload::MAX_PHOTOS; $n++): $foto = $booking["foto_storan_{$n}"]; ?>
            <div class="photo-slot" id="slot-<?= $n ?>">
                <label class="photo-tap-area" for="foto_<?= $n ?>">
                    <?php if ($foto): ?>
                        <img src="<?= e($foto) ?>" alt="Storage photo <?= $n ?>" class="photo-thumb">
                    <?php else: ?>
                        <div class="photo-slot-empty" id="slot-empty-<?= $n ?>">
                            <i class="fa-solid fa-camera" style="font-size:1.6rem;color:var(--color-muted);"></i>
                            <span style="font-size:0.72rem;color:var(--color-muted);margin-top:4px;">Tap to add</span>
                        </div>
                        <img src="" alt="" class="photo-thumb" id="slot-preview-<?= $n ?>" style="display:none;">
                    <?php endif; ?>
                </label>
                <input type="file" id="foto_<?= $n ?>" name="foto_<?= $n ?>" accept="image/*" capture="environment" class="photo-file-input">
                <?php if ($foto): ?>
                    <label class="remove-row">
                        <input type="checkbox" name="remove_foto_<?= $n ?>"> Remove
                    </label>
                <?php endif; ?>
            </div>
        <?php endfor; ?>
        </div>

        <button type="submit" class="btn btn-block" style="margin-top:var(--space-4);"><i class="fa-solid fa-floppy-disk"></i> Save Photos</button>
    </form>
    <script>
    document.querySelectorAll('.photo-file-input').forEach(input => {
        input.addEventListener('change', function() {
            const n = this.id.replace('foto_', '');
            const preview = document.getElementById('slot-preview-' + n);
            const empty = document.getElementById('slot-empty-' + n);
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = e => {
                    if (preview) { preview.src = e.target.result; preview.style.display = 'block'; preview.className = 'photo-thumb'; }
                    if (empty) empty.style.display = 'none';
                };
                reader.readAsDataURL(this.files[0]);
            }
        });
    });
    </script>
</div>
<?php endif; ?>

<?php if ($overdue['days'] > 0 && $booking['status'] !== 'returned'): ?>
    <div class="alert alert-error">
        This booking has been overdue for <strong><?= $overdue['days'] ?> day(s)</strong> - outstanding charge: <strong><?= rm($overdue['amount']) ?></strong>.
    </div>
<?php endif; ?>

<?php if ($booking['status'] === 'pending_approval'): ?>
    <div class="card">
        <h2>Booking Approval</h2>
        <p class="muted">Confirm the final price. The storage charge is calculated automatically based on the rate card, but you may override it if needed.</p>
        <form method="post">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="approve">

            <label class="required" for="harga_storage">Storage Charge (RM)</label>
            <input type="number" step="0.01" min="0" id="harga_storage" name="harga_storage"
                   value="<?= e((string) RateCard::calculateStorage((int) $booking['bilangan_kotak'])) ?>" required>

            <?php if ($booking['jenis_servis'] === 'pickup'): ?>
                <label class="required" for="harga_pickup">Pickup Charge - Distance + Labour (RM)</label>
                <input type="number" step="0.01" min="0" id="harga_pickup" name="harga_pickup" value="0" required>
                <p class="field-hint">Please calculate based on the actual distance to the customer's address above.</p>
            <?php endif; ?>

            <button type="submit" class="btn btn-block" style="margin-top:var(--space-5);" onclick="return confirm('Approve this booking with the price entered?')"><i class="fa-solid fa-check"></i> Approve Booking &amp; Generate QR</button>
        </form>
    </div>
<?php else: ?>
    <?php
    // Status action cards: what admin can do next
    $statusActions = [
        'approved' => [
            ['status' => 'in_storage', 'label' => 'Items Received — In Storage', 'icon' => 'fa-warehouse', 'desc' => 'Tap when you have received the customer\'s items.', 'primary' => true],
        ],
        'in_storage' => [
            ['status' => 'ready_for_return', 'label' => 'Ready to Collect', 'icon' => 'fa-bell', 'desc' => 'Notify that items are ready for the customer to collect.', 'primary' => true],
            ['status' => 'overdue', 'label' => 'Mark Overdue', 'icon' => 'fa-triangle-exclamation', 'desc' => 'Customer has not collected past the deadline.', 'primary' => false],
        ],
        'ready_for_return' => [
            ['status' => 'returned', 'label' => 'Items Collected ✓', 'icon' => 'fa-circle-check', 'desc' => 'Customer has collected their items. Close this booking.', 'primary' => true],
            ['status' => 'overdue', 'label' => 'Mark Overdue', 'icon' => 'fa-triangle-exclamation', 'desc' => 'Customer has not collected past the deadline.', 'primary' => false],
        ],
        'overdue' => [
            ['status' => 'returned', 'label' => 'Items Collected ✓', 'icon' => 'fa-circle-check', 'desc' => 'Customer has finally collected their items.', 'primary' => true],
        ],
        'returned' => [],
    ];
    $nextActions = $statusActions[$booking['status']] ?? [];
    ?>

    <?php if ($booking['status'] !== 'returned'): ?>
    <div class="card" id="status-section">
        <h2><i class="fa-solid fa-arrow-right-arrow-left"></i> Update Status</h2>

        <?php foreach ($nextActions as $act): ?>
        <form method="post" style="margin-bottom:var(--space-3);">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="new_status" value="<?= e($act['status']) ?>">
            <button type="submit" class="status-action-btn <?= $act['primary'] ? 'status-action-primary' : 'status-action-secondary' ?>"
                onclick="return confirm('Mark as: <?= e($act['label']) ?>?')">
                <i class="fa-solid <?= e($act['icon']) ?> status-action-icon"></i>
                <span>
                    <strong><?= e($act['label']) ?></strong>
                    <small><?= e($act['desc']) ?></small>
                </span>
            </button>
        </form>
        <?php endforeach; ?>

        <details style="margin-top:var(--space-2);">
            <summary style="font-size:0.82rem;color:var(--color-muted);cursor:pointer;">Add a note / other status</summary>
            <form method="post" style="margin-top:var(--space-3);">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="update_status">
                <label for="new_status_other">Status</label>
                <select id="new_status_other" name="new_status" required>
                    <option value="">- Select -</option>
                    <?php foreach ($updatableStatuses as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= $booking['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <label for="notes">Notes (optional)</label>
                <textarea id="notes" name="notes" rows="2" placeholder="e.g. Items handed over at counter"></textarea>
                <button type="submit" class="btn btn-secondary btn-block" style="margin-top:var(--space-3);">Update</button>
            </form>
        </details>
    </div>
    <?php else: ?>
    <div class="card" style="text-align:center;padding:var(--space-5);">
        <i class="fa-solid fa-circle-check" style="font-size:2rem;color:var(--color-success);"></i>
        <p style="font-weight:700;margin-top:var(--space-2);">Booking Complete</p>
        <p class="muted">Items have been collected. No further action needed.</p>
    </div>
    <?php endif; ?>

    <div class="card">
        <h2><i class="fa-solid fa-dollar-sign"></i> Price</h2>
        <div class="kv"><span class="k">Storage</span><span class="v"><?= rm((float) $booking['harga_storage']) ?></span></div>
        <?php if ($booking['harga_pickup'] !== null): ?>
            <div class="kv"><span class="k">Pickup</span><span class="v"><?= rm((float) $booking['harga_pickup']) ?></span></div>
        <?php endif; ?>
        <div class="kv"><span class="k"><strong>Total</strong></span><span class="v"><strong style="font-size:1.1rem;"><?= rm((float) $booking['harga_total']) ?></strong></span></div>
    </div>

    <details class="card" style="padding:var(--space-4);">
        <summary style="cursor:pointer;font-weight:700;font-size:0.95rem;"><i class="fa-solid fa-pen-to-square"></i> Edit Booking Details</summary>
        <form method="post" style="margin-top:var(--space-4);">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="edit_booking">

            <label for="edit_date">Proposed Date (Drop-off / Pickup)</label>
            <input type="date" id="edit_date" name="tarikh_dicadang" value="<?= e($booking['tarikh_dicadang']) ?>">

            <label for="edit_boxes">Number of Boxes</label>
            <input type="number" id="edit_boxes" name="bilangan_kotak" min="1" max="50" value="<?= (int) $booking['bilangan_kotak'] ?>">

            <?php if ($booking['jenis_servis'] === 'pickup'): ?>
                <label for="edit_address">Pickup Address</label>
                <textarea id="edit_address" name="alamat_pickup" rows="2"><?= e($booking['alamat_pickup'] ?? '') ?></textarea>

                <label for="edit_distance">Estimated Distance</label>
                <input type="text" id="edit_distance" name="jarak_anggaran" value="<?= e($booking['jarak_anggaran'] ?? '') ?>" placeholder="e.g. 5km">
            <?php endif; ?>

            <button type="submit" class="btn btn-secondary btn-block" style="margin-top:var(--space-4);"><i class="fa-solid fa-floppy-disk"></i> Save Changes</button>
        </form>
    </details>
<?php endif; ?>

<div class="card">
    <h2>Status History (Log)</h2>
    <?php if (empty($logs)): ?>
        <div class="empty-state">
            <div class="icon"><i class="fa-solid fa-clock"></i></div>
            <p>No log entries yet.</p>
        </div>
    <?php else: ?>
    <div class="table-responsive">
    <table>
        <thead><tr><th>Date/Time</th><th>Old Status</th><th>New Status</th><th>By</th><th>Notes</th></tr></thead>
        <tbody>
        <?php foreach (array_reverse($logs) as $log): ?>
            <tr>
                <td><?= e(date('d/m/Y H:i', strtotime($log['created_at']))) ?></td>
                <td><?= e($log['status_lama'] ?? '-') ?></td>
                <td><?= e($log['status_baru']) ?></td>
                <td><?= e($log['updated_by']) ?></td>
                <td style="white-space:normal;min-width:160px;"><?= e($log['notes'] ?? '') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
