<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';

use Cukru\OwnerAuth;
use Cukru\Settings;

if (OwnerAuth::isLoggedIn()) {
    redirect('dashboard.php');
}

$pageTitle = 'Welcome';
require __DIR__ . '/partials/header.php';
?>

<div class="card" style="text-align:center;padding:var(--space-8) var(--space-5);">
    <div class="auth-icon" style="font-size:1.8rem;width:64px;height:64px;"><i class="fa-solid fa-box"></i></div>
    <h1><?= e(Settings::get('site_name', 'CukruStorage')) ?></h1>
    <p class="muted">Item storage service for students during the semester break. Easy, secure, and you can check your status anytime.</p>
    <div style="display:flex;gap:var(--space-3);justify-content:center;flex-wrap:wrap;margin-top:var(--space-5);">
        <a class="btn" href="booking.php"><i class="fa-solid fa-clipboard-list"></i> Make a New Booking</a>
        <a class="btn btn-secondary" href="login.php">Log In to Check Status</a>
    </div>
</div>

<div class="card">
    <h3 class="eyebrow" style="margin-bottom:var(--space-4);">How It Works</h3>
    <div class="step-row">
        <div class="step-num">1</div>
        <div><strong>Register</strong><span>Fill in the booking form & choose Self Drop-off or Team Pickup</span></div>
    </div>
    <div class="step-row">
        <div class="step-num">2</div>
        <div><strong>Approval</strong><span>Admin confirms the final price & generates a QR code for your booking</span></div>
    </div>
    <div class="step-row">
        <div class="step-num">3</div>
        <div><strong>Drop-off / Collection</strong><span>Show the QR code when dropping off items and when collecting them again</span></div>
    </div>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
