<?php
declare(strict_types=1);

namespace Cukru;

use PDO;
use PDOException;
use DateTimeImmutable;

final class ReturnRequestRepository
{
    private const MYSQL_DUPLICATE_ENTRY = 1062;

    /** @return array{enabled: bool, start: string, end: string, duration: int, fee: float} */
    public static function getScheduleConfig(): array
    {
        return [
            'enabled' => Settings::getBool('return_team_pickup_enabled', true),
            'start' => Settings::get('return_operating_start_time', '09:00'),
            'end' => Settings::get('return_operating_end_time', '17:00'),
            'duration' => Settings::getInt('return_slot_duration_minutes', 60),
            'fee' => Settings::getFloat('return_fast_lane_fee', 10.0),
        ];
    }

    /**
     * Slot grid untuk satu tarikh, dikira terus dari Settings semasa (bukan pra-jana) -
     * apa-apa perubahan admin buat kat Settings terus terpakai untuk tarikh akan datang.
     * @return list<array{time: string, available: bool}>
     */
    public static function getSlotsForDate(string $date): array
    {
        $config = self::getScheduleConfig();
        $start = DateTimeImmutable::createFromFormat('H:i', $config['start']);
        $end = DateTimeImmutable::createFromFormat('H:i', $config['end']);
        if (!$start || !$end || $start >= $end || $config['duration'] < 1) {
            return [];
        }

        $taken = self::getTakenSlotTimes($date);

        $slots = [];
        $cursor = $start;
        while ($cursor < $end) {
            $time = $cursor->format('H:i');
            $slots[] = ['time' => $time, 'available' => !in_array($time, $taken, true)];
            $cursor = $cursor->modify('+' . $config['duration'] . ' minutes');
        }

        return $slots;
    }

