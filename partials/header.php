<?php
declare(strict_types=1);
// Diharapkan: $pageTitle ditetapkan sebelum include fail ni.
use Cukru\OwnerAuth;
use Cukru\Settings;

$siteName = Settings::get('site_name', 'CukruStorage');
$currentPage = basename($_SERVER['SCRIPT_NAME']);

function owner_nav_active(string $file, string $current): string
{
    return $file === $current ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle ?? $siteName) ?> - <?= e($siteName) ?></title>
<link rel="icon" type="image/png" href="<?= asset('images/favicon.png') ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
<link rel="stylesheet" href="<?= asset('css/style.css') ?>">
<?= $extraHead ?? '' ?>
</head>
<body>
<div class="topbar">
    <a class="brand" href="<?= base_path() ?>/index.php">
        <img src="<?= asset('images/favicon.png') ?>" alt="" class="brand-logo">
        <?= brand_name_html($siteName) ?>
    </a>
    <nav>
        <a href="<?= base_path() ?>/booking.php" class="<?= owner_nav_active('booking.php', $currentPage) ?>">
            <i class="fa-solid fa-box"></i><span>New Booking</span>
        </a>
        <?php if (OwnerAuth::isLoggedIn()): ?>
            <a href="<?= base_path() ?>/dashboard.php" class="<?= owner_nav_active('dashboard.php', $currentPage) ?>">
                <i class="fa-solid fa-warehouse"></i><span>My Storage</span>
            </a>
            <a href="<?= base_path() ?>/logout.php">
                <i class="fa-solid fa-door-open"></i><span>Log Out</span>
            </a>
        <?php else: ?>
            <a href="<?= base_path() ?>/login.php" class="<?= owner_nav_active('login.php', $currentPage) ?>">
                <i class="fa-solid fa-key"></i><span>Log In</span>
            </a>
        <?php endif; ?>
    </nav>
</div>
<div class="container">
<?php if ($msg = flash_get('error')): ?>
    <div class="alert alert-error"><?= e($msg) ?></div>
<?php endif; ?>
<?php if ($msg = flash_get('success')): ?>
    <div class="alert alert-success"><?= e($msg) ?></div>
<?php endif; ?>
<?php if ($msg = flash_get('info')): ?>
    <div class="alert alert-info"><?= e($msg) ?></div>
<?php endif; ?>
