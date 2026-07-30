<?php

/**
 * Hotel booking shared helpers (segment, status, photos, planning, portal customer).
 */

if (!function_exists('itm_hotel_booking_resolve_segment')) {
  function itm_hotel_booking_resolve_segment($checkIn, $checkOut, $today = null) {
    $today = $today ?? date('Y-m-d');
    if ($checkOut < $today) {
      return 'history';
    }
    if ($checkIn > $today) {
      return 'future';
    }
    return 'present';
  }
}

if (!function_exists('itm_hotel_booking_status_table_for_segment')) {
  function itm_hotel_booking_status_table_for_segment($segment) {
    $map = [
      'future' => 'hotel_bookings_future',
      'present' => 'hotel_bookings_present',
      'history' => 'hotel_bookings_history',
    ];
    return $map[$segment] ?? null;
  }
}

if (!function_exists('itm_hotel_booking_status_id_by_name')) {
  function itm_hotel_booking_status_id_by_name($conn, $companyId, $table, $name) {
    if (!preg_match('/^hotel_bookings_(future|present|history)$/', $table) && $table !== 'hotel_booking_housekeeping_statuses') {
      return null;
    }
    $companyId = (int) $companyId;
    $name = trim((string) $name);
    if ($companyId < 1 || $name === '') {
      return null;
    }
    $sql = 'SELECT id FROM `' . str_replace('`', '``', $table) . '` WHERE company_id = ? AND name = ? AND deleted_at IS NULL LIMIT 1';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
      return null;
    }
    mysqli_stmt_bind_param($stmt, 'is', $companyId, $name);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    return $row ? (int) $row['id'] : null;
  }
}

if (!function_exists('itm_hotel_booking_photo_storage_dir')) {
  function itm_hotel_booking_photo_storage_dir($companyId, $scope, $parentId) {
    $companyId = (int) $companyId;
    $parentId = (int) $parentId;
    $scope = preg_replace('/[^a-z_]/', '', (string) $scope);
    return 'images/hotel_booking/' . $companyId . '/' . $scope . '/' . $parentId;
  }
}

if (!function_exists('itm_hotel_booking_photo_public_url')) {
  function itm_hotel_booking_photo_public_url($companyId, $scope, $parentId, $storedFilename) {
    $storedFilename = basename((string) $storedFilename);
    if ($storedFilename === '') {
      return '';
    }
    $rel = itm_hotel_booking_photo_storage_dir($companyId, $scope, $parentId) . '/' . $storedFilename;
    return '/it-management/' . str_replace('\\', '/', $rel);
  }
}

if (!function_exists('itm_hotel_booking_photos_load')) {
  function itm_hotel_booking_photos_load($conn, $companyId, $photoTable, $parentColumn, $parentId) {
    $allowed = [
      'hotel_booking_hotel_photos' => 'hotel_id',
      'hotel_booking_room_photos' => 'room_id',
      'booking_rooms_type_photos' => 'room_type_id',
    ];
    if (!isset($allowed[$photoTable]) || $allowed[$photoTable] !== $parentColumn) {
      return [];
    }
    $companyId = (int) $companyId;
    $parentId = (int) $parentId;
    $sql = 'SELECT * FROM `' . str_replace('`', '``', $photoTable) . '` WHERE company_id = ? AND `' . str_replace('`', '``', $parentColumn) . '` = ? AND deleted_at IS NULL ORDER BY is_cover DESC, sort_order ASC, id ASC';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
      return [];
    }
    mysqli_stmt_bind_param($stmt, 'ii', $companyId, $parentId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($res && ($row = mysqli_fetch_assoc($res))) {
      $rows[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $rows;
  }
}

if (!function_exists('itm_hotel_booking_photos_config_for_parent_table')) {
  function itm_hotel_booking_photos_config_for_parent_table($parentTable) {
    $map = [
      'hotel_booking_hotels' => ['scope' => 'hotel', 'photo_table' => 'hotel_booking_hotel_photos', 'parent_column' => 'hotel_id'],
      'hotel_booking_rooms' => ['scope' => 'room', 'photo_table' => 'hotel_booking_room_photos', 'parent_column' => 'room_id'],
      'booking_rooms_types' => ['scope' => 'room_type', 'photo_table' => 'booking_rooms_type_photos', 'parent_column' => 'room_type_id'],
    ];
    return $map[$parentTable] ?? null;
  }
}

if (!function_exists('itm_hotel_booking_photos_handle_upload')) {
  function itm_hotel_booking_photos_handle_upload($conn, $companyId, $parentTable, $parentId) {
    $cfg = itm_hotel_booking_photos_config_for_parent_table($parentTable);
    if (!$cfg || empty($_FILES['hb_photos']) || !is_array($_FILES['hb_photos']['name'])) {
      return;
    }
    $companyId = (int) $companyId;
    $parentId = (int) $parentId;
    if ($companyId < 1 || $parentId < 1) {
      return;
    }
    $scope = $cfg['scope'];
    $photoTable = $cfg['photo_table'];
    $parentColumn = $cfg['parent_column'];
    $relDir = itm_hotel_booking_photo_storage_dir($companyId, $scope, $parentId);
    $absDir = rtrim(ROOT_PATH, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relDir);
    if (!function_exists('itm_ensure_upload_directory')) {
      require_once ROOT_PATH . 'includes/bootstrap_helpers.php';
    }
    itm_ensure_upload_directory($absDir, 'upload');
    $names = $_FILES['hb_photos']['name'];
    $tmp = $_FILES['hb_photos']['tmp_name'];
    $errs = $_FILES['hb_photos']['error'];
    $sort = 0;
    $count = count($names);
    for ($i = 0; $i < $count; $i++) {
      if ((int) ($errs[$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        continue;
      }
      $orig = basename((string) $names[$i]);
      if ($orig === '' || !is_uploaded_file($tmp[$i])) {
        continue;
      }
      $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
      if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
        continue;
      }
      $stored = 'hb_' . bin2hex(random_bytes(8)) . '.' . $ext;
      $dest = $absDir . DIRECTORY_SEPARATOR . $stored;
      if (!move_uploaded_file($tmp[$i], $dest)) {
        continue;
      }
      $isCover = ($sort === 0) ? 1 : 0;
      $sql = 'INSERT INTO `' . str_replace('`', '``', $photoTable) . '` (company_id, `' . str_replace('`', '``', $parentColumn) . '`, stored_filename, original_filename, sort_order, is_cover, active, created_at) VALUES (?, ?, ?, ?, ?, ?, 1, NOW())';
      $stmt = mysqli_prepare($conn, $sql);
      if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'iissii', $companyId, $parentId, $stored, $orig, $sort, $isCover);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
      }
      $sort++;
    }
  }
}

if (!function_exists('itm_hotel_booking_customer_last_name_matches')) {
  function itm_hotel_booking_customer_last_name_matches($customerName, $lastNameInput) {
    $lastNameInput = trim(mb_strtolower((string) $lastNameInput, 'UTF-8'));
    if ($lastNameInput === '') {
      return false;
    }
    $customerName = trim(mb_strtolower((string) $customerName, 'UTF-8'));
    if ($customerName === '') {
      return false;
    }
    $parts = preg_split('/\s+/u', $customerName);
    if (!$parts) {
      return false;
    }
    $last = (string) end($parts);
    return $last === $lastNameInput || $customerName === $lastNameInput;
  }
}

if (!function_exists('itm_hotel_booking_fetch_for_guest_manage')) {
  function itm_hotel_booking_fetch_for_guest_manage($conn, $companyId, $reservationId, $lastName) {
    $companyId = (int) $companyId;
    $reservationId = (int) $reservationId;
    if ($companyId < 1 || $reservationId < 1) {
      return null;
    }
    $sql = 'SELECT b.*, c.name AS customer_name, c.email AS customer_email, c.phone AS customer_phone,
                   r.room_number, r.name AS room_name, h.name AS hotel_name
            FROM hotel_bookings b
            INNER JOIN customers c ON c.id = b.customer_id AND c.company_id = b.company_id
            INNER JOIN hotel_booking_rooms r ON r.id = b.room_id AND r.company_id = b.company_id
            INNER JOIN hotel_booking_hotels h ON h.id = r.hotel_id AND h.company_id = r.company_id
            WHERE b.company_id = ? AND b.id = ? AND b.deleted_at IS NULL
            LIMIT 1';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
      return null;
    }
    mysqli_stmt_bind_param($stmt, 'ii', $companyId, $reservationId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    if (!$row || !itm_hotel_booking_customer_last_name_matches($row['customer_name'] ?? '', $lastName)) {
      return null;
    }
    return $row;
  }
}

if (!function_exists('itm_hotel_booking_normalize_reviews_url')) {
  function itm_hotel_booking_normalize_reviews_url($url) {
    $url = trim((string) $url);
    if ($url === '') {
      return '';
    }
    if (!preg_match('#^https?://#i', $url)) {
      return '';
    }
    if (strlen($url) > 500) {
      $url = substr($url, 0, 500);
    }
    return $url;
  }
}

if (!function_exists('itm_hotel_booking_resolve_reviews_url')) {
  function itm_hotel_booking_resolve_reviews_url($hotelRow, $settingsRow) {
    $hotelRow = is_array($hotelRow) ? $hotelRow : [];
    $settingsRow = is_array($settingsRow) ? $settingsRow : [];
    $hotelUrl = itm_hotel_booking_normalize_reviews_url($hotelRow['reviews_url'] ?? '');
    if ($hotelUrl !== '') {
      return $hotelUrl;
    }
    return itm_hotel_booking_normalize_reviews_url($settingsRow['reviews_url'] ?? '');
  }
}

if (!function_exists('itm_hotel_booking_portal_sanitize_rate_code')) {
  function itm_hotel_booking_portal_sanitize_rate_code($value, $maxLen = 8) {
    $v = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $value));
    if ($maxLen < 1) {
      return '';
    }
    return substr($v, 0, (int) $maxLen);
  }
}

if (!function_exists('itm_hotel_booking_portal_parse_bool_param')) {
  function itm_hotel_booking_portal_parse_bool_param(array $source, $key) {
    if (!array_key_exists($key, $source)) {
      return 0;
    }
    $v = $source[$key];
    if ($v === true || $v === 1 || $v === '1' || $v === 'on' || $v === 'yes') {
      return 1;
    }
    return 0;
  }
}

