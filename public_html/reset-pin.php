<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';

use Cukru\Csrf;
use Cukru\Validation;
use Cukru\PinReset;

$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$tokenRow = $token !== '' ? PinReset::validateToken($token) : null;
$errors = [];
$done = false;

if (!$tokenRow) {
    $errors[] = 'Pautan reset PIN tidak sah atau telah tamat tempoh. Sila mohon pautan baharu.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    $pin = trim($_POST['pin'] ?? '');
    $pinConfirm = trim($_POST['pin_confirm'] ?? '');

    if (!Validation::isValidPin($pin)) {
        $errors[] = 'PIN mesti 4-6 digit nombor sahaja.';
    } elseif ($pin !== $pinConfirm) {
        $errors[] = 'PIN dan pengesahan PIN tidak sepadan.';
    }

    if (empty($errors)) {
        PinReset::reset($token, $pin);
        $done = true;
    }
}

$pageTitle = 'Set PIN Baharu';
require __DIR__ . '/partials/header.php';
?>

<h1>Set PIN Baharu</h1>

<?php if ($done): ?>
    <div class="alert alert-success">PIN anda telah berjaya dikemaskini. Sila log masuk dengan PIN baharu.</div>
    <p><a class="btn" href="login.php">Log Masuk</a></p>
<?php else: ?>
    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <ul style="margin:0;padding-left:18px;">
                <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    <?php if ($tokenRow): ?>
        <form method="post" class="card">
            <?= Csrf::field() ?>
            <input type="hidden" name="token" value="<?= e($token) ?>">
            <label class="required" for="pin">PIN Baharu (4-6 digit)</label>
            <input type="password" inputmode="numeric" pattern="\d*" id="pin" name="pin" maxlength="6" required>

            <label class="required" for="pin_confirm">Sahkan PIN Baharu</label>
            <input type="password" inputmode="numeric" pattern="\d*" id="pin_confirm" name="pin_confirm" maxlength="6" required>

            <button type="submit" class="btn btn-block" style="margin-top:18px;">Kemaskini PIN</button>
        </form>
    <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
