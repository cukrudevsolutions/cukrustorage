<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';

use Cukru\Csrf;
use Cukru\Settings;
use Cukru\Validation;
use Cukru\RateCard;
use Cukru\BookingRepository;
use Cukru\Terms;

$windows = [
    [Settings::get('window1_start'), Settings::get('window1_end')],
    [Settings::get('window2_start'), Settings::get('window2_end')],
];
$pickupMinAdvance = Settings::getInt('pickup_min_advance_days', 1);

$errors = [];

function date_in_any_window(string $date, array $windows): bool
{
    foreach ($windows as [$start, $end]) {
        if ($date >= $start && $date <= $end) {
            return true;
        }
    }
    return false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();

    $nama = trim($_POST['nama'] ?? '');
    $noTelefonRaw = trim($_POST['no_telefon'] ?? '');
    $pin = trim($_POST['pin'] ?? '');
    $pinConfirm = trim($_POST['pin_confirm'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $bilanganKotak = (int) ($_POST['bilangan_kotak'] ?? 0);
    $jenisServis = $_POST['jenis_servis'] ?? '';
    $alamatPickup = trim($_POST['alamat_pickup'] ?? '');
    $jarakAnggaran = trim($_POST['jarak_anggaran'] ?? '');
    $tarikhDicadang = trim($_POST['tarikh_dicadang'] ?? '');
    $termsAccepted = isset($_POST['terms_accepted']);

    $_SESSION['_old'] = $_POST;

    if (!Validation::isValidName($nama)) {
        $errors[] = 'Sila isi nama penuh yang sah.';
    }
    if (!Validation::isValidMalaysianPhone($noTelefonRaw)) {
        $errors[] = 'Format no. telefon tidak sah. Contoh: 012-3456789.';
    }
    if (!Validation::isValidPin($pin)) {
        $errors[] = 'PIN mesti 4-6 digit nombor sahaja.';
    } elseif ($pin !== $pinConfirm) {
        $errors[] = 'PIN dan pengesahan PIN tidak sepadan.';
    }
    if (!Validation::isValidEmail($email)) {
        $errors[] = 'Sila isi alamat emel yang sah.';
    }
    if ($bilanganKotak < 1 || $bilanganKotak > 50) {
        $errors[] = 'Bilangan kotak mesti antara 1 hingga 50.';
    }
    if (!in_array($jenisServis, ['dropoff', 'pickup'], true)) {
        $errors[] = 'Sila pilih jenis servis.';
    }
    if ($jenisServis === 'pickup' && $alamatPickup === '') {
        $errors[] = 'Sila isi alamat penuh untuk servis pickup.';
    }

    $dateObj = DateTime::createFromFormat('Y-m-d', $tarikhDicadang);
    if (!$dateObj) {
        $errors[] = 'Sila pilih tarikh yang sah.';
    } else {
        if (!date_in_any_window($tarikhDicadang, $windows)) {
            $errors[] = 'Tarikh dicadang mesti dalam tempoh drop-off/pickup yang dibenarkan (lihat tarikh di atas borang).';
        }
        if ($jenisServis === 'pickup') {
            $minDate = (new DateTime('today'))->modify("+{$pickupMinAdvance} day")->format('Y-m-d');
            if ($tarikhDicadang < $minDate) {
                $errors[] = "Permintaan pickup mesti dibuat sekurang-kurangnya {$pickupMinAdvance} hari sebelum tarikh pickup.";
            }
        }
    }

    if (!$termsAccepted) {
        $errors[] = 'Sila bersetuju dengan Terma & Syarat untuk meneruskan.';
    }

    if (empty($errors)) {
        $phone = Validation::normalizePhone($noTelefonRaw);
        $bookingRef = BookingRepository::generateBookingRef();
        $hargaStorage = RateCard::calculateStorage($bilanganKotak);

        $id = BookingRepository::create([
            'booking_ref' => $bookingRef,
            'nama' => $nama,
            'no_telefon' => $phone,
            'pin_hash' => password_hash($pin, PASSWORD_DEFAULT),
            'email' => $email,
            'bilangan_kotak' => $bilanganKotak,
            'jenis_servis' => $jenisServis,
            'alamat_pickup' => $jenisServis === 'pickup' ? $alamatPickup : null,
            'jarak_anggaran' => $jenisServis === 'pickup' && $jarakAnggaran !== '' ? $jarakAnggaran : null,
            'tarikh_dicadang' => $tarikhDicadang,
            'harga_storage' => $hargaStorage,
        ]);

        unset($_SESSION['_old']);
        redirect('booking-success.php?ref=' . urlencode($bookingRef));
    }
}

$pageTitle = 'Borang Booking';
require __DIR__ . '/partials/header.php';
?>

<h1>Borang Booking <?= e(Settings::get('site_name', 'CukruStorage')) ?></h1>
<p class="muted">Isi borang ni untuk daftar simpanan barang anda semasa cuti semester. Harga akhir akan disahkan oleh admin selepas semakan.</p>

<div class="card">
    <h3>Tarikh Penting Sesi Semasa</h3>
    <div class="kv"><span class="k">Drop-off / Pickup Window 1</span><span class="v"><?= e(Settings::get('window1_start')) ?> - <?= e(Settings::get('window1_end')) ?></span></div>
    <div class="kv"><span class="k">Drop-off / Pickup Window 2</span><span class="v"><?= e(Settings::get('window2_start')) ?> - <?= e(Settings::get('window2_end')) ?></span></div>
    <div class="kv"><span class="k">Return Window (ambil semula)</span><span class="v"><?= e(Settings::get('return_window_start')) ?> - <?= e(Settings::get('return_window_end')) ?></span></div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <strong>Sila betulkan ralat berikut:</strong>
        <ul style="margin:8px 0 0;padding-left:18px;">
            <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" class="card" novalidate>
    <?= Csrf::field() ?>

    <h2>Maklumat Anda</h2>
    <label class="required" for="nama">Nama Penuh</label>
    <input type="text" id="nama" name="nama" value="<?= old('nama') ?>" required>

    <div class="grid-2">
        <div>
            <label class="required" for="no_telefon">No. Telefon</label>
            <input type="tel" id="no_telefon" name="no_telefon" placeholder="012-3456789" value="<?= old('no_telefon') ?>" required>
            <p class="muted" style="margin:4px 0 0;">Digunakan untuk log masuk semakan status.</p>
        </div>
        <div>
            <label class="required" for="email">Emel</label>
            <input type="email" id="email" name="email" value="<?= old('email') ?>" required>
            <p class="muted" style="margin:4px 0 0;">Untuk reset PIN sahaja.</p>
        </div>
    </div>

    <div class="grid-2">
        <div>
            <label class="required" for="pin">Set PIN (4-6 digit)</label>
            <input type="password" inputmode="numeric" pattern="\d*" id="pin" name="pin" maxlength="6" required>
        </div>
        <div>
            <label class="required" for="pin_confirm">Sahkan PIN</label>
            <input type="password" inputmode="numeric" pattern="\d*" id="pin_confirm" name="pin_confirm" maxlength="6" required>
        </div>
    </div>

    <h2 style="margin-top:24px;">Maklumat Barang</h2>
    <label class="required" for="bilangan_kotak">Bilangan Kotak</label>
    <input type="number" id="bilangan_kotak" name="bilangan_kotak" min="1" max="50" value="<?= old('bilangan_kotak', '1') ?>" required>
    <p class="muted" id="hargaPreview" style="margin:4px 0 0;"></p>

    <label class="required">Jenis Servis</label>
    <div class="grid-2">
        <label style="font-weight:400;display:flex;align-items:center;gap:8px;">
            <input type="radio" name="jenis_servis" value="dropoff" style="width:auto;" <?= ($_POST['jenis_servis'] ?? '') === 'dropoff' ? 'checked' : '' ?> required>
            Drop-off sendiri
        </label>
        <label style="font-weight:400;display:flex;align-items:center;gap:8px;">
            <input type="radio" name="jenis_servis" value="pickup" style="width:auto;" <?= ($_POST['jenis_servis'] ?? '') === 'pickup' ? 'checked' : '' ?> required>
            Pickup oleh team
        </label>
    </div>

    <div id="pickupFields" style="display:none;">
        <label class="required" for="alamat_pickup">Alamat Penuh untuk Pickup</label>
        <textarea id="alamat_pickup" name="alamat_pickup"><?= old('alamat_pickup') ?></textarea>

        <label for="jarak_anggaran">Jarak Anggaran (km, jika tahu)</label>
        <input type="text" id="jarak_anggaran" name="jarak_anggaran" placeholder="Contoh: 5km" value="<?= old('jarak_anggaran') ?>">
        <p class="muted" style="margin:4px 0 0;">Caj jarak + upah angkat akan disahkan oleh admin semasa kelulusan.</p>
    </div>

    <label class="required" for="tarikh_dicadang">Tarikh Dicadang (Drop-off / Pickup)</label>
    <input type="date" id="tarikh_dicadang" name="tarikh_dicadang" value="<?= old('tarikh_dicadang') ?>" required>

    <div class="checkbox-row">
        <input type="checkbox" id="terms_accepted" name="terms_accepted" <?= isset($_POST['terms_accepted']) ? 'checked' : '' ?> required>
        <label for="terms_accepted" style="margin:0;font-weight:400;">
            Saya bersetuju dengan <a href="terms.php" target="_blank">Terma &amp; Syarat <?= e(Settings::get('site_name', 'CukruStorage')) ?></a>
        </label>
    </div>

    <button type="submit" class="btn btn-block">Hantar Permohonan</button>
</form>

<script>
const rates = {
    box1: <?= (float) Settings::getFloat('rate_box1', 30) ?>,
    box2: <?= (float) Settings::getFloat('rate_box2', 55) ?>,
    box3: <?= (float) Settings::getFloat('rate_box3', 80) ?>,
    extra: <?= (float) Settings::getFloat('rate_extra_box', 10) ?>
};

function calcStorage(n) {
    if (n <= 0) return 0;
    if (n === 1) return rates.box1;
    if (n === 2) return rates.box2;
    if (n === 3) return rates.box3;
    return rates.box3 + (n - 3) * rates.extra;
}

const boxInput = document.getElementById('bilangan_kotak');
const preview = document.getElementById('hargaPreview');
function updatePreview() {
    const n = parseInt(boxInput.value, 10) || 0;
    preview.textContent = n > 0 ? `Anggaran caj storan: RM${calcStorage(n).toFixed(2)} (tertakluk kelulusan admin)` : '';
}
boxInput.addEventListener('input', updatePreview);
updatePreview();

const pickupFields = document.getElementById('pickupFields');
const servisRadios = document.querySelectorAll('input[name=jenis_servis]');
function togglePickupFields() {
    const selected = document.querySelector('input[name=jenis_servis]:checked');
    pickupFields.style.display = (selected && selected.value === 'pickup') ? 'block' : 'none';
    document.getElementById('alamat_pickup').required = (selected && selected.value === 'pickup');
}
servisRadios.forEach(r => r.addEventListener('change', togglePickupFields));
togglePickupFields();
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>
