<?php
declare(strict_types=1);
// Diharapkan: $pageTitle ditetapkan, dan AdminAuth::requireLogin() telah dipanggil sebelum include fail ni.
use Cukru\Settings;
use Cukru\AdminAuth;
use Cukru\BookingRepository;

$siteName = Settings::get('site_name', 'CukruStorage');
$pendingCount = BookingRepository::countPendingApproval();
$current = basename($_SERVER['SCRIPT_NAME']);

function nav_active(string $file, string $current): string
{
    return $file === $current ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0f172a">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black">
<meta name="apple-mobile-web-app-title" content="CS Admin">
<title><?= e($pageTitle ?? 'Admin') ?> - Admin <?= e($siteName) ?></title>
<link rel="icon" type="image/png" href="../assets/images/favicon.png">
<link rel="apple-touch-icon" href="../assets/images/icon-192.png">
<link rel="manifest" href="../manifest.json">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
<link rel="stylesheet" href="<?= asset('css/style.css', true) ?>">
</head>
<body>
<div class="admin-layout">
    <div class="admin-sidebar">
        <span class="brand">
            <img src="../assets/images/favicon.png" alt="" class="brand-logo">
            <span class="brand-text">
                <?= brand_name_html($siteName) ?>
                <small>Admin Panel</small>
            </span>
        </span>
        <nav>
            <a href="dashboard.php" class="<?= nav_active('dashboard.php', $current) ?>">
                <span class="icon"><i class="fa-solid fa-house"></i></span>Dashboard
                <?php if ($pendingCount > 0): ?><span class="notif-badge"><?= $pendingCount ?></span><?php endif; ?>
            </a>
            <a href="bookings.php" class="<?= nav_active('bookings.php', $current) ?>"><span class="icon"><i class="fa-solid fa-clipboard-list"></i></span>Bookings</a>
            <a href="scan.php" class="<?= nav_active('scan.php', $current) ?>"><span class="icon"><i class="fa-solid fa-qrcode"></i></span>Scan</a>
            <a href="pickups.php" class="<?= nav_active('pickups.php', $current) ?>"><span class="icon"><i class="fa-solid fa-truck"></i></span>Pickups</a>
            <a href="settings.php" class="<?= nav_active('settings.php', $current) ?>"><span class="icon"><i class="fa-solid fa-gear"></i></span>Settings</a>
        </nav>
        <div class="sidebar-footer">
            <a href="logout.php"><span class="icon"><i class="fa-solid fa-right-from-bracket"></i></span>Log Out (<?= e(AdminAuth::username() ?? '') ?>)</a>
        </div>
    </div>
    <div class="admin-main">
        <div class="admin-topbar">
            <strong><?= e($pageTitle ?? '') ?></strong>
            <div class="admin-topbar-actions">
                <span class="muted"><?= e(AdminAuth::username() ?? '') ?></span>
                <a class="admin-mobile-logout" href="logout.php" aria-label="Log out">
                    <i class="fa-solid fa-right-from-bracket"></i>
                    <span>Log Out</span>
                </a>
            </div>
        </div>
        <div class="container-wide">
        <?php if ($msg = flash_get('error')): ?>
            <div class="alert alert-error"><?= e($msg) ?></div>
        <?php endif; ?>
        <?php if ($msg = flash_get('success')): ?>
            <div class="alert alert-success"><?= e($msg) ?></div>
        <?php endif; ?>
        <?php if ($msg = flash_get('info')): ?>
            <div class="alert alert-info"><?= e($msg) ?></div>
        <?php endif; ?>
