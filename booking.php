<?php
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';

use Cukru\Csrf;
use Cukru\Settings;
use Cukru\Validation;
use Cukru\RateCard;
use Cukru\BookingRepository;
use Cukru\OwnerAuth;

$windows = [
    [Settings::get('window1_start'), Settings::get('window1_end')],
    [Settings::get('window2_start'), Settings::get('window2_end')],
];
$pickupMinAdvance = Settings::getInt('pickup_min_advance_days', 1);

$errors = [];

// Logged-in customers don't need to re-enter their personal details & PIN - reuse the existing record.
$existingBooking = null;
if (OwnerAuth::isLoggedIn()) {
    $ownerIds = OwnerAuth::bookingIds();
    if (!empty($ownerIds)) {
        $existingBooking = BookingRepository::findById($ownerIds[0]);
    }
}

function date_in_any_window(string $date, array $windows): bool
{
    foreach ($windows as [$start, $end]) {
        if ($date >= $start && $date <= $end) {
            return true;
        }
    }
    return false;
}

function format_booking_date(DateTime $d): string
{
    return $d->format('j F Y (l)');
}

/** List every individual date (from today onward) within any allowed window. */
function build_date_options(array $windows, int $pickupMinAdvance): array
{
    $today = new DateTime('today');
    $pickupMinDate = (clone $today)->modify("+{$pickupMinAdvance} day");
    $options = [];

    foreach ($windows as [$start, $end]) {
        $cursor = DateTime::createFromFormat('Y-m-d', $start);
        $endDate = DateTime::createFromFormat('Y-m-d', $end);
        if (!$cursor || !$endDate) {
            continue;
        }
        while ($cursor <= $endDate) {
            if ($cursor >= $today) {
                $options[] = [
                    'value' => $cursor->format('Y-m-d'),
                    'label' => format_booking_date($cursor),
                    'pickupOk' => $cursor >= $pickupMinDate,
                ];
            }
            $cursor->modify('+1 day');
        }
    }

    return $options;
}