if (!function_exists('itm_hotel_booking_portal_parse_occupancy')) {
  function itm_hotel_booking_portal_parse_occupancy(array $source) {
    $rooms = max(1, min(4, (int) ($source['rooms'] ?? 1)));
    $adults = max(1, min(12, (int) ($source['adults'] ?? 1)));
    $children = max(0, min(6, (int) ($source['children'] ?? 0)));
    $babies = max(0, min(3, (int) ($source['babies'] ?? 0)));
    $rateSlug = strtolower(preg_replace('/[^a-z0-9_-]/', '', (string) ($source['rate'] ?? '')));
    $occupancy = [
      'rooms' => $rooms,
      'adults' => $adults,
      'children' => $children,
      'babies' => $babies,
      'rate' => $rateSlug,
      'use_points' => itm_hotel_booking_portal_parse_bool_param($source, 'use_points'),
      'travel_agents' => itm_hotel_booking_portal_parse_bool_param($source, 'travel_agents'),
      'aaa_rate' => itm_hotel_booking_portal_parse_bool_param($source, 'aaa_rate'),
      'senior_rate' => itm_hotel_booking_portal_parse_bool_param($source, 'senior_rate'),
      'gov_military' => itm_hotel_booking_portal_parse_bool_param($source, 'gov_military'),
      'promo_code' => itm_hotel_booking_portal_sanitize_rate_code($source['promo_code'] ?? ''),
      'group_code' => itm_hotel_booking_portal_sanitize_rate_code($source['group_code'] ?? ''),
      'corporate_account' => itm_hotel_booking_portal_sanitize_rate_code($source['corporate_account'] ?? ''),
      'member_account' => itm_hotel_booking_portal_sanitize_rate_code($source['member_account'] ?? ''),
    ];
    return itm_hotel_booking_portal_enforce_exclusive_rate_checkboxes($occupancy);
  }
}

if (!function_exists('itm_hotel_booking_portal_enforce_exclusive_rate_checkboxes')) {
  function itm_hotel_booking_portal_enforce_exclusive_rate_checkboxes(array $occupancy) {
    $keys = ['use_points', 'travel_agents', 'aaa_rate', 'senior_rate', 'gov_military'];
    $keep = null;
    foreach ($keys as $key) {
      if (!empty($occupancy[$key])) {
        if ($keep === null) {
          $keep = $key;
        } else {
          $occupancy[$key] = 0;
        }
      }
    }
    return $occupancy;
  }
}

if (!function_exists('itm_hotel_booking_portal_rate_program_options')) {
  function itm_hotel_booking_portal_rate_program_options() {
    return [
      ['param' => 'use_points', 'label' => 'Use Points', 'slug' => 'points'],
      ['param' => 'travel_agents', 'label' => 'Travel agents', 'slug' => 'travel_agent'],
      ['param' => 'aaa_rate', 'label' => 'AAA rate', 'slug' => 'aaa'],
      ['param' => 'senior_rate', 'label' => 'Senior rate', 'slug' => 'senior'],
      ['param' => 'gov_military', 'label' => 'Government and military rates', 'slug' => 'government'],
    ];
  }
}

if (!function_exists('itm_hotel_booking_portal_code_rate_options')) {
  function itm_hotel_booking_portal_code_rate_options() {
    return [
      ['param' => 'promo_code', 'label' => 'Promotion code', 'slug' => 'promo'],
      ['param' => 'group_code', 'label' => 'Group code', 'slug' => 'group'],
      ['param' => 'corporate_account', 'label' => 'Corporate account', 'slug' => 'corporate'],
      ['param' => 'member_account', 'label' => 'Member account', 'slug' => 'member'],
    ];
  }
}

if (!function_exists('itm_hotel_booking_canonical_special_rate_definitions')) {
  function itm_hotel_booking_canonical_special_rate_definitions() {
    $defs = [];
    foreach (itm_hotel_booking_portal_rate_program_options() as $row) {
      $defs[] = [
        'slug' => (string) ($row['slug'] ?? ''),
        'name' => (string) ($row['label'] ?? ''),
        'description' => '',
      ];
    }
    foreach (itm_hotel_booking_portal_code_rate_options() as $row) {
      $defs[] = [
        'slug' => (string) ($row['slug'] ?? ''),
        'name' => (string) ($row['label'] ?? ''),
        'description' => '',
      ];
    }
    return $defs;
  }
}

if (!function_exists('itm_hotel_booking_normalize_special_rate_percent_input')) {
  function itm_hotel_booking_normalize_special_rate_percent_input($value) {
    $raw = str_replace(',', '.', trim((string) $value));
    if ($raw === '' || !is_numeric($raw)) {
      return 0.0;
    }
    $n = (float) $raw;
    if ($n < 0) {
      return 0.0;
    }
    if ($n > 100) {
      return 100.0;
    }
    return round($n, 2);
  }
}

if (!function_exists('itm_hotel_booking_ensure_special_rates_for_hotel')) {
  function itm_hotel_booking_ensure_special_rates_for_hotel($conn, $companyId, $hotelId, $employeeId = null) {
    $companyId = (int) $companyId;
    $hotelId = (int) $hotelId;
    $employeeId = $employeeId ? (int) $employeeId : null;
    if ($companyId < 1 || $hotelId < 1) {
      return;
    }
    foreach (itm_hotel_booking_canonical_special_rate_definitions() as $def) {
      $slug = strtolower(preg_replace('/[^a-z0-9_-]/', '', (string) ($def['slug'] ?? '')));
      $name = trim((string) ($def['name'] ?? ''));
      if ($slug === '' || $name === '') {
        continue;
      }
      $stmt = mysqli_prepare($conn, 'SELECT id FROM hotel_booking_special_rates WHERE company_id = ? AND hotel_id = ? AND rate_slug = ? AND deleted_at IS NULL LIMIT 1');
      if (!$stmt) {
        continue;
      }
      mysqli_stmt_bind_param($stmt, 'iis', $companyId, $hotelId, $slug);
      mysqli_stmt_execute($stmt);
      $res = mysqli_stmt_get_result($stmt);
      $exists = $res && mysqli_fetch_assoc($res);
      mysqli_stmt_close($stmt);
      if ($exists) {
        continue;
      }
      $description = (string) ($def['description'] ?? '');
      $ins = mysqli_prepare($conn, 'INSERT INTO hotel_booking_special_rates (company_id, hotel_id, rate_slug, name, discount_percent, description, active, created_by, created_at) VALUES (?, ?, ?, ?, 0.00, ?, 1, ?, NOW())');
      if ($ins) {
        mysqli_stmt_bind_param($ins, 'iisssi', $companyId, $hotelId, $slug, $name, $description, $employeeId);
        mysqli_stmt_execute($ins);
        mysqli_stmt_close($ins);
      }
    }
  }
}

if (!function_exists('itm_hotel_booking_special_rates_admin_rows')) {
  function itm_hotel_booking_special_rates_admin_rows($conn, $companyId, $hotelId) {
    itm_hotel_booking_ensure_special_rates_for_hotel($conn, $companyId, $hotelId);
    $map = [];
    $stmt = mysqli_prepare($conn, 'SELECT rate_slug, name, discount_percent, description, active FROM hotel_booking_special_rates WHERE company_id = ? AND hotel_id = ? AND deleted_at IS NULL ORDER BY name ASC');
    if ($stmt) {
      mysqli_stmt_bind_param($stmt, 'ii', $companyId, $hotelId);
      mysqli_stmt_execute($stmt);
      $res = mysqli_stmt_get_result($stmt);
      while ($res && ($row = mysqli_fetch_assoc($res))) {
        $map[(string) ($row['rate_slug'] ?? '')] = $row;
      }
      mysqli_stmt_close($stmt);
    }
    $rows = [];
    foreach (itm_hotel_booking_canonical_special_rate_definitions() as $def) {
      $slug = (string) ($def['slug'] ?? '');
      if ($slug === '' || !isset($map[$slug])) {
        continue;
      }
      $rows[] = $map[$slug];
    }
    return $rows;
  }
}

if (!function_exists('itm_hotel_booking_portal_rate_plan_definitions')) {
  function itm_hotel_booking_portal_rate_plan_definitions() {
    return [
      [
        'plan_slot' => 1,
        'rate_plan_slug' => 'room_only',
        'name' => 'Best Available Rate',
        'default_policy_path' => 'cancellation_policy/1_cancellation_policy.html',
      ],
      [
        'plan_slot' => 2,
        'rate_plan_slug' => 'breakfast',
        'name' => 'Breakfast Included',
        'default_policy_path' => 'cancellation_policy/2_cancellation_policy.html',
      ],
      [
        'plan_slot' => 3,
        'rate_plan_slug' => 'flexible',
        'name' => 'Flexible Rate',
        'default_policy_path' => 'cancellation_policy/3_cancellation_policy.html',
      ],
      [
        'plan_slot' => 4,
        'rate_plan_slug' => 'non_refundable',
        'name' => 'Non-Refundable Rate',
        'default_policy_path' => 'cancellation_policy/4_cancellation_policy.html',
      ],
    ];
  }
}

if (!function_exists('itm_hotel_booking_normalize_cancellation_policy_url')) {
  function itm_hotel_booking_normalize_cancellation_policy_url($url) {
    $url = trim((string) $url);
    if ($url === '') {
      return '';
    }
    if (preg_match('#^https?://#i', $url)) {
      return strlen($url) > 500 ? substr($url, 0, 500) : $url;
    }
    $url = ltrim(str_replace('\\', '/', $url), '/');
    if ($url === '' || strpos($url, '..') !== false) {
      return '';
    }
    return strlen($url) > 500 ? substr($url, 0, 500) : $url;
  }
}

if (!function_exists('itm_hotel_booking_portal_default_cancellation_policy_path')) {
  function itm_hotel_booking_portal_default_cancellation_policy_path($ratePlanSlug) {
    $slug = strtolower(preg_replace('/[^a-z0-9_-]/', '', (string) $ratePlanSlug));
    foreach (itm_hotel_booking_portal_rate_plan_definitions() as $def) {
      if ((string) ($def['rate_plan_slug'] ?? '') === $slug) {
        return (string) ($def['default_policy_path'] ?? '');
      }
    }
    return 'cancellation_policy/1_cancellation_policy.html';
  }
}

if (!function_exists('itm_hotel_booking_ensure_portal_rate_plans_for_hotel')) {
  function itm_hotel_booking_ensure_portal_rate_plans_for_hotel($conn, $companyId, $hotelId, $employeeId = null) {
    $companyId = (int) $companyId;
    $hotelId = (int) $hotelId;
    $employeeId = $employeeId ? (int) $employeeId : null;
    if ($companyId < 1 || $hotelId < 1) {
      return;
    }
    foreach (itm_hotel_booking_portal_rate_plan_definitions() as $def) {
      $slot = (int) ($def['plan_slot'] ?? 0);
      $slug = (string) ($def['rate_plan_slug'] ?? '');
      $name = trim((string) ($def['name'] ?? ''));
      $defaultPath = itm_hotel_booking_normalize_cancellation_policy_url($def['default_policy_path'] ?? '');
      if ($slot < 1 || $name === '') {
        continue;
      }
      $stmt = mysqli_prepare($conn, 'SELECT id FROM hotel_booking_portal_rate_plans WHERE company_id = ? AND hotel_id = ? AND plan_slot = ? AND deleted_at IS NULL LIMIT 1');
      if (!$stmt) {
        continue;
      }
      mysqli_stmt_bind_param($stmt, 'iii', $companyId, $hotelId, $slot);
      mysqli_stmt_execute($stmt);
      $res = mysqli_stmt_get_result($stmt);
      $exists = $res && mysqli_fetch_assoc($res);
      mysqli_stmt_close($stmt);
      if ($exists) {
        continue;
      }
      $ins = mysqli_prepare($conn, 'INSERT INTO hotel_booking_portal_rate_plans (company_id, hotel_id, plan_slot, name, rate_plan_slug, cancellation_policy_url, active, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, 1, ?, NOW())');
      if ($ins) {
        mysqli_stmt_bind_param($ins, 'iiisssi', $companyId, $hotelId, $slot, $name, $slug, $defaultPath, $employeeId);
        mysqli_stmt_execute($ins);
        mysqli_stmt_close($ins);
      }
    }
  }
}

