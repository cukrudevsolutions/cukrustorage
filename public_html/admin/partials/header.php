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
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle ?? 'Admin') ?> - Admin <?= e($siteName) ?></title>
<link rel="icon" type="image/png" href="../assets/images/favicon.png">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="admin-layout">
    <div class="admin-sidebar">
        <span class="brand">
            <img src="../assets/images/favicon.png" alt="" class="brand-logo">
            <?= brand_name_html($siteName) ?> Admin
        </span>
        <nav>
            <a href="dashboard.php" class="<?= nav_active('dashboard.php', $current) ?>">
                <span class="icon"><i class="fa-solid fa-house"></i></span>Dashboard
                <?php if ($pendingCount > 0): ?><span class="notif-badge"><?= $pendingCount ?></span><?php endif; ?>
            </a>
            <a href="bookings.php" class="<?= nav_active('bookings.php', $current) ?>"><span class="icon"><i class="fa-solid fa-clipboard-list"></i></span>Bookings</a>
            <a href="scan.php" class="<?= nav_active('scan.php', $current) ?>"><span class="icon"><i class="fa-solid fa-qrcode"></i></span>Scan</a>
            <a href="settings.php" class="<?= nav_active('settings.php', $current) ?>"><span class="icon"><i class="fa-solid fa-gear"></i></span>Settings</a>
        </nav>
        <div class="sidebar-footer">
            <a href="logout.php"><span class="icon"><i class="fa-solid fa-right-from-bracket"></i></span>Log Out (<?= e(AdminAuth::username() ?? '') ?>)</a>
        </div>
    </div>
    <div class="admin-main">
        <div class="admin-topbar">
            <strong><?= e($pageTitle ?? '') ?></strong>
            <span class="muted"><?= e(AdminAuth::username() ?? '') ?></span>
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
