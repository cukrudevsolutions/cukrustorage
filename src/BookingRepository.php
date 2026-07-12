<?php
declare(strict_types=1);

namespace Cukru;

use PDO;
use DateTimeImmutable;

final class BookingRepository
{
    public static function generateBookingRef(): string
    {
        $pdo = Database::pdo();
        do {
            $ref = 'CKS-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
            $stmt = $pdo->prepare('SELECT id FROM bookings WHERE booking_ref = ?');
            $stmt->execute([$ref]);
        } while ($stmt->fetch());

        return $ref;
    }

    public static function generateQrToken(): string
    {
        $pdo = Database::pdo();
        do {
            $token = bin2hex(random_bytes(24));
            $stmt = $pdo->prepare('SELECT id FROM bookings WHERE qr_token = ?');
            $stmt->execute([$token]);
        } while ($stmt->fetch());

        return $token;
    }

    public static function create(array $d): int
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO bookings
                (booking_ref, nama, no_telefon, pin_hash, email, bilangan_kotak, jenis_servis,
                 alamat_pickup, jarak_anggaran, tarikh_dicadang, harga_storage, status, terms_accepted_at)
             VALUES
                (:booking_ref, :nama, :no_telefon, :pin_hash, :email, :bilangan_kotak, :jenis_servis,
                 :alamat_pickup, :jarak_anggaran, :tarikh_dicadang, :harga_storage, :status, NOW())'
        );
        $stmt->execute([
            'booking_ref' => $d['booking_ref'],
            'nama' => $d['nama'],
            'no_telefon' => $d['no_telefon'],
            'pin_hash' => $d['pin_hash'],
            'email' => $d['email'],
            'bilangan_kotak' => $d['bilangan_kotak'],
            'jenis_servis' => $d['jenis_servis'],
            'alamat_pickup' => $d['alamat_pickup'] ?? null,
            'jarak_anggaran' => $d['jarak_anggaran'] ?? null,
            'tarikh_dicadang' => $d['tarikh_dicadang'],
            'harga_storage' => $d['harga_storage'],
            'status' => 'pending_approval',
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function findById(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM bookings WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findByRef(string $ref): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM bookings WHERE booking_ref = ?');
        $stmt->execute([$ref]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findByQrToken(string $token): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM bookings WHERE qr_token = ?');
        $stmt->execute([$token]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findAllByPhone(string $normalizedPhone): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM bookings WHERE no_telefon = ? ORDER BY created_at DESC');
        $stmt->execute([$normalizedPhone]);
        return $stmt->fetchAll();
    }

    /** Reset PIN owner kepada nombor 4-digit rawak. Pulangkan PIN plain sekali sahaja untuk admin maklumkan kepada pelanggan. */
    public static function resetPin(int $id, string $adminUsername): string
    {
        $booking = self::findById($id);
        if (!$booking) {
            throw new \RuntimeException('Booking not found');
        }

        $pin = (string) random_int(1000, 9999);
        $stmt = Database::pdo()->prepare('UPDATE bookings SET pin_hash = ? WHERE id = ?');
        $stmt->execute([password_hash($pin, PASSWORD_DEFAULT), $id]);

        self::logStatus($id, $booking['status'], $booking['status'], $adminUsername, 'Owner PIN reset by admin');

        return $pin;
    }

    /**
     * Kemaskini sehingga 3 gambar barang di lokasi storan (data URI base64, atau null untuk slot kosong/dibuang).
     */
    public static function updatePhotos(int $id, ?string $foto1, ?string $foto2, ?string $foto3, string $adminUsername): void
    {
        $booking = self::findById($id);
        if (!$booking) {
            throw new \RuntimeException('Booking not found');
        }

        $stmt = Database::pdo()->prepare(
            'UPDATE bookings SET foto_storan_1 = ?, foto_storan_2 = ?, foto_storan_3 = ? WHERE id = ?'
        );
        $stmt->execute([$foto1, $foto2, $foto3, $id]);

        self::logStatus($id, $booking['status'], $booking['status'], $adminUsername, 'Storage photos updated');
    }

    public static function approve(int $id, float $hargaStorage, ?float $hargaPickup, float $hargaTotal, string $qrToken, string $adminUsername): void
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $current = self::findById($id);
            if (!$current) {
                throw new \RuntimeException('Booking not found');
            }

            $stmt = $pdo->prepare(
                'UPDATE bookings SET harga_storage = :hs, harga_pickup = :hp, harga_total = :ht,
                 status = :status, qr_token = :qr WHERE id = :id'
            );
            $stmt->execute([
                'hs' => $hargaStorage,
                'hp' => $hargaPickup,
                'ht' => $hargaTotal,
                'status' => 'approved',
                'qr' => $qrToken,
                'id' => $id,
            ]);

            self::logStatus($id, $current['status'], 'approved', $adminUsername, 'Approved with price ' . rm($hargaTotal));

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function updateStatus(int $id, string $newStatus, string $adminUsername, ?string $notes = null): void
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $current = self::findById($id);
            if (!$current) {
                throw new \RuntimeException('Booking not found');
            }

            $extra = '';
            $params = ['status' => $newStatus, 'id' => $id];
            if ($newStatus === 'returned') {
                $extra = ', returned_at = NOW()';
            }

            $stmt = $pdo->prepare("UPDATE bookings SET status = :status{$extra} WHERE id = :id");
            $stmt->execute($params);

            self::logStatus($id, $current['status'], $newStatus, $adminUsername, $notes);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function logStatus(int $bookingId, ?string $oldStatus, string $newStatus, string $updatedBy, ?string $notes = null): void
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO status_logs (booking_id, status_lama, status_baru, updated_by, notes)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$bookingId, $oldStatus, $newStatus, $updatedBy, $notes]);
    }

    public static function getLogs(int $bookingId): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM status_logs WHERE booking_id = ? ORDER BY created_at ASC');
        $stmt->execute([$bookingId]);
        return $stmt->fetchAll();
    }

    /**
     * @return array{rows: array, total: int}
     */
    public static function listFiltered(?string $status, ?string $search, int $page = 1, int $perPage = 20): array
    {
        $where = [];
        $params = [];

        if ($status && $status !== 'all') {
            $where[] = 'status = :status';
            $params['status'] = $status;
        }
        if ($search) {
            $where[] = '(nama LIKE :search1 OR no_telefon LIKE :search2 OR booking_ref LIKE :search3)';
            $params['search1'] = '%' . $search . '%';
            $params['search2'] = '%' . $search . '%';
            $params['search3'] = '%' . $search . '%';
        }

        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $pdo = Database::pdo();

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM bookings {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $offset = max(0, ($page - 1) * $perPage);
        $stmt = $pdo->prepare("SELECT * FROM bookings {$whereSql} ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}");
        $stmt->execute($params);

        return ['rows' => $stmt->fetchAll(), 'total' => $total];
    }

    public static function countPendingApproval(): int
    {
        $stmt = Database::pdo()->query("SELECT COUNT(*) FROM bookings WHERE status = 'pending_approval'");
        return (int) $stmt->fetchColumn();
    }

    /**
     * Tukar status bookings yang dah lepas tarikh return window (+ grace) kepada
     * 'overdue' secara automatik, dan log perubahan tu sebagai 'system'.
     * Dipanggil setiap kali dashboard admin/owner dimuatkan supaya status sentiasa terkini
     * walaupun tiada cron job disediakan di hosting.
     */
    public static function syncOverdueStatuses(): int
    {
        $cutoff = (new DateTimeImmutable(Settings::get('return_window_end', '2026-10-09')))
            ->modify('+' . Settings::getInt('overdue_grace_days', 0) . ' days')
            ->modify('+1 day');

        if (new DateTimeImmutable('today') < $cutoff) {
            return 0;
        }

        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            "SELECT id, status FROM bookings WHERE status IN ('approved', 'in_storage', 'return_scheduled', 'return_pending_approval')"
        );
        $stmt->execute();
        $rows = $stmt->fetchAll();

        foreach ($rows as $row) {
            self::updateStatus((int) $row['id'], 'overdue', 'system (auto)', 'Automatically changed to overdue after the return period ended');
        }

        return count($rows);
    }
}
