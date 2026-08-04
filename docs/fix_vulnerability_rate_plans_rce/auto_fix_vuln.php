<?php
/**
 * Auto Fix Script for hotel_booking_portal_rate_plans RCE Vulnerability.
 */

$live_file = __DIR__ . '/../../includes/itm_hotel_booking.php';
if (!is_file($live_file)) {
    $live_file = dirname(dirname(__DIR__)) . '/includes/itm_hotel_booking.php';
}

if (!is_file($live_file)) {
    die("Error: includes/itm_hotel_booking.php not found.\n");
}

$content = file_get_contents($live_file);

$search_pattern = '/if\s*\(\s*!function_exists\s*\(\s*\'itm_hotel_booking_normalize_cancellation_policy_url\'\s*\)\s*\)\s*\{\s*function\s+itm_hotel_booking_normalize_cancellation_policy_url.*?return\s+strlen.*?\}\s*\}/s';

$replace = "if (!function_exists('itm_hotel_booking_normalize_cancellation_policy_url')) {
  function itm_hotel_booking_normalize_cancellation_policy_url(\$url) {
    \$url = trim((string) \$url);
    if (\$url === '') {
      return '';
    }
    if (preg_match('#^https?://#i', \$url)) {
      return strlen(\$url) > 500 ? substr(\$url, 0, 500) : \$url;
    }
    \$url = ltrim(str_replace('\\\\', '/', \$url), '/');
    if (\$url === '' || strpos(\$url, '..') !== false) {
      return '';
    }
    // Why: To prevent Remote Code Execution (RCE) via cancellation policy URL file uploads, we strictly allow only safe file extensions.
    \$pathInfo = pathinfo(\$url);
    \$ext = strtolower(\$pathInfo['extension'] ?? '');
    if (!in_array(\$ext, ['html', 'htm', 'txt'], true)) {
      return '';
    }
    return strlen(\$url) > 500 ? substr(\$url, 0, 500) : \$url;
  }
}";

if (preg_match($search_pattern, $content)) {
    $updated = preg_replace($search_pattern, $replace, $content);
    if (file_put_contents($live_file, $updated) !== false) {
        echo "SUCCESS: hotel_booking_portal_rate_plans RCE vulnerability successfully fixed!\n";
    } else {
        echo "ERROR: Failed to write to includes/itm_hotel_booking.php\n";
    }
} else {
    echo "INFO: Already fixed or function signature not matched.\n";
}
