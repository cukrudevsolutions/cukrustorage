<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';

use Cukru\AdminAuth;
use Cukru\Csrf;
use Cukru\Settings;
use Cukru\Database;

AdminAuth::requireLogin();

$numericKeys = ['rate_box1', 'rate_box2', 'rate_box3', 'rate_extra_box', 'overdue_rate_per_day', 'return_fast_lane_fee'];
$intKeys = ['overdue_grace_days', 'unclaimed_days', 'pickup_min_advance_days'];
$dateKeys = ['window1_start', 'window1_end', 'window2_start', 'window2_end', 'return_window_start', 'return_window_end'];
$timeKeys = ['return_operating_start_time', 'return_operating_end_time'];
$timeRegex = '/^([01]\d|2[0-3]):[0-5]\d$/';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    $action = $_POST['action'] ?? '';

    if ($action === 'save_settings') {
        $errors = [];

        foreach ($numericKeys as $key) {
            $val = $_POST[$key] ?? '';
            if (!is_numeric($val) || (float) $val < 0) {
                $errors[] = "Invalid value for {$key}.";
            }
        }
        foreach ($intKeys as $key) {
            $val = $_POST[$key] ?? '';
            if (!ctype_digit((string) $val)) {
                $errors[] = "Invalid value for {$key}.";
            }
        }
        foreach ($dateKeys as $key) {
            $val = $_POST[$key] ?? '';
            if (!DateTime::createFromFormat('Y-m-d', $val)) {
                $errors[] = "Invalid date for {$key}.";
            }
        }

        $timeValid = true;
        foreach ($timeKeys as $key) {
            $val = $_POST[$key] ?? '';
            if (!preg_match($timeRegex, $val)) {
                $errors[] = "Invalid time for {$key}.";
                $timeValid = false;
            }
        }

        // Hand-rolled (not part of $intKeys): ctype_digit('0') is true, but a slot
        // duration of 0 would infinite-loop the slot-grid generator.
        $slotDurationRaw = (string) ($_POST['return_slot_duration_minutes'] ?? '');
        $slotDurationValid = ctype_digit($slotDurationRaw) && (int) $slotDurationRaw >= 5;
        if (!$slotDurationValid) {
            $errors[] = 'Return pickup slot duration must be a whole number of at least 5 minutes.';
        }

        if ($timeValid && $slotDurationValid) {
            $startMinutes = (int) substr($_POST['return_operating_start_time'], 0, 2) * 60 + (int) substr($_POST['return_operating_start_time'], 3, 2);
            $endMinutes = (int) substr($_POST['return_operating_end_time'], 0, 2) * 60 + (int) substr($_POST['return_operating_end_time'], 3, 2);
            if ($startMinutes >= $endMinutes) {
                $errors[] = 'Return pickup operating end time must be after the start time.';
            } elseif (($endMinutes - $startMinutes) < (int) $slotDurationRaw) {
                $errors[] = 'Return pickup operating hours must be long enough to fit at least one slot of the configured duration.';
            }
        }

        $siteName = trim($_POST['site_name'] ?? '');
        if ($siteName === '') {
            $errors[] = 'System name cannot be empty.';
        }

        $waPhone = preg_replace('/\D+/', '', trim($_POST['admin_whatsapp'] ?? '')) ?? '';
        if ($waPhone !== '' && (strlen($waPhone) < 8 || strlen($waPhone) > 15)) {
            $errors[] = 'Invalid admin WhatsApp number (use international format without "+", e.g. 60147978792).';
        }

        $mapsUrl = trim($_POST['location_maps_url'] ?? '');
        if ($mapsUrl !== '' && !filter_var($mapsUrl, FILTER_VALIDATE_URL)) {
            $errors[] = 'Invalid Google Maps link.';
        }

        $terms = trim($_POST['terms_and_conditions'] ?? '');
        if ($terms === '') {
            $errors[] = 'Terms & Conditions cannot be empty.';
        }

        if (empty($errors)) {
            Settings::set('site_name', $siteName);
            Settings::set('admin_whatsapp', $waPhone);
            Settings::set('location_maps_url', $mapsUrl);
            foreach (array_merge($numericKeys, $intKeys, $dateKeys, $timeKeys) as $key) {
                Settings::set($key, (string) $_POST[$key]);
            }
            Settings::set('return_slot_duration_minutes', $slotDurationRaw);
            // Checkboxes are absent from $_POST entirely when unchecked - presence IS the value.
            Settings::set('return_team_pickup_enabled', isset($_POST['return_team_pickup_enabled']) ? '1' : '0');
            Settings::set('terms_and_conditions', $terms);
            flash_set('success', 'Settings updated successfully.');
        } else {
            flash_set('error', implode(' ', $errors));
        }
        redirect('admin/settings.php');
    }

    if ($action === 'change_password') {
        $current = (string) ($_POST['current_password'] ?? '');
        $new = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');

        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT * FROM admins WHERE id = ?');
        $stmt->execute([$_SESSION['admin_id']]);
        $admin = $stmt->fetch();

        if (!$admin || !password_verify($current, $admin['password_hash'])) {
            flash_set('error', 'Current password is incorrect.');
        } elseif (strlen($new) < 8) {
            flash_set('error', 'New password must be at least 8 characters.');
        } elseif ($new !== $confirm) {
            flash_set('error', 'New password confirmation does not match.');
        } else {
            $upd = $pdo->prepare('UPDATE admins SET password_hash = ? WHERE id = ?');
            $upd->execute([password_hash($new, PASSWORD_DEFAULT), $admin['id']]);
            flash_set('success', 'Password updated successfully.');
        }
        redirect('admin/settings.php');
    }
}

