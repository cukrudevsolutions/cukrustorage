<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';

use Cukru\AdminAuth;
use Cukru\Csrf;
use Cukru\Settings;
use Cukru\Database;

AdminAuth::requireLogin();

$numericKeys = ['rate_box1', 'rate_box2', 'rate_box3', 'rate_extra_box', 'overdue_rate_per_day'];
$intKeys = ['overdue_grace_days', 'unclaimed_days', 'pickup_min_advance_days'];
$dateKeys = ['window1_start', 'window1_end', 'window2_start', 'window2_end', 'return_window_start', 'return_window_end'];

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
            foreach (array_merge($numericKeys, $intKeys, $dateKeys) as $key) {
                Settings::set($key, (string) $_POST[$key]);
            }
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

<form method="post" class="card">
    <?= Csrf::field() ?>
    <input type="hidden" name="action" value="save_settings">

    <label class="required" for="site_name">System Name</label>
    <input type="text" id="site_name" name="site_name" value="<?= e(Settings::get('site_name')) ?>" required>

    <hr class="section-divider"><h3>Contact & Location</h3>
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

    <hr class="section-divider"><h3>Storage Rate Card</h3>
    <div class="grid-2">
        <div><label>1 Box (RM)</label><input type="number" step="0.01" min="0" name="rate_box1" value="<?= e(Settings::get('rate_box1')) ?>" required></div>
        <div><label>2 Boxes (RM)</label><input type="number" step="0.01" min="0" name="rate_box2" value="<?= e(Settings::get('rate_box2')) ?>" required></div>
        <div><label>3 Boxes (RM)</label><input type="number" step="0.01" min="0" name="rate_box3" value="<?= e(Settings::get('rate_box3')) ?>" required></div>
        <div><label>Each Box from 4th Onwards (RM)</label><input type="number" step="0.01" min="0" name="rate_extra_box" value="<?= e(Settings::get('rate_extra_box')) ?>" required></div>
    </div>

    <hr class="section-divider"><h3>Late (Overdue) Charges</h3>
    <div class="grid-2">
        <div><label>Rate/Day (RM)</label><input type="number" step="0.01" min="0" name="overdue_rate_per_day" value="<?= e(Settings::get('overdue_rate_per_day')) ?>" required></div>
        <div><label>Grace Period (days)</label><input type="number" min="0" name="overdue_grace_days" value="<?= e(Settings::get('overdue_grace_days')) ?>" required></div>
    </div>

    <hr class="section-divider"><h3>Other Settings</h3>
    <div class="grid-2">
        <div><label>Unclaimed Items (days after Return Period)</label><input type="number" min="0" name="unclaimed_days" value="<?= e(Settings::get('unclaimed_days')) ?>" required></div>
        <div><label>Pickup Min. Advance Notice (days)</label><input type="number" min="0" name="pickup_min_advance_days" value="<?= e(Settings::get('pickup_min_advance_days')) ?>" required></div>
    </div>

    <hr class="section-divider"><h3>Important Dates for the Current Session</h3>
    <div class="grid-2">
        <div><label>Period 1 Start</label><input type="date" name="window1_start" value="<?= e(Settings::get('window1_start')) ?>" required></div>
        <div><label>Period 1 End</label><input type="date" name="window1_end" value="<?= e(Settings::get('window1_end')) ?>" required></div>
        <div><label>Period 2 Start</label><input type="date" name="window2_start" value="<?= e(Settings::get('window2_start')) ?>" required></div>
        <div><label>Period 2 End</label><input type="date" name="window2_end" value="<?= e(Settings::get('window2_end')) ?>" required></div>
        <div><label>Return Period Start</label><input type="date" name="return_window_start" value="<?= e(Settings::get('return_window_start')) ?>" required></div>
        <div><label>Return Period End</label><input type="date" name="return_window_end" value="<?= e(Settings::get('return_window_end')) ?>" required></div>
    </div>

    <hr class="section-divider"><h3>Terms & Conditions</h3>
    <p class="field-hint" style="margin-bottom:var(--space-2);">Use **text** to bold a section heading. A blank line separates paragraphs.</p>
    <textarea name="terms_and_conditions" rows="16" required><?= e(Settings::get('terms_and_conditions')) ?></textarea>

    <button type="submit" class="btn btn-block" style="margin-top:var(--space-5);">Save Settings</button>
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
