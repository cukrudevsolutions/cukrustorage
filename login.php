<?php
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';

use Cukru\Csrf;
use Cukru\OwnerAuth;

$redirectTarget = OwnerAuth::sanitizeRedirectTarget($_GET['redirect'] ?? $_POST['redirect'] ?? null);

if (OwnerAuth::isLoggedIn()) {
    redirect($redirectTarget);
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    $phone = trim($_POST['no_telefon'] ?? '');
    $pin = trim($_POST['pin'] ?? '');

    $result = OwnerAuth::attempt($phone, $pin);
    if ($result['success']) {
        redirect($redirectTarget);
    }
    $error = $result['message'];
}

$pageTitle = 'Log In';
require __DIR__ . '/partials/header.php';
?>

<div style="text-align:center;margin-bottom:var(--space-5);">
    <img src="<?= asset('images/favicon.png') ?>" alt="" style="width:52px;height:52px;border-radius:var(--radius-md);margin:0 auto var(--space-3);">
    <h1 style="margin-bottom:var(--space-1);">Log In to My Storage</h1>
    <p class="muted" style="margin:0;">Enter your phone number and PIN to track your booking.</p>
</div>

<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:var(--space-2);margin-bottom:var(--space-5);">
    <div style="background:transparent;border:0;padding:var(--space-2);text-align:center;">
        <i class="fa-solid fa-warehouse" style="color:var(--color-primary);font-size:1rem;display:block;margin-bottom:5px;"></i>
        <span style="font-size:0.72rem;font-weight:700;color:var(--color-text);">Track Status</span>
    </div>
    <div style="background:transparent;border:0;padding:var(--space-2);text-align:center;">
        <i class="fa-solid fa-camera" style="color:var(--color-primary);font-size:1rem;display:block;margin-bottom:5px;"></i>
        <span style="font-size:0.72rem;font-weight:700;color:var(--color-text);">View Updates</span>
    </div>
    <div style="background:transparent;border:0;padding:var(--space-2);text-align:center;">
        <i class="fa-solid fa-receipt" style="color:var(--color-primary);font-size:1rem;display:block;margin-bottom:5px;"></i>
        <span style="font-size:0.72rem;font-weight:700;color:var(--color-text);">Download Slip</span>
    </div>
</div>

<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<form method="post" class="card">
    <?= Csrf::field() ?>
    <input type="hidden" name="redirect" value="<?= e($redirectTarget) ?>">
    <label class="required" for="no_telefon">Phone Number</label>
    <input type="tel" id="no_telefon" name="no_telefon" placeholder="012-3456789" data-phone-format inputmode="numeric" maxlength="12" required autofocus autocomplete="tel">

    <label class="required" for="pin">PIN</label>
    <input type="password" inputmode="numeric" pattern="\d*" id="pin" name="pin" maxlength="6" required autocomplete="current-password">

    <button type="submit" class="btn btn-block" style="margin-top:var(--space-5);">Log In</button>
    <p class="muted" style="text-align:center;margin-top:var(--space-4);margin-bottom:0;font-size:0.8rem;">
        Use the phone number &amp; PIN from your booking registration.<br>
        Forgot your PIN? Contact admin to reset.
    </p>
</form>

<script src="<?= asset('js/phone-format.js') ?>"></script>
<?php require __DIR__ . '/partials/footer.php'; ?>
