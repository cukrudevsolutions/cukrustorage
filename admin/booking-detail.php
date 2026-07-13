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
use Cukru\ReturnRequestRepository;

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
    'return_scheduled' => 'Return Scheduled (RETURN_SCHEDULED)',
    'returned' => 'Returned (RETURNED)',
    'overdue' => 'Overdue',
    'cancelled' => 'Cancelled',
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

    if ($action === 'approve_return_request' && $booking['return_request_id']) {
        $result = ReturnRequestRepository::approveFastLane((int) $booking['return_request_id'], AdminAuth::username());
        flash_set($result['success'] ? 'success' : 'error', $result['success']
            ? 'Fast Lane request approved and slot confirmed.'
            : 'That slot was taken in the meantime - please reject this request or ask the customer to pick a different slot.');
        redirect('admin/booking-detail.php?id=' . $id);
    }

    if ($action === 'reject_return_request' && $booking['return_request_id']) {
        $notes = trim($_POST['notes'] ?? '') ?: 'Rejected by admin';
        ReturnRequestRepository::rejectFastLane((int) $booking['return_request_id'], AdminAuth::username(), $notes);
        flash_set('success', 'Fast Lane request rejected. The customer can submit a new request.');
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

    if ($action === 'update_status' && $booking['status'] !== 'returned') {
        $newStatus = $_POST['new_status'] ?? '';
        $notes = trim($_POST['notes'] ?? '') ?: null;

        if (!array_key_exists($newStatus, $updatableStatuses)) {
            flash_set('error', 'Invalid status.');
        } elseif ($newStatus === 'cancelled' && $notes === null) {
            flash_set('error', 'Please provide a reason for cancellation.');
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

        // Booking has already been priced (approved or later) — keep the price in sync with
        // the edited details, same rate card used for a new booking. Admin can still override
        // the submitted amount since the form field is editable.
        if ($booking['harga_total'] !== null) {
            $newHargaStorage = (float) ($_POST['harga_storage'] ?? $booking['harga_storage']);
            $newHargaPickup = $booking['harga_pickup'];
            if ($booking['jenis_servis'] === 'pickup') {
                $newHargaPickup = (float) ($_POST['harga_pickup'] ?? $booking['harga_pickup']);
            }

            if ($newHargaStorage >= 0 && ($newHargaPickup === null || $newHargaPickup >= 0)) {
                $fields['harga_storage'] = $newHargaStorage;
                if ($booking['jenis_servis'] === 'pickup') {
                    $fields['harga_pickup'] = $newHargaPickup;
                }
                $fields['harga_total'] = $newHargaStorage + ($newHargaPickup ?? 0);
            }
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
    'return_scheduled' => 'Return Scheduled',
    'return_pending_approval' => 'Return Pending Approval',
    'returned' => 'Returned',
    'overdue' => 'Overdue',
    'cancelled' => 'Cancelled',
];

$waPhone = preg_replace('/\D/', '', $booking['no_telefon']);
if (str_starts_with($waPhone, '0')) $waPhone = '6' . $waPhone;
$waUrl = 'https://wa.me/' . $waPhone;

if ($booking['qr_token']) {
    $returnLinkUrl = APP_URL . '/return-schedule.php?ref=' . urlencode($booking['booking_ref']) . '&token=' . urlencode($booking['qr_token']);
    $waReturnMsg = "Hi {$booking['nama']}, sila tempah tarikh pengambilan/pulangan barang anda ({$booking['booking_ref']}) di sini: {$returnLinkUrl}";
    $waReturnUrl = $waUrl . '?text=' . rawurlencode($waReturnMsg);
}

$pageTitle = $booking['booking_ref'];
require __DIR__ . '/partials/header.php';
?>

<div style="display:flex;align-items:center;gap:var(--space-2);margin-bottom:var(--space-3);">
    <a href="bookings.php" style="color:var(--color-muted);font-size:0.88rem;"><i class="fa-solid fa-arrow-left"></i> All Bookings</a>
</div>

<div class="card" style="padding:var(--space-4);">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:var(--space-2);">
        <div>
            <p class="eyebrow" style="margin:0 0 4px;"><?= e($booking['booking_ref']) ?></p>
            <span class="badge badge-<?= e($booking['status']) ?>" style="font-size:0.82rem;"><?= e($statusLabels[$booking['status']] ?? $booking['status']) ?></span>
        </div>
        <div class="actions-row">
            <a href="<?= e($waUrl) ?>" target="_blank" class="btn btn-sm" style="background:#25D366;color:#fff;border:none;"><i class="fa-brands fa-whatsapp"></i> WA</a>
            <?php if (isset($waReturnUrl)): ?>
                <a href="<?= e($waReturnUrl) ?>" target="_blank" class="btn btn-sm" style="background:#25D366;color:#fff;border:none;" title="Send return-scheduling link via WhatsApp"><i class="fa-brands fa-whatsapp"></i> <i class="fa-solid fa-calendar-check"></i></a>
            <?php endif; ?>
            <?php if ($booking['qr_token']): ?>
                <a class="btn btn-sm btn-secondary" href="../slip.php?ref=<?= urlencode($booking['booking_ref']) ?>" target="_blank"><i class="fa-solid fa-receipt"></i></a>
                <form method="post" style="display:inline;">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="action" value="resend_email">
                    <button class="btn btn-sm btn-secondary"><i class="fa-solid fa-envelope"></i></button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <?php if ($booking['status'] === 'approved' && $booking['qr_token']): ?>
        <p class="field-hint" style="margin-top:var(--space-2);margin-bottom:0;"><i class="fa-solid fa-circle-info"></i> Slip is ready to print for box labelling. Customer will only receive it once you mark as <strong>In Storage</strong>.</p>
    <?php endif; ?>
</div>


<?php
// Status action buttons
$statusActions = [
    'approved'                => [['status'=>'in_storage','label'=>'Items Received — In Storage','icon'=>'fa-warehouse','desc'=>'Tap when you have received the customer\'s items.','primary'=>true]],
    // in_storage's next step is now owner-driven (they book their own return date/slot via
    // return-schedule.php) rather than admin manually marking "ready for return".
    'in_storage'              => [['status'=>'overdue','label'=>'Mark Overdue','icon'=>'fa-triangle-exclamation','desc'=>'Customer has not collected past the deadline.','primary'=>false]],
    'return_scheduled'        => [['status'=>'returned','label'=>'Items Collected ✓','icon'=>'fa-circle-check','desc'=>'Customer has collected. This booking is now closed.','primary'=>true],['status'=>'overdue','label'=>'Mark Overdue','icon'=>'fa-triangle-exclamation','desc'=>'Customer has not collected past the deadline.','primary'=>false]],
    'return_pending_approval' => [],
    'overdue'                 => [['status'=>'returned','label'=>'Items Collected ✓','icon'=>'fa-circle-check','desc'=>'Customer has finally collected their items.','primary'=>true]],
    'returned'                => [],
];
$nextActions = $statusActions[$booking['status']] ?? [];
?>

<?php if ($overdue['days'] > 0 && $booking['status'] !== 'returned'): ?>
    <div class="alert alert-error"><i class="fa-solid fa-triangle-exclamation"></i>
        <span>Overdue by <strong><?= $overdue['days'] ?> day(s)</strong> — outstanding charge: <strong><?= rm($overdue['amount']) ?></strong>.</span>
    </div>
<?php endif; ?>

<?php $returnRequest = $booking['return_request_id'] ? ReturnRequestRepository::findById((int) $booking['return_request_id']) : null; ?>

<?php if ($booking['status'] === 'return_pending_approval' && $returnRequest): ?>
<div class="card">
    <h2><i class="fa-solid fa-hourglass-half"></i> Fast Lane Approval Needed</h2>
    <p class="muted" style="margin-bottom:var(--space-3);">Customer requested a priority slot outside the normal queue. Confirm the slot is still free before approving.</p>
    <div class="kv"><span class="k">Requested Date</span><span class="v"><?= e(date('j F Y', strtotime($returnRequest['return_date']))) ?></span></div>
    <div class="kv"><span class="k">Requested Time</span><span class="v"><?= e(substr((string) $returnRequest['slot_time'], 0, 5)) ?></span></div>
    <div class="kv"><span class="k">Fast Lane Fee</span><span class="v"><?= rm((float) $returnRequest['fast_lane_fee']) ?></span></div>
    <form method="post" style="margin-top:var(--space-3);">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="approve_return_request">
        <button type="submit" class="btn btn-block" onclick="return confirm('Approve this Fast Lane request and lock the slot?')"><i class="fa-solid fa-check"></i> Approve &amp; Confirm Slot</button>
    </form>
    <form method="post" style="margin-top:var(--space-2);">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="reject_return_request">
        <input type="hidden" name="notes" value="Rejected by admin from booking detail">
        <button type="submit" class="btn btn-secondary btn-block" onclick="return confirm('Reject this Fast Lane request?')"><i class="fa-solid fa-xmark"></i> Reject</button>
    </form>
</div>
<?php endif; ?>

<?php if ($booking['status'] === 'pending_approval'): ?>
<div class="card">
    <h2><i class="fa-solid fa-check"></i> Approve Booking</h2>
    <p class="muted" style="margin-bottom:var(--space-4);">Storage charge is auto-calculated from the rate card. Override if needed.</p>
    <form method="post">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="approve">
        <label class="required" for="harga_storage">Storage Charge (RM)</label>
        <input type="number" step="0.01" min="0" id="harga_storage" name="harga_storage"
               value="<?= e((string) RateCard::calculateStorage((int) $booking['bilangan_kotak'])) ?>" required>
        <?php if ($booking['jenis_servis'] === 'pickup'): ?>
            <label class="required" for="harga_pickup">Pickup Charge — Distance + Labour (RM)</label>
            <input type="number" step="0.01" min="0" id="harga_pickup" name="harga_pickup" value="0" required>
        <?php endif; ?>
        <button type="submit" class="btn btn-block" style="margin-top:var(--space-5);" onclick="return confirm('Approve this booking?')"><i class="fa-solid fa-check"></i> Approve &amp; Generate QR</button>
    </form>
</div>

<?php else: ?>

<?php if ($booking['status'] === 'returned'): ?>
<div class="card" style="text-align:center;padding:var(--space-5);">
    <i class="fa-solid fa-circle-check" style="font-size:2.5rem;color:var(--color-success);"></i>
    <p style="font-weight:800;margin:var(--space-2) 0 4px;">Booking Complete</p>
    <p class="muted" style="margin:0;">Items have been collected — no further action needed.</p>
</div>
<?php else: ?>
<div class="card">
    <h2 style="margin-bottom:var(--space-3);"><i class="fa-solid fa-arrow-right-arrow-left"></i> Update Status</h2>
    <?php foreach ($nextActions as $act): ?>
    <form method="post" style="margin-bottom:var(--space-3);">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="update_status">
        <input type="hidden" name="new_status" value="<?= e($act['status']) ?>">
        <input type="hidden" name="notes" value="<?= e($act['status'] === 'cancelled' ? 'Cancelled by admin' : '') ?>">
        <button type="submit" class="status-action-btn <?= $act['primary'] ? 'status-action-primary' : 'status-action-secondary' ?>"
            onclick="return confirm('Mark as: <?= e($act['label']) ?>?')">
            <i class="fa-solid <?= e($act['icon']) ?> status-action-icon"></i>
            <span><strong><?= e($act['label']) ?></strong><small><?= e($act['desc']) ?></small></span>
        </button>
    </form>
    <?php endforeach; ?>
    <div class="card" style="padding:var(--space-4);margin-top:var(--space-3);background:var(--color-danger-bg);border:1px solid #fecaca;">
        <h3 style="margin:0 0 var(--space-3);font-size:1rem;color:#991b1b;"><i class="fa-solid fa-ban"></i> Cancel Booking with Reason</h3>
        <form method="post">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="new_status" value="cancelled">
            <label for="cancel_reason">Reason for cancellation</label>
            <textarea id="cancel_reason" name="notes" rows="3" required><?php if ($booking['status'] === 'cancelled') echo e($booking['notes'] ?? ''); ?></textarea>
            <button type="submit" class="btn btn-danger btn-block" style="margin-top:var(--space-3);" onclick="return confirm('Cancel this booking?')"><i class="fa-solid fa-ban"></i> Cancel Booking</button>
        </form>
    </div>
    <details style="margin-top:var(--space-1);">
        <summary style="font-size:0.8rem;color:var(--color-muted);cursor:pointer;padding:var(--space-2) 0;">Custom status / add note</summary>
        <form method="post" style="margin-top:var(--space-3);">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="update_status">
            <select name="new_status" required>
                <option value="">- Select -</option>
                <?php foreach ($updatableStatuses as $key => $lbl): ?>
                    <option value="<?= e($key) ?>" <?= $booking['status'] === $key ? 'selected' : '' ?>><?= e($lbl) ?></option>
                <?php endforeach; ?>
            </select>
            <textarea name="notes" rows="2" placeholder="Optional note (e.g. customer collected at 3pm)" style="margin-top:var(--space-2);"></textarea>
            <button type="submit" class="btn btn-secondary btn-block" style="margin-top:var(--space-3);">Update</button>
        </form>
    </details>
</div>
<?php endif; ?>

<?php endif; // closes outer pending_approval if/else ?>

<?php
// Details: customer + storage compact collapsible
$hasPriceSet = $booking['harga_total'] !== null;
?>
<details class="card" style="padding:var(--space-4);">
    <summary style="cursor:pointer;font-weight:700;font-size:0.95rem;"><i class="fa-solid fa-circle-user"></i> Customer &amp; Booking Details</summary>
    <div style="margin-top:var(--space-3);">
        <div class="kv"><span class="k">Name</span><span class="v"><?= e($booking['nama']) ?></span></div>
        <div class="kv"><span class="k">Phone</span><span class="v"><?= e(format_phone($booking['no_telefon'])) ?></span></div>
        <div class="kv"><span class="k">Email</span><span class="v" style="font-size:0.85rem;word-break:break-all;"><?= e($booking['email']) ?></span></div>
        <div class="kv"><span class="k">Boxes</span><span class="v"><?= (int) $booking['bilangan_kotak'] ?></span></div>
        <div class="kv"><span class="k">Service</span><span class="v"><?= $booking['jenis_servis'] === 'pickup' ? 'Team Pickup' : 'Self Drop-off' ?></span></div>
        <div class="kv"><span class="k">Date</span><span class="v"><?= e($booking['tarikh_dicadang']) ?></span></div>
        <?php if ($booking['jenis_servis'] === 'pickup' && $booking['alamat_pickup']): ?>
        <div class="kv" style="flex-wrap:wrap;gap:4px;"><span class="k">Address</span><span class="v" style="text-align:right;font-size:0.85rem;"><?= e($booking['alamat_pickup']) ?></span></div>
        <?php endif; ?>
        <?php if ($hasPriceSet): ?>
        <div class="kv"><span class="k">Storage</span><span class="v"><?= rm((float) $booking['harga_storage']) ?></span></div>
        <?php if ($booking['harga_pickup'] !== null): ?>
        <div class="kv"><span class="k">Pickup</span><span class="v"><?= rm((float) $booking['harga_pickup']) ?></span></div>
        <?php endif; ?>
        <div class="kv"><span class="k"><strong>Total</strong></span><span class="v"><strong><?= rm((float) $booking['harga_total']) ?></strong></span></div>
        <?php endif; ?>
        <?php if ($returnRequest): ?>
        <div class="kv"><span class="k">Return Method</span><span class="v"><?= $returnRequest['method'] === 'team_pickup' ? 'Team Pickup' : 'Self Pickup' ?></span></div>
        <div class="kv"><span class="k">Return Date</span><span class="v"><?= e(date('j F Y', strtotime($returnRequest['return_date']))) ?><?= $returnRequest['slot_time'] ? ' ' . e(substr((string) $returnRequest['slot_time'], 0, 5)) : '' ?></span></div>
        <?php if ($returnRequest['lane'] === 'fast'): ?>
        <div class="kv"><span class="k">Lane</span><span class="v">Fast Lane (<?= rm((float) $returnRequest['fast_lane_fee']) ?>)</span></div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</details>

<details class="card" style="padding:var(--space-4);">
    <summary style="cursor:pointer;font-weight:700;font-size:0.95rem;"><i class="fa-solid fa-pen-to-square"></i> Edit Details</summary>
    <form method="post" style="margin-top:var(--space-3);">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="edit_booking">
        <label for="edit_date">Drop-off / Pickup Date</label>
        <input type="date" id="edit_date" name="tarikh_dicadang" value="<?= e($booking['tarikh_dicadang']) ?>">
        <label for="edit_boxes">Number of Boxes</label>
        <input type="number" id="edit_boxes" name="bilangan_kotak" min="1" max="50" value="<?= (int) $booking['bilangan_kotak'] ?>">
        <?php if ($booking['jenis_servis'] === 'pickup'): ?>
            <label for="edit_address">Pickup Address</label>
            <textarea id="edit_address" name="alamat_pickup" rows="2"><?= e($booking['alamat_pickup'] ?? '') ?></textarea>
            <label for="edit_distance">Est. Distance</label>
            <input type="text" id="edit_distance" name="jarak_anggaran" value="<?= e($booking['jarak_anggaran'] ?? '') ?>" placeholder="e.g. 5km">
        <?php endif; ?>
        <?php if ($hasPriceSet): ?>
            <label for="edit_harga_storage">Storage Charge (RM)</label>
            <input type="number" step="0.01" min="0" id="edit_harga_storage" name="harga_storage"
                   value="<?= e((string) $booking['harga_storage']) ?>"
                   data-rate1="<?= e((string) Settings::getFloat('rate_box1', 30)) ?>"
                   data-rate2="<?= e((string) Settings::getFloat('rate_box2', 55)) ?>"
                   data-rate3="<?= e((string) Settings::getFloat('rate_box3', 80)) ?>"
                   data-rate-extra="<?= e((string) Settings::getFloat('rate_extra_box', 10)) ?>">
            <p class="field-hint" style="margin-top:4px;">Auto-recalculated from the rate card when boxes change — edit to override.</p>
            <?php if ($booking['jenis_servis'] === 'pickup'): ?>
                <label for="edit_harga_pickup">Pickup Charge (RM)</label>
                <input type="number" step="0.01" min="0" id="edit_harga_pickup" name="harga_pickup" value="<?= e((string) $booking['harga_pickup']) ?>">
            <?php endif; ?>
        <?php endif; ?>
        <button type="submit" class="btn btn-block" style="margin-top:var(--space-3);"><i class="fa-solid fa-floppy-disk"></i> Save Changes</button>
    </form>
    <?php if ($hasPriceSet): ?>
    <script>
    (function() {
        var boxesInput = document.getElementById('edit_boxes');
        var storageInput = document.getElementById('edit_harga_storage');
        if (!boxesInput || !storageInput) return;
        function calcStorage(boxes) {
            var r1 = parseFloat(storageInput.dataset.rate1);
            var r2 = parseFloat(storageInput.dataset.rate2);
            var r3 = parseFloat(storageInput.dataset.rate3);
            var rx = parseFloat(storageInput.dataset.rateExtra);
            if (boxes <= 0) return 0;
            if (boxes === 1) return r1;
            if (boxes === 2) return r2;
            if (boxes === 3) return r3;
            return r3 + (boxes - 3) * rx;
        }
        boxesInput.addEventListener('input', function() {
            var boxes = parseInt(boxesInput.value, 10) || 0;
            storageInput.value = calcStorage(boxes).toFixed(2);
        });
    })();
    </script>
    <?php endif; ?>
    <form method="post" style="margin-top:var(--space-2);" onsubmit="return confirm('Reset this customer\'s PIN to a new random PIN?')">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="reset_pin">
        <button type="submit" class="btn btn-sm btn-secondary btn-block"><i class="fa-solid fa-key"></i> Reset Customer PIN</button>
    </form>
</details>

<div class="card">
    <h3><i class="fa-solid fa-camera"></i> Storage Photos (max 3)</h3>
    <p class="muted" style="margin-bottom:var(--space-3);">Upload up to 3 reference photos for the storage location.</p>
    <form method="post" enctype="multipart/form-data">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="update_photos">
        <div class="photo-grid">
            <?php for ($n = 1; $n <= 3; $n++): ?>
                <?php $photoValue = $booking['foto_storan_' . $n] ?? null; ?>
                <div class="photo-slot">
                    <label class="photo-tap-area" for="foto_<?= $n ?>">
                        <div id="photo-preview-<?= $n ?>">
                        <?php if (!empty($photoValue)): ?>
                            <img class="photo-thumb" src="<?= e($photoValue) ?>" alt="Storage photo <?= $n ?>">
                        <?php else: ?>
                            <div class="photo-slot-empty">
                                <i class="fa-solid fa-image" style="font-size:1rem;color:var(--color-muted);"></i>
                                <span style="margin-top:6px;font-size:0.8rem;color:var(--color-muted);">Add photo <?= $n ?></span>
                            </div>
                        <?php endif; ?>
                        </div>
                    </label>
                    <input class="photo-file-input" id="foto_<?= $n ?>" name="foto_<?= $n ?>" type="file" accept="image/*" data-slot="<?= $n ?>">
                    <?php if (!empty($photoValue)): ?>
                        <label class="remove-row">
                            <input type="checkbox" id="remove_foto_<?= $n ?>" name="remove_foto_<?= $n ?>" value="1">
                            <span>Remove</span>
                        </label>
                    <?php endif; ?>
                </div>
            <?php endfor; ?>
        </div>
        <p class="field-hint" style="margin-top:var(--space-3);">Accepted formats: JPG, PNG, WEBP. Max size 10MB each.</p>
        <button type="submit" class="btn btn-block" style="margin-top:var(--space-3);"><i class="fa-solid fa-cloud-arrow-up"></i> Save Photos</button>
    </form>
    <script>
    (function() {
        document.querySelectorAll('.photo-file-input').forEach(function(input) {
            input.addEventListener('change', function() {
                if (!input.files || !input.files[0]) return;
                var n = input.dataset.slot;
                var preview = document.getElementById('photo-preview-' + n);
                var url = URL.createObjectURL(input.files[0]);
                preview.innerHTML = '<img class="photo-thumb" src="' + url + '" alt="Storage photo ' + n + ' preview">';
                var removeBox = document.getElementById('remove_foto_' + n);
                if (removeBox) removeBox.checked = false;
            });
        });
    })();
    </script>
</div>

<div class="card">
    <h3><i class="fa-solid fa-clock-rotate-left"></i> Activity Log</h3>
    <?php if (empty($logs)): ?>
        <p class="muted" style="text-align:center;padding:var(--space-4) 0;">No log entries yet.</p>
    <?php else: ?>
        <?php foreach (array_reverse($logs) as $i => $log): ?>
        <div style="display:flex;gap:var(--space-3);padding:var(--space-3) 0;<?= $i > 0 ? 'border-top:1px dashed var(--color-border);' : '' ?>">
            <div style="flex-shrink:0;width:32px;height:32px;background:var(--color-bg);border-radius:50%;display:flex;align-items:center;justify-content:center;">
                <i class="fa-solid fa-circle-dot" style="font-size:0.6rem;color:var(--color-primary);"></i>
            </div>
            <div style="flex:1;min-width:0;">
                <div style="display:flex;align-items:center;gap:var(--space-2);flex-wrap:wrap;">
                    <span class="badge badge-<?= e($log['status_baru']) ?>" style="font-size:0.65rem;"><?= e($log['status_baru']) ?></span>
                    <span class="field-hint" style="margin:0;"><?= e(date('d/m/Y H:i', strtotime($log['created_at']))) ?></span>
                </div>
                <?php if ($log['notes']): ?>
                    <p style="margin:4px 0 0;font-size:0.82rem;color:var(--color-muted);"><?= e($log['notes']) ?></p>
                <?php endif; ?>
                <p style="margin:2px 0 0;font-size:0.72rem;color:var(--color-muted);">by <?= e($log['updated_by']) ?></p>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