if (!function_exists('itm_hotel_booking_portal_rate_plans_admin_rows')) {
  function itm_hotel_booking_portal_rate_plans_admin_rows($conn, $companyId, $hotelId) {
    itm_hotel_booking_ensure_portal_rate_plans_for_hotel($conn, $companyId, $hotelId);
    $map = [];
    $stmt = mysqli_prepare($conn, 'SELECT id, plan_slot, name, rate_plan_slug, cancellation_policy_url, cancellation_policy_html, active FROM hotel_booking_portal_rate_plans WHERE company_id = ? AND hotel_id = ? AND deleted_at IS NULL ORDER BY plan_slot ASC');
    if ($stmt) {
      mysqli_stmt_bind_param($stmt, 'ii', $companyId, $hotelId);
      mysqli_stmt_execute($stmt);
      $res = mysqli_stmt_get_result($stmt);
      while ($res && ($row = mysqli_fetch_assoc($res))) {
        $map[(int) ($row['plan_slot'] ?? 0)] = $row;
      }
      mysqli_stmt_close($stmt);
    }
    $rows = [];
    foreach (itm_hotel_booking_portal_rate_plan_definitions() as $def) {
      $slot = (int) ($def['plan_slot'] ?? 0);
      if ($slot < 1 || !isset($map[$slot])) {
        continue;
      }
      $rows[] = $map[$slot];
    }
    return $rows;
  }
}

if (!function_exists('itm_hotel_booking_portal_parse_rate_plan_from_notes')) {
  function itm_hotel_booking_portal_parse_rate_plan_from_notes($notes) {
    $notes = (string) $notes;
    if (preg_match('/^Rate:\s*Breakfast included/im', $notes)) {
      return 'breakfast';
    }
    if (preg_match('/^Rate:\s*Best available/im', $notes)) {
      return 'room_only';
    }
    if (preg_match('/^Rate plan:\s*([a-z0-9_-]+)/im', $notes, $m)) {
      return strtolower(preg_replace('/[^a-z0-9_-]/', '', (string) ($m[1] ?? '')));
    }
    return 'room_only';
  }
}

if (!function_exists('itm_hotel_booking_portal_resolve_cancellation_policy_url')) {
  function itm_hotel_booking_portal_resolve_cancellation_policy_url($conn, $companyId, $hotelId, $ratePlanSlug) {
    $companyId = (int) $companyId;
    $hotelId = (int) $hotelId;
    $ratePlanSlug = strtolower(preg_replace('/[^a-z0-9_-]/', '', (string) $ratePlanSlug));
    if ($ratePlanSlug === '') {
      $ratePlanSlug = 'room_only';
    }
    if ($companyId < 1 || $hotelId < 1) {
      return itm_hotel_booking_portal_default_cancellation_policy_path($ratePlanSlug);
    }
    itm_hotel_booking_ensure_portal_rate_plans_for_hotel($conn, $companyId, $hotelId);
    $stmt = mysqli_prepare($conn, 'SELECT cancellation_policy_url FROM hotel_booking_portal_rate_plans WHERE company_id = ? AND hotel_id = ? AND rate_plan_slug = ? AND deleted_at IS NULL AND active = 1 LIMIT 1');
    if ($stmt) {
      mysqli_stmt_bind_param($stmt, 'iis', $companyId, $hotelId, $ratePlanSlug);
      mysqli_stmt_execute($stmt);
      $res = mysqli_stmt_get_result($stmt);
      $row = $res ? mysqli_fetch_assoc($res) : null;
      mysqli_stmt_close($stmt);
      $url = itm_hotel_booking_normalize_cancellation_policy_url($row['cancellation_policy_url'] ?? '');
      if ($url !== '') {
        return $url;
      }
    }
    return itm_hotel_booking_portal_default_cancellation_policy_path($ratePlanSlug);
  }
}

if (!function_exists('itm_hotel_booking_format_discount_percent_label')) {
  function itm_hotel_booking_format_discount_percent_label($percent) {
    $n = (float) $percent;
    $text = rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
    return $text === '' ? '0' : $text;
  }
}

if (!function_exists('itm_hotel_booking_portal_resolved_rate_slug')) {
  function itm_hotel_booking_portal_resolved_rate_slug(array $occupancy) {
    $explicit = strtolower(preg_replace('/[^a-z0-9_-]/', '', (string) ($occupancy['rate'] ?? '')));
    if ($explicit !== '') {
      return $explicit;
    }
    if (!empty($occupancy['promo_code'])) {
      return 'promo';
    }
    if (!empty($occupancy['group_code'])) {
      return 'group';
    }
    if (!empty($occupancy['corporate_account'])) {
      return 'corporate';
    }
    if (!empty($occupancy['member_account'])) {
      return 'member';
    }
    if (!empty($occupancy['use_points'])) {
      return 'points';
    }
    if (!empty($occupancy['travel_agents'])) {
      return 'travel_agent';
    }
    if (!empty($occupancy['aaa_rate'])) {
      return 'aaa';
    }
    if (!empty($occupancy['senior_rate'])) {
      return 'senior';
    }
    if (!empty($occupancy['gov_military'])) {
      return 'government';
    }
    return '';
  }
}

if (!function_exists('itm_hotel_booking_portal_occupancy_query_params')) {
  function itm_hotel_booking_portal_occupancy_query_params(array $occupancy) {
    $params = [
      'rooms' => (int) ($occupancy['rooms'] ?? 1),
      'adults' => (int) ($occupancy['adults'] ?? 1),
      'children' => (int) ($occupancy['children'] ?? 0),
      'babies' => (int) ($occupancy['babies'] ?? 0),
    ];
    $rateSlug = strtolower(preg_replace('/[^a-z0-9_-]/', '', (string) ($occupancy['rate'] ?? '')));
    if ($rateSlug !== '') {
      $params['rate'] = $rateSlug;
    }
    $boolKeys = ['use_points', 'travel_agents', 'aaa_rate', 'senior_rate', 'gov_military'];
    foreach ($boolKeys as $key) {
      if (!empty($occupancy[$key])) {
        $params[$key] = '1';
      }
    }
    $codeKeys = ['promo_code', 'group_code', 'corporate_account', 'member_account'];
    foreach ($codeKeys as $key) {
      $code = itm_hotel_booking_portal_sanitize_rate_code($occupancy[$key] ?? '');
      if ($code !== '') {
        $params[$key] = $code;
      }
    }
    return $params;
  }
}

if (!function_exists('itm_hotel_booking_portal_occupancy_label')) {
  function itm_hotel_booking_portal_occupancy_label(array $occupancy) {
    $rooms = (int) ($occupancy['rooms'] ?? 1);
    $adults = (int) ($occupancy['adults'] ?? 1);
    $children = (int) ($occupancy['children'] ?? 0);
    $babies = (int) ($occupancy['babies'] ?? 0);
    $roomWord = $rooms === 1 ? 'room' : 'rooms';
    $parts = [];
    $parts[] = $adults === 1 ? '1 adult' : $adults . ' adults';
    if ($children > 0) {
      $parts[] = $children === 1 ? '1 child' : $children . ' children';
    }
    if ($babies > 0) {
      $parts[] = $babies === 1 ? '1 baby' : $babies . ' babies';
    }
    return $rooms . ' ' . $roomWord . ' for ' . implode(' + ', $parts);
  }
}

if (!function_exists('itm_hotel_booking_portal_quote_nightly')) {
  function itm_hotel_booking_portal_quote_nightly($basePerNight, array $occupancy, $discountPercent = 0.0) {
    $basePerNight = (float) $basePerNight;
    $rooms = max(1, (int) ($occupancy['rooms'] ?? 1));
    $adults = max(1, (int) ($occupancy['adults'] ?? 1));
    $children = max(0, (int) ($occupancy['children'] ?? 0));
    $includedAdults = 2 * $rooms;
    $extraAdults = max(0, $adults - $includedAdults);
    $nightly = $basePerNight * $rooms;
    $nightly += $extraAdults * ($basePerNight * 0.35);
    $nightly += $children * 22.0;
    $discountPercent = max(0.0, min(50.0, (float) $discountPercent));
    if ($discountPercent > 0) {
      $nightly *= (1 - ($discountPercent / 100));
    }
    return round($nightly, 2);
  }
}

if (!function_exists('itm_hotel_booking_special_rates_for_hotel')) {
  function itm_hotel_booking_special_rates_for_hotel($conn, $companyId, $hotelId) {
    $rows = [];
    $companyId = (int) $companyId;
    $hotelId = (int) $hotelId;
    $stmt = mysqli_prepare($conn, 'SELECT rate_slug, name, discount_percent, description FROM hotel_booking_special_rates WHERE company_id = ? AND hotel_id = ? AND deleted_at IS NULL AND active = 1 ORDER BY name ASC');
    if ($stmt) {
      mysqli_stmt_bind_param($stmt, 'ii', $companyId, $hotelId);
      mysqli_stmt_execute($stmt);
      $res = mysqli_stmt_get_result($stmt);
      while ($res && ($row = mysqli_fetch_assoc($res))) {
        $rows[] = $row;
      }
      mysqli_stmt_close($stmt);
    }
    return $rows;
  }
}

if (!function_exists('itm_hotel_booking_special_rate_discount_map')) {
  function itm_hotel_booking_special_rate_discount_map($conn, $companyId, $hotelId) {
    $map = [];
    foreach (itm_hotel_booking_special_rates_for_hotel($conn, $companyId, $hotelId) as $row) {
      $slug = (string) ($row['rate_slug'] ?? '');
      if ($slug !== '') {
        $map[$slug] = (float) ($row['discount_percent'] ?? 0);
      }
    }
    return $map;
  }
}

