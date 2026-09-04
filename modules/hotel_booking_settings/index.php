<?php
require '../../config/config.php';
// Why: Single RBAC chokepoint for POST create/edit/delete (do not duplicate per handler).
itm_crud_mutation_guard_entry($conn, $crud_action, $crud_table);

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
    $portalMaxDiscount = str_replace(',', '.', trim((string) ($_POST['portal_max_discount_percent'] ?? '50')));
    if ($portalMaxDiscount === '' || !is_numeric($portalMaxDiscount)) {
        $portalMaxDiscount = '50';
    }
    $portalMaxDiscount = (float) $portalMaxDiscount;
    if ($portalMaxDiscount < 0) {
        $portalMaxDiscount = 0;
    }
    if ($portalMaxDiscount > 100) {
        $portalMaxDiscount = 100;
    }
    $portalTouristTaxLabel = mb_substr(trim((string) ($_POST['portal_tourist_tax_label'] ?? 'Tourist tax')), 0, 100);
    if ($portalTouristTaxLabel === '') {
        $portalTouristTaxLabel = 'Tourist tax';
    }
    $portalPriceIncludesTaxLabel = mb_substr(trim((string) ($_POST['portal_price_includes_tax_label'] ?? 'incl. tax')), 0, 80);
    if ($portalPriceIncludesTaxLabel === '') {
        $portalPriceIncludesTaxLabel = 'incl. tax';
    }
    $portalPriceIncludesTaxLongLabel = mb_substr(trim((string) ($_POST['portal_price_includes_tax_long_label'] ?? 'incl. taxes')), 0, 80);
    if ($portalPriceIncludesTaxLongLabel === '') {
        $portalPriceIncludesTaxLongLabel = 'incl. taxes';
    }
    $portalDefaultRateLabel = mb_substr(trim((string) ($_POST['portal_default_rate_label'] ?? 'Best available rate')), 0, 120);
    if ($portalDefaultRateLabel === '') {
        $portalDefaultRateLabel = 'Best available rate';
    }
    $portalBreakfastRateLabel = mb_substr(trim((string) ($_POST['portal_breakfast_rate_label'] ?? 'Breakfast included')), 0, 120);
    if ($portalBreakfastRateLabel === '') {
        $portalBreakfastRateLabel = 'Breakfast included';
    }
    $portalDefaultPetMaxWeightKg = (int) ($_POST['portal_default_pet_max_weight_kg'] ?? 30);
    if ($portalDefaultPetMaxWeightKg < 1) {
        $portalDefaultPetMaxWeightKg = 1;
    }
    if ($portalDefaultPetMaxWeightKg > 200) {
        $portalDefaultPetMaxWeightKg = 200;
    }
    $portalMapsBaseUrl = mb_substr(trim((string) ($_POST['portal_maps_base_url'] ?? 'https://maps.google.com/?q=')), 0, 500);
    if ($portalMapsBaseUrl === '') {
        $portalMapsBaseUrl = 'https://maps.google.com/?q=';
    }
    $portalCalendarMonthHorizon = (int) ($_POST['portal_calendar_month_horizon'] ?? 14);
    if ($portalCalendarMonthHorizon < 1) {
        $portalCalendarMonthHorizon = 1;
    }
    if ($portalCalendarMonthHorizon > 36) {
        $portalCalendarMonthHorizon = 36;
    }
    $portalPhoneExample = mb_substr(trim((string) ($_POST['portal_phone_example'] ?? '+351912345678')), 0, 30);
    if ($portalPhoneExample === '') {
        $portalPhoneExample = '+351912345678';
    }
    $portalDirectBookBannerText = mb_substr(trim((string) ($_POST['portal_direct_book_banner_text'] ?? 'Book direct for the best available rate and flexible stay options.')), 0, 500);
    if ($portalDirectBookBannerText === '') {
        $portalDirectBookBannerText = 'Book direct for the best available rate and flexible stay options.';
    }
    $portalRatingTitle = mb_substr(trim((string) ($_POST['portal_rating_title'] ?? 'Guest rating')), 0, 120);
    if ($portalRatingTitle === '') {
        $portalRatingTitle = 'Guest rating';
    }
    $portalRatingSubtitle = mb_substr((string) ($_POST['portal_rating_subtitle'] ?? ' — based on recent stays'), 0, 200);
    $portalStepLabelRoom = mb_substr(trim((string) ($_POST['portal_step_label_room'] ?? '')), 0, 80);
    $portalStepLabelRate = mb_substr(trim((string) ($_POST['portal_step_label_rate'] ?? 'Select a Rate')), 0, 80);
    if ($portalStepLabelRate === '') {
        $portalStepLabelRate = 'Select a Rate';
    }
    $portalStepLabelCustomize = mb_substr(trim((string) ($_POST['portal_step_label_customize'] ?? 'Customize Your Stay')), 0, 80);
    if ($portalStepLabelCustomize === '') {
        $portalStepLabelCustomize = 'Customize Your Stay';
    }
    $portalStepLabelPayment = mb_substr(trim((string) ($_POST['portal_step_label_payment'] ?? 'Payment and Guest Details')), 0, 80);
    if ($portalStepLabelPayment === '') {
        $portalStepLabelPayment = 'Payment and Guest Details';
    }
    $portalDefaultRoomImageUrl = mb_substr(trim((string) ($_POST['portal_default_room_image_url'] ?? '/images/room-5.jpg')), 0, 500);
    if ($portalDefaultRoomImageUrl === '') {
        $portalDefaultRoomImageUrl = '/images/room-5.jpg';
    }
    $portalRoomTypeCodeFallbackJson = trim((string) ($_POST['portal_room_type_code_fallback_json'] ?? ''));
    if ($portalRoomTypeCodeFallbackJson === '') {
        $portalRoomTypeCodeFallbackJson = itm_hotel_booking_portal_default_room_type_code_fallback_json();
    } else {
        $decodedFallback = json_decode($portalRoomTypeCodeFallbackJson, true);
        if (!is_array($decodedFallback)) {
            $errors[] = 'Room type image fallback JSON must be valid JSON (e.g. {"DLX":"/images/room-5.jpg"}).';
        }
    }
    $portalOccMaxRooms = (int) ($_POST['portal_occupancy_max_rooms'] ?? 4);
    $portalOccMaxAdults = (int) ($_POST['portal_occupancy_max_adults'] ?? 12);
    $portalOccMaxChildren = (int) ($_POST['portal_occupancy_max_children'] ?? 6);
    $portalOccMaxBabies = (int) ($_POST['portal_occupancy_max_babies'] ?? 3);
    $portalOccMaxRooms = max(1, min(20, $portalOccMaxRooms));
    $portalOccMaxAdults = max(1, min(50, $portalOccMaxAdults));
    $portalOccMaxChildren = max(0, min(50, $portalOccMaxChildren));
    $portalOccMaxBabies = max(0, min(20, $portalOccMaxBabies));
    $portalDefaultIncludedAdults = (int) ($_POST['portal_default_included_adults_per_room'] ?? 2);
    $portalDefaultIncludedAdults = max(1, min(20, $portalDefaultIncludedAdults));
    $portalCancellationPolicyNotFoundUrl = itm_hotel_booking_normalize_cancellation_policy_url(
        (string) ($_POST['portal_cancellation_policy_not_found_url'] ?? itm_hotel_booking_portal_default_cancellation_policy_not_found_url())
    );
    if ($portalCancellationPolicyNotFoundUrl === '') {
        $errors[] = 'Cancellation policy not-found page must be a relative .html, .htm, or .txt path (e.g. cancellation_policy/404.html).';
    }
    $portalManageBookingLabel = mb_substr(trim((string) ($_POST['portal_manage_booking_label'] ?? 'Manage my booking')), 0, 80);
    if ($portalManageBookingLabel === '') {
        $errors[] = 'Manage booking label is required.';
    }
    $portalAccessibleRoomBannerText = mb_substr(trim((string) ($_POST['portal_accessible_room_banner_text'] ?? 'Accessible rooms are available at this property. Use Room Filters and select Accessible room to narrow results.')), 0, 500);
    if ($portalAccessibleRoomBannerText === '') {
        $portalAccessibleRoomBannerText = 'Accessible rooms are available at this property. Use Room Filters and select Accessible room to narrow results.';
    }
    $portalDisabledMessage = mb_substr(trim((string) ($_POST['portal_disabled_message'] ?? 'Public booking portal is disabled for this hotel.')), 0, 255);
    if ($portalDisabledMessage === '') {
        $portalDisabledMessage = 'Public booking portal is disabled for this hotel.';
    }
    $portalStepProgressTemplate = mb_substr(trim((string) ($_POST['portal_step_progress_template'] ?? 'Step {step} of {total}')), 0, 80);
    if ($portalStepProgressTemplate === '' || strpos($portalStepProgressTemplate, '{step}') === false) {
        $errors[] = 'Step progress template must include {step} (and may include {total}).';
    }
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
    $showRoomNumberOnConfirmation = !empty($_POST['portal_show_room_number_on_confirmation']) ? 1 : 0;
    $hideUpgradeUpsellMultiRoom = !empty($_POST['portal_hide_upgrade_upsell_when_multi_room']) ? 1 : 0;
    $moneySymbol = strtoupper(trim((string) ($_POST['portal_money_symbol'] ?? 'EUR')));
    if (!in_array($moneySymbol, ['EUR', 'GBP', 'USD'], true)) {
        $moneySymbol = 'EUR';
    }
    $moneySymbolSuffix = !empty($_POST['portal_money_symbol_suffix']) ? 1 : 0;
    $moneySymbolPrefix = !empty($_POST['portal_money_symbol_prefix']) ? 1 : 0;
    if ($moneySymbolPrefix) {
        $moneySymbolSuffix = 0;
    } elseif (!$moneySymbolSuffix) {
        $moneySymbolSuffix = 1;
    }
    $showInternalRates = !empty($_POST['portal_show_internal_rates']) ? 1 : 0;
    $portalDateFormat = strtolower(trim((string) ($_POST['portal_date_format'] ?? 'european_ddmmyyyy')));
    if (!in_array($portalDateFormat, ['european_ddmmyyyy', 'european_ddmmmyyyy', 'us_mmddyyyy', 'iso_yyyymmdd'], true)) {
        $portalDateFormat = 'european_ddmmyyyy';
    }
    $portalTimeFormat = strtolower(trim((string) ($_POST['portal_time_format'] ?? 'h24')));
    if ($portalTimeFormat !== 'h12') {
        $portalTimeFormat = 'h24';
    }
    $dtEuropean1 = !empty($_POST['portal_datetime_european1_enabled']) ? 1 : 0;
    $dtEuropean2 = !empty($_POST['portal_datetime_european2_enabled']) ? 1 : 0;
    $dtIso = !empty($_POST['portal_datetime_iso_enabled']) ? 1 : 0;
    $dtReadable = !empty($_POST['portal_datetime_readable_enabled']) ? 1 : 0;
    if (!$dtEuropean1 && !$dtEuropean2 && !$dtIso && !$dtReadable) {
        $dtEuropean2 = 1;
    }
    $dtDefault = strtolower(trim((string) ($_POST['portal_datetime_format_default'] ?? 'european2')));
    if (!in_array($dtDefault, ['european1', 'european2', 'iso', 'readable'], true)) {
        $dtDefault = 'european2';
    }
    $enabledMap = [
        'european1' => $dtEuropean1,
        'european2' => $dtEuropean2,
        'iso' => $dtIso,
        'readable' => $dtReadable,
    ];
    if (empty($enabledMap[$dtDefault])) {
        foreach (['european2', 'european1', 'readable', 'iso'] as $candidate) {
            if (!empty($enabledMap[$candidate])) {
                $dtDefault = $candidate;
                break;
            }
        }
    }
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
    $portalAccessibleBanner = !empty($_POST['portal_accessible_banner_enabled']) ? 1 : 0;
    $portalAccessibilityOptions = !empty($_POST['portal_accessibility_options_enabled']) ? 1 : 0;
    $urlaccessibilitypep = trim((string) ($_POST['urlaccessibilitypep'] ?? ''));
    if ($urlaccessibilitypep === '') {
        $urlaccessibilitypep = 'https://localhost/it-management/booking/accessibility/pep.html';
    }
    $urlaccessibilitypep_norm = itm_hotel_booking_normalize_reviews_url($urlaccessibilitypep);
    if ($urlaccessibilitypep_norm === '') {
        $errors[] = 'Accessibility PEP URL must start with http:// or https://';
    } else {
        $urlaccessibilitypep = $urlaccessibilitypep_norm;
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
    $uiCopyValidated = itm_hotel_booking_portal_ui_copy_validate_post_values($_POST);
    $errors = array_merge($errors, $uiCopyValidated['errors']);
    if (empty($errors)) {
    $upd = mysqli_prepare($conn, 'UPDATE hotel_booking_settings SET public_portal_enabled = ?, stripe_enabled = ?, stripe_mode = ?, stripe_publishable_key = ?, stripe_secret_key_encrypted = ?, stripe_webhook_signing_secret_encrypted = ?, deposit_percent = ?, welcome_title = ?, welcome_subtitle = ?, accessible_features_default = ?, airport_info = ?, price_footnote = ?, reviews_url = ?, tourist_tax_per_person_per_night = ?, portal_max_discount_percent = ?, portal_tourist_tax_label = ?, portal_price_includes_tax_label = ?, portal_price_includes_tax_long_label = ?, portal_default_rate_label = ?, portal_breakfast_rate_label = ?, portal_default_pet_max_weight_kg = ?, portal_maps_base_url = ?, portal_calendar_month_horizon = ?, portal_phone_example = ?, portal_direct_book_banner_text = ?, portal_rating_title = ?, portal_rating_subtitle = ?, portal_step_label_room = ?, portal_step_label_rate = ?, portal_step_label_customize = ?, portal_step_label_payment = ?, portal_default_room_image_url = ?, portal_room_type_code_fallback_json = ?, portal_occupancy_max_rooms = ?, portal_occupancy_max_adults = ?, portal_occupancy_max_children = ?, portal_occupancy_max_babies = ?, portal_default_included_adults_per_room = ?, portal_cancellation_policy_not_found_url = ?, portal_manage_booking_label = ?, portal_accessible_room_banner_text = ?, portal_disabled_message = ?, portal_step_progress_template = ?, free_cancellation_days_before_check_in = ?, calendar_month_advance_days_left = ?, show_discount_strikethrough = ?, portal_complimentary_min_rooms_paid = ?, portal_complimentary_rooms_free = ?, portal_confirmation_email_guest = ?, portal_confirmation_email_reservations = ?, portal_show_room_number_on_confirmation = ?, portal_hide_upgrade_upsell_when_multi_room = ?, portal_money_symbol = ?, portal_money_symbol_suffix = ?, portal_money_symbol_prefix = ?, portal_show_internal_rates = ?, portal_date_format = ?, portal_time_format = ?, portal_datetime_european1_enabled = ?, portal_datetime_european2_enabled = ?, portal_datetime_iso_enabled = ?, portal_datetime_readable_enabled = ?, portal_datetime_format_default = ?, portal_accessible_banner_enabled = ?, portal_accessibility_options_enabled = ?, urlaccessibilitypep = ?, urlmybooking = ?, updated_by = ?, updated_at = NOW() WHERE id = ? AND company_id = ?');
    if ($upd) {
        mysqli_stmt_bind_param($upd, 'iissssdssssssddsssssisissssssssssiiiiisssssiiiiiiiiisiiissiiiisiissiii', $enabled, $stripeEnabled, $stripeMode, $stripePublishableKey, $stripeSecretEnc, $stripeWebhookEnc, $depositPercent, $welcomeTitle, $welcomeSubtitle, $accessible, $airport, $footnote, $reviewsUrl, $touristTax, $portalMaxDiscount, $portalTouristTaxLabel, $portalPriceIncludesTaxLabel, $portalPriceIncludesTaxLongLabel, $portalDefaultRateLabel, $portalBreakfastRateLabel, $portalDefaultPetMaxWeightKg, $portalMapsBaseUrl, $portalCalendarMonthHorizon, $portalPhoneExample, $portalDirectBookBannerText, $portalRatingTitle, $portalRatingSubtitle, $portalStepLabelRoom, $portalStepLabelRate, $portalStepLabelCustomize, $portalStepLabelPayment, $portalDefaultRoomImageUrl, $portalRoomTypeCodeFallbackJson, $portalOccMaxRooms, $portalOccMaxAdults, $portalOccMaxChildren, $portalOccMaxBabies, $portalDefaultIncludedAdults, $portalCancellationPolicyNotFoundUrl, $portalManageBookingLabel, $portalAccessibleRoomBannerText, $portalDisabledMessage, $portalStepProgressTemplate, $freeCancelDays, $calendarAdvanceDaysLeft, $showDiscountStrikethrough, $complimentaryMinRooms, $complimentaryRoomsFree, $confirmEmailGuest, $confirmEmailReservations, $showRoomNumberOnConfirmation, $hideUpgradeUpsellMultiRoom, $moneySymbol, $moneySymbolSuffix, $moneySymbolPrefix, $showInternalRates, $portalDateFormat, $portalTimeFormat, $dtEuropean1, $dtEuropean2, $dtIso, $dtReadable, $dtDefault, $portalAccessibleBanner, $portalAccessibilityOptions, $urlaccessibilitypep, $urlmybooking, $employee_id, $sid, $company_id);
        mysqli_stmt_execute($upd);
        mysqli_stmt_close($upd);
        if (!itm_hotel_booking_portal_ui_copy_save_values($conn, $company_id, $uiCopyValidated['values'], $employee_id)) {
            $errors[] = 'Portal UI copy save failed.';
        } else {
            header('Location: index.php?saved=1');
            exit;
        }
    }
    if (empty($errors)) {
        $errors[] = 'Save failed.';
    }
    }
}

