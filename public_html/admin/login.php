<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/config.php';

use Cukru\Csrf;
use Cukru\AdminAuth;
use Cukru\Settings;

if (AdminAuth::isLoggedIn()) {
    redirect('admin/dashboard.php');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    $username = trim($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    $result = AdminAuth::attempt($username, $password);
    if ($result['success']) {
        redirect('admin/dashboard.php');
    }
    $error = $result['message'];
}

$siteName = Settings::get('site_name', 'CukruStorage');
?>
<!DOCTYPE html>
<html lang="ms">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin Log Masuk - <?= e($siteName) ?></title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="container" style="max-width:420px;padding-top:60px;">
    <h1 style="text-align:center;">Admin <?= e($siteName) ?></h1>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" class="card">
        <?= Csrf::field() ?>
        <label class="required" for="username">Username</label>
        <input type="text" id="username" name="username" required autofocus>

        <label class="required" for="password">Kata Laluan</label>
        <input type="password" id="password" name="password" required>

        <button type="submit" class="btn btn-block" style="margin-top:18px;">Log Masuk</button>
    </form>
</div>
</body>
</html>
