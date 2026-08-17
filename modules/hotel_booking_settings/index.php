<?php
require '../../config/config.php';
require_once ROOT_PATH . 'includes/itm_stripe_checkout.php';

$company_id = (int) ($_SESSION['company_id'] ?? 0);
$employee_id = (int) ($_SESSION['employee_id'] ?? 0);
if ($company_id < 1) {
    header('Location: ../../login.php');
    exit;
}

$row = itm_hotel_booking_settings_row($conn, $company_id);
if (!$row) {
    $ins = mysqli_prepare($conn, 'INSERT INTO hotel_booking_settings (company_id, public_portal_enabled, active, created_at) VALUES (?, 0, 1, NOW())');
    if ($ins) {
        mysqli_stmt_bind_param($ins, 'i', $company_id);
        mysqli_stmt_execute($ins);
        mysqli_stmt_close($ins);
    }
    $row = itm_hotel_booking_settings_row($conn, $company_id);
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    itm_require_post_csrf();
    $enabled = !empty($_POST['public_portal_enabled']) ? 1 : 0;
    $welcomeTitle = trim((string) ($_POST['welcome_title'] ?? ''));
    $welcomeSubtitle = trim((string) ($_POST['welcome_subtitle'] ?? ''));
    $accessible = trim((string) ($_POST['accessible_features_default'] ?? ''));
    $airport = trim((string) ($_POST['airport_info'] ?? ''));
    $footnote = trim((string) ($_POST['price_footnote'] ?? ''));
    $reviewsUrl = itm_hotel_booking_normalize_reviews_url($_POST['reviews_url'] ?? '');
    $touristTax = str_replace(',', '.', trim((string) ($_POST['tourist_tax_per_person_per_night'] ?? '0')));
    if ($touristTax === '' || !is_numeric($touristTax)) {
        $touristTax = '0';
    }
    $touristTax = max(0, (float) $touristTax);
    $freeCancelDays = (int) ($_POST['free_cancellation_days_before_check_in'] ?? 5);
    if ($freeCancelDays < 0) {
        $freeCancelDays = 0;
    }
    if ($freeCancelDays > 365) {
        $freeCancelDays = 365;
    }
    $calendarAdvanceDaysLeft = (int) ($_POST['calendar_month_advance_days_left'] ?? 3);
    if ($calendarAdvanceDaysLeft < 0) {
        $calendarAdvanceDaysLeft = 0;
    }
    if ($calendarAdvanceDaysLeft > 31) {
        $calendarAdvanceDaysLeft = 31;
    }
    $showDiscountStrikethrough = !empty($_POST['show_discount_strikethrough']) ? 1 : 0;
    $complimentaryMinRooms = max(0, (int) ($_POST['portal_complimentary_min_rooms_paid'] ?? 0));
    $complimentaryRoomsFree = max(0, (int) ($_POST['portal_complimentary_rooms_free'] ?? 1));
    if ($complimentaryMinRooms < 1) {
        $complimentaryRoomsFree = max(0, $complimentaryRoomsFree);
    }
    $confirmEmailGuest = !empty($_POST['portal_confirmation_email_guest']) ? 1 : 0;
    $confirmEmailReservations = !empty($_POST['portal_confirmation_email_reservations']) ? 1 : 0;
    if (trim((string) ($_POST['reviews_url'] ?? '')) !== '' && $reviewsUrl === '') {
        $errors[] = 'Reviews URL must start with http:// or https://';
    }
    $urlmybooking = trim((string) ($_POST['urlmybooking'] ?? ''));
    if ($urlmybooking === '') {
        $urlmybooking = 'https://localhost/it-management/booking/users/bookings.php';
    }
    $urlmybooking_norm = itm_hotel_booking_normalize_reviews_url($urlmybooking);
    if ($urlmybooking_norm === '') {
        $errors[] = 'Manage my booking URL must start with http:// or https://';
    } else {
        $urlmybooking = $urlmybooking_norm;
    }
    $stripeEnabled = !empty($_POST['stripe_enabled']) ? 1 : 0;
    $stripeMode = trim((string) ($_POST['stripe_mode'] ?? 'test'));
    if ($stripeMode !== 'live') {
        $stripeMode = 'test';
    }
    $stripePublishableKey = trim((string) ($_POST['stripe_publishable_key'] ?? ''));
    $stripeSecretPlain = trim((string) ($_POST['stripe_secret_key'] ?? ''));
    $stripeWebhookPlain = trim((string) ($_POST['stripe_webhook_signing_secret'] ?? ''));
    $stripeSecretEnc = (string) ($row['stripe_secret_key_encrypted'] ?? '');
    $stripeWebhookEnc = (string) ($row['stripe_webhook_signing_secret_encrypted'] ?? '');
    if ($stripeSecretPlain !== '') {
        $stripeSecretEnc = itm_stripe_checkout_encrypt_secret($stripeSecretPlain);
    }
    if ($stripeWebhookPlain !== '') {
        $stripeWebhookEnc = itm_stripe_checkout_encrypt_secret($stripeWebhookPlain);
    }
    $depositPercent = str_replace(',', '.', trim((string) ($_POST['deposit_percent'] ?? '100')));
    if ($depositPercent === '' || !is_numeric($depositPercent)) {
        $depositPercent = '100';
    }
    $depositPercent = (float) $depositPercent;
    if ($depositPercent < 0) {
        $depositPercent = 0;
    }
    if ($depositPercent > 100) {
        $depositPercent = 100;
    }
    $sid = (int) ($row['id'] ?? 0);
    if (empty($errors)) {
    $upd = mysqli_prepare($conn, 'UPDATE hotel_booking_settings SET public_portal_enabled = ?, stripe_enabled = ?, stripe_mode = ?, stripe_publishable_key = ?, stripe_secret_key_encrypted = ?, stripe_webhook_signing_secret_encrypted = ?, deposit_percent = ?, welcome_title = ?, welcome_subtitle = ?, accessible_features_default = ?, airport_info = ?, price_footnote = ?, reviews_url = ?, tourist_tax_per_person_per_night = ?, free_cancellation_days_before_check_in = ?, calendar_month_advance_days_left = ?, show_discount_strikethrough = ?, portal_complimentary_min_rooms_paid = ?, portal_complimentary_rooms_free = ?, portal_confirmation_email_guest = ?, portal_confirmation_email_reservations = ?, urlmybooking = ?, updated_by = ?, updated_at = NOW() WHERE id = ? AND company_id = ?');
    if ($upd) {
        mysqli_stmt_bind_param($upd, 'iissssdssssssdiiiiiiiisiii', $enabled, $stripeEnabled, $stripeMode, $stripePublishableKey, $stripeSecretEnc, $stripeWebhookEnc, $depositPercent, $welcomeTitle, $welcomeSubtitle, $accessible, $airport, $footnote, $reviewsUrl, $touristTax, $freeCancelDays, $calendarAdvanceDaysLeft, $showDiscountStrikethrough, $complimentaryMinRooms, $complimentaryRoomsFree, $confirmEmailGuest, $confirmEmailReservations, $urlmybooking, $employee_id, $sid, $company_id);
        mysqli_stmt_execute($upd);
        mysqli_stmt_close($upd);
        header('Location: index.php?saved=1');
        exit;
    }
    $errors[] = 'Save failed.';
    }
}

