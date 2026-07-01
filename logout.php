<?php
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';

use Cukru\OwnerAuth;

OwnerAuth::logout();
redirect('login.php');
