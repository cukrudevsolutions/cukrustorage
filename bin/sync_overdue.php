<?php
declare(strict_types=1);

/**
 * Skrip CLI untuk dijadualkan sebagai Cron Job (cPanel Hostinger), contoh harian jam 12:01am:
 *   php /home/USERNAME/bin/sync_overdue.php
 *
 * Tujuan: pastikan status booking bertukar ke 'overdue' secara automatik sebaik sahaja
 * tamat Return Window, walaupun tiada admin/owner log masuk pada hari tersebut.
 * (Dashboard admin & owner turut menjalankan sync yang sama setiap kali dimuatkan,
 * jadi cron ni adalah lapisan tambahan untuk ketepatan data, bukan keperluan mutlak.)
 */

require_once __DIR__ . '/../config/config.php';

use Cukru\BookingRepository;

$count = BookingRepository::syncOverdueStatuses();
echo date('Y-m-d H:i:s') . " - {$count} booking ditukar ke status OVERDUE.\n";