if (!function_exists('itm_hotel_booking_special_rate_discount')) {
  function itm_hotel_booking_special_rate_discount($conn, $companyId, $hotelId, $rateSlug) {
    $rateSlug = strtolower(preg_replace('/[^a-z0-9_-]/', '', (string) $rateSlug));
    if ($rateSlug === '') {
      return 0.0;
    }
    $companyId = (int) $companyId;
    $hotelId = (int) $hotelId;
    $stmt = mysqli_prepare($conn, 'SELECT discount_percent FROM hotel_booking_special_rates WHERE company_id = ? AND hotel_id = ? AND rate_slug = ? AND deleted_at IS NULL AND active = 1 LIMIT 1');
    if (!$stmt) {
      return 0.0;
    }
    mysqli_stmt_bind_param($stmt, 'iis', $companyId, $hotelId, $rateSlug);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    return $row ? (float) $row['discount_percent'] : 0.0;
  }
}

if (!function_exists('itm_hotel_booking_room_type_fits_occupancy')) {
  function itm_hotel_booking_room_type_fits_occupancy(array $typeRow, array $occupancy) {
    $rooms = max(1, (int) ($occupancy['rooms'] ?? 1));
    $adults = max(1, (int) ($occupancy['adults'] ?? 1));
    $children = max(0, (int) ($occupancy['children'] ?? 0));
    $babies = max(0, (int) ($occupancy['babies'] ?? 0));
    $maxAdults = max(1, (int) ($typeRow['max_adults'] ?? 2)) * $rooms;
    $maxChildren = max(0, (int) ($typeRow['max_children'] ?? 1)) * $rooms;
    $maxBabies = max(0, (int) ($typeRow['max_babies'] ?? 1)) * $rooms;
    return $adults <= $maxAdults && $children <= $maxChildren && $babies <= $maxBabies;
  }
}

if (!function_exists('itm_hotel_booking_hotel_calendar_month')) {
  function itm_hotel_booking_hotel_calendar_month($conn, $companyId, $hotelId, $year, $month) {
    $companyId = (int) $companyId;
    $hotelId = (int) $hotelId;
    $year = (int) $year;
    $month = (int) $month;
    if ($companyId < 1 || $hotelId < 1 || $month < 1 || $month > 12) {
      return ['year' => $year, 'month' => $month, 'currency_code' => 'EUR', 'days' => []];
    }
    $start = sprintf('%04d-%02d-01', $year, $month);
    $daysInMonth = (int) date('t', strtotime($start));
    $rangeEnd = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);
    $today = date('Y-m-d');

    $currency = 'EUR';
    $rooms = [];
    $rstmt = mysqli_prepare($conn, 'SELECT id, price_per_night FROM hotel_booking_rooms WHERE company_id = ? AND hotel_id = ? AND deleted_at IS NULL AND active = 1');
    if ($rstmt) {
      mysqli_stmt_bind_param($rstmt, 'ii', $companyId, $hotelId);
      mysqli_stmt_execute($rstmt);
      $res = mysqli_stmt_get_result($rstmt);
      while ($res && ($row = mysqli_fetch_assoc($res))) {
        $rooms[] = ['id' => (int) $row['id'], 'price' => (float) $row['price_per_night']];
      }
      mysqli_stmt_close($rstmt);
    }

    $hstmt = mysqli_prepare($conn, 'SELECT currency_code FROM hotel_booking_hotels WHERE id = ? AND company_id = ? AND deleted_at IS NULL LIMIT 1');
    if ($hstmt) {
      mysqli_stmt_bind_param($hstmt, 'ii', $hotelId, $companyId);
      mysqli_stmt_execute($hstmt);
      $hr = mysqli_stmt_get_result($hstmt);
      $hotelRow = $hr ? mysqli_fetch_assoc($hr) : null;
      mysqli_stmt_close($hstmt);
      if ($hotelRow && !empty($hotelRow['currency_code'])) {
        $currency = (string) $hotelRow['currency_code'];
      }
    }

    $bookingsByRoom = [];
    if (!empty($rooms)) {
      $roomIds = array_column($rooms, 'id');
      $placeholders = implode(',', array_fill(0, count($roomIds), '?'));
      $types = 'ii' . str_repeat('i', count($roomIds)) . 'ss';
      $sql = "SELECT room_id, check_in, check_out, future_status_id, present_status_id, history_status_id
              FROM hotel_bookings WHERE company_id = ? AND hotel_id = ? AND room_id IN ($placeholders)
              AND deleted_at IS NULL AND check_in <= ? AND check_out > ?";
      $bstmt = mysqli_prepare($conn, $sql);
      if ($bstmt) {
        $params = array_merge([$companyId, $hotelId], $roomIds, [$rangeEnd, $start]);
        mysqli_stmt_bind_param($bstmt, $types, ...$params);
        mysqli_stmt_execute($bstmt);
        $bres = mysqli_stmt_get_result($bstmt);
        while ($bres && ($b = mysqli_fetch_assoc($bres))) {
          $rid = (int) $b['room_id'];
          if (!isset($bookingsByRoom[$rid])) {
            $bookingsByRoom[$rid] = [];
          }
          if (!itm_hotel_booking_booking_is_cancelled($conn, $companyId, $b)) {
            $bookingsByRoom[$rid][] = $b;
          }
        }
        mysqli_stmt_close($bstmt);
      }
    }

    $roomFreeForNight = static function ($roomId, $checkIn, $checkOut) use ($bookingsByRoom) {
      $list = $bookingsByRoom[$roomId] ?? [];
      foreach ($list as $b) {
        if ($b['check_in'] < $checkOut && $b['check_out'] > $checkIn) {
          return false;
        }
      }
      return true;
    };

    $days = [];
    for ($d = 1; $d <= $daysInMonth; $d++) {
      $checkIn = sprintf('%04d-%02d-%02d', $year, $month, $d);
      $checkOut = date('Y-m-d', strtotime($checkIn . ' +1 day'));
      if ($checkIn < $today) {
        $days[$checkIn] = ['available' => false, 'past' => true];
        continue;
      }
      $best = null;
      foreach ($rooms as $room) {
        if ($roomFreeForNight($room['id'], $checkIn, $checkOut)) {
          if ($best === null || $room['price'] < $best) {
            $best = $room['price'];
          }
        }
      }
      if ($best !== null) {
        $days[$checkIn] = ['available' => true, 'price' => round($best, 2)];
      } else {
        $days[$checkIn] = ['available' => false];
      }
    }

    return [
      'year' => $year,
      'month' => $month,
      'currency_code' => $currency,
      'days' => $days,
    ];
  }
}

if (!function_exists('itm_hotel_booking_compute_payment_amount')) {
  function itm_hotel_booking_compute_payment_amount($pricePerNight, $checkIn, $checkOut) {
    $in = DateTime::createFromFormat('Y-m-d', $checkIn);
    $out = DateTime::createFromFormat('Y-m-d', $checkOut);
    if (!$in || !$out || $out <= $in) {
      return 0.0;
    }
    $nights = (int) $in->diff($out)->days;
    return round((float) $pricePerNight * $nights, 2);
  }
}

if (!function_exists('itm_hotel_booking_compute_stay_payment')) {
  function itm_hotel_booking_compute_stay_payment($basePerNight, $checkIn, $checkOut, array $occupancy, $discountPercent = 0.0) {
    $nightly = itm_hotel_booking_portal_quote_nightly($basePerNight, $occupancy, $discountPercent);
    return itm_hotel_booking_compute_payment_amount($nightly, $checkIn, $checkOut);
  }
}

if (!function_exists('itm_hotel_booking_portal_breakfast_adult_price')) {
  function itm_hotel_booking_portal_breakfast_adult_price() {
    return 30.0;
  }
}

if (!function_exists('itm_hotel_booking_portal_breakfast_child_price')) {
  function itm_hotel_booking_portal_breakfast_child_price() {
    return 20.0;
  }
}

if (!function_exists('itm_hotel_booking_portal_pet_daily_fee')) {
  function itm_hotel_booking_portal_pet_daily_fee() {
    return 50.0;
  }
}

if (!function_exists('itm_hotel_booking_portal_breakfast_supplement_per_night')) {
  function itm_hotel_booking_portal_breakfast_supplement_per_night(array $occupancy) {
    $adults = max(0, (int) ($occupancy['adults'] ?? 0));
    $children = max(0, (int) ($occupancy['children'] ?? 0));
    return $adults * itm_hotel_booking_portal_breakfast_adult_price()
      + $children * itm_hotel_booking_portal_breakfast_child_price();
  }
}

if (!function_exists('itm_hotel_booking_portal_stay_nights')) {
  function itm_hotel_booking_portal_stay_nights($checkIn, $checkOut) {
    $in = DateTime::createFromFormat('Y-m-d', (string) $checkIn);
    $out = DateTime::createFromFormat('Y-m-d', (string) $checkOut);
    if (!$in || !$out || $out <= $in) {
      return 0;
    }
    return (int) $in->diff($out)->days;
  }
}

if (!function_exists('itm_hotel_booking_portal_draft_session_key')) {
  function itm_hotel_booking_portal_draft_session_key() {
    return 'hotel_booking_portal_draft';
  }
}

if (!function_exists('itm_hotel_booking_portal_draft_get')) {
  function itm_hotel_booking_portal_draft_get() {
    $key = itm_hotel_booking_portal_draft_session_key();
    return isset($_SESSION[$key]) && is_array($_SESSION[$key]) ? $_SESSION[$key] : null;
  }
}

if (!function_exists('itm_hotel_booking_portal_draft_save')) {
  function itm_hotel_booking_portal_draft_save(array $draft) {
    $_SESSION[itm_hotel_booking_portal_draft_session_key()] = $draft;
  }
}

if (!function_exists('itm_hotel_booking_portal_draft_clear')) {
  function itm_hotel_booking_portal_draft_clear() {
    unset($_SESSION[itm_hotel_booking_portal_draft_session_key()]);
  }
}

if (!function_exists('itm_hotel_booking_portal_sanitize_comments')) {
  function itm_hotel_booking_portal_sanitize_comments($value, $maxLen = 130) {
    $text = trim((string) $value);
    if ($text === '') {
      return '';
    }
    if (function_exists('mb_substr')) {
      return mb_substr($text, 0, $maxLen, 'UTF-8');
    }
    return substr($text, 0, $maxLen);
  }
}

if (!function_exists('itm_hotel_booking_portal_normalize_guest_phone')) {
  function itm_hotel_booking_portal_normalize_guest_phone($phone) {
    $phone = trim((string) $phone);
    if ($phone === '') {
      return '';
    }
    $phone = preg_replace('/[\s\-\.\(\)]+/', '', $phone);
    if ($phone !== '' && $phone[0] !== '+' && preg_match('/^\d+$/', $phone)) {
      $phone = '+' . $phone;
    }
    return $phone;
  }
}

if (!function_exists('itm_hotel_booking_portal_validate_guest_email')) {
  function itm_hotel_booking_portal_validate_guest_email($email) {
    $email = trim((string) $email);
    if ($email === '') {
      return false;
    }
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
  }
}