$row = itm_hotel_booking_settings_row($conn, $company_id);
$confirmEmailFlags = itm_hotel_booking_portal_confirmation_email_flags_from_settings($row ?: []);
$crud_title = 'Hotel Booking Settings';
$crud_title = itm_crud_apply_module_icon_to_browser_title($conn, $company_id, $employee_id, 'hotel_booking_settings', $crud_title);
require_once ROOT_PATH . 'includes/itm_hospitality_admin_layout.php';
itm_hospitality_admin_layout_begin($crud_title);
?>
<div class="card">
<h1 title="Hotel booking settings">⚙️</h1>
<div class="itm-hospitality-list-actions" style="margin-bottom:16px;">
<?php itm_hospitality_render_bookings_hub_link('btn'); ?>
</div>
<?php if (!empty($_GET['saved'])): ?>
<p class="badge badge-success">Saved</p>
<?php endif; ?>
<?php foreach ($errors as $e): ?>
<p class="badge badge-danger"><?php echo sanitize($e); ?></p>
<?php endforeach; ?>
<form method="post">
<input type="hidden" name="csrf_token" value="<?php echo sanitize(itm_get_csrf_token()); ?>">
<div class="form-group">
<label class="itm-checkbox-control">
<input type="checkbox" name="public_portal_enabled" value="1" <?php echo !empty($row['public_portal_enabled']) ? 'checked' : ''; ?>>
<span>Public portal enabled</span>
</label>
</div>
<div class="form-group">
<label>Welcome title</label>
<input type="text" name="welcome_title" class="form-control" value="<?php echo sanitize($row['welcome_title'] ?? ''); ?>">
</div>
<div class="form-group">
<label>Welcome subtitle</label>
<textarea name="welcome_subtitle" class="form-control" rows="2"><?php echo sanitize($row['welcome_subtitle'] ?? ''); ?></textarea>
</div>
<div class="form-group">
<label>Accessible features default</label>
<textarea name="accessible_features_default" class="form-control" rows="3"><?php echo sanitize($row['accessible_features_default'] ?? ''); ?></textarea>
</div>
<div class="form-group">
<label>Airport info</label>
<textarea name="airport_info" class="form-control" rows="3"><?php echo sanitize($row['airport_info'] ?? ''); ?></textarea>
</div>
<div class="form-group">
<label>Price footnote</label>
<textarea name="price_footnote" class="form-control" rows="2"><?php echo sanitize($row['price_footnote'] ?? ''); ?></textarea>
</div>
<div class="form-group">
<label>External reviews URL</label>
<input type="url" name="reviews_url" class="form-control" maxlength="500" placeholder="https://www.tripadvisor.pt/Hotel_Review-...html#REVIEWS" value="<?php echo sanitize($row['reviews_url'] ?? ''); ?>">
<p class="text-muted" style="font-size:.85rem;margin-top:4px;">Shown under “Guest rating — based on recent stays” as <strong>Read reviews ↗</strong> (new tab).</p>
</div>
<div class="form-group">
<label>Manage my booking URL</label>
<input type="url" name="urlmybooking" class="form-control" maxlength="500" placeholder="https://localhost/it-management/booking/users/bookings.php" value="<?php echo sanitize($row['urlmybooking'] ?? 'https://localhost/it-management/booking/users/bookings.php'); ?>">
<p class="text-muted" style="font-size:.85rem;margin-top:4px;">Shown under “Manage my booking” as target blank link.</p>
</div>
<div class="form-group">
<label>Tourist tax (per person per night)</label>
<input type="text" name="tourist_tax_per_person_per_night" class="form-control" inputmode="decimal" placeholder="2.00" value="<?php echo sanitize(number_format((float) ($row['tourist_tax_per_person_per_night'] ?? 0), 2, '.', '')); ?>">
<p class="text-muted" style="font-size:.85rem;margin-top:4px;">Added to portal checkout totals (steps 3–4) for adults and children.</p>
</div>
<div class="form-group">
<label>Free cancellation days before check-in</label>
<input type="number" name="free_cancellation_days_before_check_in" class="form-control" min="0" max="365" step="1" value="<?php echo (int) ($row['free_cancellation_days_before_check_in'] ?? 5); ?>">
<p class="text-muted" style="font-size:.85rem;margin-top:4px;">Default for Step 2 rate cards that use <code>{date}</code> in the cancel template (unless a rate plan sets its own days).</p>
</div>
<div class="form-group">
<label>Select Dates calendar advance (days left)</label>
<input type="number" name="calendar_month_advance_days_left" class="form-control" min="0" max="31" step="1" value="<?php echo (int) ($row['calendar_month_advance_days_left'] ?? 3); ?>">
<p class="text-muted" style="font-size:.85rem;margin-top:4px;">After check-in, auto-advance to the next month when <code>daysLeftInMonth &lt; value</code> (seed <strong>3</strong>). Use <strong>0</strong> to never auto-advance (guest uses ◀ / ▶ or month tabs).</p>
</div>
<div class="form-group">
<label class="itm-checkbox-control">
<input type="checkbox" name="show_discount_strikethrough" value="1" <?php echo itm_hotel_booking_portal_show_discount_strikethrough_from_settings($row ?: []) ? 'checked' : ''; ?>>
<span>Show discount as strikethrough</span>
</label>
<p class="text-muted" style="font-size:.85rem;margin-top:4px;">When enabled, Step 1 room cards and Step 2 rate totals show the list price struck through next to the discounted sale price. When disabled, only the sale price is shown.</p>
</div>
<div class="form-group">
<label>Complimentary rooms — minimum paid rooms</label>
<input type="number" name="portal_complimentary_min_rooms_paid" class="form-control" min="0" max="20" step="1" value="<?php echo (int) ($row['portal_complimentary_min_rooms_paid'] ?? 0); ?>">
<p class="text-muted" style="font-size:.85rem;margin-top:4px;">When a guest books more than this many rooms, the cheapest room night(s) are credited. Use <strong>0</strong> to disable complimentary rooms.</p>
</div>
<div class="form-group">
<label>Complimentary rooms — free room count</label>
<input type="number" name="portal_complimentary_rooms_free" class="form-control" min="0" max="10" step="1" value="<?php echo (int) ($row['portal_complimentary_rooms_free'] ?? 1); ?>">
<p class="text-muted" style="font-size:.85rem;margin-top:4px;">Number of lowest-priced room stays credited once the minimum paid rooms threshold is exceeded.</p>
</div>
<div class="card" style="margin-top:24px;">
<h2 style="margin-top:0;">Step 4 — Confirmation emails</h2>
<p class="text-muted" style="font-size:.85rem;">After a portal booking is saved (or after Stripe payment completes), the system can email the guest and/or the hotel reservations desk.</p>
<div class="form-group">
<label class="itm-checkbox-control">
<input type="checkbox" name="portal_confirmation_email_guest" id="itm-hb-confirm-email-guest" value="1" <?php echo !empty($confirmEmailFlags['guest']) ? 'checked' : ''; ?>>
<span>Confirmation email to guest</span>
</label>
</div>
<div class="form-group">
<label class="itm-checkbox-control">
<input type="checkbox" name="portal_confirmation_email_reservations" id="itm-hb-confirm-email-reservations" value="1" <?php echo !empty($confirmEmailFlags['reservations']) ? 'checked' : ''; ?>>
<span>Confirmation email to reservations desk</span>
</label>
<p class="text-muted" style="font-size:.85rem;margin-top:4px;">Uses each hotel’s <strong>Reservations email</strong> from Hotel Booking Hotels.</p>
</div>
<div class="form-group">
<label class="itm-checkbox-control">
<input type="checkbox" id="itm-hb-confirm-email-both" value="1" <?php echo (!empty($confirmEmailFlags['guest']) && !empty($confirmEmailFlags['reservations'])) ? 'checked' : ''; ?>>
<span>Both (guest and reservations)</span>
</label>
</div>
</div>
<div class="card" style="margin-top:24px;">
<h2 style="margin-top:0;">Stripe Checkout</h2>
<p class="text-muted" style="font-size:.85rem;">Guest portal Step 4 can redirect to Stripe Checkout. Webhook URL: <code><?php echo sanitize(rtrim(BASE_URL, '/') . '/booking/stripe-webhook.php?company_id=' . $company_id); ?></code></p>
<div class="form-group">
<label class="itm-checkbox-control">
<input type="checkbox" name="stripe_enabled" value="1" <?php echo !empty($row['stripe_enabled']) ? 'checked' : ''; ?>>
<span>Enable Stripe Checkout</span>
</label>
</div>
<div class="form-group">
<label>Stripe mode</label>
<select name="stripe_mode" class="form-control">
<option value="test" <?php echo (($row['stripe_mode'] ?? 'test') === 'test') ? 'selected' : ''; ?>>Test</option>
<option value="live" <?php echo (($row['stripe_mode'] ?? '') === 'live') ? 'selected' : ''; ?>>Live</option>
</select>
</div>
<div class="form-group">
<label>Stripe publishable key</label>
<input type="text" name="stripe_publishable_key" class="form-control" maxlength="255" value="<?php echo sanitize($row['stripe_publishable_key'] ?? ''); ?>">
</div>
<div class="form-group">
<label>Stripe secret key</label>
<input type="password" name="stripe_secret_key" class="form-control" autocomplete="new-password" placeholder="<?php echo !empty($row['stripe_secret_key_encrypted']) ? 'Leave blank to keep existing key' : 'sk_test_…'; ?>">
</div>
<div class="form-group">
<label>Stripe webhook signing secret</label>
<input type="password" name="stripe_webhook_signing_secret" class="form-control" autocomplete="new-password" placeholder="<?php echo !empty($row['stripe_webhook_signing_secret_encrypted']) ? 'Leave blank to keep existing secret' : 'whsec_…'; ?>">
</div>
<div class="form-group">
<label>Deposit percent (online charge)</label>
<input type="text" name="deposit_percent" class="form-control" inputmode="decimal" value="<?php echo sanitize(number_format((float) ($row['deposit_percent'] ?? 100), 2, '.', '')); ?>">
<p class="text-muted" style="font-size:.85rem;margin-top:4px;">Percent of stay total charged online (100 = full prepayment).</p>
</div>
</div>
<button type="submit" class="btn btn-primary" title="Save">💾</button>
</form>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var guest = document.getElementById('itm-hb-confirm-email-guest');
  var desk = document.getElementById('itm-hb-confirm-email-reservations');
  var both = document.getElementById('itm-hb-confirm-email-both');
  if (!guest || !desk || !both) {
    return;
  }
  function syncBothFromChildren() {
    both.checked = guest.checked && desk.checked;
  }
  function applyBothMaster() {
    guest.checked = both.checked;
    desk.checked = both.checked;
  }
  guest.addEventListener('change', syncBothFromChildren);
  desk.addEventListener('change', syncBothFromChildren);
  both.addEventListener('change', applyBothMaster);
  syncBothFromChildren();
});
</script>
</div>
<?php itm_hospitality_admin_layout_end(); ?>
