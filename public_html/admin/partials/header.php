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
<html lang="ms">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle ?? 'Admin') ?> - Admin <?= e($siteName) ?></title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="admin-layout">
    <div class="admin-sidebar">
        <span class="brand"><?= e($siteName) ?> Admin</span>
        <a href="dashboard.php" class="<?= nav_active('dashboard.php', $current) ?>">Dashboard
            <?php if ($pendingCount > 0): ?><span class="notif-badge"><?= $pendingCount ?></span><?php endif; ?>
        </a>
        <a href="bookings.php" class="<?= nav_active('bookings.php', $current) ?>">Semua Booking</a>
        <a href="scan.php" class="<?= nav_active('scan.php', $current) ?>">Scan / Update Status</a>
        <a href="settings.php" class="<?= nav_active('settings.php', $current) ?>">Tetapan</a>
        <a href="logout.php">Log Keluar (<?= e(AdminAuth::username() ?? '') ?>)</a>
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
