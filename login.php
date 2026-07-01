<?php
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';

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

<div style="text-align:center;margin-bottom:var(--space-5);">
    <img src="<?= asset('images/favicon.png') ?>" alt="" style="width:52px;height:52px;border-radius:var(--radius-md);margin:0 auto var(--space-3);">
    <h1 style="margin-bottom:var(--space-1);">My Storage</h1>
    <p class="muted" style="margin:0;">Track your items — anytime, anywhere.</p>
</div>

<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:var(--space-2);margin-bottom:var(--space-5);">
    <div style="background:var(--color-card);border:1px solid var(--color-border);border-radius:var(--radius-md);padding:var(--space-3);text-align:center;">
        <i class="fa-solid fa-warehouse" style="color:var(--color-primary);font-size:1.2rem;display:block;margin-bottom:4px;"></i>
        <span style="font-size:0.72rem;font-weight:600;color:var(--color-text);">Check Status</span>
    </div>
    <div style="background:var(--color-card);border:1px solid var(--color-border);border-radius:var(--radius-md);padding:var(--space-3);text-align:center;">
        <i class="fa-solid fa-camera" style="color:var(--color-primary);font-size:1.2rem;display:block;margin-bottom:4px;"></i>
        <span style="font-size:0.72rem;font-weight:600;color:var(--color-text);">View Photos</span>
    </div>
    <div style="background:var(--color-card);border:1px solid var(--color-border);border-radius:var(--radius-md);padding:var(--space-3);text-align:center;">
        <i class="fa-solid fa-receipt" style="color:var(--color-primary);font-size:1.2rem;display:block;margin-bottom:4px;"></i>
        <span style="font-size:0.72rem;font-weight:600;color:var(--color-text);">Get Slip</span>
    </div>
</div>

<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<form method="post" class="card">
    <?= Csrf::field() ?>
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
