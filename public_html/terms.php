<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';

use Cukru\Settings;
use Cukru\Terms;

$pageTitle = 'Terma & Syarat';
require __DIR__ . '/partials/header.php';
?>

<div class="card">
    <?= Terms::render(Settings::get('terms_and_conditions', '')) ?>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
