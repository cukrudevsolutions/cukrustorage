<?php
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';

use Cukru\OwnerAuth;
use Cukru\BookingRepository;
use Cukru\ReturnRequestRepository;
use Cukru\Csrf;
use Cukru\Settings;

/**
 * Skip-login access via the same booking_ref + qr_token pattern already used by
 * slip.php/qr-image.php - the token is a 64-char random secret, stronger than the
 * PIN it substitutes for. Re-validated on every single request (GET and POST), never
 * persisted as a session login, since this grants access to ALL of that phone
 * number's bookings, not just the one the token belongs to.
 */
function resolve_token_access(string $ref, string $token): ?string
{
    if ($ref === '' || $token === '') {
        return null;
    }
    $booking = BookingRepository::findByRef($ref);
    if ($booking && $booking['qr_token'] && hash_equals($booking['qr_token'], $token)) {
        return $booking['no_telefon'];
    }
    return null;
}

$refParam = trim((string) ($_GET['ref'] ?? $_POST['ref'] ?? ''));
$tokenParam = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$tokenPhone = resolve_token_access($refParam, $tokenParam);
$tokenLinkSuffix = $tokenPhone ? '&ref=' . urlencode($refParam) . '&token=' . urlencode($tokenParam) : '';

if ($tokenPhone) {
    $bookingIds = array_map(static fn (array $b): int => (int) $b['id'], BookingRepository::findAllByPhone($tokenPhone));
} else {
    OwnerAuth::requireLogin();
    $bookingIds = OwnerAuth::bookingIds();
}
BookingRepository::syncOverdueStatuses();

$allBookings = array_values(array_filter(array_map(
    static fn (int $id): ?array => BookingRepository::findById($id),
    $bookingIds
)));
$eligibleBookings = array_values(array_filter($allBookings, static fn (array $b): bool => $b['status'] === 'in_storage'));
$eligibleIds = array_map(static fn (array $b): int => (int) $b['id'], $eligibleBookings);

$activeRequest = ReturnRequestRepository::findActiveForBookingIds($bookingIds);
$config = ReturnRequestRepository::getScheduleConfig();

/** List every date (from today onward) within the configured return window. */
function build_return_date_options(): array
{
    $today = new DateTime('today');
    $start = DateTime::createFromFormat('Y-m-d', Settings::get('return_window_start', ''));
    $end = DateTime::createFromFormat('Y-m-d', Settings::get('return_window_end', ''));
    $options = [];
    if (!$start || !$end) {
        return $options;
    }
    $cursor = clone $start;
    while ($cursor <= $end) {
        if ($cursor >= $today) {
            $options[] = ['value' => $cursor->format('Y-m-d'), 'label' => $cursor->format('j F Y (l)')];
        }
        $cursor->modify('+1 day');
    }
    return $options;
}

$dateOptions = build_return_date_options();
$validDates = array_map(static fn (array $o): string => $o['value'], $dateOptions);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();

    $method = $_POST['method'] ?? '';
    $returnDate = trim($_POST['return_date'] ?? '');
    $slotTime = trim($_POST['slot_time'] ?? '');
    $fastLane = isset($_POST['fast_lane']);

    if (empty($eligibleIds)) {
        $errors[] = 'You have no items eligible for return scheduling.';
    }
    if ($activeRequest) {
        $errors[] = 'You already have an active return request.';
    }
    if (!in_array($method, ['self_pickup', 'team_pickup'], true)) {
        $errors[] = 'Please select a pickup method.';
    }
    if (!in_array($returnDate, $validDates, true)) {
        $errors[] = 'Please select a valid return date.';
    }
    if ($method === 'team_pickup') {
        if (!$config['enabled']) {
            $errors[] = 'Team Pickup is currently not available.';
        } elseif (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $slotTime)) {
            $errors[] = 'Please select a time slot.';
        }
    }

    if (empty($errors)) {
        $noTelefon = $eligibleBookings[0]['no_telefon'];
        $actor = $tokenPhone ? 'owner (link access)' : 'owner';
        // Token access has no login session/dashboard to return to - land back on this
        // same page (still carrying ref+token), which then shows the confirmed status.
        $successRedirect = $tokenPhone ? ('return-schedule.php?ref=' . urlencode($refParam) . '&token=' . urlencode($tokenParam)) : 'dashboard.php';

        if ($method === 'self_pickup') {
            ReturnRequestRepository::createSelfPickup($eligibleIds, $noTelefon, $returnDate, $actor);
            flash_set('success', 'Your Self Pickup date has been scheduled.');
            redirect($successRedirect);
        } elseif ($fastLane) {
            ReturnRequestRepository::createTeamPickupFast($eligibleIds, $noTelefon, $returnDate, $slotTime, $config['fee'], $actor);
            flash_set('success', 'Your Fast Lane request has been submitted and is awaiting admin approval.');
            redirect($successRedirect);
        } else {
            $result = ReturnRequestRepository::createTeamPickupNormal($eligibleIds, $noTelefon, $returnDate, $slotTime, $actor);
            if ($result['success']) {
                flash_set('success', 'Your Team Pickup has been scheduled and confirmed.');
                redirect($successRedirect);
            } else {
                flash_set('error', 'That slot was just taken by someone else - please pick another time.');
                redirect('return-schedule.php?method=team_pickup&date=' . urlencode($returnDate) . $tokenLinkSuffix);
            }
        }
    }
}