$dateOptions = build_date_options($windows, $pickupMinAdvance);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();

    // Logged-in customers: ignore any name/phone/email/PIN values submitted from the form
    // (these fields are readonly/hidden in the UI) - use the trusted record from the database instead.
    if ($existingBooking) {
        $nama = $existingBooking['nama'];
        $noTelefonRaw = $existingBooking['no_telefon'];
        $email = $existingBooking['email'];
        $pin = $pinConfirm = '';
    } else {
        $nama = trim($_POST['nama'] ?? '');
        $noTelefonRaw = trim($_POST['no_telefon'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $pin = trim($_POST['pin'] ?? '');
        $pinConfirm = trim($_POST['pin_confirm'] ?? '');
    }
    $bilanganKotak = (int) ($_POST['bilangan_kotak'] ?? 0);
    $jenisServis = $_POST['jenis_servis'] ?? '';
    $alamatPickup = trim($_POST['alamat_pickup'] ?? '');
    $jarakAnggaran = trim($_POST['jarak_anggaran'] ?? '');
    $tarikhDicadang = trim($_POST['tarikh_dicadang'] ?? '');
    $termsAccepted = isset($_POST['terms_accepted']);

    $_SESSION['_old'] = $_POST;

    if (!Validation::isValidName($nama)) {
        $errors[] = 'Please enter a valid full name.';
    }
    if (!Validation::isValidMalaysianPhone($noTelefonRaw)) {
        $errors[] = 'Invalid phone number format. Example: 012-3456789.';
    } elseif (!$existingBooking) {
        // Phone exists in the system but user is not logged in — redirect to login.
        // This prevents a different person from registering under someone else's number.
        $normalizedPhone = Validation::normalizePhone($noTelefonRaw);
        if (!empty(BookingRepository::findAllByPhone($normalizedPhone))) {
            flash_set('info', 'This phone number already has a booking. Please log in to make another booking.');
            redirect('login.php');
        }
    }
    if (!$existingBooking) {
        if (!Validation::isValidPin($pin)) {
            $errors[] = 'PIN must be 4-6 digits, numbers only.';
        } elseif ($pin !== $pinConfirm) {
            $errors[] = 'PIN and PIN confirmation do not match.';
        }
    }
    if (!Validation::isValidEmail($email)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if ($bilanganKotak < 1 || $bilanganKotak > 50) {
        $errors[] = 'Number of boxes must be between 1 and 50.';
    }
    if (!in_array($jenisServis, ['dropoff', 'pickup'], true)) {
        $errors[] = 'Please select a service type.';
    }
    if ($jenisServis === 'pickup' && $alamatPickup === '') {
        $errors[] = 'Please enter your full address for the pickup service.';
    }

    $dateObj = DateTime::createFromFormat('Y-m-d', $tarikhDicadang);
    if (!$dateObj) {
        $errors[] = 'Please select a valid date.';
    } else {
        if (!date_in_any_window($tarikhDicadang, $windows)) {
            $errors[] = 'The proposed date must fall within an allowed drop-off/pickup period (see dates above the form).';
        }
        if ($jenisServis === 'pickup') {
            $minDate = (new DateTime('today'))->modify("+{$pickupMinAdvance} day")->format('Y-m-d');
            if ($tarikhDicadang < $minDate) {
                $errors[] = "Pickup requests must be made at least {$pickupMinAdvance} day(s) before the pickup date.";
            }
        }
    }

    if (!$termsAccepted) {
        $errors[] = 'Please agree to the Terms & Conditions to continue.';
    }

    if (empty($errors)) {
        $phone = Validation::normalizePhone($noTelefonRaw);
        $bookingRef = BookingRepository::generateBookingRef();
        $hargaStorage = RateCard::calculateStorage($bilanganKotak);

        $id = BookingRepository::create([
            'booking_ref' => $bookingRef,
            'nama' => $nama,
            'no_telefon' => $phone,
            'pin_hash' => $existingBooking ? $existingBooking['pin_hash'] : password_hash($pin, PASSWORD_DEFAULT),
            'email' => $email,
            'bilangan_kotak' => $bilanganKotak,
            'jenis_servis' => $jenisServis,
            'alamat_pickup' => $jenisServis === 'pickup' ? $alamatPickup : null,
            'jarak_anggaran' => $jenisServis === 'pickup' && $jarakAnggaran !== '' ? $jarakAnggaran : null,
            'tarikh_dicadang' => $tarikhDicadang,
            'harga_storage' => $hargaStorage,
        ]);

        unset($_SESSION['_old']);
        if ($existingBooking) {
            $_SESSION['owner_booking_ids'][] = $id;
        }
        redirect('booking-success.php?ref=' . urlencode($bookingRef));
    }
}

$waPhoneRaw = Settings::get('admin_whatsapp', '');
$waEnquiryUrl = $waPhoneRaw ? 'https://api.whatsapp.com/send/?phone=' . urlencode($waPhoneRaw) . '&text=' . rawurlencode('Hi ' . Settings::get('site_name', 'CukruStorage') . '! I\'d like to enquire about storing my items for the semester break.') : null;

$pageTitle = 'Storage Booking';
require __DIR__ . '/partials/header.php';
?>

<?php if ($waEnquiryUrl): ?>
<div style="background:#0f172a;border-radius:var(--radius-lg);padding:var(--space-5);margin-bottom:var(--space-4);">
    <p style="color:#fff;font-weight:800;font-size:0.95rem;letter-spacing:-0.01em;margin:0 0 var(--space-2);">WhatsApp First, Then Fill This Form</p>
    <p style="color:#94a3b8;font-size:0.82rem;margin:0 0 var(--space-4);line-height:1.5;">This form is for <span style="color:#e2e8f0;font-weight:600;">record purposes only.</span> Confirm pricing &amp; box count on WhatsApp before submitting.</p>
    <a href="<?= e($waEnquiryUrl) ?>" target="_blank" rel="noopener"
       style="display:inline-flex;align-items:center;gap:9px;background:#25D366;color:#fff;font-weight:800;font-size:0.95rem;padding:11px 20px;border-radius:var(--radius-sm);text-decoration:none;">
        <svg viewBox="0 0 32 32" style="width:19px;height:19px;flex-shrink:0;"><path fill="#fff" d="M22.7 9.3a8.9 8.9 0 0 0-14 10.7L7 25l5.2-1.6a8.9 8.9 0 0 0 12.6-8 8.8 8.8 0 0 0-2.1-6.1zm-6.6 13.6a7.4 7.4 0 0 1-3.8-1l-.3-.2-2.8.9.9-2.7-.2-.3a7.4 7.4 0 1 1 13.8-3.7 7.4 7.4 0 0 1-7.6 7zm4-5.5c-.2-.1-1.3-.6-1.5-.7-.2-.1-.3-.1-.5.1l-.7.9c-.1.1-.3.2-.5.1-.2-.1-1-.4-1.9-1.2-.7-.6-1.2-1.4-1.3-1.6-.1-.2 0-.3.1-.5l.4-.4.2-.4v-.4c-.1-.1-.5-1.3-.7-1.8-.2-.4-.4-.4-.5-.4h-.5c-.1 0-.4.1-.6.3-.2.2-.8.8-.8 1.9s.8 2.2 1 2.4c.1.1 1.7 2.6 4 3.6.6.2 1 .4 1.4.5.6.2 1.1.1 1.5.1.5-.1 1.3-.5 1.5-1 .2-.5.2-.9.1-1l-.4-.2z"/>
        </svg>
        Chat with Us on WhatsApp
    </a>
</div>
<?php endif; ?>

<div class="card" id="section-dates">
    <h3 class="eyebrow" style="margin-bottom:var(--space-3);"><i class="fa-solid fa-calendar-days"></i> Key Dates</h3>
    <div class="kv"><span class="k">Drop-off / Pickup Period 1</span><span class="v"><?= e(Settings::get('window1_start')) ?> – <?= e(Settings::get('window1_end')) ?></span></div>
    <div class="kv"><span class="k">Drop-off / Pickup Period 2</span><span class="v"><?= e(Settings::get('window2_start')) ?> – <?= e(Settings::get('window2_end')) ?></span></div>
    <div class="kv"><span class="k">Collection Period</span><span class="v"><?= e(Settings::get('return_window_start')) ?> – <?= e(Settings::get('return_window_end')) ?></span></div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <strong>Please fix the following errors:</strong>
        <ul style="margin:8px 0 0;padding-left:18px;">
            <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" class="card" novalidate>
    <?= Csrf::field() ?>

    <h2><i class="fa-solid fa-user"></i> Your Details</h2>
    <?php if ($existingBooking): ?>
        <div class="alert alert-info"><span>Logged in as <strong><?= e($existingBooking['nama']) ?></strong> — your details and PIN will be reused.</span></div>
    <?php endif; ?>

    <label class="required" for="nama">Full Name</label>
    <input type="text" id="nama" name="nama" value="<?= $existingBooking ? e($existingBooking['nama']) : old('nama') ?>" autocomplete="name" <?= $existingBooking ? 'readonly' : '' ?> required>

    <div class="grid-2">
        <div>
            <label class="required" for="no_telefon">Phone Number</label>
            <input type="tel" id="no_telefon" name="no_telefon" placeholder="012-3456789"
                value="<?= $existingBooking ? e(format_phone($existingBooking['no_telefon'])) : old('no_telefon') ?>"
                data-phone-format inputmode="numeric" maxlength="12" autocomplete="tel" <?= $existingBooking ? 'readonly' : '' ?> required>
            <?php if (!$existingBooking): ?>
            <div id="phone-status" style="display:none;margin-top:6px;font-size:0.8rem;align-items:center;gap:6px;"></div>
            <?php endif; ?>
        </div>
        <div>
            <label class="required" for="email">Email</label>
            <input type="email" id="email" name="email" value="<?= $existingBooking ? e($existingBooking['email']) : old('email') ?>" autocomplete="email" <?= $existingBooking ? 'readonly' : '' ?> required>
        </div>
    </div>

    <?php if (!$existingBooking): ?>
    <div class="grid-2">
        <div>
            <label class="required" for="pin">PIN (4-6 digits)</label>
            <input type="password" inputmode="numeric" pattern="\d*" id="pin" name="pin" maxlength="6" placeholder="••••" required>
        </div>
        <div>
            <label class="required" for="pin_confirm">Confirm PIN</label>
            <input type="password" inputmode="numeric" pattern="\d*" id="pin_confirm" name="pin_confirm" maxlength="6" placeholder="••••" required>
        </div>
    </div>
    <p class="field-hint">Your PIN is used to log in and check your booking status anytime.</p>
    <?php endif; ?>

    <hr class="section-divider">
    <h2><i class="fa-solid fa-box"></i> Item Details</h2>

    <label class="required" for="bilangan_kotak">Number of Boxes</label>
    <input type="number" id="bilangan_kotak" name="bilangan_kotak" min="1" max="50" value="<?= old('bilangan_kotak', '1') ?>" required>
    <div class="price-preview" id="hargaPreview"></div>
    <p class="field-hint"><i class="fa-solid fa-info-circle"></i> Confirm your box count with us on WhatsApp before submitting.</p>

    <div class="tip-box" style="margin-top:var(--space-3);">
        <i class="fa-solid fa-shield" style="color:var(--color-primary);flex-shrink:0;margin-top:2px;"></i>
        <span><strong>Packing tip:</strong> Please wrap all items in clear stretch wrap / plastic before packing — this keeps your belongings secure and intact during storage.</span>
    </div>

    <label class="required" style="margin-top:var(--space-4);">Service Type
        <button type="button" id="serviceInfoToggle" style="background:none;border:none;padding:0 4px;cursor:pointer;vertical-align:middle;color:var(--color-muted);" title="What's the difference?">
            <i class="fa-solid fa-circle-info"></i>
        </button>
    </label>
    <div id="serviceInfoBox" style="display:none;background:var(--color-bg);border:1px solid var(--color-border);border-radius:var(--radius-sm);padding:var(--space-3);margin-bottom:var(--space-3);font-size:0.84rem;line-height:1.55;">
        <p style="margin:0 0 var(--space-2);"><i class="fa-solid fa-box-open" style="width:18px;"></i> <strong>Self Drop-off</strong> — you bring your items to our storage location yourself, within the allowed drop-off period. <?php if ($locationMapsUrl = Settings::get('location_maps_url')): ?><a href="<?= e($locationMapsUrl) ?>" target="_blank" rel="noopener">View location <i class="fa-solid fa-arrow-up-right-from-square" style="font-size:0.7rem;"></i></a><?php endif; ?></p>
        <p style="margin:0;"><i class="fa-solid fa-truck" style="width:18px;"></i> <strong>Team Pickup</strong> — our team comes to you and collects your items. An extra charge applies based on distance and labour — confirmed by admin before finalising.</p>
    </div>
    <div class="grid-2">
        <label class="radio-card">
            <input type="radio" name="jenis_servis" value="dropoff" <?= ($_POST['jenis_servis'] ?? '') === 'dropoff' ? 'checked' : '' ?> required>
            <div>
                <span style="font-weight:700;"><i class="fa-solid fa-box-open"></i> Self Drop-off</span>
                <small style="display:block;font-weight:400;color:var(--color-muted);margin-top:2px;font-size:0.75rem;">You bring items to us</small>
            </div>
        </label>
        <label class="radio-card">
            <input type="radio" name="jenis_servis" value="pickup" <?= ($_POST['jenis_servis'] ?? '') === 'pickup' ? 'checked' : '' ?> required>
            <div>
                <span style="font-weight:700;"><i class="fa-solid fa-truck"></i> Team Pickup</span>
                <small style="display:block;font-weight:400;color:var(--color-muted);margin-top:2px;font-size:0.75rem;">We collect from you (extra charge)</small>
            </div>
        </label>
    </div>

    <div id="pickupFields" style="display:none;">
        <label class="required" for="alamat_pickup">Pickup Address</label>
        <textarea id="alamat_pickup" name="alamat_pickup"><?= old('alamat_pickup') ?></textarea>
        <label for="jarak_anggaran">Estimated Distance (optional)</label>
        <input type="text" id="jarak_anggaran" name="jarak_anggaran" placeholder="e.g. 5km" value="<?= old('jarak_anggaran') ?>">
    </div>

    <label class="required" for="tarikh_dicadang">Date <a href="#section-dates" class="info-scroll-link" title="View allowed dates"><i class="fa-solid fa-circle-info"></i></a></label>
    <select id="tarikh_dicadang" name="tarikh_dicadang" required>
        <option value="">- Select a date -</option>
        <?php foreach ($dateOptions as $opt): ?>
            <option value="<?= e($opt['value']) ?>" data-pickup-ok="<?= $opt['pickupOk'] ? '1' : '0' ?>" <?= ($_POST['tarikh_dicadang'] ?? '') === $opt['value'] ? 'selected' : '' ?>><?= e($opt['label']) ?></option>
        <?php endforeach; ?>
    </select>

    <hr class="section-divider">
    <div class="checkbox-row">
        <input type="checkbox" id="terms_accepted" name="terms_accepted" <?= isset($_POST['terms_accepted']) ? 'checked' : '' ?> required>
        <label for="terms_accepted" style="margin:0;font-weight:400;">
            I agree to the <a href="terms.php" target="_blank"><?= e(Settings::get('site_name', 'CukruStorage')) ?> Terms &amp; Conditions</a>
        </label>
    </div>

    <button type="submit" class="btn btn-block">Submit Booking</button>
</form>

<script src="<?= asset('js/phone-format.js') ?>"></script>
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
    if (n > 0) {
        preview.style.display = 'block';
        preview.innerHTML = `Estimated storage charge: <strong>RM${calcStorage(n).toFixed(2)}</strong> (subject to admin approval)`;
    } else {
        preview.style.display = 'none';
    }
}
boxInput.addEventListener('input', updatePreview);
updatePreview();

// Force numeric-only input for PIN fields
document.querySelectorAll('#pin, #pin_confirm').forEach(el => {
    el.addEventListener('input', () => { el.value = el.value.replace(/\D/g, ''); });
});

const pickupFields = document.getElementById('pickupFields');
const servisRadios = document.querySelectorAll('input[name=jenis_servis]');
function togglePickupFields() {
    const selected = document.querySelector('input[name=jenis_servis]:checked');
    pickupFields.style.display = (selected && selected.value === 'pickup') ? 'block' : 'none';
    document.getElementById('alamat_pickup').required = (selected && selected.value === 'pickup');
}
servisRadios.forEach(r => r.addEventListener('change', togglePickupFields));
togglePickupFields();

// Date dropdown - when Pickup is selected, hide dates that don't meet the minimum advance notice.
const tarikhSelect = document.getElementById('tarikh_dicadang');
const tarikhOptions = Array.from(tarikhSelect.options).filter(o => o.value !== '');

function updateTarikhOptions() {
    const isPickup = document.querySelector('input[name=jenis_servis]:checked')?.value === 'pickup';
    let selectedStillValid = true;

    tarikhOptions.forEach(opt => {
        const pickupOk = opt.dataset.pickupOk === '1';
        const shouldDisable = isPickup && !pickupOk;
        opt.disabled = shouldDisable;
        opt.hidden = shouldDisable;
        if (opt.value === tarikhSelect.value && shouldDisable) {
            selectedStillValid = false;
        }
    });

    if (!selectedStillValid) {
        tarikhSelect.value = '';
    }
}
servisRadios.forEach(r => r.addEventListener('change', updateTarikhOptions));
updateTarikhOptions();

// Service type info toggle
const toggleBtn = document.getElementById('serviceInfoToggle');
const infoBox = document.getElementById('serviceInfoBox');
if (toggleBtn && infoBox) {
    toggleBtn.addEventListener('click', () => {
        const open = infoBox.style.display !== 'none';
        infoBox.style.display = open ? 'none' : 'block';
        toggleBtn.querySelector('i').style.color = open ? 'var(--color-muted)' : 'var(--color-primary)';
    });
}

// Smooth scroll for info links
document.querySelectorAll('.info-scroll-link').forEach(link => {
    link.addEventListener('click', e => {
        e.preventDefault();
        const target = document.querySelector(link.getAttribute('href'));
        if (target) {
            const headerH = document.querySelector('.topbar')?.offsetHeight ?? 0;
            const top = target.getBoundingClientRect().top + window.scrollY - headerH - 12;
            window.scrollTo({ top, behavior: 'smooth' });
        }
    });
});

<?php if (!$existingBooking): ?>
// Real-time phone number availability check
const phoneInput = document.getElementById('no_telefon');
const phoneStatus = document.getElementById('phone-status');
const submitBtn = document.querySelector('button[type=submit]');
let phoneCheckTimer = null;

function setPhoneStatus(type, message, loginUrl) {
    phoneStatus.innerHTML = '';
    phoneStatus.style.display = 'flex';

    const icon = document.createElement('i');
    const text = document.createElement('span');
    text.style.flex = '1';

    if (type === 'available') {
        icon.className = 'fa-solid fa-circle-check';
        icon.style.color = 'var(--color-success)';
        text.style.color = 'var(--color-success)';
        text.textContent = 'Available — you can proceed.';
        submitBtn.disabled = false;
    } else if (type === 'exists') {
        icon.className = 'fa-solid fa-circle-xmark';
        icon.style.color = 'var(--color-danger)';
        text.style.color = 'var(--color-danger)';
        text.innerHTML = 'This number is already registered. <a href="' + loginUrl + '" style="font-weight:700;">Log in here</a> to make another booking.';
        submitBtn.disabled = true;
    } else if (type === 'checking') {
        icon.className = 'fa-solid fa-circle-notch fa-spin';
        icon.style.color = 'var(--color-muted)';
        text.style.color = 'var(--color-muted)';
        text.textContent = 'Checking...';
        submitBtn.disabled = true;
    } else {
        phoneStatus.style.display = 'none';
        submitBtn.disabled = false;
    }

    phoneStatus.appendChild(icon);
    phoneStatus.appendChild(text);
}

function checkPhone() {
    const raw = phoneInput.value.replace(/\D/g, '');
    if (raw.length < 10) { setPhoneStatus('hide'); return; }

    setPhoneStatus('checking');
    fetch('<?= base_path() ?>/check-phone.php?phone=' + encodeURIComponent(phoneInput.value))
        .then(r => r.json())
        .then(data => {
            if (data.status === 'exists') setPhoneStatus('exists', '', '<?= base_path() ?>/login.php');
            else if (data.status === 'available') setPhoneStatus('available');
            else setPhoneStatus('hide');
        })
        .catch(() => setPhoneStatus('hide'));
}

phoneInput.addEventListener('input', () => {
    clearTimeout(phoneCheckTimer);
    phoneCheckTimer = setTimeout(checkPhone, 500);
});

if (phoneInput.value.trim()) checkPhone();
<?php endif; ?>
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>
