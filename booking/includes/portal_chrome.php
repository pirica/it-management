<?php
/**
 * Shared chrome for full-width booking flow pages (select room, etc.).
 */

if (!function_exists('hb_portal_format_stay_range_label')) {
    function hb_portal_format_stay_range_label($checkInIso, $nights = 1) {
        $nights = max(1, (int) $nights);
        $in = DateTime::createFromFormat('Y-m-d', $checkInIso);
        if (!$in) {
            return '';
        }
        $out = clone $in;
        $out->modify('+' . $nights . ' day');
        $nightWord = $nights === 1 ? 'night' : 'nights';
        return $in->format('D, M j') . ' – ' . $out->format('D, M j, Y') . ' (' . $nights . ' ' . $nightWord . ')';
    }
}

if (!function_exists('hb_portal_money_format')) {
    function hb_portal_money_format($amount, $currencyCode = 'EUR') {
        $amount = (float) $amount;
        $code = strtoupper((string) $currencyCode);
        if ($code === 'EUR') {
            return '€' . number_format($amount, 0, '.', '');
        }
        return $code . ' ' . number_format($amount, 2, '.', ',');
    }
}

if (!function_exists('hb_portal_render_header')) {
    function hb_portal_render_header($settings, $activeNav = '') {
        $brand = $settings['welcome_title'] ?? 'Hotel booking';
        ?>
<header class="hb-portal-header">
<div class="hb-portal-header-inner">
<a class="hb-portal-brand" href="<?php echo htmlspecialchars(APPURL . '/', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($brand, ENT_QUOTES, 'UTF-8'); ?></a>
<nav class="hb-portal-nav">
<a href="<?php echo htmlspecialchars(APPURL . '/users/bookings.php', ENT_QUOTES, 'UTF-8'); ?>">Manage my booking</a>
</nav>
</div>
</header>
        <?php
    }
}

if (!function_exists('hb_portal_render_stay_bar')) {
    function hb_portal_render_stay_bar(array $hotel, $checkInIso, $nights = 1, $adults = 1) {
        $hotelId = (int) ($hotel['id'] ?? 0);
        $editUrl = APPURL . '/?hotel=' . $hotelId . '&dates=1';
        $rangeLabel = hb_portal_format_stay_range_label($checkInIso, $nights);
        ?>
<div class="hb-stay-bar">
<div class="hb-stay-bar-inner">
<span class="hb-stay-item" title="Hotel">📍 <?php echo htmlspecialchars($hotel['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
<span class="hb-stay-item" title="Dates">📅 <?php echo htmlspecialchars($rangeLabel, ENT_QUOTES, 'UTF-8'); ?></span>
<span class="hb-stay-item" title="Guests">👤 1 room for <?php echo (int) $adults; ?> adult</span>
<a class="hb-stay-edit" href="<?php echo htmlspecialchars($editUrl, ENT_QUOTES, 'UTF-8'); ?>">Edit stay</a>
</div>
</div>
        <?php
    }
}