$method = $_GET['method'] ?? '';
$selectedDate = trim($_GET['date'] ?? '');
$slots = ($method === 'team_pickup' && in_array($selectedDate, $validDates, true))
    ? ReturnRequestRepository::getSlotsForDate($selectedDate)
    : [];

$pageTitle = 'Schedule Your Return';
require __DIR__ . '/partials/header.php';
?>

<h1><i class="fa-solid fa-calendar-check" style="color:var(--color-primary);font-size:1.2rem;"></i> Schedule Your Return</h1>
<p class="muted">Pick one date to arrange getting all your stored items back.</p>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <strong>Please fix the following:</strong>
        <ul style="margin:8px 0 0;padding-left:18px;">
            <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if ($activeRequest): ?>
    <div class="card">
        <h2><i class="fa-solid fa-circle-check"></i> Return Already Scheduled</h2>
        <div class="kv"><span class="k">Method</span><span class="v"><?= $activeRequest['method'] === 'team_pickup' ? 'Team Pickup' : 'Self Pickup' ?></span></div>
        <div class="kv"><span class="k">Date</span><span class="v"><?= e(date('j F Y', strtotime($activeRequest['return_date']))) ?></span></div>
        <?php if ($activeRequest['slot_time']): ?>
            <div class="kv"><span class="k">Time</span><span class="v"><?= e(substr($activeRequest['slot_time'], 0, 5)) ?></span></div>
        <?php endif; ?>
        <div class="kv"><span class="k">Status</span><span class="v"><span class="badge badge-<?= $activeRequest['status'] === 'confirmed' ? 'return_scheduled' : 'return_pending_approval' ?>"><?= $activeRequest['status'] === 'confirmed' ? 'Confirmed' : 'Pending Approval' ?></span></span></div>
        <?php if ($activeRequest['lane'] === 'fast'): ?>
            <div class="kv"><span class="k">Fast Lane Fee</span><span class="v"><?= rm((float) $activeRequest['fast_lane_fee']) ?></span></div>
        <?php endif; ?>
    </div>

<?php elseif (empty($eligibleBookings)): ?>
    <div class="card empty-state">
        <div class="icon"><i class="fa-solid fa-box-open"></i></div>
        <p>You have no items currently in storage that are eligible for return scheduling.</p>
    </div>

