<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';

use Cukru\AdminAuth;

AdminAuth::logout();
redirect('admin/login.php');