$pageTitle = 'Settings';
require __DIR__ . '/partials/header.php';
?>

<h1>System Settings</h1>
<p class="muted">Everything below saves together with one "Save Settings" tap.</p>

<form method="post">
    <?= Csrf::field() ?>
    <input type="hidden" name="action" value="save_settings">

    <div class="card">
        <label class="required" for="site_name">System Name</label>
        <input type="text" id="site_name" name="site_name" value="<?= e(Settings::get('site_name')) ?>" required>
    </div>

    <div class="card">
        <h3><i class="fa-solid fa-location-dot"></i> Contact & Location</h3>
        <div class="grid-2">
            <div>
                <label for="admin_whatsapp">Admin WhatsApp Number</label>
                <input type="text" id="admin_whatsapp" name="admin_whatsapp" placeholder="60147978792" value="<?= e(Settings::get('admin_whatsapp')) ?>">
                <p class="field-hint">International format without "+". Used for the "Contact Admin" button on the booking form.</p>
            </div>
            <div>
                <label for="location_maps_url">Drop-off Location Google Maps Link</label>
                <input type="text" id="location_maps_url" name="location_maps_url" placeholder="https://maps.app.goo.gl/..." value="<?= e(Settings::get('location_maps_url')) ?>">
            </div>
        </div>
    </div>

    <div class="card">
        <h3><i class="fa-solid fa-tags"></i> Storage Rate Card</h3>
        <div class="grid-2">
            <div><label>1 Box (RM)</label><input type="number" step="0.01" min="0" name="rate_box1" value="<?= e(Settings::get('rate_box1')) ?>" required></div>
            <div><label>2 Boxes (RM)</label><input type="number" step="0.01" min="0" name="rate_box2" value="<?= e(Settings::get('rate_box2')) ?>" required></div>
            <div><label>3 Boxes (RM)</label><input type="number" step="0.01" min="0" name="rate_box3" value="<?= e(Settings::get('rate_box3')) ?>" required></div>
            <div><label>Each Box from 4th Onwards (RM)</label><input type="number" step="0.01" min="0" name="rate_extra_box" value="<?= e(Settings::get('rate_extra_box')) ?>" required></div>
        </div>
    </div>

    <div class="card">
        <h3><i class="fa-solid fa-clock"></i> Late (Overdue) Charges</h3>
        <div class="grid-2">
            <div><label>Rate/Day (RM)</label><input type="number" step="0.01" min="0" name="overdue_rate_per_day" value="<?= e(Settings::get('overdue_rate_per_day')) ?>" required></div>
            <div><label>Grace Period (days)</label><input type="number" min="0" name="overdue_grace_days" value="<?= e(Settings::get('overdue_grace_days')) ?>" required></div>
        </div>
    </div>

    <div class="card">
        <h3><i class="fa-solid fa-sliders"></i> Other Settings</h3>
        <div class="grid-2">
            <div><label>Unclaimed Items (days after Return Period)</label><input type="number" min="0" name="unclaimed_days" value="<?= e(Settings::get('unclaimed_days')) ?>" required></div>
            <div><label>Pickup Min. Advance Notice (days)</label><input type="number" min="0" name="pickup_min_advance_days" value="<?= e(Settings::get('pickup_min_advance_days')) ?>" required></div>
        </div>
    </div>

    <div class="card">
        <h3><i class="fa-solid fa-calendar-days"></i> Important Dates for the Current Session</h3>
        <div class="grid-2">
            <div><label>Period 1 Start</label><input type="date" name="window1_start" value="<?= e(Settings::get('window1_start')) ?>" required></div>
            <div><label>Period 1 End</label><input type="date" name="window1_end" value="<?= e(Settings::get('window1_end')) ?>" required></div>
            <div><label>Period 2 Start</label><input type="date" name="window2_start" value="<?= e(Settings::get('window2_start')) ?>" required></div>
            <div><label>Period 2 End</label><input type="date" name="window2_end" value="<?= e(Settings::get('window2_end')) ?>" required></div>
            <div><label>Return Period Start</label><input type="date" name="return_window_start" value="<?= e(Settings::get('return_window_start')) ?>" required></div>
            <div><label>Return Period End</label><input type="date" name="return_window_end" value="<?= e(Settings::get('return_window_end')) ?>" required></div>
        </div>
    </div>

    <div class="card">
        <h3><i class="fa-solid fa-truck-fast"></i> Return Pickup Schedule</h3>
        <p class="field-hint" style="margin-bottom:var(--space-3);">Controls the slot grid owners see when booking their return date. Changes take effect immediately on the booking form.</p>
        <div class="checkbox-row">
            <input type="checkbox" id="return_team_pickup_enabled" name="return_team_pickup_enabled" value="1" <?= Settings::getBool('return_team_pickup_enabled') ? 'checked' : '' ?>>
            <label for="return_team_pickup_enabled" style="margin:0;font-weight:400;">Enable Team Pickup option (Self Pickup is always available)</label>
        </div>
        <div class="grid-2">
            <div><label>Operating Start Time</label><input type="time" name="return_operating_start_time" value="<?= e(Settings::get('return_operating_start_time')) ?>" required></div>
            <div><label>Operating End Time</label><input type="time" name="return_operating_end_time" value="<?= e(Settings::get('return_operating_end_time')) ?>" required></div>
            <div><label>Slot Duration (minutes)</label><input type="number" min="5" step="5" name="return_slot_duration_minutes" value="<?= e(Settings::get('return_slot_duration_minutes')) ?>" required></div>
            <div><label>Fast Lane Fee (RM)</label><input type="number" step="0.01" min="0" name="return_fast_lane_fee" value="<?= e(Settings::get('return_fast_lane_fee')) ?>" required></div>
        </div>
        <p class="field-hint">Fast Lane lets an owner request a slot outside the normal queue for this extra fee, subject to admin approval.</p>
    </div>

    <div class="card">
        <h3><i class="fa-solid fa-file-lines"></i> Terms & Conditions</h3>
        <p class="field-hint" style="margin-bottom:var(--space-2);">Use **text** to bold a section heading. A blank line separates paragraphs.</p>
        <textarea name="terms_and_conditions" rows="16" required><?= e(Settings::get('terms_and_conditions')) ?></textarea>
    </div>

    <button type="submit" class="btn btn-block">Save Settings</button>
</form>

<div class="card">
    <h2>Change Admin Password</h2>
    <form method="post">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="change_password">

        <label class="required" for="current_password">Current Password</label>
        <input type="password" id="current_password" name="current_password" required>

        <label class="required" for="new_password">New Password (min. 8 characters)</label>
        <input type="password" id="new_password" name="new_password" minlength="8" required>

        <label class="required" for="confirm_password">Confirm New Password</label>
        <input type="password" id="confirm_password" name="confirm_password" minlength="8" required>

        <button type="submit" class="btn btn-block" style="margin-top:var(--space-5);">Change Password</button>
    </form>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