if (!function_exists('itm_hotel_booking_portal_validate_guest_phone')) {
  /** E.164-style: leading + then 8–15 digits (country code required). */
  function itm_hotel_booking_portal_validate_guest_phone($phone) {
    $normalized = itm_hotel_booking_portal_normalize_guest_phone($phone);
    if ($normalized === '') {
      return false;
    }
    return (bool) preg_match('/^\+\d{8,15}$/', $normalized);
  }
}

if (!function_exists('itm_hotel_booking_portal_occupancy_meta_line')) {
  /** Machine-readable occupancy stored in hotel_bookings.notes (not shown in Reservation notes UI). */
  function itm_hotel_booking_portal_occupancy_meta_line(array $occupancy) {
    $parsed = itm_hotel_booking_portal_parse_occupancy($occupancy);
    return sprintf(
      'Occupancy: rooms=%d;adults=%d;children=%d;babies=%d',
      max(1, (int) ($parsed['rooms'] ?? 1)),
      max(1, (int) ($parsed['adults'] ?? 1)),
      max(0, (int) ($parsed['children'] ?? 0)),
      max(0, (int) ($parsed['babies'] ?? 0))
    );
  }
}

if (!function_exists('itm_hotel_booking_portal_parse_occupancy_meta_from_notes')) {
  function itm_hotel_booking_portal_parse_occupancy_meta_from_notes($notesRaw) {
    $lines = preg_split('/\r\n|\r|\n/', (string) $notesRaw);
    foreach ($lines as $line) {
      $line = trim($line);
      if (preg_match('/^Occupancy:\s*rooms=(\d+);adults=(\d+);children=(\d+);babies=(\d+)$/i', $line, $m)) {
        return itm_hotel_booking_portal_parse_occupancy([
          'rooms' => (int) $m[1],
          'adults' => (int) $m[2],
          'children' => (int) $m[3],
          'babies' => (int) $m[4],
        ]);
      }
    }
    return null;
  }
}

if (!function_exists('itm_hotel_booking_portal_build_booking_notes')) {
  function itm_hotel_booking_portal_build_booking_notes(array $draft, array $occupancy = null) {
    $parts = [];
    if (is_array($occupancy)) {
      $parts[] = itm_hotel_booking_portal_occupancy_meta_line($occupancy);
    }
    $plan = (string) ($draft['rate_plan'] ?? '');
    if ($plan === 'breakfast') {
      $parts[] = 'Rate: Breakfast included';
      $parts[] = 'Rate plan: breakfast';
    } elseif ($plan === 'room_only') {
      $parts[] = 'Rate: Best available (room only)';
      $parts[] = 'Rate plan: room_only';
    }
    if (!empty($draft['traveling_with_pet'])) {
      $parts[] = 'Traveling with pet: yes';
    }
    if (!empty($draft['service_animal'])) {
      $parts[] = 'Service animal: yes';
    }
    if (!empty($draft['upgrade_accepted']) && !empty($draft['upgrade_target_name'])) {
      $parts[] = 'Room: ' . (string) $draft['upgrade_target_name'];
    }
    $comments = trim((string) ($draft['additional_comments'] ?? ''));
    if ($comments !== '') {
      $parts[] = 'Guest comments:';
      $parts[] = $comments;
    }
    return implode("\n", $parts);
  }
}

if (!function_exists('itm_hotel_booking_portal_room_charges_subtotal')) {
  function itm_hotel_booking_portal_room_charges_subtotal($basePerNight, $checkIn, $checkOut, array $occupancy, $discountPercent, array $draft) {
    if (isset($draft['base_price_per_night']) && $draft['base_price_per_night'] !== '') {
      $basePerNight = (float) $draft['base_price_per_night'];
    }
    $roomTotal = itm_hotel_booking_compute_stay_payment($basePerNight, $checkIn, $checkOut, $occupancy, $discountPercent);
    $nights = itm_hotel_booking_portal_stay_nights($checkIn, $checkOut);
    $extras = 0.0;
    if (($draft['rate_plan'] ?? '') === 'breakfast' && $nights > 0) {
      $extras += itm_hotel_booking_portal_breakfast_supplement_per_night($occupancy) * $nights;
    }
    if (!empty($draft['traveling_with_pet']) && $nights > 0) {
      $extras += itm_hotel_booking_portal_pet_daily_fee() * $nights;
    }
    if (!empty($draft['upgrade_accepted']) && $nights > 0) {
      $upgradePerNight = (float) ($draft['upgrade_price_per_night'] ?? 0);
      if ($upgradePerNight > 0) {
        $extras += $upgradePerNight * $nights;
      }
    }
    return round($roomTotal + $extras, 2);
  }
}

if (!function_exists('itm_hotel_booking_portal_tourist_tax_amount')) {
  function itm_hotel_booking_portal_tourist_tax_amount(array $occupancy, $nights, $perPersonPerNight) {
    $nights = max(1, (int) $nights);
    $per = max(0.0, (float) $perPersonPerNight);
    if ($per <= 0) {
      return 0.0;
    }
    $guests = max(0, (int) ($occupancy['adults'] ?? 0)) + max(0, (int) ($occupancy['children'] ?? 0));
    if ($guests < 1) {
      $guests = 1;
    }
    return round($per * $guests * $nights, 2);
  }
}

if (!function_exists('itm_hotel_booking_portal_checkout_breakdown')) {
  function itm_hotel_booking_portal_checkout_breakdown($basePerNight, $checkIn, $checkOut, array $occupancy, $discountPercent, array $draft, $touristTaxPerPersonPerNight = 0.0) {
    $nights = itm_hotel_booking_portal_stay_nights($checkIn, $checkOut);
    $roomCharges = itm_hotel_booking_portal_room_charges_subtotal($basePerNight, $checkIn, $checkOut, $occupancy, $discountPercent, $draft);
    $taxRate = max(0.0, (float) $touristTaxPerPersonPerNight);
    $touristTax = itm_hotel_booking_portal_tourist_tax_amount($occupancy, $nights, $taxRate);
    return [
      'nights' => $nights,
      'room_charges' => $roomCharges,
      'tourist_tax' => $touristTax,
      'tourist_tax_per_person_per_night' => $taxRate,
      'total' => round($roomCharges + $touristTax, 2),
    ];
  }
}

if (!function_exists('itm_hotel_booking_portal_compute_checkout_total')) {
  function itm_hotel_booking_portal_compute_checkout_total($basePerNight, $checkIn, $checkOut, array $occupancy, $discountPercent, array $draft, $touristTaxPerPersonPerNight = null) {
    if ($touristTaxPerPersonPerNight === null) {
      $touristTaxPerPersonPerNight = (float) ($draft['tourist_tax_per_person_per_night'] ?? 0);
    }
    $breakdown = itm_hotel_booking_portal_checkout_breakdown($basePerNight, $checkIn, $checkOut, $occupancy, $discountPercent, $draft, $touristTaxPerPersonPerNight);
    return $breakdown['total'];
  }
}

if (!function_exists('itm_hotel_booking_portal_room_type_upgrade_offer')) {
  function itm_hotel_booking_portal_room_type_upgrade_offer($conn, $companyId, $roomTypeId) {
    $companyId = (int) $companyId;
    $roomTypeId = (int) $roomTypeId;
    if ($companyId < 1 || $roomTypeId < 1) {
      return null;
    }
    $sql = 'SELECT t.upgrade_to_room_type_id, t.upgrade_price_per_night, t.upgrade_pitch,
            u.id AS target_type_id, u.name AS target_name, u.code AS target_code, u.description AS target_description,
            u.bed_summary AS target_bed_summary, u.room_size_sqm AS target_size_sqm
            FROM booking_rooms_types t
            LEFT JOIN booking_rooms_types u ON u.id = t.upgrade_to_room_type_id AND u.company_id = t.company_id AND u.deleted_at IS NULL AND u.active = 1
            WHERE t.id = ? AND t.company_id = ? AND t.deleted_at IS NULL AND t.active = 1
            AND t.upgrade_to_room_type_id IS NOT NULL AND t.upgrade_price_per_night IS NOT NULL AND t.upgrade_price_per_night > 0
            LIMIT 1';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
      return null;
    }
    mysqli_stmt_bind_param($stmt, 'ii', $roomTypeId, $companyId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    if (!$row || empty($row['target_type_id'])) {
      return null;
    }
    return $row;
  }
}

if (!function_exists('itm_hotel_booking_portal_find_available_room_for_type')) {
  function itm_hotel_booking_portal_find_available_room_for_type($conn, $companyId, $hotelId, $roomTypeId, $checkIn, $checkOut) {
    $companyId = (int) $companyId;
    $hotelId = (int) $hotelId;
    $roomTypeId = (int) $roomTypeId;
    $sql = 'SELECT r.id, r.price_per_night FROM hotel_booking_rooms r
            WHERE r.company_id = ? AND r.hotel_id = ? AND r.room_type_id = ? AND r.deleted_at IS NULL AND r.active = 1
            ORDER BY r.price_per_night ASC, r.id ASC';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
      return null;
    }
    mysqli_stmt_bind_param($stmt, 'iii', $companyId, $hotelId, $roomTypeId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $pick = null;
    while ($res && ($row = mysqli_fetch_assoc($res))) {
      $rid = (int) ($row['id'] ?? 0);
      if ($rid > 0 && !itm_hotel_booking_has_overlap($conn, $companyId, $rid, $checkIn, $checkOut)) {
        $pick = $row;
        break;
      }
    }
    mysqli_stmt_close($stmt);
    return $pick;
  }
}

if (!function_exists('itm_hotel_booking_booking_is_cancelled')) {
  function itm_hotel_booking_booking_is_cancelled($conn, $companyId, array $bookingRow) {
    $segment = itm_hotel_booking_resolve_segment($bookingRow['check_in'], $bookingRow['check_out']);
    $col = $segment . '_status_id';
    $statusId = (int) ($bookingRow[$col] ?? 0);
    if ($statusId < 1) {
      return false;
    }
    $table = itm_hotel_booking_status_table_for_segment($segment);
    $cancelId = itm_hotel_booking_status_id_by_name($conn, $companyId, $table, 'CANCELLED');
    return $cancelId !== null && $statusId === $cancelId;
  }
}

if (!function_exists('itm_hotel_booking_portal_guest_can_cancel_booking')) {
  function itm_hotel_booking_portal_guest_can_cancel_booking($conn, $companyId, array $bookingRow) {
    if (itm_hotel_booking_booking_is_cancelled($conn, $companyId, $bookingRow)) {
      return false;
    }
    $segment = itm_hotel_booking_resolve_segment($bookingRow['check_in'] ?? '', $bookingRow['check_out'] ?? '');
    return $segment === 'future';
  }
}

