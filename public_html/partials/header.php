<?php
declare(strict_types=1);
// Diharapkan: $pageTitle ditetapkan sebelum include fail ni.
use Cukru\OwnerAuth;
use Cukru\Settings;

$siteName = Settings::get('site_name', 'CukruStorage');
?>
<!DOCTYPE html>
<html lang="ms">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle ?? $siteName) ?> - <?= e($siteName) ?></title>
<link rel="stylesheet" href="<?= asset('css/style.css') ?>">
</head>
<body>
<div class="topbar">
    <a class="brand" href="<?= base_path() ?>/index.php"><?= e($siteName) ?></a>
    <nav>
        <a href="<?= base_path() ?>/booking.php">Booking Baharu</a>
        <?php if (OwnerAuth::isLoggedIn()): ?>
            <a href="<?= base_path() ?>/dashboard.php">Dashboard Saya</a>
            <a href="<?= base_path() ?>/logout.php">Log Keluar</a>
        <?php else: ?>
            <a href="<?= base_path() ?>/login.php">Log Masuk</a>
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
