<?php
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';

use Cukru\BookingRepository;

$ref = trim((string) ($_GET['ref'] ?? ''));
$booking = $ref !== '' ? BookingRepository::findByRef($ref) : null;

$pageTitle = 'Booking Submitted';
require __DIR__ . '/partials/header.php';
?>

<div class="card" style="text-align:center;padding:var(--space-8) var(--space-5);">
    <div class="auth-icon" style="background:var(--color-success-bg);color:var(--color-success);"><i class="fa-solid fa-check"></i></div>
    <h1>Your Booking Has Been Submitted</h1>
    <p class="muted">Please wait for admin approval to get the final price. You'll be able to check your status anytime via My Dashboard.</p>

    <?php if ($booking): ?>
        <div class="card" style="background:var(--color-bg);box-shadow:none;text-align:left;margin:var(--space-5) 0 0;">
            <div class="kv"><span class="k">Booking No.</span><span class="v"><?= e($booking['booking_ref']) ?></span></div>
            <div class="kv"><span class="k">Status</span><span class="v"><span class="badge badge-<?= e($booking['status']) ?>">Pending Approval</span></span></div>
        </div>
    <?php endif; ?>

    <a class="btn btn-block" style="margin-top:var(--space-5);" href="login.php">Log In to Check Status</a>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