if (!function_exists('itm_hotel_booking_portal_cancel_booking_for_guest')) {
  /**
   * @return array{ok:bool,error?:string}
   */
  function itm_hotel_booking_portal_cancel_booking_for_guest($conn, $companyId, $reservationId, $lastName) {
    $companyId = (int) $companyId;
    $reservationId = (int) $reservationId;
    $lastName = trim((string) $lastName);
    if ($companyId < 1 || $reservationId < 1 || $lastName === '') {
      return ['ok' => false, 'error' => 'Enter your last name and reservation ID.'];
    }
    $booking = itm_hotel_booking_fetch_for_guest_manage($conn, $companyId, $reservationId, $lastName);
    if (!$booking) {
      return ['ok' => false, 'error' => 'No reservation found. Check your last name and reservation ID.'];
    }
    if (itm_hotel_booking_booking_is_cancelled($conn, $companyId, $booking)) {
      return ['ok' => false, 'error' => 'This reservation is already cancelled.'];
    }
    if (!itm_hotel_booking_portal_guest_can_cancel_booking($conn, $companyId, $booking)) {
      return ['ok' => false, 'error' => 'This reservation can no longer be cancelled online. Please contact the hotel.'];
    }
    $segment = itm_hotel_booking_resolve_segment($booking['check_in'], $booking['check_out']);
    $statusCol = $segment . '_status_id';
    $allowedCols = ['future_status_id', 'present_status_id', 'history_status_id'];
    if (!in_array($statusCol, $allowedCols, true)) {
      return ['ok' => false, 'error' => 'Unable to cancel this reservation.'];
    }
    $table = itm_hotel_booking_status_table_for_segment($segment);
    $cancelId = itm_hotel_booking_status_id_by_name($conn, $companyId, $table, 'CANCELLED');
    if ($cancelId === null || $cancelId < 1) {
      return ['ok' => false, 'error' => 'Cancellation is not available right now. Please contact the hotel.'];
    }
    $sql = 'UPDATE hotel_bookings SET `' . $statusCol . '` = ?, updated_at = NOW() WHERE id = ? AND company_id = ? AND deleted_at IS NULL';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
      return ['ok' => false, 'error' => 'Unable to cancel this reservation.'];
    }
    mysqli_stmt_bind_param($stmt, 'iii', $cancelId, $reservationId, $companyId);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    if (!$ok) {
      return ['ok' => false, 'error' => 'Unable to cancel this reservation.'];
    }
    return ['ok' => true];
  }
}

if (!function_exists('itm_hotel_booking_has_overlap')) {
  function itm_hotel_booking_has_overlap($conn, $companyId, $roomId, $checkIn, $checkOut, $excludeBookingId = 0) {
    $companyId = (int) $companyId;
    $roomId = (int) $roomId;
    $excludeBookingId = (int) $excludeBookingId;
    $sql = 'SELECT id, check_in, check_out, future_status_id, present_status_id, history_status_id FROM hotel_bookings
            WHERE company_id = ? AND room_id = ? AND deleted_at IS NULL AND id <> ?
            AND check_in < ? AND check_out > ?';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
      return true;
    }
    mysqli_stmt_bind_param($stmt, 'iiiss', $companyId, $roomId, $excludeBookingId, $checkOut, $checkIn);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($res && ($row = mysqli_fetch_assoc($res))) {
      if (!itm_hotel_booking_booking_is_cancelled($conn, $companyId, $row)) {
        mysqli_stmt_close($stmt);
        return true;
      }
    }
    mysqli_stmt_close($stmt);
    return false;
  }
}

if (!function_exists('itm_hotel_booking_ensure_customer_for_portal')) {
  function itm_hotel_booking_ensure_customer_for_portal($conn, $companyId, $email, $fullName, $phone = '') {
    $companyId = (int) $companyId;
    $email = trim((string) $email);
    $fullName = trim((string) $fullName);
    $phone = trim((string) $phone);
    if ($companyId < 1 || $email === '' || $fullName === '') {
      return null;
    }
    $stmt = mysqli_prepare($conn, 'SELECT id FROM customers WHERE company_id = ? AND email = ? AND deleted_at IS NULL LIMIT 1');
    if ($stmt) {
      mysqli_stmt_bind_param($stmt, 'is', $companyId, $email);
      mysqli_stmt_execute($stmt);
      $res = mysqli_stmt_get_result($stmt);
      $row = $res ? mysqli_fetch_assoc($res) : null;
      mysqli_stmt_close($stmt);
      if ($row) {
        return (int) $row['id'];
      }
    }
    $statusId = null;
    $st = mysqli_prepare($conn, 'SELECT id FROM customer_statuses WHERE company_id = ? AND name = ? AND deleted_at IS NULL LIMIT 1');
    if ($st) {
      $activeName = 'Active';
      mysqli_stmt_bind_param($st, 'is', $companyId, $activeName);
      mysqli_stmt_execute($st);
      $r = mysqli_stmt_get_result($st);
      $sr = $r ? mysqli_fetch_assoc($r) : null;
      mysqli_stmt_close($st);
      $statusId = $sr ? (int) $sr['id'] : null;
    }
    if ($statusId === null) {
      return null;
    }
    $ins = mysqli_prepare($conn, 'INSERT INTO customers (company_id, name, email, phone, status_id, active, created_at) VALUES (?, ?, ?, ?, ?, 1, NOW())');
    if (!$ins) {
      return null;
    }
    mysqli_stmt_bind_param($ins, 'isssi', $companyId, $fullName, $email, $phone, $statusId);
    if (!mysqli_stmt_execute($ins)) {
      mysqli_stmt_close($ins);
      return null;
    }
    $newId = (int) mysqli_insert_id($conn);
    mysqli_stmt_close($ins);
    return $newId > 0 ? $newId : null;
  }
}

if (!function_exists('itm_hotel_booking_portal_tourist_tax_per_person_from_settings')) {
  /** EUR per guest per night; defaults to 2.00 when settings row or column is missing. */
  function itm_hotel_booking_portal_tourist_tax_per_person_from_settings($settingsRow) {
    if (!is_array($settingsRow)) {
      return 2.0;
    }
    if (!array_key_exists('tourist_tax_per_person_per_night', $settingsRow)) {
      return 2.0;
    }
    $rate = (float) ($settingsRow['tourist_tax_per_person_per_night'] ?? 0);
    return $rate > 0 ? $rate : 2.0;
  }
}