<?php else: ?>

    <div class="card">
        <h3 class="eyebrow" style="margin-bottom:var(--space-3);">This will apply to</h3>
        <?php foreach ($eligibleBookings as $b): ?>
            <div class="kv"><span class="k"><?= e($b['booking_ref']) ?></span><span class="v"><?= (int) $b['bilangan_kotak'] ?> box(es)</span></div>
        <?php endforeach; ?>
    </div>

    <div class="card">
        <h3><i class="fa-solid fa-list-check"></i> 1. Choose Method &amp; Date</h3>
        <form method="get">
            <?php if ($tokenPhone): ?>
                <input type="hidden" name="ref" value="<?= e($refParam) ?>">
                <input type="hidden" name="token" value="<?= e($tokenParam) ?>">
            <?php endif; ?>
            <div class="grid-2">
                <label class="radio-card">
                    <input type="radio" name="method" value="self_pickup" <?= $method === 'self_pickup' ? 'checked' : '' ?> onchange="this.form.submit()">
                    <div>
                        <span style="font-weight:700;"><i class="fa-solid fa-box-open"></i> Self Pickup</span>
                        <small style="display:block;font-weight:400;color:var(--color-muted);margin-top:2px;font-size:0.75rem;">You come collect at our location</small>
                    </div>
                </label>
                <?php if ($config['enabled']): ?>
                <label class="radio-card">
                    <input type="radio" name="method" value="team_pickup" <?= $method === 'team_pickup' ? 'checked' : '' ?> onchange="this.form.submit()">
                    <div>
                        <span style="font-weight:700;"><i class="fa-solid fa-truck"></i> Team Pickup</span>
                        <small style="display:block;font-weight:400;color:var(--color-muted);margin-top:2px;font-size:0.75rem;">We deliver back to you</small>
                    </div>
                </label>
                <?php endif; ?>
            </div>
            <label for="date" style="margin-top:var(--space-4);">Return Date</label>
            <select id="date" name="date" onchange="this.form.submit()">
                <option value="">- Select a date -</option>
                <?php foreach ($dateOptions as $opt): ?>
                    <option value="<?= e($opt['value']) ?>" <?= $selectedDate === $opt['value'] ? 'selected' : '' ?>><?= e($opt['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <?php if ($method && in_array($selectedDate, $validDates, true)): ?>
    <div class="card">
        <h3><i class="fa-solid fa-check-double"></i> 2. Confirm</h3>
        <form method="post">
            <?= Csrf::field() ?>
            <?php if ($tokenPhone): ?>
                <input type="hidden" name="ref" value="<?= e($refParam) ?>">
                <input type="hidden" name="token" value="<?= e($tokenParam) ?>">
            <?php endif; ?>
            <input type="hidden" name="method" value="<?= e($method) ?>">
            <input type="hidden" name="return_date" value="<?= e($selectedDate) ?>">

            <?php if ($method === 'self_pickup'): ?>
                <p>Come collect your items on <strong><?= e(date('j F Y', strtotime($selectedDate))) ?></strong> at our location.</p>
            <?php else: ?>
                <?php $hasAvailable = !empty(array_filter($slots, static fn (array $s): bool => $s['available'])); ?>
                <?php if (empty($slots)): ?>
                    <div class="alert alert-error"><span>No pickup slots are configured for this date. Please contact admin.</span></div>
                <?php else: ?>
                    <?php if (!$hasAvailable): ?>
                        <div class="alert alert-info"><span>All normal slots for this date are taken. You may still request Fast Lane below (subject to admin approval).</span></div>
                    <?php endif; ?>
                    <label>Available Time Slots</label>
                    <div class="grid-2">
                        <?php foreach ($slots as $slot): ?>
                            <label class="radio-card" style="<?= $slot['available'] ? '' : 'opacity:0.5;' ?>">
                                <input type="radio" name="slot_time" value="<?= e($slot['time']) ?>" <?= $slot['available'] ? '' : 'disabled' ?> <?= $hasAvailable ? 'required' : '' ?>>
                                <span style="font-weight:700;"><?= e($slot['time']) ?></span>
                                <?php if (!$slot['available']): ?><small style="margin-left:auto;color:var(--color-muted);">Taken</small><?php endif; ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <div class="checkbox-row">
                        <input type="checkbox" id="fast_lane" name="fast_lane" value="1" <?= $hasAvailable ? '' : 'checked' ?>>
                        <label for="fast_lane" style="margin:0;font-weight:400;">Fast Lane (+<?= rm($config['fee']) ?>) - request priority slot, subject to admin approval</label>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <button type="submit" class="btn btn-block" style="margin-top:var(--space-4);">Confirm Return Booking</button>
        </form>
    </div>
    <?php endif; ?>

<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