$row = itm_hotel_booking_settings_row($conn, $company_id);
$confirmEmailFlags = itm_hotel_booking_portal_confirmation_email_flags_from_settings($row ?: []);
$moneyFormatOptions = itm_hotel_booking_portal_money_format_options_from_settings($row ?: []);
$portalDateFormat = itm_hotel_booking_portal_date_format_from_settings($row ?: []);
$portalTimeFormat = itm_hotel_booking_portal_time_format_from_settings($row ?: []);
$portalDatetimeEnabled = itm_hotel_booking_portal_datetime_format_enabled_map($row ?: []);
$portalDatetimeDefault = itm_hotel_booking_portal_datetime_format_default_from_settings($row ?: []);
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
<label>Accessibility PEP URL</label>
<input type="url" name="urlaccessibilitypep" class="form-control" maxlength="500" placeholder="https://localhost/it-management/booking/accessibility/pep.html" value="<?php echo sanitize($row['urlaccessibilitypep'] ?? 'https://localhost/it-management/booking/accessibility/pep.html'); ?>">
<p class="text-muted" style="font-size:.85rem;margin-top:4px;">Opened from portal Step 3 when a guest selects an accessibility need (new tab).</p>
</div>
<div class="form-group">
<label class="itm-checkbox-control">
<input type="checkbox" name="portal_accessible_banner_enabled" value="1" <?php echo itm_hotel_booking_portal_accessible_banner_enabled_from_settings($row ?: []) ? 'checked' : ''; ?>>
<span>Show accessible room banner on portal Step 1</span>
</label>
</div>
<div class="form-group">
<label class="itm-checkbox-control">
<input type="checkbox" name="portal_accessibility_options_enabled" value="1" <?php echo itm_hotel_booking_portal_accessibility_options_enabled_from_settings($row ?: []) ? 'checked' : ''; ?>>
<span>Show accessibility options on portal Step 3</span>
</label>
</div>
<div class="form-group">
<label>Tourist tax (per person per night)</label>
<input type="text" name="tourist_tax_per_person_per_night" class="form-control" inputmode="decimal" placeholder="2.00" value="<?php echo sanitize(number_format((float) ($row['tourist_tax_per_person_per_night'] ?? 0), 2, '.', '')); ?>">
<p class="text-muted" style="font-size:.85rem;margin-top:4px;">Added to portal checkout totals (steps 3–4) for adults and children.</p>
</div>
<div class="form-group">
<label>Maximum discount / surcharge (%)</label>
<input type="text" name="portal_max_discount_percent" class="form-control" inputmode="decimal" value="<?php echo sanitize(number_format((float) ($row['portal_max_discount_percent'] ?? 50), 2, '.', '')); ?>">
<p class="text-muted" style="font-size:.85rem;margin-top:4px;">Caps combined special + plan discounts and plan surcharges on the guest portal (default 50).</p>
</div>
<div class="form-group">
<label>Tourist tax label (checkout)</label>
<input type="text" name="portal_tourist_tax_label" class="form-control" maxlength="100" value="<?php echo sanitize((string) ($row['portal_tourist_tax_label'] ?? 'Tourist tax')); ?>">
</div>
<div class="form-group">
<label>Price includes tax — short label</label>
<input type="text" name="portal_price_includes_tax_label" class="form-control" maxlength="80" value="<?php echo sanitize((string) ($row['portal_price_includes_tax_label'] ?? 'incl. tax')); ?>">
<p class="text-muted" style="font-size:.85rem;margin-top:4px;">Shown beside From prices on hotel cards and room rates.</p>
</div>
<div class="form-group">
<label>Price includes tax — long label</label>
<input type="text" name="portal_price_includes_tax_long_label" class="form-control" maxlength="80" value="<?php echo sanitize((string) ($row['portal_price_includes_tax_long_label'] ?? 'incl. taxes')); ?>">
<p class="text-muted" style="font-size:.85rem;margin-top:4px;">Used in Select Dates calendar summary.</p>
</div>
<div class="form-group">
<label>Default rate label (BAR)</label>
<input type="text" name="portal_default_rate_label" class="form-control" maxlength="120" value="<?php echo sanitize((string) ($row['portal_default_rate_label'] ?? 'Best available rate')); ?>">
</div>
<div class="form-group">
<label>Breakfast rate label</label>
<input type="text" name="portal_breakfast_rate_label" class="form-control" maxlength="120" value="<?php echo sanitize((string) ($row['portal_breakfast_rate_label'] ?? 'Breakfast included')); ?>">
</div>
<div class="form-group">
<label>Default pet max weight (kg)</label>
<input type="number" name="portal_default_pet_max_weight_kg" class="form-control" min="1" max="200" step="1" value="<?php echo (int) ($row['portal_default_pet_max_weight_kg'] ?? 30); ?>">
<p class="text-muted" style="font-size:.85rem;margin-top:4px;">Checkout pet hint when the room type has no <code>pet_max_weight_kg</code>.</p>
</div>
<div class="card" style="margin-top:16px;">
<h3 style="margin-top:0;">Maps and calendar</h3>
<div class="form-group">
<label>Maps base URL</label>
<input type="url" name="portal_maps_base_url" class="form-control" maxlength="500" value="<?php echo sanitize((string) ($row['portal_maps_base_url'] ?? 'https://maps.google.com/?q=')); ?>">
<p class="text-muted" style="font-size:.85rem;margin-top:4px;">Guest Directions links append the hotel address (default Google Maps <code>?q=</code>).</p>
</div>
<div class="form-group">
<label>Calendar month horizon</label>
<input type="number" name="portal_calendar_month_horizon" class="form-control" min="1" max="36" step="1" value="<?php echo (int) ($row['portal_calendar_month_horizon'] ?? 14); ?>">
<p class="text-muted" style="font-size:.85rem;margin-top:4px;">Number of month tabs on Select Dates (default 14).</p>
</div>
<div class="form-group">
<label>Guest phone example</label>
<input type="text" name="portal_phone_example" class="form-control" maxlength="30" value="<?php echo sanitize((string) ($row['portal_phone_example'] ?? '+351912345678')); ?>">
</div>
</div>
<div class="card" style="margin-top:16px;">
<h3 style="margin-top:0;">Marketing copy</h3>
<div class="form-group">
<label>Direct book banner</label>
<input type="text" name="portal_direct_book_banner_text" class="form-control" maxlength="500" value="<?php echo sanitize((string) ($row['portal_direct_book_banner_text'] ?? 'Book direct for the best available rate and flexible stay options.')); ?>">
</div>
<div class="form-group">
<label>Guest rating title</label>
<input type="text" name="portal_rating_title" class="form-control" maxlength="120" value="<?php echo sanitize((string) ($row['portal_rating_title'] ?? 'Guest rating')); ?>">
</div>
<div class="form-group">
<label>Guest rating subtitle</label>
<input type="text" name="portal_rating_subtitle" class="form-control" maxlength="200" value="<?php echo sanitize((string) ($row['portal_rating_subtitle'] ?? ' — based on recent stays')); ?>">
</div>
<div class="form-group">
<label>Manage booking link label</label>
<input type="text" name="portal_manage_booking_label" class="form-control" maxlength="80" value="<?php echo sanitize((string) ($row['portal_manage_booking_label'] ?? 'Manage my booking')); ?>">
</div>
<div class="form-group">
<label>Accessible room banner text</label>
<input type="text" name="portal_accessible_room_banner_text" class="form-control" maxlength="500" value="<?php echo sanitize((string) ($row['portal_accessible_room_banner_text'] ?? 'Accessible rooms are available at this property. Use Room Filters and select Accessible room to narrow results.')); ?>">
<p class="text-muted" style="font-size:.85rem;margin-top:4px;">Shown on Step 1 when <strong>Show accessible room banner</strong> is enabled.</p>
</div>
<div class="form-group">
<label>Portal disabled message</label>
<input type="text" name="portal_disabled_message" class="form-control" maxlength="255" value="<?php echo sanitize((string) ($row['portal_disabled_message'] ?? 'Public booking portal is disabled for this hotel.')); ?>">
</div>
<div class="form-group">
<label>Step progress template</label>
<input type="text" name="portal_step_progress_template" class="form-control" maxlength="80" value="<?php echo sanitize((string) ($row['portal_step_progress_template'] ?? 'Step {step} of {total}')); ?>">
<p class="text-muted" style="font-size:.85rem;margin-top:4px;">Use <code>{step}</code> and <code>{total}</code> (default total is 4).</p>
</div>
<div class="form-group">
<label>Checkout step label — room</label>
<input type="text" name="portal_step_label_room" class="form-control" maxlength="80" value="<?php echo sanitize((string) ($row['portal_step_label_room'] ?? 'Select a Room')); ?>">
</div>
<div class="form-group">
<label>Checkout step label — rate</label>
<input type="text" name="portal_step_label_rate" class="form-control" maxlength="80" value="<?php echo sanitize((string) ($row['portal_step_label_rate'] ?? 'Select a Rate')); ?>">
</div>
<div class="form-group">
<label>Checkout step label — customize</label>
<input type="text" name="portal_step_label_customize" class="form-control" maxlength="80" value="<?php echo sanitize((string) ($row['portal_step_label_customize'] ?? 'Customize Your Stay')); ?>">
</div>
<div class="form-group">
<label>Checkout step label — payment</label>
<input type="text" name="portal_step_label_payment" class="form-control" maxlength="80" value="<?php echo sanitize((string) ($row['portal_step_label_payment'] ?? 'Payment and Guest Details')); ?>">
</div>
</div>
<div class="card" style="margin-top:16px;">
<h3 style="margin-top:0;">Occupancy ceilings</h3>
<div class="form-group">
<label>Max rooms (stepper)</label>
<input type="number" name="portal_occupancy_max_rooms" class="form-control" min="1" max="20" value="<?php echo (int) ($row['portal_occupancy_max_rooms'] ?? 4); ?>">
</div>
<div class="form-group">
<label>Max adults</label>
<input type="number" name="portal_occupancy_max_adults" class="form-control" min="1" max="50" value="<?php echo (int) ($row['portal_occupancy_max_adults'] ?? 12); ?>">
</div>
<div class="form-group">
<label>Max children</label>
<input type="number" name="portal_occupancy_max_children" class="form-control" min="0" max="50" value="<?php echo (int) ($row['portal_occupancy_max_children'] ?? 6); ?>">
</div>
<div class="form-group">
<label>Max babies</label>
<input type="number" name="portal_occupancy_max_babies" class="form-control" min="0" max="20" value="<?php echo (int) ($row['portal_occupancy_max_babies'] ?? 3); ?>">
</div>
<div class="form-group">
<label>Default included adults per room (JS quote fallback)</label>
<input type="number" name="portal_default_included_adults_per_room" class="form-control" min="1" max="20" value="<?php echo (int) ($row['portal_default_included_adults_per_room'] ?? 2); ?>">
</div>
</div>
<div class="card" style="margin-top:16px;">
<h3 style="margin-top:0;">Room image fallbacks</h3>
<div class="form-group">
<label>Default room image path</label>
<input type="text" name="portal_default_room_image_url" class="form-control" maxlength="500" value="<?php echo sanitize((string) ($row['portal_default_room_image_url'] ?? '/images/room-5.jpg')); ?>">
</div>
<div class="form-group">
<label>Room type code fallback JSON</label>
<textarea name="portal_room_type_code_fallback_json" class="form-control" rows="4"><?php echo sanitize((string) ($row['portal_room_type_code_fallback_json'] ?? itm_hotel_booking_portal_default_room_type_code_fallback_json())); ?></textarea>
<p class="text-muted" style="font-size:.85rem;margin-top:4px;">Used when a room type has no uploaded photos (e.g. <code>{"DLX":"/images/room-5.jpg"}</code>).</p>
</div>
</div>
<div class="card" style="margin-top:16px;">
<h3 style="margin-top:0;">Cancellation policy</h3>
<div class="form-group">
<label>Not found page (404.html path)</label>
<input type="text" name="portal_cancellation_policy_not_found_url" class="form-control" maxlength="500" value="<?php echo sanitize((string) ($row['portal_cancellation_policy_not_found_url'] ?? itm_hotel_booking_portal_default_cancellation_policy_not_found_url())); ?>">
<p class="text-muted" style="font-size:.85rem;margin-top:4px;">Relative path under <code>booking/</code> shown when a rate plan policy is missing or empty (guest modal via <code>cancellation-policy.php</code>). Example: <code>cancellation_policy/404.html</code>.</p>
</div>
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
<h2 style="margin-top:0;">Portal display</h2>
<div class="form-group">
<label class="itm-checkbox-control">
<input type="checkbox" name="portal_show_room_number_on_confirmation" value="1" <?php echo !empty($row['portal_show_room_number_on_confirmation']) ? 'checked' : ''; ?>>
<span>Show room number on booking / confirmation</span>
</label>
<p class="text-muted" style="font-size:.85rem;margin-top:4px;">When enabled, guest-facing summaries and confirmation cards prefix the assigned room number (e.g. <strong>101 — Standard Double</strong>).</p>
</div>
<div class="form-group">
<label class="itm-checkbox-control">
<input type="checkbox" name="portal_hide_upgrade_upsell_when_multi_room" value="1" <?php echo itm_hotel_booking_portal_hide_upgrade_upsell_when_multi_room_from_settings($row ?: []) ? 'checked' : ''; ?>>
<span>Hide room upgrade upsell when rooms &gt; 1</span>
</label>
</div>
<div class="form-group">
<label>Money symbol</label>
<select name="portal_money_symbol" class="form-control">
<option value="EUR" <?php echo itm_hotel_booking_portal_money_symbol_code_from_settings($row ?: []) === 'EUR' ? 'selected' : ''; ?>>€ Euro</option>
<option value="GBP" <?php echo itm_hotel_booking_portal_money_symbol_code_from_settings($row ?: []) === 'GBP' ? 'selected' : ''; ?>>£ Pound</option>
<option value="USD" <?php echo itm_hotel_booking_portal_money_symbol_code_from_settings($row ?: []) === 'USD' ? 'selected' : ''; ?>>$ Dollar</option>
</select>
</div>
<div class="form-group">
<label class="itm-checkbox-control">
<input type="checkbox" name="portal_money_symbol_suffix" id="itm-hb-money-suffix" value="1" <?php echo !empty($moneyFormatOptions['suffix']) ? 'checked' : ''; ?>>
<span>Suffix style (e.g. 69.50<?php echo htmlspecialchars($moneyFormatOptions['symbol'], ENT_QUOTES, 'UTF-8'); ?>)</span>
</label>
</div>
<div class="form-group">
<label class="itm-checkbox-control">
<input type="checkbox" name="portal_money_symbol_prefix" id="itm-hb-money-prefix" value="1" <?php echo empty($moneyFormatOptions['suffix']) ? 'checked' : ''; ?>>
<span>Prefix style (e.g. <?php echo htmlspecialchars($moneyFormatOptions['symbol'], ENT_QUOTES, 'UTF-8'); ?>69.50)</span>
</label>
</div>
</div>
<div class="card" style="margin-top:24px;">
<h2 style="margin-top:0;">Portal date and time</h2>
<div class="form-group">
<label class="itm-checkbox-control">
<input type="checkbox" name="portal_show_internal_rates" value="1" <?php echo !empty($row['portal_show_internal_rates']) ? 'checked' : ''; ?>>
<span>Show internal rates on booking portal</span>
</label>
<p class="text-muted" style="font-size:.85rem;margin-top:4px;">When enabled, guests can select <strong>HOUSE USE (USE)</strong> or <strong>COMPLIMENTARY (COMP)</strong> in Special rates. Admin reservations always allow internal rates on create/edit.</p>
</div>
<div class="form-group">
<label>Date positioning</label>
<label class="itm-checkbox-control"><input type="radio" name="portal_date_format" value="european_ddmmyyyy" <?php echo $portalDateFormat === 'european_ddmmyyyy' ? 'checked' : ''; ?>> European — DD/MM/YYYY</label>
<label class="itm-checkbox-control"><input type="radio" name="portal_date_format" value="european_ddmmmyyyy" <?php echo $portalDateFormat === 'european_ddmmmyyyy' ? 'checked' : ''; ?>> European — DD/MMM/YYYY</label>
<label class="itm-checkbox-control"><input type="radio" name="portal_date_format" value="us_mmddyyyy" <?php echo $portalDateFormat === 'us_mmddyyyy' ? 'checked' : ''; ?>> US — MM/DD/YYYY</label>
<label class="itm-checkbox-control"><input type="radio" name="portal_date_format" value="iso_yyyymmdd" <?php echo $portalDateFormat === 'iso_yyyymmdd' ? 'checked' : ''; ?>> ISO — YYYY-MM-DD</label>
</div>
<div class="form-group">
<label>Time format</label>
<label class="itm-checkbox-control"><input type="radio" name="portal_time_format" value="h24" <?php echo $portalTimeFormat === 'h24' ? 'checked' : ''; ?>> 24-hour format</label>
<label class="itm-checkbox-control"><input type="radio" name="portal_time_format" value="h12" <?php echo $portalTimeFormat === 'h12' ? 'checked' : ''; ?>> 12-hour format</label>
</div>
<div class="form-group">
<label>Combined date-time formats</label>
<label class="itm-checkbox-control"><input type="checkbox" name="portal_datetime_european1_enabled" value="1" <?php echo !empty($portalDatetimeEnabled['european1']) ? 'checked' : ''; ?>> European datetime1 — 17/08/2026 22:58</label>
<label class="itm-checkbox-control"><input type="checkbox" name="portal_datetime_european2_enabled" value="1" <?php echo !empty($portalDatetimeEnabled['european2']) ? 'checked' : ''; ?>> European datetime2 — 17/AUG/2026 22:58</label>
<label class="itm-checkbox-control"><input type="checkbox" name="portal_datetime_iso_enabled" value="1" <?php echo !empty($portalDatetimeEnabled['iso']) ? 'checked' : ''; ?>> ISO datetime — 2026-08-17T22:58:00Z</label>
<label class="itm-checkbox-control"><input type="checkbox" name="portal_datetime_readable_enabled" value="1" <?php echo !empty($portalDatetimeEnabled['readable']) ? 'checked' : ''; ?>> Readable datetime — 17 Aug 2026, 22:58</label>
</div>
<div class="form-group">
<label>Default datetime display</label>
<select name="portal_datetime_format_default" class="form-control">
<option value="european1" <?php echo $portalDatetimeDefault === 'european1' ? 'selected' : ''; ?>>European datetime1</option>
<option value="european2" <?php echo $portalDatetimeDefault === 'european2' ? 'selected' : ''; ?>>European datetime2 (default)</option>
<option value="iso" <?php echo $portalDatetimeDefault === 'iso' ? 'selected' : ''; ?>>ISO datetime</option>
<option value="readable" <?php echo $portalDatetimeDefault === 'readable' ? 'selected' : ''; ?>>Readable datetime</option>
</select>
</div>
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
<?php
$uiCopySectionLabels = itm_hotel_booking_portal_ui_copy_section_labels();
$uiCopyBySection = [];
foreach (itm_hotel_booking_portal_ui_copy_registry() as $uiCopyRow) {
    $section = (string) ($uiCopyRow['section'] ?? 'home');
    if (!isset($uiCopyBySection[$section])) {
        $uiCopyBySection[$section] = [];
    }
    $uiCopyBySection[$section][] = $uiCopyRow;
}
foreach ($uiCopySectionLabels as $sectionKey => $sectionTitle):
    if (empty($uiCopyBySection[$sectionKey])) {
        continue;
    }
