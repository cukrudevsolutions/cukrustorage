# CukruStorage

Sistem booking, tracking & pengurusan simpanan barang student semasa cuti semester.

## Struktur Folder

```
CUKRUSTORE/
├── bin/                  # Skrip CLI (cron)
├── config/               # config.php - load .env & sambungan DB (LUAR public_html)
├── database/
│   └── schema.sql        # Struktur DB + seed data (rate card, tarikh, Terma & Syarat)
├── src/                  # Kelas PHP (Database, Auth, RateCard, dll.) (LUAR public_html)
├── vendor/                # Composer dependencies (LUAR public_html)
├── public_html/          # INI SAHAJA yang jadi document root web
│   ├── admin/             # Panel admin
│   ├── assets/             # CSS/JS
│   ├── partials/           # header/footer owner (tak boleh diakses terus)
│   └── *.php               # Borang booking, login, dashboard owner, dll.
├── composer.json
├── .env.example
└── README.md
```

**Kenapa config/ dan src/ di luar public_html?** Supaya kredential database & logik sistem
tidak boleh diakses terus dari browser walaupun seseorang teka nama failnya — ini ikut
keperluan keselamatan #6 dalam spesifikasi projek.

## Keperluan

- PHP 8.1+ dengan extension: pdo_mysql, mbstring, gd (untuk QR code)
- MySQL/MariaDB
- Composer (untuk pasang `vlucas/phpdotenv` dan `endroid/qr-code`)

## Setup Tempatan (XAMPP)

1. `composer install` dalam root folder projek.
2. Cipta database MySQL (contoh `cukrustorage`), import `database/schema.sql` melalui phpMyAdmin.
3. Salin `.env.example` kepada `.env`, isi `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, dan
   `APP_SECRET` (rentetan rawak panjang).
4. Arahkan document root XAMPP/Apache ke folder `public_html/` (guna Virtual Host), ATAU
   akses terus melalui `http://localhost/CUKRUSTORE/public_html/`.
5. Log masuk admin default: **username `admin`, password `ChangeMe123!`** —
   **WAJIB tukar password ni serta-merta** dari menu Tetapan selepas login pertama.

## Deploy ke Hostinger (Shared Hosting / cPanel)

1. **Buat database MySQL** dari cPanel → MySQL Databases. Catat nama DB, username, password.
2. **Muat naik fail** melalui File Manager atau FTP:
   - Letak folder `config/`, `src/`, `database/`, `bin/`, `vendor/`, `composer.json`, `.env`
     di **root akaun hosting** (contoh `/home/u123456789/`), bukan dalam `public_html`.
   - Letak **kandungan dalam folder `public_html/` projek ni** terus ke dalam
     `public_html/` cPanel sedia ada (timpa/gabung, bukan letak sub-folder lagi).
   - Struktur akhir di server patut jadi:
     ```
     /home/u123456789/config/...
     /home/u123456789/src/...
     /home/u123456789/vendor/...
     /home/u123456789/database/...
     /home/u123456789/bin/...
     /home/u123456789/composer.json
     /home/u123456789/.env
     /home/u123456789/public_html/index.php
     /home/u123456789/public_html/booking.php
     /home/u123456789/public_html/admin/...
     ```
3. **Jika Hostinger plan ada SSH/Terminal**: masuk ke root akaun, jalankan `composer install
   --no-dev --optimize-autoloader`. Jika tiada SSH, jalankan `composer install` di komputer
   sendiri dan muat naik folder `vendor/` sekali (pastikan tak masuk dalam `.gitignore` semasa upload).
4. **Import `database/schema.sql`** melalui phpMyAdmin (cPanel → phpMyAdmin → pilih DB → Import).
5. **Salin `.env.example` jadi `.env`** (kalau belum), isi maklumat DB sebenar dari Hostinger
   dan `APP_SECRET` rawak. Set `APP_ENV=production` dan `APP_URL` kepada domain sebenar.
6. **Tukar password admin default** (`admin` / `ChangeMe123!`) serta-merta selepas login
   pertama melalui `/admin/settings.php`.
7. **(Disyorkan) Setup Cron Job** di cPanel → Cron Jobs, jadual harian:
   ```
   php /home/u123456789/bin/sync_overdue.php
   ```
   Ini pastikan status booking auto-tukar ke `OVERDUE` tepat pada waktunya walaupun
   tiada sesiapa log masuk sistem pada hari tersebut.
8. Pastikan SSL/HTTPS diaktifkan (Hostinger sediakan percuma) — sistem set cookie session
   `secure` secara automatik bila HTTPS dikesan.

## Apa Yang Perlu Ditest

### Owner (Customer)
- [ ] Isi borang booking (dropoff & pickup) — cuba tarikh di luar window yang dibenarkan, patut ditolak
- [ ] Cuba pickup dengan tarikh kurang dari H-1 — patut ditolak
- [ ] Submit tanpa tick Terma & Syarat — patut ditolak
- [ ] Log masuk guna no. telefon + PIN yang didaftarkan
- [ ] Dashboard tunjuk status "Menunggu kelulusan admin" sebelum admin approve

### Admin
- [ ] Log masuk admin, lihat notification badge bila ada booking baharu
- [ ] Approve booking → harga storan auto-calculate, boleh override; caj pickup admin set manual
- [ ] Selepas approve, QR code & slip printable boleh diakses
- [ ] Scan QR (guna kamera telefon/laptop) → terus ke butiran booking
- [ ] Carian manual (no. booking / no. telefon / nama) di halaman Scan
- [ ] Kemaskini status (IN_STORAGE / READY_FOR_RETURN / RETURNED) → semak log tersimpan
- [ ] Senarai booking — test filter status & carian
- [ ] Reset PIN pelanggan dari Butiran Booking → PIN baharu dipaparkan sekali sahaja, log masuk owner dengan PIN baharu berjaya
- [ ] Cuba 5x kata laluan salah → akaun admin patut dikunci 15 minit
- [ ] Tukar tarikh/rate card/Terma & Syarat di Tetapan → semak ia terpapar terkini di borang booking

### Keselamatan
- [ ] Akses terus fail `.env` melalui browser — patut blocked (404/403)
- [ ] Akses `config/` atau `src/` melalui URL — patut blocked
- [ ] Submit borang tanpa CSRF token (contoh guna Postman) — patut ditolak (419)

## Nota Teknikal

- **QR Code**: dijana secara live guna `endroid/qr-code` (tiada fail disimpan ke disk).
- **Slip booking**: HTML printable (klik "Cetak / Simpan PDF" guna fungsi print browser).
- **Caj Overdue**: RM10/hari (boleh ubah di Tetapan), grace period 0 hari secara default.
- **Auto-overdue**: dikira semula setiap kali dashboard admin/owner dimuatkan, dan boleh
  dijadualkan via cron (`bin/sync_overdue.php`) untuk ketepatan tanpa perlu sesiapa log masuk.
- **Reset PIN**: tiada flow emel — admin reset terus dari halaman Butiran Booking, sistem
  jana PIN 4-digit rawak yang dipaparkan sekali sahaja untuk admin maklumkan kepada pelanggan
  (contoh: via WhatsApp/panggilan). PIN lama terus tidak sah selepas reset.
