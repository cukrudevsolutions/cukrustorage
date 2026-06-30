<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/config.php';

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
                $errors[] = "Nilai untuk {$key} tidak sah.";
            }
        }
        foreach ($intKeys as $key) {
            $val = $_POST[$key] ?? '';
            if (!ctype_digit((string) $val)) {
                $errors[] = "Nilai untuk {$key} tidak sah.";
            }
        }
        foreach ($dateKeys as $key) {
            $val = $_POST[$key] ?? '';
            if (!DateTime::createFromFormat('Y-m-d', $val)) {
                $errors[] = "Tarikh untuk {$key} tidak sah.";
            }
        }

        $siteName = trim($_POST['site_name'] ?? '');
        if ($siteName === '') {
            $errors[] = 'Nama sistem tidak boleh kosong.';
        }

        $terms = trim($_POST['terms_and_conditions'] ?? '');
        if ($terms === '') {
            $errors[] = 'Terma & Syarat tidak boleh kosong.';
        }

        if (empty($errors)) {
            Settings::set('site_name', $siteName);
            foreach (array_merge($numericKeys, $intKeys, $dateKeys) as $key) {
                Settings::set($key, (string) $_POST[$key]);
            }
            Settings::set('terms_and_conditions', $terms);
            flash_set('success', 'Tetapan berjaya dikemaskini.');
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
            flash_set('error', 'Kata laluan semasa tidak tepat.');
        } elseif (strlen($new) < 8) {
            flash_set('error', 'Kata laluan baharu mesti sekurang-kurangnya 8 aksara.');
        } elseif ($new !== $confirm) {
            flash_set('error', 'Pengesahan kata laluan baharu tidak sepadan.');
        } else {
            $upd = $pdo->prepare('UPDATE admins SET password_hash = ? WHERE id = ?');
            $upd->execute([password_hash($new, PASSWORD_DEFAULT), $admin['id']]);
            flash_set('success', 'Kata laluan berjaya dikemaskini.');
        }
        redirect('admin/settings.php');
    }
}

$pageTitle = 'Tetapan';
require __DIR__ . '/partials/header.php';
?>

<h1>Tetapan Sistem</h1>

<form method="post" class="card">
    <?= Csrf::field() ?>
    <input type="hidden" name="action" value="save_settings">

    <label class="required" for="site_name">Nama Sistem</label>
    <input type="text" id="site_name" name="site_name" value="<?= e(Settings::get('site_name')) ?>" required>

    <h3 style="margin-top:20px;">Rate Card Storan</h3>
    <div class="grid-2">
        <div><label>1 Kotak (RM)</label><input type="number" step="0.01" min="0" name="rate_box1" value="<?= e(Settings::get('rate_box1')) ?>" required></div>
        <div><label>2 Kotak (RM)</label><input type="number" step="0.01" min="0" name="rate_box2" value="<?= e(Settings::get('rate_box2')) ?>" required></div>
        <div><label>3 Kotak (RM)</label><input type="number" step="0.01" min="0" name="rate_box3" value="<?= e(Settings::get('rate_box3')) ?>" required></div>
        <div><label>Tiap Kotak Ke-4+ (RM)</label><input type="number" step="0.01" min="0" name="rate_extra_box" value="<?= e(Settings::get('rate_extra_box')) ?>" required></div>
    </div>

    <h3 style="margin-top:20px;">Caj Lewat (Overdue)</h3>
    <div class="grid-2">
        <div><label>Kadar/Hari (RM)</label><input type="number" step="0.01" min="0" name="overdue_rate_per_day" value="<?= e(Settings::get('overdue_rate_per_day')) ?>" required></div>
        <div><label>Grace Period (hari)</label><input type="number" min="0" name="overdue_grace_days" value="<?= e(Settings::get('overdue_grace_days')) ?>" required></div>
    </div>

    <h3 style="margin-top:20px;">Lain-lain</h3>
    <div class="grid-2">
        <div><label>Barang Tak Dituntut (hari selepas Return Window)</label><input type="number" min="0" name="unclaimed_days" value="<?= e(Settings::get('unclaimed_days')) ?>" required></div>
        <div><label>Pickup Min. Notis Awal (hari)</label><input type="number" min="0" name="pickup_min_advance_days" value="<?= e(Settings::get('pickup_min_advance_days')) ?>" required></div>
    </div>

    <h3 style="margin-top:20px;">Tarikh Penting Sesi Semasa</h3>
    <div class="grid-2">
        <div><label>Window 1 Mula</label><input type="date" name="window1_start" value="<?= e(Settings::get('window1_start')) ?>" required></div>
        <div><label>Window 1 Tamat</label><input type="date" name="window1_end" value="<?= e(Settings::get('window1_end')) ?>" required></div>
        <div><label>Window 2 Mula</label><input type="date" name="window2_start" value="<?= e(Settings::get('window2_start')) ?>" required></div>
        <div><label>Window 2 Tamat</label><input type="date" name="window2_end" value="<?= e(Settings::get('window2_end')) ?>" required></div>
        <div><label>Return Window Mula</label><input type="date" name="return_window_start" value="<?= e(Settings::get('return_window_start')) ?>" required></div>
        <div><label>Return Window Tamat</label><input type="date" name="return_window_end" value="<?= e(Settings::get('return_window_end')) ?>" required></div>
    </div>

    <h3 style="margin-top:20px;">Terma & Syarat</h3>
    <p class="muted">Gunakan **teks** untuk bold tajuk bahagian. Baris kosong memisahkan perenggan.</p>
    <textarea name="terms_and_conditions" rows="16" required><?= e(Settings::get('terms_and_conditions')) ?></textarea>

    <button type="submit" class="btn" style="margin-top:18px;">Simpan Tetapan</button>
</form>

<div class="card">
    <h2>Tukar Kata Laluan Admin</h2>
    <form method="post">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="change_password">

        <label class="required" for="current_password">Kata Laluan Semasa</label>
        <input type="password" id="current_password" name="current_password" required>

        <label class="required" for="new_password">Kata Laluan Baharu (min. 8 aksara)</label>
        <input type="password" id="new_password" name="new_password" minlength="8" required>

        <label class="required" for="confirm_password">Sahkan Kata Laluan Baharu</label>
        <input type="password" id="confirm_password" name="confirm_password" minlength="8" required>

        <button type="submit" class="btn" style="margin-top:16px;">Tukar Kata Laluan</button>
    </form>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
