<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';

use Cukru\Csrf;
use Cukru\OwnerAuth;

if (OwnerAuth::isLoggedIn()) {
    redirect('dashboard.php');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    $phone = trim($_POST['no_telefon'] ?? '');
    $pin = trim($_POST['pin'] ?? '');

    $result = OwnerAuth::attempt($phone, $pin);
    if ($result['success']) {
        redirect('dashboard.php');
    }
    $error = $result['message'];
}

$pageTitle = 'Log Masuk';
require __DIR__ . '/partials/header.php';
?>

<h1>Log Masuk</h1>
<p class="muted">Guna no. telefon &amp; PIN yang didaftarkan semasa booking.</p>

<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<form method="post" class="card">
    <?= Csrf::field() ?>
    <label class="required" for="no_telefon">No. Telefon</label>
    <input type="tel" id="no_telefon" name="no_telefon" placeholder="012-3456789" required autofocus>

    <label class="required" for="pin">PIN</label>
    <input type="password" inputmode="numeric" pattern="\d*" id="pin" name="pin" maxlength="6" required>

    <button type="submit" class="btn btn-block" style="margin-top:18px;">Log Masuk</button>
    <p class="muted" style="text-align:center;margin-top:14px;">
        <a href="forgot-pin.php">Lupa PIN?</a>
    </p>
</form>

<?php require __DIR__ . '/partials/footer.php'; ?>
