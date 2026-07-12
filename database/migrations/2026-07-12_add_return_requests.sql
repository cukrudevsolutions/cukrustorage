-- =====================================================================
-- Feature: Return period self-service pickup/drop-off scheduling.
-- Owners book ONE return date (Self Pickup or Team Pickup) covering all
-- their bookings at once. Team Pickup uses hourly slots enforced unique
-- by database.
--
-- Run this ONCE against the production database (phpMyAdmin / mysql CLI).
-- Steps 1-3 (new tables, ENUM widen+narrow) are NOT safe to blindly re-run
-- as a whole script - if you must re-run after a partial failure, check
-- which steps already applied first. Step 4 (settings) IS safe to re-run
-- (no-op upsert, won't clobber values already edited by admin).
-- =====================================================================

-- 1. New tables.
CREATE TABLE IF NOT EXISTS return_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    no_telefon VARCHAR(15) NOT NULL,
    method ENUM('team_pickup', 'self_pickup') NOT NULL,
    return_date DATE NOT NULL,
    slot_time TIME NULL DEFAULT NULL,
    lane ENUM('normal', 'fast') NOT NULL DEFAULT 'normal',
    fast_lane_fee DECIMAL(10,2) NULL DEFAULT NULL,
    status ENUM('confirmed', 'pending_approval', 'rejected', 'cancelled') NOT NULL DEFAULT 'confirmed',
    admin_notes TEXT NULL DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_no_telefon (no_telefon),
    INDEX idx_return_date (return_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS return_slot_locks (
    return_date DATE NOT NULL,
    slot_time TIME NOT NULL,
    return_request_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (return_date, slot_time),
    UNIQUE KEY uniq_request (return_request_id),
    CONSTRAINT fk_slot_lock_request FOREIGN KEY (return_request_id) REFERENCES return_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Retire 'ready_for_return' in favour of 'return_scheduled' / 'return_pending_approval'.
--    Widen the ENUM first so existing rows can be remapped, THEN narrow it.
ALTER TABLE bookings
    MODIFY status ENUM(
        'pending_approval', 'approved', 'in_storage', 'ready_for_return',
        'return_scheduled', 'return_pending_approval', 'returned', 'overdue', 'cancelled'
    ) NOT NULL DEFAULT 'pending_approval';

UPDATE bookings SET status = 'return_scheduled' WHERE status = 'ready_for_return';

ALTER TABLE bookings
    MODIFY status ENUM(
        'pending_approval', 'approved', 'in_storage',
        'return_scheduled', 'return_pending_approval', 'returned', 'overdue', 'cancelled'
    ) NOT NULL DEFAULT 'pending_approval';

-- 3. Link bookings to the return request that covers them.
ALTER TABLE bookings
    ADD COLUMN return_request_id INT UNSIGNED NULL DEFAULT NULL AFTER foto_storan_3,
    ADD INDEX idx_return_request_id (return_request_id),
    ADD CONSTRAINT fk_bookings_return_request FOREIGN KEY (return_request_id) REFERENCES return_requests(id) ON DELETE SET NULL;

-- 4. New settings (safe to re-run - no-op upsert preserves any value admin already edited).
INSERT INTO settings (setting_key, setting_value) VALUES
('return_team_pickup_enabled', '1'),
('return_operating_start_time', '09:00'),
('return_operating_end_time', '17:00'),
('return_slot_duration_minutes', '60'),
('return_fast_lane_fee', '10')
ON DUPLICATE KEY UPDATE setting_key = setting_key;
