<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';

use Cukru\AdminAuth;
use Cukru\Csrf;
use Cukru\ReturnRequestRepository;

AdminAuth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    $action = $_POST['action'] ?? '';
    $requestId = (int) ($_POST['request_id'] ?? 0);

    if ($action === 'approve_fast_lane') {
        $result = ReturnRequestRepository::approveFastLane($requestId, AdminAuth::username());
        if ($result['success']) {
            flash_set('success', 'Fast Lane request approved and slot confirmed.');
        } else {
            flash_set('error', 'That slot was taken in the meantime - please reject this request or ask the customer to pick a different slot.');
        }
        redirect('admin/return-schedule.php');
    }

    if ($action === 'reject_fast_lane') {
        $notes = trim($_POST['notes'] ?? '') ?: 'Rejected by admin';
        ReturnRequestRepository::rejectFastLane($requestId, AdminAuth::username(), $notes);
        flash_set('success', 'Fast Lane request rejected. The customer can submit a new request.');
        redirect('admin/return-schedule.php');
    }
}

$filterStatus = trim((string) ($_GET['status'] ?? 'all'));
$requests = ReturnRequestRepository::listAll($filterStatus === 'all' ? null : $filterStatus);

$grouped = [];
foreach ($requests as $r) {
    $grouped[$r['return_date']][] = $r;
}

$statusLabels = [
    'confirmed' => 'Confirmed',
    'pending_approval' => 'Pending Approval',
    'rejected' => 'Rejected',
    'cancelled' => 'Cancelled',
];

$pageTitle = 'Return Schedule';
require __DIR__ . '/partials/header.php';
?>

<h1>Return Schedule</h1>
<p class="muted">All owner-scheduled return dates, grouped by date. Fast Lane requests need your approval.</p>

<form method="get" class="filter-bar">
    <select name="status" onchange="this.form.submit()">
        <option value="all" <?= $filterStatus === 'all' ? 'selected' : '' ?>>All</option>
        <?php foreach ($statusLabels as $key => $label): ?>
            <option value="<?= e($key) ?>" <?= $filterStatus === $key ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
</form>

<?php if (empty($grouped)): ?>
    <div class="card empty-state">
        <div class="icon"><i class="fa-solid fa-calendar-xmark"></i></div>
        <p>No return requests found.</p>
    </div>
<?php else: ?>
    <?php foreach ($grouped as $date => $items): ?>
        <p class="eyebrow" style="margin:var(--space-4) 0 var(--space-2);"><i class="fa-solid fa-calendar-day"></i> <?= e(date('j F Y (l)', strtotime($date))) ?></p>
        <?php foreach ($items as $r): ?>
            <?php $bookings = ReturnRequestRepository::findLinkedBookings((int) $r['id']); ?>
            <div class="card" style="margin-bottom:var(--space-3);">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:var(--space-2);flex-wrap:wrap;">
                    <div>
                        <div style="font-weight:700;font-size:0.9rem;">
                            <?= $r['method'] === 'team_pickup' ? '<i class="fa-solid fa-truck"></i> Team Pickup' : '<i class="fa-solid fa-store"></i> Self Pickup' ?>
                            <?= $r['slot_time'] ? ' - ' . e(substr($r['slot_time'], 0, 5)) : '' ?>
                            <?= $r['lane'] === 'fast' ? '<span class="badge" style="background:#fef3c7;color:#92400e;margin-left:6px;">FAST LANE</span>' : '' ?>
                        </div>
                        <span class="badge badge-<?= $r['status'] === 'confirmed' ? 'return_scheduled' : ($r['status'] === 'pending_approval' ? 'return_pending_approval' : 'cancelled') ?>" style="margin-top:4px;">
                            <?= e($statusLabels[$r['status']] ?? $r['status']) ?>
                        </span>
                    </div>
                    <?php if ($r['status'] === 'pending_approval'): ?>
                        <div class="actions-row">
                            <form method="post" style="display:inline;">
                                <?= Csrf::field() ?>
                                <input type="hidden" name="action" value="approve_fast_lane">
                                <input type="hidden" name="request_id" value="<?= (int) $r['id'] ?>">
                                <button type="submit" class="btn btn-sm" onclick="return confirm('Approve this Fast Lane request and lock the slot?')"><i class="fa-solid fa-check"></i> Approve</button>
                            </form>
                            <form method="post" style="display:inline;">
                                <?= Csrf::field() ?>
                                <input type="hidden" name="action" value="reject_fast_lane">
                                <input type="hidden" name="request_id" value="<?= (int) $r['id'] ?>">
                                <input type="hidden" name="notes" value="Rejected by admin">
                                <button type="submit" class="btn btn-sm btn-secondary" onclick="return confirm('Reject this Fast Lane request?')"><i class="fa-solid fa-xmark"></i> Reject</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>

                <div style="margin-top:var(--space-3);">
                    <?php foreach ($bookings as $b): ?>
                        <div class="kv">
                            <span class="k"><a href="booking-detail.php?id=<?= (int) $b['id'] ?>"><?= e($b['booking_ref']) ?></a></span>
                            <span class="v"><?= e($b['nama']) ?> (<?= e(format_phone($b['no_telefon'])) ?>)</span>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($r['lane'] === 'fast' && $r['fast_lane_fee'] !== null): ?>
                        <div class="kv"><span class="k">Fast Lane Fee</span><span class="v"><?= rm((float) $r['fast_lane_fee']) ?></span></div>
                    <?php endif; ?>
                    <?php if ($r['admin_notes']): ?>
                        <div class="kv"><span class="k">Notes</span><span class="v"><?= e($r['admin_notes']) ?></span></div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endforeach; ?>
<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