    /** @return list<string> */
    private static function getTakenSlotTimes(string $date): array
    {
        $stmt = Database::pdo()->prepare('SELECT slot_time FROM return_slot_locks WHERE return_date = ?');
        $stmt->execute([$date]);
        return array_map(static fn (string $t): string => substr($t, 0, 5), $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public static function findById(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM return_requests WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Rekod return_request aktif (belum rejected/cancelled) yang dah dipautkan kepada mana-mana booking id yang diberi. */
    public static function findActiveForBookingIds(array $bookingIds): ?array
    {
        if (empty($bookingIds)) {
            return null;
        }
        $placeholders = implode(',', array_fill(0, count($bookingIds), '?'));
        $stmt = Database::pdo()->prepare(
            "SELECT return_request_id FROM bookings WHERE id IN ({$placeholders}) AND return_request_id IS NOT NULL LIMIT 1"
        );
        $stmt->execute($bookingIds);
        $requestId = $stmt->fetchColumn();
        if (!$requestId) {
            return null;
        }
        $request = self::findById((int) $requestId);
        if ($request && in_array($request['status'], ['confirmed', 'pending_approval'], true)) {
            return $request;
        }
        return null;
    }

    /** @return list<array> Semua booking yang dipautkan kepada satu return_request. */
    public static function findLinkedBookings(int $requestId): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM bookings WHERE return_request_id = ?');
        $stmt->execute([$requestId]);
        return $stmt->fetchAll();
    }

    /**
     * Cipta tempahan Self Pickup - terus 'confirmed', tiada slot/lock (tiada resource contention).
     * @param list<int> $bookingIds
     */
    public static function createSelfPickup(array $bookingIds, string $noTelefon, string $returnDate, string $actor): int
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO return_requests (no_telefon, method, return_date, lane, status) VALUES (?, \'self_pickup\', ?, \'normal\', \'confirmed\')'
            );
            $stmt->execute([$noTelefon, $returnDate]);
            $requestId = (int) $pdo->lastInsertId();

            self::applyToBookings($bookingIds, $requestId, 'return_scheduled', $actor, 'Self Pickup scheduled for ' . $returnDate);

            $pdo->commit();
            return $requestId;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Cipta tempahan Team Pickup normal-lane. Slot dikunci dalam transaksi yang SAMA
     * dengan INSERT return_requests - kalau lock gagal (slot dah diambil), whole
     * transaction rollback termasuk return_requests tu, elak orphan row.
     * @param list<int> $bookingIds
     * @return array{success: bool, request_id: ?int, error: ?string}
     */
    public static function createTeamPickupNormal(array $bookingIds, string $noTelefon, string $returnDate, string $slotTime, string $actor): array
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO return_requests (no_telefon, method, return_date, slot_time, lane, status) VALUES (?, \'team_pickup\', ?, ?, \'normal\', \'confirmed\')'
            );
            $stmt->execute([$noTelefon, $returnDate, $slotTime]);
            $requestId = (int) $pdo->lastInsertId();

            $lockStmt = $pdo->prepare('INSERT INTO return_slot_locks (return_date, slot_time, return_request_id) VALUES (?, ?, ?)');
            $lockStmt->execute([$returnDate, $slotTime, $requestId]);

            self::applyToBookings($bookingIds, $requestId, 'return_scheduled', $actor, "Team Pickup scheduled for {$returnDate} {$slotTime}");

            $pdo->commit();
            return ['success' => true, 'request_id' => $requestId, 'error' => null];
        } catch (PDOException $e) {
            $pdo->rollBack();
            if (($e->errorInfo[1] ?? null) === self::MYSQL_DUPLICATE_ENTRY) {
                return ['success' => false, 'request_id' => null, 'error' => 'slot_taken'];
            }
            throw $e;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Cipta permintaan Fast Lane - status 'pending_approval', TIADA lock diambil lagi
     * (sengaja ditangguhkan sehingga admin approve, sebab keputusan kapasiti perlu manusia,
     * bukan sekadar data-integrity check).
     * @param list<int> $bookingIds
     */
    public static function createTeamPickupFast(array $bookingIds, string $noTelefon, string $returnDate, string $slotTime, float $fee, string $actor): int
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO return_requests (no_telefon, method, return_date, slot_time, lane, fast_lane_fee, status)
                 VALUES (?, \'team_pickup\', ?, ?, \'fast\', ?, \'pending_approval\')'
            );
            $stmt->execute([$noTelefon, $returnDate, $slotTime, $fee]);
            $requestId = (int) $pdo->lastInsertId();

            self::applyToBookings($bookingIds, $requestId, 'return_pending_approval', $actor, "Fast Lane requested for {$returnDate} {$slotTime}");

            $pdo->commit();
            return $requestId;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Admin lulus permintaan Fast Lane. Cuba kunci slot SEKARANG (masa keputusan manusia
     * dibuat) - kalau slot dah diambil orang lain dalam masa menunggu approval, gagal
     * dengan bersih (tak auto-reassign) supaya admin boleh reject dengan sebab yang jelas.
     * @return array{success: bool, error: ?string}
     */
    public static function approveFastLane(int $requestId, string $actor): array
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $request = self::findById($requestId);
            if (!$request || $request['status'] !== 'pending_approval') {
                throw new \RuntimeException('Return request not found or not pending approval');
            }

            $lockStmt = $pdo->prepare('INSERT INTO return_slot_locks (return_date, slot_time, return_request_id) VALUES (?, ?, ?)');
            $lockStmt->execute([$request['return_date'], $request['slot_time'], $requestId]);

            $pdo->prepare('UPDATE return_requests SET status = \'confirmed\' WHERE id = ?')->execute([$requestId]);

            $bookingIds = array_map(static fn (array $b): int => (int) $b['id'], self::findLinkedBookings($requestId));
            self::applyToBookings($bookingIds, $requestId, 'return_scheduled', $actor, 'Fast Lane request approved');

            $pdo->commit();
            return ['success' => true, 'error' => null];
        } catch (PDOException $e) {
            $pdo->rollBack();
            if (($e->errorInfo[1] ?? null) === self::MYSQL_DUPLICATE_ENTRY) {
                return ['success' => false, 'error' => 'slot_taken'];
            }
            throw $e;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** Admin tolak permintaan Fast Lane - booking kembali ke in_storage, owner boleh submit semula. */
    public static function rejectFastLane(int $requestId, string $actor, string $notes): void
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $request = self::findById($requestId);
            if (!$request || $request['status'] !== 'pending_approval') {
                throw new \RuntimeException('Return request not found or not pending approval');
            }

            $pdo->prepare('UPDATE return_requests SET status = \'rejected\', admin_notes = ? WHERE id = ?')->execute([$notes, $requestId]);

            $bookingIds = array_map(static fn (array $b): int => (int) $b['id'], self::findLinkedBookings($requestId));
            self::applyToBookings($bookingIds, null, 'in_storage', $actor, 'Fast Lane request rejected: ' . $notes);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** Kemaskini status + return_request_id untuk sekumpulan booking, dalam transaksi caller sendiri (tak buka transaksi baru). */
    private static function applyToBookings(array $bookingIds, ?int $requestId, string $newStatus, string $actor, ?string $notes): void
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('UPDATE bookings SET status = ?, return_request_id = ? WHERE id = ?');
        foreach ($bookingIds as $id) {
            $current = BookingRepository::findById((int) $id);
            if (!$current) {
                continue;
            }
            $stmt->execute([$newStatus, $requestId, $id]);
            BookingRepository::logStatus((int) $id, $current['status'], $newStatus, $actor, $notes);
        }
    }

    /** @return list<array> Untuk senarai admin, dikelompokkan ikut tarikh oleh caller. */
    public static function listAll(?string $status = null): array
    {
        $sql = 'SELECT * FROM return_requests';
        $params = [];
        if ($status) {
            $sql .= ' WHERE status = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY return_date ASC, slot_time ASC';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function countPendingFastLane(): int
    {
        $stmt = Database::pdo()->query("SELECT COUNT(*) FROM return_requests WHERE status = 'pending_approval'");
        return (int) $stmt->fetchColumn();
    }
}
