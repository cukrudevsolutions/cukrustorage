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
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin Log In - <?= e($siteName) ?></title>
<link rel="icon" type="image/png" href="../assets/images/favicon.png">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="auth-screen">
    <div class="auth-box">
        <img src="../assets/images/favicon.png" alt="" style="width:56px;height:56px;border-radius:var(--radius-md);display:block;margin:0 auto var(--space-4);">
        <h1 style="text-align:center;">Admin <?= brand_name_html($siteName) ?></h1>
        <p class="muted" style="text-align:center;margin-bottom:var(--space-5);">Log in to manage bookings & system settings.</p>
        <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
        <form method="post" class="card">
            <?= Csrf::field() ?>
            <label class="required" for="username">Username</label>
            <input type="text" id="username" name="username" required autofocus autocomplete="username">

            <label class="required" for="password">Password</label>
            <input type="password" id="password" name="password" required autocomplete="current-password">

            <button type="submit" class="btn btn-block" style="margin-top:var(--space-5);">Log In</button>
        </form>
    </div>
</div>
</body>
</html>