if (!function_exists('itm_hotel_booking_settings_row')) {
  function itm_hotel_booking_settings_row($conn, $companyId) {
    $companyId = (int) $companyId;
    $stmt = mysqli_prepare($conn, 'SELECT * FROM hotel_booking_settings WHERE company_id = ? AND deleted_at IS NULL LIMIT 1');
    if (!$stmt) {
      return null;
    }
    mysqli_stmt_bind_param($stmt, 'i', $companyId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    return $row;
  }
}

if (!function_exists('itm_hotel_booking_planning_bar_palette')) {
  /**
   * Booking bar colors (excludes reserved OOO red and OOS blue).
   *
   * @return string[]
   */
  function itm_hotel_booking_planning_bar_palette() {
    return ['#2da44e', '#6f42c1', '#e36209', '#bf3989', '#0a7c71', '#8b5a2b', '#5a32a3', '#1b7f79', '#a37100'];
  }
}

if (!function_exists('itm_hotel_booking_normalize_booking_color')) {
  function itm_hotel_booking_normalize_booking_color($raw) {
    $hex = trim((string) $raw);
    if (preg_match('/^#[0-9A-Fa-f]{6}$/', $hex)) {
      return strtolower($hex);
    }
    return '';
  }
}

if (!function_exists('itm_hotel_booking_resolve_booking_color')) {
  /**
   * Stored #rrggbb or palette fallback (by booking id / seed).
   */
  function itm_hotel_booking_resolve_booking_color($raw, $fallbackSeed = 0) {
    $normalized = itm_hotel_booking_normalize_booking_color($raw);
    if ($normalized !== '') {
      return $normalized;
    }
    return itm_hotel_booking_planning_booking_bar_color((int) $fallbackSeed);
  }
}

if (!function_exists('itm_hotel_booking_planning_booking_bar_color')) {
  function itm_hotel_booking_planning_booking_bar_color($bookingId, $storedColor = null) {
    $normalized = itm_hotel_booking_normalize_booking_color($storedColor);
    if ($normalized !== '') {
      return $normalized;
    }
    $palette = itm_hotel_booking_planning_bar_palette();
    $idx = (int) $bookingId % count($palette);
    return $palette[$idx];
  }
}

if (!function_exists('itm_hotel_booking_planning_maintenance_bar_color')) {
  function itm_hotel_booking_planning_maintenance_bar_color($statusCode) {
    $code = strtolower(trim((string) $statusCode));
    if ($code === 'ooo') {
      return '#c62828';
    }
    if ($code === 'oos') {
      return '#1565c0';
    }
    return '#6c757d';
  }
}

if (!function_exists('itm_hotel_booking_planning_bar_segment_class')) {
  function itm_hotel_booking_planning_bar_segment_class($dayYmd, $checkIn, $checkOut) {
    $ci = (string) $checkIn;
    $co = (string) $checkOut;
    if ($ci === $co && $dayYmd === $ci) {
      return 'hb-plan-bar-segment-sameday';
    }
    if ($dayYmd === $ci) {
      return 'hb-plan-bar-segment-start';
    }
    if ($dayYmd === $co) {
      return 'hb-plan-bar-segment-end';
    }
    return 'hb-plan-bar-segment-middle';
  }
}

if (!function_exists('itm_hotel_booking_planning_match_bookings_for_day')) {
  function itm_hotel_booking_planning_match_bookings_for_day(array $bookings, $dayYmd) {
    $matched = [];
    foreach ($bookings as $booking) {
      $ci = (string) ($booking['check_in'] ?? '');
      $co = (string) ($booking['check_out'] ?? '');
      if ($dayYmd < $ci || $dayYmd > $co) {
        continue;
      }
      $segment = 'middle';
      if ($ci === $co && $dayYmd === $ci) {
        $segment = 'sameday';
      } elseif ($dayYmd === $ci) {
        $segment = 'start';
      } elseif ($dayYmd === $co) {
        $segment = 'end';
      }
      $matched[] = [
        'booking' => $booking,
        'segment' => $segment,
        'segment_class' => itm_hotel_booking_planning_bar_segment_class($dayYmd, $ci, $co),
      ];
    }
    $order = ['end' => 0, 'start' => 1, 'sameday' => 2, 'middle' => 3];
    usort($matched, function ($a, $b) {
      $oa = $order[$a['segment']] ?? 9;
      $ob = $order[$b['segment']] ?? 9;
      if ($oa !== $ob) {
        return $oa <=> $ob;
      }
      return (int) ($a['booking']['id'] ?? 0) <=> (int) ($b['booking']['id'] ?? 0);
    });
    return $matched;
  }
}

if (!function_exists('itm_hotel_booking_planning_match_maintenance_for_day')) {
  function itm_hotel_booking_planning_match_maintenance_for_day(array $rows, $dayYmd) {
    $matched = [];
    foreach ($rows as $row) {
      $from = (string) ($row['from_date'] ?? '');
      $through = (string) ($row['through_date'] ?? '');
      if ($from === '' || $through === '' || $dayYmd < $from || $dayYmd > $through) {
        continue;
      }
      $matched[] = $row;
    }
    return $matched;
  }
}

if (!function_exists('itm_hotel_booking_planning_grid_rows')) {
  function itm_hotel_booking_planning_grid_rows($conn, $companyId, $anchorDate, $hotelId = 0, $roomTypeId = 0, $floor = '', $days = 14) {
    $companyId = (int) $companyId;
    $hotelId = (int) $hotelId;
    $days = max(7, min(31, (int) $days));
    $anchor = DateTime::createFromFormat('Y-m-d', $anchorDate) ?: new DateTime('today');
    $rangeStart = $anchor->format('Y-m-d');
    $rangeEnd = (clone $anchor)->modify('+' . ($days - 1) . ' days')->format('Y-m-d');

    $roomSql = 'SELECT r.*, h.name AS hotel_name, t.code AS type_code, t.name AS type_name, hk.name AS hk_name, hk.color_hex AS hk_color
                FROM hotel_booking_rooms r
                INNER JOIN hotel_booking_hotels h ON h.id = r.hotel_id AND h.company_id = r.company_id
                INNER JOIN booking_rooms_types t ON t.id = r.room_type_id AND t.company_id = r.company_id
                LEFT JOIN hotel_booking_housekeeping_statuses hk ON hk.id = r.housekeeping_status_id
                WHERE r.company_id = ? AND r.deleted_at IS NULL';
    $types = 'i';
    $params = [$companyId];
    if ($hotelId > 0) {
      $roomSql .= ' AND r.hotel_id = ?';
      $types .= 'i';
      $params[] = $hotelId;
    }
    if ($roomTypeId > 0) {
      $roomSql .= ' AND r.room_type_id = ?';
      $types .= 'i';
      $params[] = $roomTypeId;
    }
    if ($floor !== '') {
      $roomSql .= ' AND r.floor = ?';
      $types .= 's';
      $params[] = $floor;
    }
    $roomSql .= ' ORDER BY r.room_number ASC';
    $stmt = mysqli_prepare($conn, $roomSql);
    if (!$stmt) {
      return ['rooms' => [], 'bookings' => [], 'maintenance' => [], 'range_start' => $rangeStart, 'range_end' => $rangeEnd, 'days' => $days];
    }
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $rooms = [];
    while ($res && ($row = mysqli_fetch_assoc($res))) {
      $rooms[] = $row;
    }
    mysqli_stmt_close($stmt);

    $bookSql = 'SELECT b.*, c.name AS customer_name FROM hotel_bookings b
                INNER JOIN customers c ON c.id = b.customer_id AND c.company_id = b.company_id
                WHERE b.company_id = ? AND b.deleted_at IS NULL AND b.check_in <= ? AND b.check_out >= ?';
    $bstmt = mysqli_prepare($conn, $bookSql);
    $bookings = [];
    if ($bstmt) {
      mysqli_stmt_bind_param($bstmt, 'iss', $companyId, $rangeEnd, $rangeStart);
      mysqli_stmt_execute($bstmt);
      $bres = mysqli_stmt_get_result($bstmt);
      while ($bres && ($brow = mysqli_fetch_assoc($bres))) {
        if (!itm_hotel_booking_booking_is_cancelled($conn, $companyId, $brow)) {
          $bookings[] = $brow;
        }
      }
      mysqli_stmt_close($bstmt);
    }

    $maintSql = 'SELECT m.*, ms.name AS maintenance_status_name, ms.code AS maintenance_status_code
                 FROM hotel_booking_housekeeping_maintenance m
                 LEFT JOIN hotel_booking_housekeeping_maintenance_status ms ON ms.id = m.maintenance_status_id AND ms.company_id = m.company_id
                 WHERE m.company_id = ? AND m.deleted_at IS NULL AND m.active = 1 AND m.from_date <= ? AND m.through_date >= ?';
    $mstmt = mysqli_prepare($conn, $maintSql);
    $maintenance = [];
    if ($mstmt) {
      mysqli_stmt_bind_param($mstmt, 'iss', $companyId, $rangeEnd, $rangeStart);
      mysqli_stmt_execute($mstmt);
      $mres = mysqli_stmt_get_result($mstmt);
      while ($mres && ($mrow = mysqli_fetch_assoc($mres))) {
        $maintenance[] = $mrow;
      }
      mysqli_stmt_close($mstmt);
    }

    return [
      'rooms' => $rooms,
      'bookings' => $bookings,
      'maintenance' => $maintenance,
      'range_start' => $rangeStart,
      'range_end' => $rangeEnd,
      'days' => $days,
    ];
  }
}

if (!function_exists('itm_hotel_booking_apply_segment_status_on_save')) {
  function itm_hotel_booking_apply_segment_status_on_save($conn, $companyId, $checkIn, $checkOut, array $statusIds = []) {
    $segment = itm_hotel_booking_resolve_segment($checkIn, $checkOut);
    $out = ['future_status_id' => null, 'present_status_id' => null, 'history_status_id' => null];
    $col = $segment . '_status_id';
    $id = (int) ($statusIds[$col] ?? 0);
    if ($id < 1) {
      $default = $segment === 'future' ? 'PENDING' : ($segment === 'present' ? 'DUE-IN' : 'CHECKED-OUT');
      $table = itm_hotel_booking_status_table_for_segment($segment);
      $id = (int) itm_hotel_booking_status_id_by_name($conn, $companyId, $table, $default);
    }
    $out[$col] = $id > 0 ? $id : null;
    return $out;
  }
}

if (!function_exists('itm_hotel_booking_planning_valid_ymd')) {
  function itm_hotel_booking_planning_valid_ymd($ymd) {
    $ymd = (string) $ymd;
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd)) {
      return false;
    }
    $dt = DateTime::createFromFormat('Y-m-d', $ymd);
    return $dt && $dt->format('Y-m-d') === $ymd;
  }
}

if (!function_exists('itm_hotel_booking_maintenance_has_overlap')) {
  function itm_hotel_booking_maintenance_has_overlap($conn, $companyId, $roomId, $fromDate, $throughDate, $excludeMaintenanceId = 0) {
    $companyId = (int) $companyId;
    $roomId = (int) $roomId;
    $excludeMaintenanceId = (int) $excludeMaintenanceId;
    $sql = 'SELECT id FROM hotel_booking_housekeeping_maintenance
            WHERE company_id = ? AND room_id = ? AND deleted_at IS NULL AND active = 1 AND id <> ?
            AND from_date <= ? AND through_date >= ? LIMIT 1';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
      return true;
    }
    mysqli_stmt_bind_param($stmt, 'iiiss', $companyId, $roomId, $excludeMaintenanceId, $throughDate, $fromDate);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    return $row !== null;
  }
}

