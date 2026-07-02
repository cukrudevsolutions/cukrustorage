-- =====================================================================
-- CukruStorage - Database Schema
-- Import fail ni melalui phpMyAdmin (atau `mysql -u user -p dbname < schema.sql`)
-- Guna utf8mb4 untuk sokongan penuh aksara (emoji, dsb.)
-- =====================================================================

SET NAMES utf8mb4;
SET time_zone = '+08:00';

-- ---------------------------------------------------------------------
-- Table: admins
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admins (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    failed_attempts INT UNSIGNED NOT NULL DEFAULT 0,
    locked_until DATETIME NULL DEFAULT NULL,
    last_login_at DATETIME NULL DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Table: bookings
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS bookings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_ref VARCHAR(20) NOT NULL UNIQUE,
    nama VARCHAR(100) NOT NULL,
    no_telefon VARCHAR(15) NOT NULL,
    pin_hash VARCHAR(255) NOT NULL,
    email VARCHAR(150) NOT NULL,
    bilangan_kotak SMALLINT UNSIGNED NOT NULL,
    jenis_servis ENUM('dropoff', 'pickup') NOT NULL,
    alamat_pickup TEXT NULL DEFAULT NULL,
    jarak_anggaran VARCHAR(50) NULL DEFAULT NULL,
    tarikh_dicadang DATE NOT NULL,
    harga_storage DECIMAL(10,2) NULL DEFAULT NULL,
    harga_pickup DECIMAL(10,2) NULL DEFAULT NULL,
    harga_total DECIMAL(10,2) NULL DEFAULT NULL,
    status ENUM(
        'pending_approval',
        'approved',
        'in_storage',
        'ready_for_return',
        'returned',
        'overdue',
        'cancelled'
    ) NOT NULL DEFAULT 'pending_approval',
    qr_token VARCHAR(64) NULL DEFAULT NULL UNIQUE,
    terms_accepted_at DATETIME NOT NULL,
    admin_notes TEXT NULL DEFAULT NULL,
    returned_at DATETIME NULL DEFAULT NULL,
    -- Gambar barang di lokasi storan (bukti rujukan utk admin/owner semasa pengambilan semula).
    -- Disimpan terus sebagai base64 data URI (dimampatkan & disaiz semula oleh aplikasi sebelum simpan).
    foto_storan_1 LONGTEXT NULL DEFAULT NULL,
    foto_storan_2 LONGTEXT NULL DEFAULT NULL,
    foto_storan_3 LONGTEXT NULL DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_no_telefon (no_telefon),
    INDEX idx_status (status),
    INDEX idx_qr_token (qr_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Table: status_logs
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS status_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id INT UNSIGNED NOT NULL,
    status_lama VARCHAR(30) NULL DEFAULT NULL,
    status_baru VARCHAR(30) NOT NULL,
    updated_by VARCHAR(50) NOT NULL,
    notes TEXT NULL DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_status_logs_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    INDEX idx_booking_id (booking_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Table: login_throttle
-- Had kadar percubaan log masuk Owner (no. telefon + PIN) untuk elak brute-force,
-- memandangkan PIN cuma 4-6 digit (ruang kemungkinan kecil).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS login_throttle (
    identifier VARCHAR(100) NOT NULL PRIMARY KEY,
    attempts INT UNSIGNED NOT NULL DEFAULT 0,
    locked_until DATETIME NULL DEFAULT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Table: settings (key/value - boleh diedit dari admin panel tanpa edit code)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS settings (
    setting_key VARCHAR(60) NOT NULL PRIMARY KEY,
    setting_value LONGTEXT NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================================
-- SEED DATA - settings
-- =====================================================================

INSERT INTO settings (setting_key, setting_value) VALUES
('site_name', 'CukruStorage'),

-- No. WhatsApp admin (format antarabangsa tanpa "+", contoh 60147978792) - untuk butang
-- "Tanya Admin" pada borang booking (contoh: bilangan kotak tak standard)
('admin_whatsapp', '60147978792'),

-- Pautan Google Maps lokasi drop-off/pickup - untuk butang "Lihat Lokasi" pada borang booking
('location_maps_url', 'https://share.google/iTlAHYYljlh38B8Ej'),

-- Rate card storan
('rate_box1', '30'),
('rate_box2', '55'),
('rate_box3', '80'),
('rate_extra_box', '10'),

-- Caj lewat (overdue)
('overdue_rate_per_day', '10'),
('overdue_grace_days', '0'),

-- Barang tidak dituntut (hari selepas tamat return window)
('unclaimed_days', '30'),

-- Pickup
('pickup_min_advance_days', '1'),

-- Tarikh penting sesi semasa
('window1_start', '2026-07-02'),
('window1_end', '2026-07-05'),
('window2_start', '2026-07-09'),
('window2_end', '2026-07-10'),
('return_window_start', '2026-10-03'),
('return_window_end', '2026-10-09'),

-- Keselamatan admin login
('admin_lockout_attempts', '5'),
('admin_lockout_minutes', '15'),

-- Keselamatan owner login (no. telefon + PIN)
('owner_lockout_attempts', '5'),
('owner_lockout_minutes', '15'),

-- Terma & Syarat penuh (boleh diedit dari admin panel)
('terms_and_conditions', '**1. Definitions & Acceptance**\n1.1 \"CukruStorage\" refers to the item storage service operated by the owner of this business.\n1.2 \"Customer\" refers to the item owner who registers for and uses this service.\n1.3 By submitting the registration form and ticking the consent checkbox, the Customer is deemed to have read, understood, and agreed to all the terms below.\n\n**2. Service Period**\n2.1 The storage service is provided for a period of 3 months, starting from the item drop-off/pickup date until the end of the stipulated Return Period.\n2.2 The following dates apply for the current session:\n- Drop-off / Pickup by team: 2/7/2026 - 5/7/2026 and 9/7/2026 - 10/7/2026\n- Item collection (Return Period): 3/10/2026 - 9/10/2026\n2.3 CukruStorage reserves the right to change the above dates for future sessions with prior notice.\n\n**3. Charges & Payment**\n3.1 Storage rates are as follows:\n- 1 Box: RM30\n- 2 Boxes: RM55\n- 3 Boxes: RM80\n- 4th box onwards: +RM10/box\n3.2 The final price is subject to admin approval after reviewing the registration form.\n3.3 Full payment must be made before or during item drop-off/pickup, unless otherwise stated by the admin.\n3.4 Any additional charges (e.g. oversized boxes, high-risk items, or special requests) will be communicated and agreed upon before final approval.\n\n**4. Drop-off Service (Customer Delivers Personally)**\n4.1 The Customer must complete the online registration form and receive confirmation beforehand, before bringing items to the CukruStorage location.\n4.2 Walk-in on the same day is allowed, provided the registration form has already been submitted and confirmed.\n4.3 CukruStorage will not accept any items without a valid registration record.\n\n**5. Pickup Service (CukruStorage Team Collects from Customer\'s Location)**\n5.1 Pickup requests must be made at least 1 day before the requested pickup date.\n5.2 Pickup charges are calculated based on distance and labour, and will be confirmed by the admin during the approval process.\n5.3 The Customer must ensure items are ready for collection at the agreed date and time. Delays or unavailability of items at the scheduled pickup time may result in additional charges or rescheduling.\n\n**6. Return of Items**\n6.1 The Customer is responsible for collecting their items within the stipulated Return Period (3/10/2026 - 9/10/2026).\n6.2 Any collection made after the end of the Return Period will incur a late (overdue) charge of RM10 per day, calculated from the first day after the end date until the items are collected.\n6.3 Overdue charges will continue to accrue for as long as the items remain uncollected and the status has not been updated to \"Returned\" by the admin.\n6.4 Customers are advised to plan for early collection to avoid additional charges.\n\n**7. Unclaimed Items**\n7.1 If items are not collected within 30 days after the end of the Return Period, CukruStorage reserves the right to treat the items as abandoned, and may dispose of, sell, or donate the items to recover outstanding costs, without further liability to CukruStorage.\n\n**8. Care of Items**\n8.1 CukruStorage will take reasonable precautions to safeguard the security of stored items.\n8.2 Customers are advised not to store high-value items, important documents, cash, perishable items, or illegal/hazardous items within the storage.\n\n**9. Prohibited Items**\n9.1 Customers are prohibited from storing items such as: flammable/explosive materials, illegal substances, weapons, wet/perishable items, live animals, cash/high-value jewellery, or any item that violates the law.\n9.2 CukruStorage reserves the right to inspect and reject any item suspected of violating these terms.\n\n**10. Privacy & Personal Data**\n10.1 Personal information (name, phone number, email, address) provided will be used solely for the purpose of managing the storage service and will not be shared with third parties without consent, except where required by law.\n\n**11. Account & System Access**\n11.1 Customers are responsible for keeping their account PIN confidential.\n11.2 CukruStorage is not responsible for any unauthorised access resulting from the Customer\'s negligence in safeguarding their PIN.\n\n**12. Amendment of Terms**\n12.1 CukruStorage reserves the right to amend these terms and conditions from time to time. Any amendments will be updated in the system and will apply to future registrations.\n\n**13. Consent**\nBy ticking the checkbox \"I agree to the CukruStorage Terms & Conditions\" during registration, the Customer confirms having read, understood, and accepted all the terms above.')

ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- =====================================================================
-- SEED DATA - admin akaun default
-- Username: admin   Password: ChangeMe123!
-- WAJIB tukar password ni serta-merta selepas login kali pertama.
-- Hash di bawah dijana guna password_hash('ChangeMe123!', PASSWORD_DEFAULT)
-- =====================================================================
INSERT INTO admins (username, password_hash) VALUES
('admin', '$2y$10$XwJDXpIx0iQLTTO5WAJeBegN.l16DHOzi2E1Tw3d3rOI034ZuJReW')
ON DUPLICATE KEY UPDATE username = username;
