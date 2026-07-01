<?php
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';

use Cukru\Settings;
use Cukru\Terms;

$pageTitle = 'Terms & Conditions';
require __DIR__ . '/partials/header.php';
?>

<h1><i class="fa-solid fa-scroll"></i> Terms &amp; Conditions</h1>
<p class="muted">Please read carefully before making a booking.</p>

<div class="card" style="font-size:0.92rem;line-height:1.7;">
    <?= Terms::render(Settings::get('terms_and_conditions', '')) ?>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