if (!function_exists('itm_hotel_booking_planning_move_booking')) {
  function itm_hotel_booking_planning_move_booking($conn, $companyId, $employeeId, $bookingId, $roomId, $checkIn, $checkOut) {
    $companyId = (int) $companyId;
    $employeeId = (int) $employeeId;
    $bookingId = (int) $bookingId;
    $roomId = (int) $roomId;
    $checkIn = (string) $checkIn;
    $checkOut = (string) $checkOut;
    if ($companyId < 1 || $bookingId < 1 || $roomId < 1) {
      return ['ok' => false, 'error' => 'Invalid request.'];
    }
    if (!itm_hotel_booking_planning_valid_ymd($checkIn) || !itm_hotel_booking_planning_valid_ymd($checkOut)) {
      return ['ok' => false, 'error' => 'Invalid dates.'];
    }
    if ($checkOut <= $checkIn) {
      return ['ok' => false, 'error' => 'Check-out must be after check-in.'];
    }
    $stmt = mysqli_prepare($conn, 'SELECT * FROM hotel_bookings WHERE id = ? AND company_id = ? AND deleted_at IS NULL LIMIT 1');
    if (!$stmt) {
      return ['ok' => false, 'error' => 'Booking not found.'];
    }
    mysqli_stmt_bind_param($stmt, 'ii', $bookingId, $companyId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    if (!$row) {
      return ['ok' => false, 'error' => 'Booking not found.'];
    }
    if (itm_hotel_booking_booking_is_cancelled($conn, $companyId, $row)) {
      return ['ok' => false, 'error' => 'Cancelled booking cannot be moved.'];
    }
    $rstmt = mysqli_prepare($conn, 'SELECT id, price_per_night FROM hotel_booking_rooms WHERE id = ? AND company_id = ? AND deleted_at IS NULL LIMIT 1');
    if (!$rstmt) {
      return ['ok' => false, 'error' => 'Room not found.'];
    }
    mysqli_stmt_bind_param($rstmt, 'ii', $roomId, $companyId);
    mysqli_stmt_execute($rstmt);
    $rres = mysqli_stmt_get_result($rstmt);
    $roomRow = $rres ? mysqli_fetch_assoc($rres) : null;
    mysqli_stmt_close($rstmt);
    if (!$roomRow) {
      return ['ok' => false, 'error' => 'Room not found.'];
    }
    if (itm_hotel_booking_has_overlap($conn, $companyId, $roomId, $checkIn, $checkOut, $bookingId)) {
      return ['ok' => false, 'error' => 'Room overlap for selected dates.'];
    }
    $segment = itm_hotel_booking_resolve_segment($checkIn, $checkOut);
    $defaults = itm_hotel_booking_apply_segment_status_on_save($conn, $companyId, $checkIn, $checkOut);
    $fs = (int) ($row['future_status_id'] ?? 0);
    $ps = (int) ($row['present_status_id'] ?? 0);
    $hs = (int) ($row['history_status_id'] ?? 0);
    if ($segment === 'future') {
      $fs = (int) ($defaults['future_status_id'] ?? $fs);
    } elseif ($segment === 'present') {
      $ps = (int) ($defaults['present_status_id'] ?? $ps);
    } else {
      $hs = (int) ($defaults['history_status_id'] ?? $hs);
    }
    $payment = itm_hotel_booking_compute_payment_amount((float) ($roomRow['price_per_night'] ?? 0), $checkIn, $checkOut);
    $upd = mysqli_prepare(
      $conn,
      'UPDATE hotel_bookings SET room_id = ?, check_in = ?, check_out = ?, payment_amount = ?, future_status_id = NULLIF(?,0), present_status_id = NULLIF(?,0), history_status_id = NULLIF(?,0), updated_by = ?, updated_at = NOW() WHERE id = ? AND company_id = ?'
    );
    if (!$upd) {
      return ['ok' => false, 'error' => 'Update failed.'];
    }
    mysqli_stmt_bind_param($upd, 'issdiiiiii', $roomId, $checkIn, $checkOut, $payment, $fs, $ps, $hs, $employeeId, $bookingId, $companyId);
    if (!mysqli_stmt_execute($upd)) {
      mysqli_stmt_close($upd);
      return ['ok' => false, 'error' => 'Update failed.'];
    }
    mysqli_stmt_close($upd);
    return [
      'ok' => true,
      'booking_id' => $bookingId,
      'room_id' => $roomId,
      'check_in' => $checkIn,
      'check_out' => $checkOut,
      'payment_amount' => $payment,
    ];
  }
}

if (!function_exists('itm_hotel_booking_planning_move_maintenance')) {
  function itm_hotel_booking_planning_move_maintenance($conn, $companyId, $employeeId, $maintenanceId, $roomId, $fromDate, $throughDate) {
    $companyId = (int) $companyId;
    $employeeId = (int) $employeeId;
    $maintenanceId = (int) $maintenanceId;
    $roomId = (int) $roomId;
    $fromDate = (string) $fromDate;
    $throughDate = (string) $throughDate;
    if ($companyId < 1 || $maintenanceId < 1 || $roomId < 1) {
      return ['ok' => false, 'error' => 'Invalid request.'];
    }
    if (!itm_hotel_booking_planning_valid_ymd($fromDate) || !itm_hotel_booking_planning_valid_ymd($throughDate)) {
      return ['ok' => false, 'error' => 'Invalid dates.'];
    }
    if ($throughDate < $fromDate) {
      return ['ok' => false, 'error' => 'Through date must be on or after from date.'];
    }
    $stmt = mysqli_prepare($conn, 'SELECT * FROM hotel_booking_housekeeping_maintenance WHERE id = ? AND company_id = ? AND deleted_at IS NULL LIMIT 1');
    if (!$stmt) {
      return ['ok' => false, 'error' => 'Maintenance record not found.'];
    }
    mysqli_stmt_bind_param($stmt, 'ii', $maintenanceId, $companyId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    if (!$row) {
      return ['ok' => false, 'error' => 'Maintenance record not found.'];
    }
    $rstmt = mysqli_prepare($conn, 'SELECT id FROM hotel_booking_rooms WHERE id = ? AND company_id = ? AND deleted_at IS NULL LIMIT 1');
    if (!$rstmt) {
      return ['ok' => false, 'error' => 'Room not found.'];
    }
    mysqli_stmt_bind_param($rstmt, 'ii', $roomId, $companyId);
    mysqli_stmt_execute($rstmt);
    $rres = mysqli_stmt_get_result($rstmt);
    $roomRow = $rres ? mysqli_fetch_assoc($rres) : null;
    mysqli_stmt_close($rstmt);
    if (!$roomRow) {
      return ['ok' => false, 'error' => 'Room not found.'];
    }
    if (itm_hotel_booking_maintenance_has_overlap($conn, $companyId, $roomId, $fromDate, $throughDate, $maintenanceId)) {
      return ['ok' => false, 'error' => 'Maintenance overlap for selected dates.'];
    }
    $upd = mysqli_prepare(
      $conn,
      'UPDATE hotel_booking_housekeeping_maintenance SET room_id = ?, from_date = ?, through_date = ?, updated_by = ?, updated_at = NOW() WHERE id = ? AND company_id = ?'
    );
    if (!$upd) {
      return ['ok' => false, 'error' => 'Update failed.'];
    }
    mysqli_stmt_bind_param($upd, 'issiii', $roomId, $fromDate, $throughDate, $employeeId, $maintenanceId, $companyId);
    if (!mysqli_stmt_execute($upd)) {
      mysqli_stmt_close($upd);
      return ['ok' => false, 'error' => 'Update failed.'];
    }
    mysqli_stmt_close($upd);
    return [
      'ok' => true,
      'maintenance_id' => $maintenanceId,
      'room_id' => $roomId,
      'from_date' => $fromDate,
      'through_date' => $throughDate,
    ];
  }
}

if (!function_exists('itm_hotel_booking_active_housekeeping_status_ids')) {
  function itm_hotel_booking_active_housekeeping_status_ids($conn, $companyId) {
    $companyId = (int) $companyId;
    $ids = [];
    $stmt = mysqli_prepare($conn, 'SELECT id FROM hotel_booking_housekeeping_statuses WHERE company_id = ? AND deleted_at IS NULL AND active = 1 ORDER BY id ASC');
    if ($stmt) {
      mysqli_stmt_bind_param($stmt, 'i', $companyId);
      mysqli_stmt_execute($stmt);
      $res = mysqli_stmt_get_result($stmt);
      while ($res && ($row = mysqli_fetch_assoc($res))) {
        $ids[] = (int) $row['id'];
      }
      mysqli_stmt_close($stmt);
    }
    return $ids;
  }
}

if (!function_exists('itm_hotel_booking_rotate_room_housekeeping_status')) {
  function itm_hotel_booking_rotate_room_housekeeping_status($conn, $companyId, $roomId, $employeeId) {
    $companyId = (int) $companyId;
    $roomId = (int) $roomId;
    $employeeId = (int) $employeeId;
    if ($companyId < 1 || $roomId < 1) {
      return ['ok' => false, 'error' => 'Invalid room.'];
    }
    $stmt = mysqli_prepare($conn, 'SELECT housekeeping_status_id FROM hotel_booking_rooms WHERE id = ? AND company_id = ? AND deleted_at IS NULL LIMIT 1');
    $currentId = 0;
    if ($stmt) {
      mysqli_stmt_bind_param($stmt, 'ii', $roomId, $companyId);
      mysqli_stmt_execute($stmt);
      $res = mysqli_stmt_get_result($stmt);
      $row = $res ? mysqli_fetch_assoc($res) : null;
      mysqli_stmt_close($stmt);
      $currentId = (int) ($row['housekeeping_status_id'] ?? 0);
    }
    $activeIds = itm_hotel_booking_active_housekeeping_status_ids($conn, $companyId);
    if (empty($activeIds)) {
      return ['ok' => false, 'error' => 'No active HK statuses.'];
    }
    $nextId = $activeIds[0];
    if ($currentId > 0) {
      $idx = array_search($currentId, $activeIds, true);
      if ($idx !== false) {
        $nextId = $activeIds[($idx + 1) % count($activeIds)];
      }
    }
    $upd = mysqli_prepare($conn, 'UPDATE hotel_booking_rooms SET housekeeping_status_id = ?, updated_by = ?, updated_at = NOW() WHERE id = ? AND company_id = ? AND deleted_at IS NULL');
    if (!$upd) {
      return ['ok' => false, 'error' => 'Update failed.'];
    }
    mysqli_stmt_bind_param($upd, 'iiii', $nextId, $employeeId, $roomId, $companyId);
    mysqli_stmt_execute($upd);
    mysqli_stmt_close($upd);
    $hkStmt = mysqli_prepare($conn, 'SELECT name, color_hex FROM hotel_booking_housekeeping_statuses WHERE id = ? AND company_id = ? LIMIT 1');
    $hkName = '';
    $hkColor = '#6c757d';
    if ($hkStmt) {
      mysqli_stmt_bind_param($hkStmt, 'ii', $nextId, $companyId);
      mysqli_stmt_execute($hkStmt);
      $hkRes = mysqli_stmt_get_result($hkStmt);
      $hkRow = $hkRes ? mysqli_fetch_assoc($hkRes) : null;
      mysqli_stmt_close($hkStmt);
      if ($hkRow) {
        $hkName = (string) ($hkRow['name'] ?? '');
        $hkColor = (string) ($hkRow['color_hex'] ?? '#6c757d');
      }
    }
    return ['ok' => true, 'housekeeping_status_id' => $nextId, 'hk_name' => $hkName, 'hk_color' => $hkColor];
  }
}

if (!function_exists('itm_hotel_booking_portal_rate_plan_row_by_id')) {
  function itm_hotel_booking_portal_rate_plan_row_by_id($conn, $companyId, $planId) {
    $companyId = (int) $companyId;
    $planId = (int) $planId;
    if ($companyId < 1 || $planId < 1) {
      return null;
    }
    $stmt = mysqli_prepare($conn, 'SELECT p.*, h.name AS hotel_name FROM hotel_booking_portal_rate_plans p INNER JOIN hotel_booking_hotels h ON h.id = p.hotel_id AND h.company_id = p.company_id WHERE p.id = ? AND p.company_id = ? AND p.deleted_at IS NULL LIMIT 1');
    if (!$stmt) {
      return null;
    }
    mysqli_stmt_bind_param($stmt, 'ii', $planId, $companyId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    return $row ?: null;
  }
}

if (!function_exists('itm_hotel_booking_load_cancellation_policy_html')) {
  function itm_hotel_booking_load_cancellation_policy_html(array $planRow) {
    $html = trim((string) ($planRow['cancellation_policy_html'] ?? ''));
    if ($html !== '') {
      return $html;
    }
    $url = itm_hotel_booking_normalize_cancellation_policy_url($planRow['cancellation_policy_url'] ?? '');
    if ($url === '' || preg_match('#^https?://#i', $url)) {
      return '';
    }
    $path = ROOT_PATH . 'booking/' . ltrim(str_replace('\\', '/', $url), '/');
    if (!is_file($path)) {
      return '';
    }
    $raw = file_get_contents($path);
    if ($raw === false) {
      return '';
    }
    if (preg_match('/<main[^>]*>(.*)<\/main>/is', $raw, $m)) {
      return trim((string) $m[1]);
    }
    return trim($raw);
  }
}

if (!function_exists('itm_hotel_booking_write_cancellation_policy_file')) {
  function itm_hotel_booking_write_cancellation_policy_file($relativeUrl, $planName, $bodyHtml) {
    $url = itm_hotel_booking_normalize_cancellation_policy_url($relativeUrl);
    if ($url === '' || preg_match('#^https?://#i', $url)) {
      return false;
    }
    $safeName = trim((string) $planName);
    if ($safeName === '') {
      $safeName = 'Cancellation policy';
    }
    $bodyHtml = trim((string) $bodyHtml);
    $full = ROOT_PATH . 'booking/' . ltrim(str_replace('\\', '/', $url), '/');
    $dir = dirname($full);
    if (!is_dir($dir)) {
      if (!@mkdir($dir, 0755, true)) {
        return false;
      }
    }
    $doc = '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
      . '<title>' . htmlspecialchars($safeName, ENT_QUOTES, 'UTF-8') . ' — Cancellation policy</title>'
      . '<link rel="stylesheet" href="../css/hotel-booking-modern.css"></head><body class="hb-public">'
      . '<main class="hb-cancellation-policy-page card">' . $bodyHtml
      . '<button type="button" class="hb-btn hb-checkout-skip hb-cancellation-policy-back" title="Back" onclick="history.go(-1);">Back</button>'
      . '</main></body></html>';
    return file_put_contents($full, $doc) !== false;
  }
}
