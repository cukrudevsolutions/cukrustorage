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
        'overdue'
    ) NOT NULL DEFAULT 'pending_approval',
    qr_token VARCHAR(64) NULL DEFAULT NULL UNIQUE,
    terms_accepted_at DATETIME NOT NULL,
    admin_notes TEXT NULL DEFAULT NULL,
    returned_at DATETIME NULL DEFAULT NULL,
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
-- Table: pin_reset_tokens
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pin_reset_tokens (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id INT UNSIGNED NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pin_reset_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    INDEX idx_token (token)
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
('terms_and_conditions', 'TERMA & SYARAT CUKRUSTORAGE\n\n**1. Definisi & Penerimaan**\n1.1 \"CukruStorage\" merujuk kepada perkhidmatan simpanan barang yang dikendalikan oleh pemilik perniagaan ini.\n1.2 \"Pelanggan\" merujuk kepada pemilik barang yang mendaftar dan menggunakan perkhidmatan ini.\n1.3 Dengan menghantar borang pendaftaran dan menandatangani/menanda checkbox persetujuan, Pelanggan dianggap telah membaca, faham, dan bersetuju dengan semua terma di bawah.\n\n**2. Tempoh Perkhidmatan**\n2.1 Perkhidmatan simpanan adalah untuk tempoh 3 bulan, bermula dari tarikh drop-off/pickup barang sehingga tarikh tamat tempoh return window yang ditetapkan.\n2.2 Tarikh-tarikh berikut terpakai untuk sesi semasa:\n- Drop-off / Pickup oleh team: 2/7/2026 - 5/7/2026 dan 9/7/2026 - 10/7/2026\n- Ambil semula barang (Return Window): 3/10/2026 - 9/10/2026\n2.3 CukruStorage berhak mengubah tarikh di atas untuk sesi akan datang dengan notis awal.\n\n**3. Caj & Pembayaran**\n3.1 Kadar simpanan adalah seperti berikut:\n- 1 Kotak: RM30\n- 2 Kotak: RM55\n- 3 Kotak: RM80\n- Kotak ke-4 dan seterusnya: +RM10/kotak\n3.2 Harga akhir tertakluk kepada kelulusan admin selepas semakan borang pendaftaran.\n3.3 Pembayaran penuh perlu dijelaskan sebelum atau semasa drop-off/pickup barang, melainkan dinyatakan sebaliknya oleh admin.\n3.4 Sebarang caj tambahan (contoh: kotak melebihi saiz standard, barang berisiko, atau permintaan khas) akan dimaklumkan dan dipersetujui sebelum kelulusan akhir.\n\n**4. Servis Drop-off (Pelanggan Hantar Sendiri)**\n4.1 Pelanggan wajib mengisi borang pendaftaran online dan mendapat pengesahan (PM) terlebih dahulu sebelum membawa barang ke lokasi CukruStorage.\n4.2 Walk-in pada hari yang sama adalah dibenarkan, dengan syarat borang pendaftaran telah dihantar dan disahkan.\n4.3 CukruStorage tidak akan menerima sebarang barang tanpa rekod pendaftaran yang sah.\n\n**5. Servis Pickup (Team CukruStorage Amik di Lokasi Pelanggan)**\n5.1 Permintaan pickup mesti dibuat sekurang-kurangnya 1 hari sebelum tarikh pickup yang dikehendaki.\n5.2 Caj pickup dikira berdasarkan jarak lokasi dan upah angkat, dan akan disahkan oleh admin semasa proses kelulusan.\n5.3 Pelanggan perlu memastikan barang sedia untuk diambil pada tarikh dan masa yang dipersetujui. Kelewatan atau ketidaksediaan barang pada masa pickup yang dijadualkan mungkin dikenakan caj tambahan atau penjadualan semula.\n\n**6. Pengambilan Semula Barang (Return)**\n6.1 Pelanggan bertanggungjawab mengambil semula barang dalam tempoh Return Window yang ditetapkan (3/10/2026 - 9/10/2026).\n6.2 Sebarang pengambilan selepas tarikh tamat Return Window akan dikenakan caj lewat (overdue) sebanyak RM10 sehari, dikira mulai hari pertama selepas tarikh tamat sehingga barang diambil.\n6.3 Caj overdue akan terus terkumpul selagi barang belum diambil dan status belum dikemaskini sebagai \"Returned\" oleh admin.\n6.4 Pelanggan dinasihatkan untuk merancang pengambilan barang awal bagi mengelakkan caj tambahan.\n\n**7. Barang Tidak Dituntut**\n7.1 Sekiranya barang tidak diambil dalam tempoh 30 hari selepas tamat Return Window, CukruStorage berhak menganggap barang tersebut sebagai ditinggalkan dan berhak melupuskan, menjual, atau menderma barang tersebut bagi menampung kos tertunggak, tanpa liabiliti lanjut kepada CukruStorage.\n\n**8. Penjagaan Barang**\n8.1 CukruStorage akan mengambil langkah berjaga-jaga yang munasabah untuk menjaga keselamatan barang yang disimpan.\n8.2 Pelanggan dinasihatkan untuk tidak menyimpan barang berharga tinggi, dokumen penting, wang tunai, barang mudah rosak, atau item haram/berbahaya di dalam simpanan.\n\n**9. Barang Dilarang**\n9.1 Pelanggan dilarang menyimpan barang seperti: bahan mudah terbakar/letupan, bahan haram/dadah, senjata, barang basah/mudah reput, haiwan hidup, wang tunai/barang kemas bernilai tinggi, atau apa-apa item yang menyalahi undang-undang.\n9.2 CukruStorage berhak memeriksa dan menolak barang yang disyaki melanggar terma ini.\n\n**10. Privasi & Data Peribadi**\n10.1 Maklumat peribadi (nama, no. telefon, emel, alamat) yang diberikan akan digunakan semata-mata untuk tujuan pengurusan perkhidmatan simpanan dan tidak akan dikongsi dengan pihak ketiga tanpa kebenaran, kecuali dikehendaki oleh undang-undang.\n\n**11. Akaun & Akses Sistem**\n11.1 Pelanggan bertanggungjawab menjaga kerahsiaan PIN akses akaun mereka.\n11.2 CukruStorage tidak bertanggungjawab ke atas sebarang akses tanpa kebenaran akibat kecuaian Pelanggan dalam menjaga PIN.\n\n**12. Pindaan Terma**\n12.1 CukruStorage berhak meminda terma dan syarat ini dari semasa ke semasa. Sebarang pindaan akan dikemaskini dalam sistem dan terpakai untuk pendaftaran akan datang.\n\n**13. Persetujuan**\nDengan menanda checkbox \"Saya bersetuju dengan Terma & Syarat CukruStorage\" semasa pendaftaran, Pelanggan mengesahkan telah membaca, faham, dan menerima kesemua terma di atas.')

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