?>
<div class="card" style="margin-top:24px;">
<h2 style="margin-top:0;"><?php echo sanitize($sectionTitle); ?></h2>
<?php foreach ($uiCopyBySection[$sectionKey] as $uiCopyRow):
    $col = (string) ($uiCopyRow['column'] ?? '');
    if ($col === '') {
        continue;
    }
    $label = (string) ($uiCopyRow['label'] ?? $col);
    $maxlen = $uiCopyRow['maxlen'] ?? 255;
    $value = (string) ($row[$col] ?? ($uiCopyRow['default'] ?? ''));
    $placeholders = $uiCopyRow['placeholders'] ?? [];
?>
<div class="form-group">
<label><?php echo sanitize($label); ?></label>
<?php if ($maxlen === 'text'): ?>
<textarea name="<?php echo sanitize($col); ?>" class="form-control" rows="3"><?php echo sanitize($value); ?></textarea>
<?php else: ?>
<input type="text" name="<?php echo sanitize($col); ?>" class="form-control" maxlength="<?php echo (int) min(500, max(1, (int) $maxlen)); ?>" value="<?php echo sanitize($value); ?>">
<?php endif; ?>
<?php if (!empty($placeholders) && is_array($placeholders)): ?>
<p class="text-muted" style="font-size:.85rem;margin-top:4px;">Placeholders: <?php echo sanitize(implode(', ', $placeholders)); ?></p>
<?php endif; ?>
</div>
<?php endforeach; ?>
</div>
<?php endforeach; ?>
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
document.addEventListener('DOMContentLoaded', function () {
  var suffix = document.getElementById('itm-hb-money-suffix');
  var prefix = document.getElementById('itm-hb-money-prefix');
  if (!suffix || !prefix) {
    return;
  }
  suffix.addEventListener('change', function () {
    if (suffix.checked) {
      prefix.checked = false;
    } else if (!prefix.checked) {
      suffix.checked = true;
    }
  });
  prefix.addEventListener('change', function () {
    if (prefix.checked) {
      suffix.checked = false;
    } else if (!suffix.checked) {
      suffix.checked = true;
    }
  });
});
</script>
</div>
<?php itm_hospitality_admin_layout_end(); ?>
