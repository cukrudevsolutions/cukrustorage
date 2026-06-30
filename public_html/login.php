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

$pageTitle = 'Log In';
require __DIR__ . '/partials/header.php';
?>

<div class="auth-icon"><i class="fa-solid fa-key"></i></div>
<h1 style="text-align:center;">Log In</h1>
<p class="muted" style="text-align:center;margin-bottom:var(--space-5);">Use the phone number &amp; PIN you registered during booking.</p>

<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<form method="post" class="card">
    <?= Csrf::field() ?>
    <label class="required" for="no_telefon">Phone Number</label>
    <input type="tel" id="no_telefon" name="no_telefon" placeholder="012-3456789" data-phone-format inputmode="numeric" maxlength="12" required autofocus autocomplete="tel">

    <label class="required" for="pin">PIN</label>
    <input type="password" inputmode="numeric" pattern="\d*" id="pin" name="pin" maxlength="6" required autocomplete="current-password">

    <button type="submit" class="btn btn-block" style="margin-top:var(--space-5);">Log In</button>
    <p class="muted" style="text-align:center;margin-top:var(--space-4);margin-bottom:0;">
        Forgot your PIN? Please contact the admin for a reset.
    </p>
</form>

<script src="<?= asset('js/phone-format.js') ?>"></script>
<?php require __DIR__ . '/partials/footer.php'; ?>
