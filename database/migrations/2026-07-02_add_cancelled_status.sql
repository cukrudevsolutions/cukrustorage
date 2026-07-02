-- =====================================================================
-- Fix: bookings.status ENUM never included 'cancelled'.
-- Cancelling a booking silently stored status = '' (MySQL truncates an
-- invalid ENUM value in non-strict mode), which showed up as a blank
-- badge (just the bullet dot, no label) everywhere the status is shown.
--
-- Run this once against the production database (phpMyAdmin / mysql CLI).
-- Safe to re-run: step 1 is idempotent, step 2 only touches rows that are
-- still broken.
-- =====================================================================

-- 1. Add 'cancelled' to the allowed status values.
ALTER TABLE bookings
    MODIFY status ENUM(
        'pending_approval',
        'approved',
        'in_storage',
        'ready_for_return',
        'returned',
        'overdue',
        'cancelled'
    ) NOT NULL DEFAULT 'pending_approval';

-- 2. Repair bookings that were cancelled before the fix and got silently
--    blanked to status = ''. We recover them using status_logs, which
--    stores status_baru as free text (VARCHAR) so it was never corrupted.
--    Only the booking's current status is fixed; it is only touched if
--    'cancelled' is genuinely the most recent status change on record.
UPDATE bookings b
JOIN (
    SELECT sl.booking_id, sl.status_baru
    FROM status_logs sl
    INNER JOIN (
        SELECT booking_id, MAX(id) AS latest_id
        FROM status_logs
        GROUP BY booking_id
    ) latest ON latest.latest_id = sl.id
) last_log ON last_log.booking_id = b.id
SET b.status = 'cancelled'
WHERE b.status = ''
  AND last_log.status_baru = 'cancelled';
