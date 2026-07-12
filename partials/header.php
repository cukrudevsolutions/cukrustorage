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
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#4f46e5">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="<?= e($siteName) ?>">
<title><?= e($pageTitle ?? $siteName) ?> - <?= e($siteName) ?></title>
<link rel="icon" type="image/png" href="<?= asset('images/favicon.png') ?>">
<link rel="apple-touch-icon" href="<?= asset('images/icon-192.png') ?>">
<link rel="manifest" href="<?= base_path() ?>/manifest.json">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
<link rel="stylesheet" href="<?= asset('css/style.css', true) ?>">
<?= $extraHead ?? '' ?>
</head>
<body>
<div class="topbar">
    <a class="brand" href="<?= base_path() ?>/index.php">
        <img src="<?= asset('images/favicon.png') ?>" alt="" class="brand-logo">
        <?= brand_name_html($siteName) ?>
    </a>
    <nav>
        <a href="<?= base_path() ?>/return-schedule.php" class="<?= owner_nav_active('return-schedule.php', $currentPage) ?>">
            <i class="fa-solid fa-calendar-check"></i><span>Return</span>
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
