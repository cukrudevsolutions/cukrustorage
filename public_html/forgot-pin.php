<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';

use Cukru\Csrf;
use Cukru\Validation;
use Cukru\PinReset;

$sent = false;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    $phone = trim($_POST['no_telefon'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if (!Validation::isValidMalaysianPhone($phone)) {
        $errors[] = 'Sila isi no. telefon yang sah.';
    }
    if (!Validation::isValidEmail($email)) {
        $errors[] = 'Sila isi alamat emel yang sah.';
    }

    if (empty($errors)) {
        PinReset::request($phone, $email);
        $sent = true;
    }
}

$pageTitle = 'Lupa PIN';
require __DIR__ . '/partials/header.php';
?>

<h1>Reset PIN</h1>

<?php if ($sent): ?>
    <div class="alert alert-success">
        Jika no. telefon &amp; emel yang anda masukkan sepadan dengan rekod kami, satu pautan reset PIN telah dihantar ke emel tersebut. Pautan sah selama 1 jam.
    </div>
    <p><a class="btn btn-secondary" href="login.php">Kembali ke Log Masuk</a></p>
<?php else: ?>
    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <ul style="margin:0;padding-left:18px;">
                <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    <p class="muted">Masukkan no. telefon dan emel yang anda daftarkan semasa booking. Pautan reset PIN akan dihantar ke emel tersebut.</p>
    <form method="post" class="card">
        <?= Csrf::field() ?>
        <label class="required" for="no_telefon">No. Telefon</label>
        <input type="tel" id="no_telefon" name="no_telefon" placeholder="012-3456789" required>

        <label class="required" for="email">Emel</label>
        <input type="email" id="email" name="email" required>

        <button type="submit" class="btn btn-block" style="margin-top:18px;">Hantar Pautan Reset</button>
    </form>
<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
