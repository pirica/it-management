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

if (!function_exists('itm_hotel_booking_photo_folder_for_scope')) {
  function itm_hotel_booking_photo_folder_for_scope($scope) {
    $map = [
      'hotel' => 'hotel_photos',
      'room_type' => 'room_types_photos',
      'room' => 'room_photos',
    ];
    $scope = preg_replace('/[^a-z_]/', '', (string) $scope);
    return $map[$scope] ?? '';
  }
}

if (!function_exists('itm_hotel_booking_photo_storage_dir')) {
  function itm_hotel_booking_photo_storage_dir($hotelId, $folderName) {
    $hotelId = (int) $hotelId;
    $folderName = preg_replace('/[^a-z_]/', '', (string) $folderName);
    if ($hotelId < 1 || $folderName === '') {
      return '';
    }
    return 'booking/images/' . $hotelId . '/' . $folderName;
  }
}

if (!function_exists('itm_hotel_booking_company_hotel_ids')) {
  function itm_hotel_booking_company_hotel_ids($conn, $companyId) {
    $companyId = (int) $companyId;
    if ($companyId < 1) {
      return [];
    }
    $ids = [];
    $stmt = mysqli_prepare($conn, 'SELECT id FROM hotel_booking_hotels WHERE company_id = ? AND deleted_at IS NULL ORDER BY id ASC');
    if (!$stmt) {
      return [];
    }
    mysqli_stmt_bind_param($stmt, 'i', $companyId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($res && ($row = mysqli_fetch_assoc($res))) {
      $ids[] = (int) ($row['id'] ?? 0);
    }
    mysqli_stmt_close($stmt);
    return array_values(array_filter($ids, static function ($id) {
      return $id > 0;
    }));
  }
}

if (!function_exists('itm_hotel_booking_photo_default_hotel_id')) {
  function itm_hotel_booking_photo_default_hotel_id($conn, $companyId) {
    $ids = itm_hotel_booking_company_hotel_ids($conn, $companyId);
    return $ids[0] ?? 0;
  }
}

if (!function_exists('itm_hotel_booking_room_hotel_id')) {
  function itm_hotel_booking_room_hotel_id($conn, $companyId, $roomId) {
    $companyId = (int) $companyId;
    $roomId = (int) $roomId;
    if ($companyId < 1 || $roomId < 1) {
      return 0;
    }
    $stmt = mysqli_prepare($conn, 'SELECT hotel_id FROM hotel_booking_rooms WHERE company_id = ? AND id = ? AND deleted_at IS NULL LIMIT 1');
    if (!$stmt) {
      return 0;
    }
    mysqli_stmt_bind_param($stmt, 'ii', $companyId, $roomId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    return (int) ($row['hotel_id'] ?? 0);
  }
}

if (!function_exists('itm_hotel_booking_photo_resolve_hotel_id')) {
  function itm_hotel_booking_photo_resolve_hotel_id($conn, $companyId, $scope, $parentId) {
    $scope = preg_replace('/[^a-z_]/', '', (string) $scope);
    $parentId = (int) $parentId;
    if ($scope === 'hotel') {
      return $parentId;
    }
    if ($scope === 'room') {
      return itm_hotel_booking_room_hotel_id($conn, (int) $companyId, $parentId);
    }
    if ($scope === 'room_type') {
      return itm_hotel_booking_photo_default_hotel_id($conn, (int) $companyId);
    }
    return 0;
  }
}

if (!function_exists('itm_hotel_booking_photo_storage_abs_dirs_for_scope')) {
  function itm_hotel_booking_photo_storage_abs_dirs_for_scope($conn, $companyId, $scope, $parentId) {
    $folder = itm_hotel_booking_photo_folder_for_scope($scope);
    if ($folder === '') {
      return [];
    }
    $companyId = (int) $companyId;
    $parentId = (int) $parentId;
    $hotelIds = [];
    if ($scope === 'hotel') {
      if ($parentId > 0) {
        $hotelIds = [$parentId];
      }
    } elseif ($scope === 'room_type') {
      $hotelIds = itm_hotel_booking_company_hotel_ids($conn, $companyId);
    } elseif ($scope === 'room') {
      $hotelId = itm_hotel_booking_room_hotel_id($conn, $companyId, $parentId);
      if ($hotelId > 0) {
        $hotelIds = [$hotelId];
      }
    }
    $dirs = [];
    $root = rtrim(ROOT_PATH, '/\\');
    foreach ($hotelIds as $hotelId) {
      $rel = itm_hotel_booking_photo_storage_dir($hotelId, $folder);
      if ($rel === '') {
        continue;
      }
      $dirs[] = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    }
    return $dirs;
  }
}

if (!function_exists('itm_hotel_booking_photo_abs_path')) {
  function itm_hotel_booking_photo_abs_path($hotelId, $folderName, $storedFilename) {
    $storedFilename = basename((string) $storedFilename);
    if ($storedFilename === '') {
      return '';
    }
    $rel = itm_hotel_booking_photo_storage_dir($hotelId, $folderName);
    if ($rel === '') {
      return '';
    }
    return rtrim(ROOT_PATH, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel . '/' . $storedFilename);
  }
}

if (!function_exists('itm_hotel_booking_photo_is_servable')) {
  function itm_hotel_booking_photo_is_servable($hotelId, $folderName, $storedFilename) {
    $abs = itm_hotel_booking_photo_abs_path($hotelId, $folderName, $storedFilename);
    if ($abs === '' || !is_file($abs)) {
      return false;
    }
    return @getimagesize($abs) !== false;
  }
}

if (!function_exists('itm_hotel_booking_portal_default_image_url')) {
  function itm_hotel_booking_portal_default_image_url($basename = 'image_2.jpg') {
    $basename = basename((string) $basename);
    if ($basename === '') {
      $basename = 'image_2.jpg';
    }
    if (defined('APPURL')) {
      return rtrim((string) APPURL, '/') . '/images/' . $basename;
    }
    if (defined('BASE_URL')) {
      return rtrim((string) BASE_URL, '/') . '/booking/images/' . $basename;
    }
    return '/images/' . $basename;
  }
}

if (!function_exists('itm_hotel_booking_portal_photo_urls_from_rows')) {
  function itm_hotel_booking_portal_photo_urls_from_rows($hotelId, $scope, array $photoRows) {
    $hotelId = (int) $hotelId;
    $folder = itm_hotel_booking_photo_folder_for_scope($scope);
    if ($hotelId < 1 || $folder === '') {
      return [];
    }
    $urls = [];
    foreach ($photoRows as $photo) {
      $storedFilename = (string) ($photo['stored_filename'] ?? '');
      if (!itm_hotel_booking_photo_is_servable($hotelId, $folder, $storedFilename)) {
        continue;
      }
      $urls[] = itm_hotel_booking_photo_public_url($hotelId, $folder, $storedFilename);
    }
    return $urls;
  }
}

if (!function_exists('itm_hotel_booking_portal_hotel_photo_urls')) {
  function itm_hotel_booking_portal_hotel_photo_urls($conn, $companyId, $hotelId) {
    $companyId = (int) $companyId;
    $hotelId = (int) $hotelId;
    $rows = itm_hotel_booking_photos_load($conn, $companyId, 'hotel_booking_hotel_photos', 'hotel_id', $hotelId);
    $urls = itm_hotel_booking_portal_photo_urls_from_rows($hotelId, 'hotel', $rows);
    if (empty($urls)) {
      $urls[] = itm_hotel_booking_portal_default_image_url('image_2.jpg');
    }
    return $urls;
  }
}

if (!function_exists('itm_hotel_booking_portal_room_type_photo_urls')) {
  function itm_hotel_booking_portal_room_type_photo_urls($conn, $companyId, $hotelId, $typeId, $typeCode, array $typeDefaultImages = []) {
    $companyId = (int) $companyId;
    $hotelId = (int) $hotelId;
    $typeId = (int) $typeId;
    $typeCode = strtoupper((string) $typeCode);
    // Why: Portal room cards show photos from the shared room type — not per physical room.
    $urls = itm_hotel_booking_portal_photo_urls_from_rows(
      $hotelId,
      'room_type',
      itm_hotel_booking_photos_load($conn, $companyId, 'booking_rooms_type_photos', 'room_type_id', $typeId)
    );
    if (empty($urls)) {
      $fallback = $typeDefaultImages[$typeCode] ?? '/images/room-5.jpg';
      $urls[] = (defined('APPURL') ? rtrim((string) APPURL, '/') . '/' : '') . ltrim((string) $fallback, '/');
    }
    return $urls;
  }
}

if (!function_exists('itm_hotel_booking_photo_public_url')) {
  function itm_hotel_booking_photo_public_url($hotelId, $folderName, $storedFilename) {
    $hotelId = (int) $hotelId;
    $storedFilename = basename((string) $storedFilename);
    $folderName = preg_replace('/[^a-z_]/', '', (string) $folderName);
    if ($storedFilename === '' || $hotelId < 1 || $folderName === '') {
      return '';
    }
    $rel = itm_hotel_booking_photo_storage_dir($hotelId, $folderName);
    if ($rel === '') {
      return '';
    }
    $portalRel = 'images/' . $hotelId . '/' . $folderName . '/' . $storedFilename;
    // Why: guest portal pages live under /booking/ — photo src is APPURL + images/{hotel_id}/…
    if (defined('ITM_HOTEL_BOOKING_PUBLIC_PORTAL') && ITM_HOTEL_BOOKING_PUBLIC_PORTAL && defined('APPURL')) {
      return rtrim((string) APPURL, '/') . '/' . $portalRel;
    }
    if (!function_exists('itm_app_root_public_path_prefix')) {
      require_once ROOT_PATH . 'includes/bootstrap_helpers.php';
    }
    $prefix = itm_app_root_public_path_prefix();
    return ($prefix !== '' ? rtrim($prefix, '/') : '') . '/' . $rel . '/' . $storedFilename;
  }
}

if (!function_exists('itm_hotel_booking_photo_public_url_for_room')) {
  function itm_hotel_booking_photo_public_url_for_room($conn, $companyId, $roomId, $storedFilename) {
    $hotelId = itm_hotel_booking_room_hotel_id($conn, (int) $companyId, (int) $roomId);
    if ($hotelId < 1) {
      return '';
    }
    return itm_hotel_booking_photo_public_url($hotelId, 'room_photos', $storedFilename);
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

if (!function_exists('itm_hotel_booking_photos_for_parent_table')) {
  function itm_hotel_booking_photos_for_parent_table($conn, $companyId, $parentTable, $parentId) {
    $cfg = itm_hotel_booking_photos_config_for_parent_table($parentTable);
    if (!$cfg) {
      return [];
    }
    $parentId = (int) $parentId;
    if ($parentId < 1) {
      return [];
    }
    return itm_hotel_booking_photos_load(
      $conn,
      (int) $companyId,
      $cfg['photo_table'],
      $cfg['parent_column'],
      $parentId
    );
  }
}

if (!function_exists('itm_hotel_booking_photo_cover_url_map_for_parents')) {
  function itm_hotel_booking_photo_cover_url_map_for_parents($conn, $companyId, $parentTable, array $parentIds) {
    $cfg = itm_hotel_booking_photos_config_for_parent_table($parentTable);
    if (!$cfg) {
      return [];
    }
    $companyId = (int) $companyId;
    $parentIds = array_values(array_unique(array_filter(array_map('intval', $parentIds), static function ($id) {
      return $id > 0;
    })));
    if ($companyId < 1 || empty($parentIds)) {
      return [];
    }
    $photoTable = $cfg['photo_table'];
    $parentColumn = $cfg['parent_column'];
    $scope = $cfg['scope'];
    $folder = itm_hotel_booking_photo_folder_for_scope($scope);
    $placeholders = implode(',', array_fill(0, count($parentIds), '?'));
    $sql = 'SELECT `' . str_replace('`', '``', $parentColumn) . '` AS parent_id, stored_filename'
      . ' FROM `' . str_replace('`', '``', $photoTable) . '`'
      . ' WHERE company_id = ? AND `' . str_replace('`', '``', $parentColumn) . '` IN (' . $placeholders . ')'
      . ' AND deleted_at IS NULL'
      . ' ORDER BY is_cover DESC, sort_order ASC, id ASC';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
      return [];
    }
    $types = 'i' . str_repeat('i', count($parentIds));
    $params = array_merge([$companyId], $parentIds);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $map = [];
    while ($res && ($row = mysqli_fetch_assoc($res))) {
      $parentId = (int) ($row['parent_id'] ?? 0);
      if ($parentId < 1 || isset($map[$parentId])) {
        continue;
      }
      $hotelId = itm_hotel_booking_photo_resolve_hotel_id($conn, $companyId, $scope, $parentId);
      if ($hotelId < 1) {
        continue;
      }
      $map[$parentId] = itm_hotel_booking_photo_public_url(
        $hotelId,
        $folder,
        (string) ($row['stored_filename'] ?? '')
      );
    }
    mysqli_stmt_close($stmt);
    return $map;
  }
}

if (!function_exists('itm_hotel_booking_photo_random_stored_filename')) {
  function itm_hotel_booking_photo_random_stored_filename($ext, $absDir = '') {
    $ext = strtolower((string) $ext);
    if ($ext === 'jpeg') {
      $ext = 'jpg';
    }
    if (!in_array($ext, ['jpg', 'png', 'gif', 'webp'], true)) {
      return '';
    }
    $absDir = (string) $absDir;
    for ($attempt = 0; $attempt < 20; $attempt++) {
      $stored = 'hb_' . bin2hex(random_bytes(16)) . '.' . $ext;
      if ($absDir === '' || !is_file($absDir . DIRECTORY_SEPARATOR . $stored)) {
        return $stored;
      }
    }
    return 'hb_' . bin2hex(random_bytes(16)) . '.' . $ext;
  }
}

if (!function_exists('itm_hotel_booking_photo_urls_map_for_parents')) {
  function itm_hotel_booking_photo_urls_map_for_parents($conn, $companyId, $parentTable, array $parentIds) {
    $cfg = itm_hotel_booking_photos_config_for_parent_table($parentTable);
    if (!$cfg) {
      return [];
    }
    $companyId = (int) $companyId;
    $parentIds = array_values(array_unique(array_filter(array_map('intval', $parentIds), static function ($id) {
      return $id > 0;
    })));
    if ($companyId < 1 || empty($parentIds)) {
      return [];
    }
    $photoTable = $cfg['photo_table'];
    $parentColumn = $cfg['parent_column'];
    $scope = $cfg['scope'];
    $folder = itm_hotel_booking_photo_folder_for_scope($scope);
    $placeholders = implode(',', array_fill(0, count($parentIds), '?'));
    $sql = 'SELECT `' . str_replace('`', '``', $parentColumn) . '` AS parent_id, stored_filename'
      . ' FROM `' . str_replace('`', '``', $photoTable) . '`'
      . ' WHERE company_id = ? AND `' . str_replace('`', '``', $parentColumn) . '` IN (' . $placeholders . ')'
      . ' AND deleted_at IS NULL'
      . ' ORDER BY is_cover DESC, sort_order ASC, id ASC';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
      return [];
    }
    $types = 'i' . str_repeat('i', count($parentIds));
    $params = array_merge([$companyId], $parentIds);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $map = [];
    while ($res && ($row = mysqli_fetch_assoc($res))) {
      $parentId = (int) ($row['parent_id'] ?? 0);
      if ($parentId < 1) {
        continue;
      }
      $hotelId = itm_hotel_booking_photo_resolve_hotel_id($conn, $companyId, $scope, $parentId);
      if ($hotelId < 1) {
        continue;
      }
      $url = itm_hotel_booking_photo_public_url(
        $hotelId,
        $folder,
        (string) ($row['stored_filename'] ?? '')
      );
      if ($url === '') {
        continue;
      }
      if (!isset($map[$parentId])) {
        $map[$parentId] = [];
      }
      $map[$parentId][] = $url;
    }
    mysqli_stmt_close($stmt);
    return $map;
  }
}

if (!function_exists('itm_hotel_booking_render_photo_thumbnail_link')) {
  function itm_hotel_booking_render_photo_thumbnail_link($publicUrl, $alt = '', $thumbSize = 60) {
    $publicUrl = trim((string) $publicUrl);
    if ($publicUrl === '') {
      return '<span style="opacity:.45;">—</span>';
    }
    $thumbSize = max(32, (int) $thumbSize);
    $urlEsc = htmlspecialchars($publicUrl, ENT_QUOTES, 'UTF-8');
    $altEsc = htmlspecialchars((string) $alt, ENT_QUOTES, 'UTF-8');
    return '<a href="' . $urlEsc . '" target="_blank" rel="noopener noreferrer" title="Open full size">'
      . '<img src="' . $urlEsc . '" alt="' . $altEsc . '" loading="lazy"'
      . ' style="width:' . $thumbSize . 'px;height:' . $thumbSize . 'px;object-fit:cover;border-radius:4px;display:block;background:#1e1e1e;">'
      . '</a>';
  }
}

if (!function_exists('itm_hotel_booking_photos_upload_was_attempted')) {
  function itm_hotel_booking_photos_upload_was_attempted() {
    if (empty($_FILES['hb_photos']) || !is_array($_FILES['hb_photos']['name'])) {
      return false;
    }
    foreach ($_FILES['hb_photos']['name'] as $name) {
      if (trim((string) $name) !== '') {
        return true;
      }
    }
    return false;
  }
}

if (!function_exists('itm_hotel_booking_photos_handle_upload')) {
  function itm_hotel_booking_photos_handle_upload($conn, $companyId, $parentTable, $parentId, array &$uploadErrors = null) {
    $trackErrors = is_array($uploadErrors);
    if (!$trackErrors) {
      $uploadErrors = [];
    }
    $cfg = itm_hotel_booking_photos_config_for_parent_table($parentTable);
    if (!$cfg) {
      return 0;
    }
    if (!itm_hotel_booking_photos_upload_was_attempted()) {
      return 0;
    }
    $companyId = (int) $companyId;
    $parentId = (int) $parentId;
    if ($companyId < 1 || $parentId < 1) {
      $uploadErrors[] = 'Photo upload requires a saved hotel record (missing company or record id).';
      return 0;
    }
    $scope = $cfg['scope'];
    $photoTable = $cfg['photo_table'];
    $parentColumn = $cfg['parent_column'];
    $absDirs = itm_hotel_booking_photo_storage_abs_dirs_for_scope($conn, $companyId, $scope, $parentId);
    if (empty($absDirs)) {
      $uploadErrors[] = 'Photo upload requires at least one hotel folder for this company.';
      return 0;
    }
    if (!function_exists('itm_ensure_upload_directory')) {
      require_once ROOT_PATH . 'includes/bootstrap_helpers.php';
    }
    foreach ($absDirs as $absDir) {
      if (!itm_ensure_upload_directory($absDir, 'upload')) {
        $uploadErrors[] = 'Could not create the photo upload folder on disk.';
        return 0;
      }
    }
    $primaryAbsDir = $absDirs[0];
    $existingPhotos = itm_hotel_booking_photos_for_parent_table($conn, $companyId, $parentTable, $parentId);
    $parentHasCover = false;
    $sort = 0;
    foreach ($existingPhotos as $existingRow) {
      if (!empty($existingRow['is_cover'])) {
        $parentHasCover = true;
      }
      $sort = max($sort, (int) ($existingRow['sort_order'] ?? 0) + 1);
    }
    $batchCoverAssigned = false;
    $names = $_FILES['hb_photos']['name'];
    $tmp = $_FILES['hb_photos']['tmp_name'];
    $errs = $_FILES['hb_photos']['error'];
    if (!is_array($names)) {
      $names = [$names];
      $tmp = [$tmp];
      $errs = [$errs];
    }
    $inserted = 0;
    $count = count($names);
    for ($i = 0; $i < $count; $i++) {
      $orig = basename((string) ($names[$i] ?? ''));
      if ($orig === '') {
        continue;
      }
      $err = (int) ($errs[$i] ?? UPLOAD_ERR_NO_FILE);
      if ($err !== UPLOAD_ERR_OK) {
        if ($err !== UPLOAD_ERR_NO_FILE) {
          $uploadErrors[] = "Upload failed for '{$orig}' (error code {$err}).";
        }
        continue;
      }
      if (!is_uploaded_file($tmp[$i])) {
        $uploadErrors[] = "Upload rejected for '{$orig}'.";
        continue;
      }
      $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
      if ($ext === 'jfif') {
        $ext = 'jpg';
      }
      if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
        $uploadErrors[] = "File '{$orig}' must be JPG, PNG, GIF, or WEBP.";
        continue;
      }
      $stored = itm_hotel_booking_photo_random_stored_filename($ext, $primaryAbsDir);
      if ($stored === '') {
        $uploadErrors[] = "Could not generate a stored name for '{$orig}'.";
        continue;
      }
      $dest = $primaryAbsDir . DIRECTORY_SEPARATOR . $stored;
      if (!move_uploaded_file($tmp[$i], $dest)) {
        $uploadErrors[] = "Could not save '{$orig}' to disk.";
        continue;
      }
      for ($dirIndex = 1, $dirCount = count($absDirs); $dirIndex < $dirCount; $dirIndex++) {
        $mirrorDest = $absDirs[$dirIndex] . DIRECTORY_SEPARATOR . $stored;
        if (!is_file($mirrorDest)) {
          @copy($dest, $mirrorDest);
        }
      }
      $isCover = (!$parentHasCover && !$batchCoverAssigned) ? 1 : 0;
      if ($isCover) {
        $batchCoverAssigned = true;
      }
      $sql = 'INSERT INTO `' . str_replace('`', '``', $photoTable) . '` (company_id, `' . str_replace('`', '``', $parentColumn) . '`, stored_filename, original_filename, sort_order, is_cover, active, created_at) VALUES (?, ?, ?, ?, ?, ?, 1, NOW())';
      $stmt = mysqli_prepare($conn, $sql);
      if (!$stmt) {
        @unlink($dest);
        $uploadErrors[] = 'Database error while saving photo metadata.';
        continue;
      }
      mysqli_stmt_bind_param($stmt, 'iissii', $companyId, $parentId, $stored, $orig, $sort, $isCover);
      if (!mysqli_stmt_execute($stmt)) {
        @unlink($dest);
        $uploadErrors[] = "Database rejected photo metadata for '{$orig}'.";
        mysqli_stmt_close($stmt);
        continue;
      }
      mysqli_stmt_close($stmt);
      $inserted++;
      $sort++;
    }
    if ($inserted === 0 && empty($uploadErrors)) {
      $uploadErrors[] = 'No photos were saved. Select JPG/PNG/GIF/WEBP files and try again.';
    }
    return $inserted;
  }
}

if (!function_exists('itm_hotel_booking_photo_unlink_stored_file')) {
  function itm_hotel_booking_photo_unlink_stored_file($conn, $companyId, $scope, $parentId, $storedFilename) {
    $storedFilename = basename((string) $storedFilename);
    if ($storedFilename === '') {
      return;
    }
    foreach (itm_hotel_booking_photo_storage_abs_dirs_for_scope($conn, (int) $companyId, $scope, (int) $parentId) as $absDir) {
      $filePath = $absDir . DIRECTORY_SEPARATOR . $storedFilename;
      if (is_file($filePath)) {
        @unlink($filePath);
      }
    }
  }
}

if (!function_exists('itm_hotel_booking_photo_delete_files_for_rows')) {
  function itm_hotel_booking_photo_delete_files_for_rows($conn, $companyId, $scope, array $rows) {
    $companyId = (int) $companyId;
    $scope = preg_replace('/[^a-z_]/', '', (string) $scope);
    foreach ($rows as $row) {
      if (!is_array($row)) {
        continue;
      }
      $parentId = (int) ($row['room_id'] ?? $row['room_type_id'] ?? $row['hotel_id'] ?? 0);
      itm_hotel_booking_photo_unlink_stored_file(
        $conn,
        $companyId,
        $scope,
        $parentId,
        (string) ($row['stored_filename'] ?? '')
      );
    }
  }
}

if (!function_exists('itm_hotel_booking_photo_clear_cover_for_parent')) {
  function itm_hotel_booking_photo_clear_cover_for_parent($conn, $companyId, $photoTable, $parentColumn, $parentId, $exceptPhotoId = 0) {
    $allowed = [
      'hotel_booking_hotel_photos' => 'hotel_id',
      'hotel_booking_room_photos' => 'room_id',
      'booking_rooms_type_photos' => 'room_type_id',
    ];
    if (!isset($allowed[$photoTable]) || $allowed[$photoTable] !== $parentColumn) {
      return;
    }
    $companyId = (int) $companyId;
    $parentId = (int) $parentId;
    $exceptPhotoId = (int) $exceptPhotoId;
    if ($companyId < 1 || $parentId < 1) {
      return;
    }
    $sql = 'UPDATE `' . str_replace('`', '``', $photoTable) . '` SET is_cover = 0 WHERE company_id = ? AND `' . str_replace('`', '``', $parentColumn) . '` = ?';
    if ($exceptPhotoId > 0) {
      $sql .= ' AND id <> ?';
    }
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
      return;
    }
    if ($exceptPhotoId > 0) {
      mysqli_stmt_bind_param($stmt, 'iii', $companyId, $parentId, $exceptPhotoId);
    } else {
      mysqli_stmt_bind_param($stmt, 'ii', $companyId, $parentId);
    }
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
  }
}

if (!function_exists('itm_hotel_booking_room_photos_fetch_rows_for_delete')) {
  function itm_hotel_booking_room_photos_fetch_rows_for_delete($conn, $companyId, $idList = null) {
    $companyId = (int) $companyId;
    if ($companyId < 1) {
      return [];
    }
    if ($idList === null) {
      $sql = 'SELECT id, room_id, stored_filename FROM hotel_booking_room_photos WHERE company_id = ?';
      $stmt = mysqli_prepare($conn, $sql);
      if (!$stmt) {
        return [];
      }
      mysqli_stmt_bind_param($stmt, 'i', $companyId);
    } else {
      $idList = array_values(array_filter(array_map('intval', $idList), static function ($id) {
        return $id > 0;
      }));
      if (empty($idList)) {
        return [];
      }
      $placeholders = implode(',', array_fill(0, count($idList), '?'));
      $sql = 'SELECT id, room_id, stored_filename FROM hotel_booking_room_photos WHERE company_id = ? AND id IN (' . $placeholders . ')';
      $stmt = mysqli_prepare($conn, $sql);
      if (!$stmt) {
        return [];
      }
      $types = 'i' . str_repeat('i', count($idList));
      $params = array_merge([$companyId], $idList);
      mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
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

if (!function_exists('itm_hotel_booking_room_photos_hard_delete')) {
  function itm_hotel_booking_room_photos_hard_delete($conn, $companyId, $idList = null) {
    $companyId = (int) $companyId;
    if ($companyId < 1) {
      return false;
    }
    $rows = itm_hotel_booking_room_photos_fetch_rows_for_delete($conn, $companyId, $idList);
    itm_hotel_booking_photo_delete_files_for_rows($conn, $companyId, 'room', $rows);
    if ($idList === null) {
      $stmt = mysqli_prepare($conn, 'DELETE FROM hotel_booking_room_photos WHERE company_id = ?');
      if (!$stmt) {
        return false;
      }
      mysqli_stmt_bind_param($stmt, 'i', $companyId);
    } else {
      $idList = array_values(array_filter(array_map('intval', $idList), static function ($id) {
        return $id > 0;
      }));
      if (empty($idList)) {
        return false;
      }
      $placeholders = implode(',', array_fill(0, count($idList), '?'));
      $sql = 'DELETE FROM hotel_booking_room_photos WHERE company_id = ? AND id IN (' . $placeholders . ')';
      $stmt = mysqli_prepare($conn, $sql);
      if (!$stmt) {
        return false;
      }
      $types = 'i' . str_repeat('i', count($idList));
      $params = array_merge([$companyId], $idList);
      mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return (bool) $ok;
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

if (!function_exists('itm_hotel_booking_auth2_is_legacy_digits')) {
  /** Legacy bookings store a 4-digit numeric PIN; new bookings use a 12-char complex code. */
  function itm_hotel_booking_auth2_is_legacy_digits($value) {
    return (bool) preg_match('/^\d{4}$/', (string) $value);
  }
}

if (!function_exists('itm_hotel_booking_generate_auth2')) {
  /** Random 12-char guest manage code (upper, lower, digit, symbol), stored on hotel_bookings.auth2. */
  function itm_hotel_booking_generate_auth2() {
    $upper = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $lower = 'abcdefghijklmnopqrstuvwxyz';
    $digits = '0123456789';
    $symbols = '!@#$%&*?';
    $all = $upper . $lower . $digits . $symbols;
    $chars = [
      $upper[random_int(0, strlen($upper) - 1)],
      $lower[random_int(0, strlen($lower) - 1)],
      $digits[random_int(0, strlen($digits) - 1)],
      $symbols[random_int(0, strlen($symbols) - 1)],
    ];
    for ($i = 4; $i < 12; $i++) {
      $chars[] = $all[random_int(0, strlen($all) - 1)];
    }
    shuffle($chars);
    return implode('', $chars);
  }
}

if (!function_exists('itm_hotel_booking_normalize_auth2')) {
  /**
   * Accept legacy 4-digit numeric PINs or new 12-char codes with upper, lower, digit, and symbol.
   */
  function itm_hotel_booking_normalize_auth2($value) {
    $raw = trim((string) $value);
    if ($raw === '') {
      return '';
    }
    // Legacy manage PIN: exactly four digits — do not strip digits from 12-char codes.
    if (preg_match('/^\d{4}$/', $raw)) {
      return $raw;
    }
    if (strlen($raw) !== 12) {
      return '';
    }
    if (!preg_match('/^[A-Za-z0-9!@#$%&*?]{12}$/', $raw)) {
      return '';
    }
    if (!preg_match('/[A-Z]/', $raw) || !preg_match('/[a-z]/', $raw) || !preg_match('/[0-9]/', $raw) || !preg_match('/[!@#$%&*?]/', $raw)) {
      return '';
    }
    return $raw;
  }
}

if (!function_exists('itm_hotel_booking_auth2_matches')) {
  function itm_hotel_booking_auth2_matches($storedAuth2, $inputAuth2) {
    $stored = trim((string) $storedAuth2);
    $input = itm_hotel_booking_normalize_auth2($inputAuth2);
    if ($stored === '' || $input === '') {
      return false;
    }
    if (itm_hotel_booking_auth2_is_legacy_digits($stored)) {
      $storedNorm = itm_hotel_booking_normalize_auth2($stored);
      return $storedNorm !== '' && hash_equals($storedNorm, $input);
    }
    if (strlen($stored) !== 12) {
      return false;
    }
    return hash_equals($stored, $input);
  }
}

if (!function_exists('itm_hotel_booking_fetch_for_guest_manage')) {
  /**
   * Guest manage/cancel lookup: reservation id + last name + auth2 PIN.
   */
  function itm_hotel_booking_fetch_for_guest_manage($conn, $companyId, $reservationId, $lastName, $auth2 = '') {
    $companyId = (int) $companyId;
    $reservationId = (int) $reservationId;
    $auth2 = itm_hotel_booking_normalize_auth2($auth2);
    if ($companyId < 1 || $reservationId < 1 || $auth2 === '') {
      return null;
    }
    $sql = 'SELECT b.*, c.name AS customer_name, c.email AS customer_email, c.phone AS customer_phone,
                   r.room_number, r.name AS room_name, h.name AS hotel_name,
                   h.contact_email AS hotel_contact_email, h.reservations_email AS hotel_reservations_email
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
    if (
      !$row
      || !itm_hotel_booking_customer_last_name_matches($row['customer_name'] ?? '', $lastName)
      || !itm_hotel_booking_auth2_matches($row['auth2'] ?? '', $auth2)
    ) {
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
    // Why: Block cancellation_policy/*.php (and other executables) so policy HTML writes cannot become RCE.
    $pathInfo = pathinfo($url);
    $ext = strtolower((string) ($pathInfo['extension'] ?? ''));
    if (!in_array($ext, ['html', 'htm', 'txt'], true)) {
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
      $ins = mysqli_prepare($conn, 'INSERT INTO hotel_booking_portal_rate_plans (company_id, hotel_id, plan_slot, name, rate_plan_slug, cancellation_policy_url, pay_badge, price_label, cancel_template, plan_discount_percent, plan_surcharge_percent, active, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, NOW())');
      if ($ins) {
        $offer = itm_hotel_booking_portal_rate_plan_offer($slug);
        $payBadge = (string) ($offer['pay_badge'] ?? '');
        $priceLabel = (string) ($offer['price_label'] ?? '');
        $cancelTpl = (string) ($offer['cancel_template'] ?? '');
        $planDisc = (float) ($offer['discount_percent'] ?? 0);
        $planSur = (float) ($offer['surcharge_percent'] ?? 0);
        mysqli_stmt_bind_param($ins, 'iiissssssddi', $companyId, $hotelId, $slot, $name, $slug, $defaultPath, $payBadge, $priceLabel, $cancelTpl, $planDisc, $planSur, $employeeId);
        mysqli_stmt_execute($ins);
        mysqli_stmt_close($ins);
      }
    }
  }
}

if (!function_exists('itm_hotel_booking_portal_rate_plans_admin_rows')) {
  function itm_hotel_booking_portal_rate_plans_admin_rows($conn, $companyId, $hotelId) {
    $rows = [];
    $stmt = mysqli_prepare($conn, 'SELECT id, plan_slot, name, rate_plan_slug, cancellation_policy_url, cancellation_policy_html, pay_badge, price_label, cancel_template, plan_discount_percent, plan_surcharge_percent, free_cancellation_days_before_check_in, active FROM hotel_booking_portal_rate_plans WHERE company_id = ? AND hotel_id = ? AND deleted_at IS NULL ORDER BY plan_slot ASC');
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

if (!function_exists('itm_hotel_booking_portal_rate_plan_slot_in_use')) {
  function itm_hotel_booking_portal_rate_plan_slot_in_use($conn, $companyId, $hotelId, $planSlot, $excludeId = 0) {
    $companyId = (int) $companyId;
    $hotelId = (int) $hotelId;
    $planSlot = (int) $planSlot;
    $excludeId = (int) $excludeId;
    if ($companyId < 1 || $hotelId < 1 || $planSlot < 1) {
      return false;
    }
    if ($excludeId > 0) {
      $stmt = mysqli_prepare($conn, 'SELECT id FROM hotel_booking_portal_rate_plans WHERE company_id = ? AND hotel_id = ? AND plan_slot = ? AND deleted_at IS NULL AND id <> ? LIMIT 1');
      if (!$stmt) {
        return true;
      }
      mysqli_stmt_bind_param($stmt, 'iiii', $companyId, $hotelId, $planSlot, $excludeId);
    } else {
      $stmt = mysqli_prepare($conn, 'SELECT id FROM hotel_booking_portal_rate_plans WHERE company_id = ? AND hotel_id = ? AND plan_slot = ? AND deleted_at IS NULL LIMIT 1');
      if (!$stmt) {
        return true;
      }
      mysqli_stmt_bind_param($stmt, 'iii', $companyId, $hotelId, $planSlot);
    }
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    return $row !== null;
  }
}

if (!function_exists('itm_hotel_booking_portal_rate_plan_next_free_slot')) {
  function itm_hotel_booking_portal_rate_plan_next_free_slot($conn, $companyId, $hotelId) {
    $companyId = (int) $companyId;
    $hotelId = (int) $hotelId;
    if ($companyId < 1 || $hotelId < 1) {
      return 1;
    }
    $used = [];
    $stmt = mysqli_prepare($conn, 'SELECT plan_slot FROM hotel_booking_portal_rate_plans WHERE company_id = ? AND hotel_id = ? AND deleted_at IS NULL');
    if ($stmt) {
      mysqli_stmt_bind_param($stmt, 'ii', $companyId, $hotelId);
      mysqli_stmt_execute($stmt);
      $res = mysqli_stmt_get_result($stmt);
      while ($res && ($row = mysqli_fetch_assoc($res))) {
        $slot = (int) ($row['plan_slot'] ?? 0);
        if ($slot > 0) {
          $used[$slot] = true;
        }
      }
      mysqli_stmt_close($stmt);
    }
    for ($slot = 1; $slot <= 127; $slot++) {
      if (!isset($used[$slot])) {
        return $slot;
      }
    }
    return 0;
  }
}

if (!function_exists('itm_hotel_booking_portal_rate_plan_create')) {
  function itm_hotel_booking_portal_rate_plan_create($conn, $companyId, $employeeId, $hotelId, $planSlot, $name, $slug, $policyUrl, $active) {
    $companyId = (int) $companyId;
    $employeeId = (int) $employeeId;
    $hotelId = (int) $hotelId;
    $planSlot = (int) $planSlot;
    $name = trim((string) $name);
    $slug = strtolower(preg_replace('/[^a-z0-9_-]/', '', (string) $slug));
    $policyUrl = itm_hotel_booking_normalize_cancellation_policy_url((string) $policyUrl);
    $active = (int) ((bool) $active);
    if ($companyId < 1 || $hotelId < 1) {
      return ['ok' => false, 'error' => 'Select a hotel.'];
    }
    if ($planSlot < 1 || $planSlot > 127) {
      return ['ok' => false, 'error' => 'Plan slot must be between 1 and 127.'];
    }
    if ($name === '') {
      return ['ok' => false, 'error' => 'Plan name is required.'];
    }
    if ($slug === '') {
      return ['ok' => false, 'error' => 'Step 2 slug is required.'];
    }
    if (itm_hotel_booking_portal_rate_plan_slot_in_use($conn, $companyId, $hotelId, $planSlot)) {
      return ['ok' => false, 'error' => 'Plan slot already in use for this hotel.'];
    }
    $rstmt = mysqli_prepare($conn, 'SELECT id FROM hotel_booking_hotels WHERE id = ? AND company_id = ? AND deleted_at IS NULL LIMIT 1');
    if (!$rstmt) {
      return ['ok' => false, 'error' => 'Hotel not found.'];
    }
    mysqli_stmt_bind_param($rstmt, 'ii', $hotelId, $companyId);
    mysqli_stmt_execute($rstmt);
    $rres = mysqli_stmt_get_result($rstmt);
    $hotelRow = $rres ? mysqli_fetch_assoc($rres) : null;
    mysqli_stmt_close($rstmt);
    if (!$hotelRow) {
      return ['ok' => false, 'error' => 'Hotel not found.'];
    }
    if ($policyUrl === '') {
      $policyUrl = itm_hotel_booking_normalize_cancellation_policy_url(itm_hotel_booking_portal_default_cancellation_policy_path($slug));
    }
    $ins = mysqli_prepare(
      $conn,
      'INSERT INTO hotel_booking_portal_rate_plans (company_id, hotel_id, plan_slot, name, rate_plan_slug, cancellation_policy_url, pay_badge, price_label, cancel_template, plan_discount_percent, plan_surcharge_percent, active, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
    );
    if (!$ins) {
      return ['ok' => false, 'error' => 'Create failed.'];
    }
    $offer = itm_hotel_booking_portal_rate_plan_offer($slug);
    $payBadge = (string) ($offer['pay_badge'] ?? '');
    $priceLabel = (string) ($offer['price_label'] ?? '');
    $cancelTpl = (string) ($offer['cancel_template'] ?? '');
    $planDisc = (float) ($offer['discount_percent'] ?? 0);
    $planSur = (float) ($offer['surcharge_percent'] ?? 0);
    mysqli_stmt_bind_param($ins, 'iiissssssddii', $companyId, $hotelId, $planSlot, $name, $slug, $policyUrl, $payBadge, $priceLabel, $cancelTpl, $planDisc, $planSur, $active, $employeeId);
    if (!mysqli_stmt_execute($ins)) {
      mysqli_stmt_close($ins);
      return ['ok' => false, 'error' => 'Create failed.'];
    }
    $newId = (int) mysqli_insert_id($conn);
    mysqli_stmt_close($ins);
    if ($newId < 1) {
      return ['ok' => false, 'error' => 'Create failed.'];
    }
    return ['ok' => true, 'id' => $newId];
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

if (!function_exists('itm_hotel_booking_portal_pricing_defaults')) {
  function itm_hotel_booking_portal_pricing_defaults() {
    return [
      'breakfast_adult_price_per_night' => 30.0,
      'breakfast_child_price_per_night' => 20.0,
      'child_nightly_supplement' => 22.0,
      'extra_adult_supplement_percent' => 35.0,
      'pet_daily_fee' => 50.0,
    ];
  }
}

if (!function_exists('itm_hotel_booking_normalize_portal_money_input')) {
  function itm_hotel_booking_normalize_portal_money_input($value) {
    $text = str_replace(',', '.', trim((string) $value));
    if ($text === '' || !is_numeric($text)) {
      return null;
    }
    return max(0.0, round((float) $text, 2));
  }
}

if (!function_exists('itm_hotel_booking_normalize_portal_percent_input')) {
  function itm_hotel_booking_normalize_portal_percent_input($value) {
    $text = str_replace(',', '.', trim((string) $value));
    if ($text === '' || !is_numeric($text)) {
      return null;
    }
    return max(0.0, min(100.0, round((float) $text, 2)));
  }
}

if (!function_exists('itm_hotel_booking_portal_hotel_pricing')) {
  function itm_hotel_booking_portal_hotel_pricing($conn, $companyId, $hotelId) {
    $defaults = itm_hotel_booking_portal_pricing_defaults();
    $companyId = (int) $companyId;
    $hotelId = (int) $hotelId;
    if ($companyId < 1 || $hotelId < 1 || !$conn) {
      return $defaults;
    }
    $stmt = mysqli_prepare(
      $conn,
      'SELECT portal_breakfast_adult_price_per_night, portal_breakfast_child_price_per_night, portal_child_nightly_supplement, portal_extra_adult_supplement_percent, portal_pet_daily_fee
       FROM hotel_booking_hotels
       WHERE id = ? AND company_id = ? AND deleted_at IS NULL
       LIMIT 1'
    );
    if (!$stmt) {
      return $defaults;
    }
    mysqli_stmt_bind_param($stmt, 'ii', $hotelId, $companyId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    if (!$row) {
      return $defaults;
    }
    return [
      'breakfast_adult_price_per_night' => (float) ($row['portal_breakfast_adult_price_per_night'] ?? $defaults['breakfast_adult_price_per_night']),
      'breakfast_child_price_per_night' => (float) ($row['portal_breakfast_child_price_per_night'] ?? $defaults['breakfast_child_price_per_night']),
      'child_nightly_supplement' => (float) ($row['portal_child_nightly_supplement'] ?? $defaults['child_nightly_supplement']),
      'extra_adult_supplement_percent' => (float) ($row['portal_extra_adult_supplement_percent'] ?? $defaults['extra_adult_supplement_percent']),
      'pet_daily_fee' => (float) ($row['portal_pet_daily_fee'] ?? $defaults['pet_daily_fee']),
    ];
  }
}

if (!function_exists('itm_hotel_booking_portal_save_hotel_pricing')) {
  function itm_hotel_booking_portal_save_hotel_pricing($conn, $companyId, $employeeId, $hotelId, array $pricing) {
    $companyId = (int) $companyId;
    $employeeId = (int) $employeeId;
    $hotelId = (int) $hotelId;
    if ($companyId < 1 || $hotelId < 1 || !$conn) {
      return ['ok' => false, 'error' => 'Select a hotel.'];
    }
    $defaults = itm_hotel_booking_portal_pricing_defaults();
    $adult = itm_hotel_booking_normalize_portal_money_input($pricing['breakfast_adult_price_per_night'] ?? null);
    $child = itm_hotel_booking_normalize_portal_money_input($pricing['breakfast_child_price_per_night'] ?? null);
    $childSupp = itm_hotel_booking_normalize_portal_money_input($pricing['child_nightly_supplement'] ?? null);
    $extraPct = itm_hotel_booking_normalize_portal_percent_input($pricing['extra_adult_supplement_percent'] ?? null);
    $petFee = itm_hotel_booking_normalize_portal_money_input($pricing['pet_daily_fee'] ?? null);
    if ($adult === null || $child === null || $childSupp === null || $extraPct === null || $petFee === null) {
      return ['ok' => false, 'error' => 'Enter valid numbers for all portal pricing fields.'];
    }
    $stmt = mysqli_prepare(
      $conn,
      'UPDATE hotel_booking_hotels SET portal_breakfast_adult_price_per_night = ?, portal_breakfast_child_price_per_night = ?, portal_child_nightly_supplement = ?, portal_extra_adult_supplement_percent = ?, portal_pet_daily_fee = ?, updated_by = ?, updated_at = NOW()
       WHERE id = ? AND company_id = ? AND deleted_at IS NULL'
    );
    if (!$stmt) {
      return ['ok' => false, 'error' => 'Save failed.'];
    }
    mysqli_stmt_bind_param($stmt, 'ddddddii', $adult, $child, $childSupp, $extraPct, $petFee, $employeeId, $hotelId, $companyId);
    mysqli_stmt_execute($stmt);
    $ok = mysqli_stmt_affected_rows($stmt) >= 0;
    mysqli_stmt_close($stmt);
    return $ok ? ['ok' => true] : ['ok' => false, 'error' => 'Hotel not found.'];
  }
}

if (!function_exists('itm_hotel_booking_portal_quote_nightly')) {
  function itm_hotel_booking_portal_quote_nightly($basePerNight, array $occupancy, $discountPercent = 0.0, array $pricing = null, $surchargePercent = 0.0) {
    if ($pricing === null || !is_array($pricing)) {
      $pricing = itm_hotel_booking_portal_pricing_defaults();
    }
    $basePerNight = (float) $basePerNight;
    $rooms = max(1, (int) ($occupancy['rooms'] ?? 1));
    $adults = max(1, (int) ($occupancy['adults'] ?? 1));
    $children = max(0, (int) ($occupancy['children'] ?? 0));
    $includedAdults = 2 * $rooms;
    $extraAdults = max(0, $adults - $includedAdults);
    $extraPct = max(0.0, (float) ($pricing['extra_adult_supplement_percent'] ?? 0)) / 100;
    $childSupp = max(0.0, (float) ($pricing['child_nightly_supplement'] ?? 0));
    $nightly = $basePerNight * $rooms;
    $nightly += $extraAdults * ($basePerNight * $extraPct);
    $nightly += $children * $childSupp;
    $discountPercent = max(0.0, min(50.0, (float) $discountPercent));
    if ($discountPercent > 0) {
      $nightly *= (1 - ($discountPercent / 100));
    }
    // Why: Plan surcharge raises after discount (e.g. flexible +2% on BAR).
    $surchargePercent = max(0.0, min(50.0, (float) $surchargePercent));
    if ($surchargePercent > 0) {
      $nightly *= (1 + ($surchargePercent / 100));
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
  function itm_hotel_booking_hotel_calendar_month($conn, $companyId, $hotelId, $year, $month, array $occupancy = null, $touristTaxPerPersonPerNight = null) {
    $companyId = (int) $companyId;
    $hotelId = (int) $hotelId;
    $year = (int) $year;
    $month = (int) $month;
    if ($companyId < 1 || $hotelId < 1 || $month < 1 || $month > 12) {
      return ['year' => $year, 'month' => $month, 'currency_code' => 'EUR', 'days' => [], 'prices_include_tax' => true];
    }
    if (!is_array($occupancy)) {
      $occupancy = itm_hotel_booking_portal_parse_occupancy(['rooms' => 1, 'adults' => 1, 'children' => 0, 'babies' => 0]);
    }
    if ($touristTaxPerPersonPerNight === null) {
      $settingsRow = itm_hotel_booking_settings_row($conn, $companyId);
      $touristTaxPerPersonPerNight = itm_hotel_booking_portal_tourist_tax_per_person_from_settings($settingsRow ?: []);
    } elseif (!isset($settingsRow) || !is_array($settingsRow)) {
      $settingsRow = itm_hotel_booking_settings_row($conn, $companyId) ?: [];
    }
    $touristTaxPerPersonPerNight = max(0.0, (float) $touristTaxPerPersonPerNight);
    $taxPerNight = itm_hotel_booking_portal_tourist_tax_amount($occupancy, 1, $touristTaxPerPersonPerNight);
    $cheapestOffer = itm_hotel_booking_portal_cheapest_rate_offer_for_hotel($conn, $companyId, $hotelId);
    $planDiscount = max(0.0, min(50.0, (float) ($cheapestOffer['discount_percent'] ?? 0)));
    $planSurcharge = max(0.0, min(50.0, (float) ($cheapestOffer['surcharge_percent'] ?? 0)));
    $portalPricing = itm_hotel_booking_portal_hotel_pricing($conn, $companyId, $hotelId);
    $start = sprintf('%04d-%02d-01', $year, $month);
    $daysInMonth = (int) date('t', strtotime($start));
    $rangeEnd = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);
    $today = date('Y-m-d');

    $currency = 'EUR';
    $rooms = [];
    $rstmt = mysqli_prepare($conn, 'SELECT r.id, r.hotel_id, r.room_type_id, COALESCE(bp.price_per_night, 0.00) AS price_per_night, r.is_out_of_order, r.is_out_of_service FROM hotel_booking_rooms r LEFT JOIN hotel_booking_room_type_base_prices bp ON bp.company_id = r.company_id AND bp.hotel_id = r.hotel_id AND bp.room_type_id = r.room_type_id AND bp.deleted_at IS NULL WHERE r.company_id = ? AND r.hotel_id = ? AND r.deleted_at IS NULL AND r.active = 1');
    if ($rstmt) {
      mysqli_stmt_bind_param($rstmt, 'ii', $companyId, $hotelId);
      mysqli_stmt_execute($rstmt);
      $res = mysqli_stmt_get_result($rstmt);
      while ($res && ($row = mysqli_fetch_assoc($res))) {
        $rooms[] = [
          'id' => (int) $row['id'],
          'hotel_id' => (int) $row['hotel_id'],
          'room_type_id' => (int) $row['room_type_id'],
          'price' => (float) $row['price_per_night'],
          'is_out_of_order' => (int) ($row['is_out_of_order'] ?? 0),
          'is_out_of_service' => (int) ($row['is_out_of_service'] ?? 0),
        ];
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

    $roomFreeForNight = static function ($room, $checkIn, $checkOut) use ($bookingsByRoom, $conn, $companyId) {
      return itm_hotel_booking_room_sellable_for_night($conn, $companyId, $room, $checkIn, $bookingsByRoom);
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
        if ($roomFreeForNight($room, $checkIn, $checkOut)) {
          $bar = itm_hotel_booking_resolve_room_type_nightly_bar(
            $conn,
            $companyId,
            $hotelId,
            (int) $room['room_type_id'],
            $checkIn,
            (float) $room['price']
          );
          if ($best === null || $bar < $best) {
            $best = $bar;
          }
        }
      }
      if ($best !== null) {
        // Why: Guest-facing calendar shows cheapest room plan (usually NR discount) + tourist tax.
        $roomQuoted = itm_hotel_booking_portal_quote_nightly((float) $best, $occupancy, $planDiscount, $portalPricing, $planSurcharge);
        $days[$checkIn] = [
          'available' => true,
          'price' => round((float) $roomQuoted + $taxPerNight, 2),
          'bar_excl_tax' => round((float) $best, 2),
          'rate_excl_tax' => round((float) $roomQuoted, 2),
        ];
      } else {
        $days[$checkIn] = ['available' => false];
      }
    }

    return [
      'year' => $year,
      'month' => $month,
      'currency_code' => $currency,
      'days' => $days,
      'prices_include_tax' => true,
      'tourist_tax_per_person_per_night' => $touristTaxPerPersonPerNight,
      'plan_discount_percent' => $planDiscount,
      'plan_surcharge_percent' => $planSurcharge,
      'cheapest_rate_plan_slug' => (string) ($cheapestOffer['slug'] ?? ''),
      'cheapest_rate_label' => (string) ($cheapestOffer['price_label'] ?? 'Best available rate'),
      'calendar_month_advance_days_left' => itm_hotel_booking_portal_calendar_month_advance_days_left_from_settings($settingsRow),
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
  function itm_hotel_booking_compute_stay_payment($basePerNight, $checkIn, $checkOut, array $occupancy, $discountPercent = 0.0, array $pricing = null, $surchargePercent = 0.0) {
    $nightly = itm_hotel_booking_portal_quote_nightly($basePerNight, $occupancy, $discountPercent, $pricing, $surchargePercent);
    return itm_hotel_booking_compute_payment_amount($nightly, $checkIn, $checkOut);
  }
}

if (!function_exists('itm_hotel_booking_portal_breakfast_adult_price')) {
  function itm_hotel_booking_portal_breakfast_adult_price($conn = null, $companyId = 0, $hotelId = 0) {
    if ($conn && (int) $companyId > 0 && (int) $hotelId > 0) {
      $pricing = itm_hotel_booking_portal_hotel_pricing($conn, $companyId, $hotelId);
      return (float) $pricing['breakfast_adult_price_per_night'];
    }
    return (float) itm_hotel_booking_portal_pricing_defaults()['breakfast_adult_price_per_night'];
  }
}

if (!function_exists('itm_hotel_booking_portal_breakfast_child_price')) {
  function itm_hotel_booking_portal_breakfast_child_price($conn = null, $companyId = 0, $hotelId = 0) {
    if ($conn && (int) $companyId > 0 && (int) $hotelId > 0) {
      $pricing = itm_hotel_booking_portal_hotel_pricing($conn, $companyId, $hotelId);
      return (float) $pricing['breakfast_child_price_per_night'];
    }
    return (float) itm_hotel_booking_portal_pricing_defaults()['breakfast_child_price_per_night'];
  }
}

if (!function_exists('itm_hotel_booking_portal_pet_daily_fee')) {
  function itm_hotel_booking_portal_pet_daily_fee($conn = null, $companyId = 0, $hotelId = 0) {
    if ($conn && (int) $companyId > 0 && (int) $hotelId > 0) {
      $pricing = itm_hotel_booking_portal_hotel_pricing($conn, $companyId, $hotelId);
      return (float) $pricing['pet_daily_fee'];
    }
    return (float) itm_hotel_booking_portal_pricing_defaults()['pet_daily_fee'];
  }
}

if (!function_exists('itm_hotel_booking_portal_notes_has_traveling_pet')) {
  /** Whether saved booking notes indicate the guest travels with a pet. */
  function itm_hotel_booking_portal_notes_has_traveling_pet($notesRaw) {
    return (bool) preg_match('/^Traveling with pet:\s*yes\s*$/im', (string) $notesRaw);
  }
}

if (!function_exists('itm_hotel_booking_portal_pet_fee_total_for_stay')) {
  function itm_hotel_booking_portal_pet_fee_total_for_stay($conn, $companyId, $hotelId, $checkIn, $checkOut) {
    $companyId = (int) $companyId;
    $hotelId = (int) $hotelId;
    $nights = itm_hotel_booking_portal_stay_nights($checkIn, $checkOut);
    if ($companyId < 1 || $hotelId < 1 || $nights < 1) {
      return 0.0;
    }
    return round(itm_hotel_booking_portal_pet_daily_fee($conn, $companyId, $hotelId) * $nights, 2);
  }
}

if (!function_exists('itm_hotel_booking_portal_confirmation_pet_fee')) {
  /**
   * Pet surcharge for a saved booking group (parsed from notes + hotel pricing).
   */
  function itm_hotel_booking_portal_confirmation_pet_fee($conn, $companyId, array $bookingRow, $checkIn, $checkOut) {
    $notes = (string) ($bookingRow['notes'] ?? '');
    if (!itm_hotel_booking_portal_notes_has_traveling_pet($notes)) {
      return 0.0;
    }
    $hotelId = (int) ($bookingRow['hotel_id'] ?? 0);
    return itm_hotel_booking_portal_pet_fee_total_for_stay($conn, (int) $companyId, $hotelId, $checkIn, $checkOut);
  }
}

if (!function_exists('itm_hotel_booking_portal_breakfast_supplement_per_night')) {
  function itm_hotel_booking_portal_breakfast_supplement_per_night(array $occupancy, $conn = null, $companyId = 0, $hotelId = 0) {
    $adults = max(0, (int) ($occupancy['adults'] ?? 0));
    $children = max(0, (int) ($occupancy['children'] ?? 0));
    return $adults * itm_hotel_booking_portal_breakfast_adult_price($conn, $companyId, $hotelId)
      + $children * itm_hotel_booking_portal_breakfast_child_price($conn, $companyId, $hotelId);
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
    unset($_SESSION['hotel_booking_portal_room_lines_active']);
  }
}

if (!function_exists('itm_hotel_booking_portal_room_lines_context_fingerprint')) {
  function itm_hotel_booking_portal_room_lines_context_fingerprint($hotelId, $checkIn, $nights, array $occupancy) {
    $occ = itm_hotel_booking_portal_parse_occupancy($occupancy);
    return (int) $hotelId . '|' . (string) $checkIn . '|' . max(1, (int) $nights)
      . '|' . (int) $occ['rooms'] . '|' . (int) $occ['adults'] . '|' . (int) $occ['children'] . '|' . (int) $occ['babies'];
  }
}

if (!function_exists('itm_hotel_booking_portal_room_lines_get_active')) {
  function itm_hotel_booking_portal_room_lines_get_active($contextFingerprint) {
    $bucket = $_SESSION['hotel_booking_portal_room_lines_active'] ?? null;
    if (!is_array($bucket) || ($bucket['context'] ?? '') !== (string) $contextFingerprint) {
      return [];
    }
    $lines = $bucket['lines'] ?? [];
    return is_array($lines) ? $lines : [];
  }
}

if (!function_exists('itm_hotel_booking_portal_room_lines_persist_active')) {
  function itm_hotel_booking_portal_room_lines_persist_active($contextFingerprint, array $lines) {
    $_SESSION['hotel_booking_portal_room_lines_active'] = [
      'context' => (string) $contextFingerprint,
      'lines' => array_values($lines),
    ];
  }
}

if (!function_exists('itm_hotel_booking_portal_room_lines_clear_active')) {
  function itm_hotel_booking_portal_room_lines_clear_active() {
    unset($_SESSION['hotel_booking_portal_room_lines_active']);
  }
}

if (!function_exists('itm_hotel_booking_portal_room_line_normalize')) {
  function itm_hotel_booking_portal_room_line_normalize(array $line) {
    return [
      'room_id' => (int) ($line['room_id'] ?? 0),
      'room_type_id' => (int) ($line['room_type_id'] ?? 0),
      'type_name' => trim((string) ($line['type_name'] ?? '')),
      'type_code' => trim((string) ($line['type_code'] ?? '')),
      'bed_summary' => trim((string) ($line['bed_summary'] ?? '')),
      'base_price_per_night' => (float) ($line['base_price_per_night'] ?? 0),
    ];
  }
}

if (!function_exists('itm_hotel_booking_portal_room_line_label')) {
  function itm_hotel_booking_portal_room_line_label(array $line) {
    $name = trim((string) ($line['type_name'] ?? ''));
    $bed = trim((string) ($line['bed_summary'] ?? ''));
    if ($name === '') {
      return 'Room';
    }
    if ($bed !== '' && stripos($name, $bed) === false) {
      return $name . ' ' . $bed;
    }
    return $name;
  }
}

if (!function_exists('itm_hotel_booking_portal_room_lines_from_draft')) {
  function itm_hotel_booking_portal_room_lines_from_draft(array $draft) {
    $raw = $draft['room_lines'] ?? [];
    if (!is_array($raw) || $raw === []) {
      $roomId = (int) ($draft['room_id'] ?? 0);
      if ($roomId < 1) {
        return [];
      }
      return [itm_hotel_booking_portal_room_line_normalize([
        'room_id' => $roomId,
        'room_type_id' => (int) ($draft['room_type_id'] ?? 0),
        'type_name' => (string) ($draft['type_name'] ?? ''),
        'bed_summary' => (string) ($draft['bed_summary'] ?? ''),
        'base_price_per_night' => (float) ($draft['base_price_per_night'] ?? 0),
      ])];
    }
    $lines = [];
    foreach ($raw as $line) {
      if (!is_array($line)) {
        continue;
      }
      $normalized = itm_hotel_booking_portal_room_line_normalize($line);
      if ($normalized['room_id'] > 0) {
        $lines[] = $normalized;
      }
    }
    return $lines;
  }
}

if (!function_exists('itm_hotel_booking_portal_draft_room_lines_for_display')) {
  /**
   * Reservation UI: use persisted room_lines rows without collapsing multi-room to one fallback line.
   *
   * @return array<int,array>
   */
  function itm_hotel_booking_portal_draft_room_lines_for_display(array $draft) {
    $raw = $draft['room_lines'] ?? null;
    $lines = [];
    if (is_array($raw)) {
      foreach ($raw as $line) {
        if (!is_array($line)) {
          continue;
        }
        $normalized = itm_hotel_booking_portal_room_line_normalize($line);
        if ($normalized['room_id'] > 0) {
          $lines[] = $normalized;
        }
      }
    }
    if ($lines !== []) {
      return $lines;
    }
    $occupancy = itm_hotel_booking_portal_parse_occupancy($draft['occupancy'] ?? []);
    if (max(1, (int) ($occupancy['rooms'] ?? 1)) > 1) {
      return [];
    }
    return itm_hotel_booking_portal_room_lines_from_draft($draft);
  }
}

if (!function_exists('itm_hotel_booking_portal_room_line_pick')) {
  /**
   * @return array{ok:bool,error?:string,lines?:array}
   */
  function itm_hotel_booking_portal_room_line_pick($conn, $companyId, $hotelId, $roomId, $checkIn, $checkOut, array $existingLines) {
    $companyId = (int) $companyId;
    $hotelId = (int) $hotelId;
    $roomId = (int) $roomId;
    if ($companyId < 1 || $hotelId < 1 || $roomId < 1) {
      return ['ok' => false, 'error' => 'Invalid room selection.'];
    }
    $sql = 'SELECT r.id, r.hotel_id, r.room_type_id, COALESCE(bp.price_per_night, 0.00) AS price_per_night,
            r.is_out_of_order, r.is_out_of_service, t.name AS type_name, t.code AS type_code, t.bed_summary
            FROM hotel_booking_rooms r
            INNER JOIN booking_rooms_types t ON t.id = r.room_type_id AND t.company_id = r.company_id
            LEFT JOIN hotel_booking_room_type_base_prices bp ON bp.company_id = r.company_id AND bp.hotel_id = r.hotel_id AND bp.room_type_id = r.room_type_id AND bp.deleted_at IS NULL
            WHERE r.id = ? AND r.company_id = ? AND r.hotel_id = ? AND r.deleted_at IS NULL AND r.active = 1 LIMIT 1';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
      return ['ok' => false, 'error' => 'Room not available.'];
    }
    mysqli_stmt_bind_param($stmt, 'iii', $roomId, $companyId, $hotelId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    if (!$row) {
      return ['ok' => false, 'error' => 'Room not available.'];
    }
    if (!empty($row['is_out_of_order']) || !empty($row['is_out_of_service'])) {
      return ['ok' => false, 'error' => 'That room type is sold out for your dates.'];
    }
    $typeId = (int) ($row['room_type_id'] ?? 0);
    $excludeRoomIds = [];
    foreach ($existingLines as $line) {
      $rid = (int) ($line['room_id'] ?? 0);
      if ($rid > 0) {
        $excludeRoomIds[] = $rid;
      }
    }
    $alloc = itm_hotel_booking_portal_find_available_room_for_type($conn, $companyId, $hotelId, $typeId, $checkIn, $checkOut, $excludeRoomIds);
    // #region agent log
    @file_put_contents(dirname(__DIR__) . '/debug-44bff2.log', json_encode([
      'sessionId' => '44bff2',
      'timestamp' => (int) round(microtime(true) * 1000),
      'location' => 'includes/itm_hotel_booking.php:room_line_pick',
      'message' => 'room line pick availability',
      'data' => [
        'pickRoomId' => $roomId,
        'typeId' => $typeId,
        'excludeRoomIds' => $excludeRoomIds,
        'allocRoomId' => $alloc ? (int) ($alloc['id'] ?? 0) : 0,
        'existingLineCount' => count($existingLines),
      ],
      'hypothesisId' => 'C',
      'runId' => 'verify',
    ]) . "\n", FILE_APPEND);
    // #endregion
    if (!$alloc) {
      return ['ok' => false, 'error' => 'Not enough availability for that room type. Choose another room type.'];
    }
    $roomId = (int) ($alloc['id'] ?? 0);
    if ($roomId > 0) {
      $row['id'] = $roomId;
      $row['price_per_night'] = $alloc['price_per_night'] ?? $row['price_per_night'];
    }
    $baseBar = itm_hotel_booking_portal_check_in_display_bar($conn, $companyId, $hotelId, $typeId, $checkIn, (float) ($row['price_per_night'] ?? 0));
    $line = itm_hotel_booking_portal_room_line_normalize([
      'room_id' => $roomId,
      'room_type_id' => $typeId,
      'type_name' => (string) ($row['type_name'] ?? ''),
      'type_code' => (string) ($row['type_code'] ?? ''),
      'bed_summary' => (string) ($row['bed_summary'] ?? ''),
      'base_price_per_night' => $baseBar,
    ]);
    $lines = $existingLines;
    $lines[] = $line;
    return ['ok' => true, 'lines' => $lines];
  }
}

if (!function_exists('itm_hotel_booking_portal_select_rate_pricing_draft')) {
  /**
   * Step 2 rate-plan totals: attach picked room_lines so multi-room stays price per line, not occupancy×rooms on one BAR.
   *
   * @param array<int,array> $roomLines
   */
  function itm_hotel_booking_portal_select_rate_pricing_draft(array $baseSlice, array $roomLines, $roomsNeeded) {
    $roomsNeeded = max(1, (int) $roomsNeeded);
    $roomLines = is_array($roomLines) ? array_values($roomLines) : [];
    if ($roomsNeeded > 1 && count($roomLines) >= $roomsNeeded) {
      $normalized = [];
      foreach ($roomLines as $line) {
        if (!is_array($line)) {
          continue;
        }
        $normalized[] = itm_hotel_booking_portal_room_line_normalize($line);
      }
      if (count($normalized) >= $roomsNeeded) {
        $baseSlice['room_lines'] = $normalized;
      }
    }
    return $baseSlice;
  }
}

if (!function_exists('itm_hotel_booking_portal_count_available_rooms_for_type')) {
  function itm_hotel_booking_portal_count_available_rooms_for_type($conn, $companyId, $hotelId, $roomTypeId, $checkIn, $checkOut, array $excludeRoomIds = []) {
    $companyId = (int) $companyId;
    $hotelId = (int) $hotelId;
    $roomTypeId = (int) $roomTypeId;
    $excludeRoomIds = array_values(array_unique(array_map('intval', $excludeRoomIds)));
    $sql = 'SELECT r.id, r.is_out_of_order, r.is_out_of_service, COALESCE(bp.price_per_night, 0.00) AS price_per_night
            FROM hotel_booking_rooms r
            LEFT JOIN hotel_booking_room_type_base_prices bp ON bp.company_id = r.company_id AND bp.hotel_id = r.hotel_id AND bp.room_type_id = r.room_type_id AND bp.deleted_at IS NULL
            WHERE r.company_id = ? AND r.hotel_id = ? AND r.room_type_id = ? AND r.deleted_at IS NULL AND r.active = 1';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
      return 0;
    }
    mysqli_stmt_bind_param($stmt, 'iii', $companyId, $hotelId, $roomTypeId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $count = 0;
    while ($res && ($row = mysqli_fetch_assoc($res))) {
      $rid = (int) ($row['id'] ?? 0);
      if ($rid < 1 || in_array($rid, $excludeRoomIds, true)) {
        continue;
      }
      if (!empty($row['is_out_of_order']) || !empty($row['is_out_of_service'])) {
        continue;
      }
      if (!itm_hotel_booking_room_unavailable_for_stay($conn, $companyId, $rid, $checkIn, $checkOut, 0, $row)) {
        $count++;
      }
    }
    mysqli_stmt_close($stmt);
    return $count;
  }
}

if (!function_exists('itm_hotel_booking_portal_allocate_room_id_for_line')) {
  function itm_hotel_booking_portal_allocate_room_id_for_line($conn, $companyId, $hotelId, array $line, $checkIn, $checkOut, array $excludeRoomIds) {
    $preferredId = (int) ($line['room_id'] ?? 0);
    $typeId = (int) ($line['room_type_id'] ?? 0);
    if ($preferredId > 0 && !in_array($preferredId, $excludeRoomIds, true)) {
      $sql = 'SELECT r.id, r.is_out_of_order, r.is_out_of_service, COALESCE(bp.price_per_night, 0.00) AS price_per_night
              FROM hotel_booking_rooms r
              LEFT JOIN hotel_booking_room_type_base_prices bp ON bp.company_id = r.company_id AND bp.hotel_id = r.hotel_id AND bp.room_type_id = r.room_type_id AND bp.deleted_at IS NULL
              WHERE r.id = ? AND r.company_id = ? AND r.hotel_id = ? AND r.deleted_at IS NULL AND r.active = 1 LIMIT 1';
      $stmt = mysqli_prepare($conn, $sql);
      if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'iii', $preferredId, $companyId, $hotelId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        if ($row && empty($row['is_out_of_order']) && empty($row['is_out_of_service'])
          && !itm_hotel_booking_room_unavailable_for_stay($conn, $companyId, $preferredId, $checkIn, $checkOut, 0, $row)) {
          return $preferredId;
        }
      }
    }
    $pick = itm_hotel_booking_portal_find_available_room_for_type($conn, $companyId, $hotelId, $typeId, $checkIn, $checkOut, $excludeRoomIds);
    return $pick ? (int) ($pick['id'] ?? 0) : 0;
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
    $planName = trim((string) ($draft['portal_rate_plan_name'] ?? ''));
    if ($planName !== '') {
      $parts[] = 'Rate: ' . $planName;
    } elseif ($plan === 'breakfast') {
      $parts[] = 'Rate: Breakfast included';
    } elseif ($plan === 'room_only') {
      $parts[] = 'Rate: Best available (room only)';
    }
    if ($plan !== '') {
      $parts[] = 'Rate plan: ' . $plan;
    }
    if (!empty($draft['traveling_with_pet'])) {
      $parts[] = 'Traveling with pet: yes';
    }
    if (!empty($draft['service_animal'])) {
      $parts[] = 'Service animal: yes';
    }
    if (!empty($draft['upgrade_accepted']) && !empty($draft['upgrade_target_name'])) {
      $upgradeTitle = trim((string) $draft['upgrade_target_name']);
      $bedSummary = trim((string) ($draft['upgrade_bed_summary'] ?? ''));
      if ($bedSummary !== '' && stripos($upgradeTitle, $bedSummary) === false) {
        $upgradeTitle .= ' ' . $bedSummary;
      }
      $parts[] = 'Room upgrade: yes';
      $parts[] = 'Room upgrade title: ' . $upgradeTitle;
      $upgradePitch = trim((string) ($draft['upgrade_pitch'] ?? ''));
      if ($upgradePitch === '') {
        $upgradePitch = 'You deserve a little extra. Enjoy a room with added perks.';
      }
      $parts[] = 'Room upgrade pitch: ' . $upgradePitch;
      $upgradePerNight = (float) ($draft['upgrade_price_per_night'] ?? 0);
      if ($upgradePerNight > 0) {
        $parts[] = 'Room upgrade per night: ' . number_format($upgradePerNight, 2, '.', '');
      }
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

if (!function_exists('itm_hotel_booking_portal_parse_booking_notes_meta')) {
  /**
   * Structured customize-step fields persisted in hotel_bookings.notes.
   *
   * @return array{
   *   traveling_with_pet:bool,
   *   service_animal:bool,
   *   guest_comments:string,
   *   room_upgrade:array{accepted:bool,title:string,pitch:string,per_night:float}
   * }
   */
  function itm_hotel_booking_portal_parse_booking_notes_meta($notesRaw) {
    $meta = [
      'traveling_with_pet' => false,
      'service_animal' => false,
      'guest_comments' => '',
      'room_upgrade' => [
        'accepted' => false,
        'title' => '',
        'pitch' => '',
        'per_night' => 0.0,
      ],
    ];
    $notesRaw = trim((string) $notesRaw);
    if ($notesRaw === '') {
      return $meta;
    }
    $text = str_replace(["\r\n", "\r"], "\n", $notesRaw);
    $lines = explode("\n", $text);
    $count = count($lines);
    for ($i = 0; $i < $count; $i++) {
      $line = trim($lines[$i]);
      if ($line === '') {
        continue;
      }
      if (preg_match('/^Traveling with pet:\s*yes\s*$/i', $line)) {
        $meta['traveling_with_pet'] = true;
        continue;
      }
      if (preg_match('/^Service animal:\s*yes\s*$/i', $line)) {
        $meta['service_animal'] = true;
        continue;
      }
      if (preg_match('/^Room upgrade:\s*yes\s*$/i', $line)) {
        $meta['room_upgrade']['accepted'] = true;
        continue;
      }
      if (preg_match('/^Room upgrade title:\s*(.+)$/i', $line, $m)) {
        $meta['room_upgrade']['title'] = trim((string) ($m[1] ?? ''));
        $meta['room_upgrade']['accepted'] = true;
        continue;
      }
      if (preg_match('/^Room upgrade pitch:\s*(.+)$/i', $line, $m)) {
        $meta['room_upgrade']['pitch'] = trim((string) ($m[1] ?? ''));
        continue;
      }
      if (preg_match('/^Room upgrade per night:\s*([0-9]+(?:\.[0-9]+)?)\s*$/i', $line, $m)) {
        $meta['room_upgrade']['per_night'] = (float) ($m[1] ?? 0);
        continue;
      }
      if (preg_match('/^Guest comments:\s*(.*)$/iu', $line, $m)) {
        $body = trim((string) ($m[1] ?? ''));
        if ($body === '') {
          for ($j = $i + 1; $j < $count; $j++) {
            $next = trim($lines[$j]);
            if ($next === '') {
              break;
            }
            if (preg_match('/^(Occupancy:|Rate:|Rate plan:|Traveling with pet:|Service animal:|Room upgrade:|Room:|Multi-room stay —)/i', $next)) {
              break;
            }
            $body .= ($body === '' ? '' : "\n") . $next;
            $i = $j;
          }
        }
        $meta['guest_comments'] = $body;
      }
    }
    if (!$meta['room_upgrade']['accepted'] && $meta['room_upgrade']['title'] === '' && $meta['room_upgrade']['per_night'] <= 0) {
      foreach ($lines as $line) {
        $line = trim($line);
        if (preg_match('/^Room:\s*(.+)$/i', $line, $m) && !preg_match('/^Room upgrade/i', $line)) {
          $meta['room_upgrade']['title'] = trim((string) ($m[1] ?? ''));
          break;
        }
      }
    }
    return $meta;
  }
}

if (!function_exists('itm_hotel_booking_portal_resolve_step4_charge')) {
  /**
   * Why: Step 4 charge must re-read BAR, special-rate discount, and plan discount/surcharge from DB — not session draft money fields.
   *
   * @return array{ok:bool,error?:string,base_per_night?:float,discount_percent?:float,surcharge_percent?:float,portal_rate_plan_id?:int,draft_for_pay?:array,check_in?:string,check_out?:string}
   */
  function itm_hotel_booking_portal_resolve_step4_charge($conn, $companyId, array $room, array $draft, array $occupancy) {
    $companyId = (int) $companyId;
    $checkIn = (string) ($draft['check_in'] ?? '');
    $checkOut = (string) ($draft['check_out'] ?? '');
    if ($companyId < 1 || $checkIn === '' || $checkOut === '' || $checkOut <= $checkIn) {
      return ['ok' => false, 'error' => 'Checkout session expired. Please start again.'];
    }
    $hotelId = (int) ($room['hotel_id'] ?? $draft['hotel_id'] ?? 0);
    $roomTypeId = (int) ($room['room_type_id'] ?? $draft['room_type_id'] ?? 0);
    $planId = (int) ($draft['portal_rate_plan_id'] ?? 0);
    $planRow = $planId > 0 ? itm_hotel_booking_portal_rate_plan_row_by_id($conn, $companyId, $planId) : null;
    if (!$planRow || (int) ($planRow['hotel_id'] ?? 0) !== $hotelId || empty($planRow['active'])) {
      return ['ok' => false, 'error' => 'Selected rate is no longer available. Please choose a rate again.'];
    }
    $planSlug = strtolower(preg_replace('/[^a-z0-9_-]/', '', (string) ($planRow['rate_plan_slug'] ?? '')));
    if ($planSlug === '') {
      return ['ok' => false, 'error' => 'Selected rate is no longer available. Please choose a rate again.'];
    }
    $rateSlug = trim((string) ($draft['resolved_rate_slug'] ?? ''));
    if ($rateSlug === '') {
      $rateSlug = itm_hotel_booking_portal_resolved_rate_slug($occupancy);
    }
    $specialDiscount = itm_hotel_booking_special_rate_discount($conn, $companyId, $hotelId, $rateSlug);
    $discountPercent = itm_hotel_booking_portal_rate_plan_effective_discount($specialDiscount, $planSlug, $planRow);
    $surchargePercent = itm_hotel_booking_portal_rate_plan_effective_surcharge($planSlug, $planRow);
    $defaultBar = (float) ($room['price_per_night'] ?? 0);
    if ($defaultBar <= 0 && $roomTypeId > 0) {
      $defaultBar = itm_hotel_booking_get_room_type_base_price($conn, $companyId, $hotelId, $roomTypeId);
    }
    $basePerNight = itm_hotel_booking_portal_check_in_display_bar($conn, $companyId, $hotelId, $roomTypeId, $checkIn, $defaultBar);
    $draftForPay = [
      'company_id' => $companyId,
      'hotel_id' => $hotelId,
      'room_type_id' => $roomTypeId,
      'rate_plan' => $planSlug,
      'portal_rate_plan_id' => $planId,
      'portal_rate_plan_name' => (string) ($planRow['name'] ?? ''),
      'traveling_with_pet' => !empty($draft['traveling_with_pet']) ? 1 : 0,
      'service_animal' => !empty($draft['service_animal']) ? 1 : 0,
      'additional_comments' => (string) ($draft['additional_comments'] ?? ''),
      'resolved_rate_slug' => $rateSlug,
      'surcharge_percent' => $surchargePercent,
      'room_lines' => itm_hotel_booking_portal_room_lines_from_draft($draft),
      'room_id' => (int) ($draft['room_id'] ?? 0),
    ];
    if (!empty($draft['upgrade_accepted'])) {
      $origTypeId = (int) ($draft['room_type_id'] ?? 0);
      $upgradeOffer = $origTypeId > 0 ? itm_hotel_booking_portal_room_type_upgrade_offer($conn, $companyId, $origTypeId) : null;
      if ($upgradeOffer) {
        $draftForPay['upgrade_accepted'] = 1;
        $draftForPay['upgrade_price_per_night'] = (float) ($draft['upgrade_price_per_night'] ?? $upgradeOffer['upgrade_price_per_night'] ?? 0);
        $draftForPay['upgrade_target_name'] = (string) ($draft['upgrade_target_name'] ?? $upgradeOffer['target_name'] ?? '');
        $draftForPay['upgrade_bed_summary'] = (string) ($draft['upgrade_bed_summary'] ?? $upgradeOffer['target_bed_summary'] ?? '');
        $draftForPay['upgrade_pitch'] = trim((string) ($draft['upgrade_pitch'] ?? $upgradeOffer['upgrade_pitch'] ?? ''));
      } elseif (!empty($draft['upgrade_target_name'])) {
        $draftForPay['upgrade_accepted'] = 1;
        $draftForPay['upgrade_price_per_night'] = (float) ($draft['upgrade_price_per_night'] ?? 0);
        $draftForPay['upgrade_target_name'] = (string) $draft['upgrade_target_name'];
        $draftForPay['upgrade_bed_summary'] = (string) ($draft['upgrade_bed_summary'] ?? '');
        $draftForPay['upgrade_pitch'] = trim((string) ($draft['upgrade_pitch'] ?? ''));
      }
    }
    return [
      'ok' => true,
      'base_per_night' => $basePerNight,
      'discount_percent' => $discountPercent,
      'surcharge_percent' => $surchargePercent,
      'portal_rate_plan_id' => $planId,
      'draft_for_pay' => $draftForPay,
      'check_in' => $checkIn,
      'check_out' => $checkOut,
    ];
  }
}

if (!function_exists('itm_hotel_booking_portal_room_charges_subtotal')) {
  function itm_hotel_booking_portal_room_charges_subtotal($basePerNight, $checkIn, $checkOut, array $occupancy, $discountPercent, array $draft, $conn = null, $companyId = 0, $trustDraftPricing = true) {
    $companyId = (int) ($draft['company_id'] ?? $companyId);
    $hotelId = (int) ($draft['hotel_id'] ?? 0);
    $roomTypeId = (int) ($draft['room_type_id'] ?? 0);
    $pricing = ($conn && $companyId > 0 && $hotelId > 0)
      ? itm_hotel_booking_portal_hotel_pricing($conn, $companyId, $hotelId)
      : itm_hotel_booking_portal_pricing_defaults();
    $surchargePercent = max(0.0, min(50.0, (float) ($draft['surcharge_percent'] ?? 0)));
    $roomLines = itm_hotel_booking_portal_room_lines_from_draft($draft);
    $lineTypeIds = [];
    foreach ($roomLines as $line) {
      $lineTypeIds[(int) ($line['room_type_id'] ?? 0)] = true;
    }
    unset($lineTypeIds[0]);
    if (count($roomLines) > 1 && $conn && $companyId > 0 && $hotelId > 0) {
      $roomTotal = 0.0;
      $lineCount = count($roomLines);
      foreach ($roomLines as $idx => $line) {
        $lineOcc = itm_hotel_booking_portal_split_occupancy_for_room_line($occupancy, (int) $idx, $lineCount);
        $base = (float) ($line['base_price_per_night'] ?? 0);
        if ($base <= 0) {
          $base = itm_hotel_booking_portal_check_in_display_bar($conn, $companyId, $hotelId, (int) ($line['room_type_id'] ?? 0), $checkIn, 0);
        }
        $roomTotal += itm_hotel_booking_compute_stay_payment_dated_rates(
          $conn,
          $companyId,
          $hotelId,
          (int) ($line['room_type_id'] ?? 0),
          $base,
          $checkIn,
          $checkOut,
          $lineOcc,
          $discountPercent,
          $pricing,
          $surchargePercent
        );
      }
      $nights = itm_hotel_booking_portal_stay_nights($checkIn, $checkOut);
      $extras = 0.0;
      if (($draft['rate_plan'] ?? '') === 'breakfast' && $nights > 0) {
        $extras += itm_hotel_booking_portal_breakfast_supplement_per_night($occupancy, $conn, $companyId, $hotelId) * $nights;
      }
      if (!empty($draft['traveling_with_pet']) && $nights > 0) {
        $extras += itm_hotel_booking_portal_pet_daily_fee($conn, $companyId, $hotelId) * $nights;
      }
      if (!empty($draft['upgrade_accepted']) && $nights > 0) {
        $upgradePerNight = (float) ($draft['upgrade_price_per_night'] ?? 0);
        if ($upgradePerNight > 0) {
          $extras += $upgradePerNight * $nights;
        }
      }
      return round($roomTotal + $extras, 2);
    }
    if ($trustDraftPricing && isset($draft['base_price_per_night']) && $draft['base_price_per_night'] !== '') {
      $basePerNight = (float) $draft['base_price_per_night'];
    }
    if ($conn && $companyId > 0 && $hotelId > 0 && $roomTypeId > 0) {
      $roomTotal = itm_hotel_booking_compute_stay_payment_dated_rates($conn, $companyId, $hotelId, $roomTypeId, $basePerNight, $checkIn, $checkOut, $occupancy, $discountPercent, $pricing, $surchargePercent);
    } else {
      $roomTotal = itm_hotel_booking_compute_stay_payment($basePerNight, $checkIn, $checkOut, $occupancy, $discountPercent, $pricing, $surchargePercent);
    }
    $nights = itm_hotel_booking_portal_stay_nights($checkIn, $checkOut);
    $extras = 0.0;
    if (($draft['rate_plan'] ?? '') === 'breakfast' && $nights > 0) {
      $extras += itm_hotel_booking_portal_breakfast_supplement_per_night($occupancy, $conn, $companyId, $hotelId) * $nights;
    }
    if (!empty($draft['traveling_with_pet']) && $nights > 0) {
      $extras += itm_hotel_booking_portal_pet_daily_fee($conn, $companyId, $hotelId) * $nights;
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

if (!function_exists('itm_hotel_booking_portal_room_line_stay_charges')) {
  /**
   * Per-room stay charges (room rate only — no breakfast/pet/upgrade extras).
   *
   * @return array<int,float>
   */
  function itm_hotel_booking_portal_room_line_stay_charges($basePerNight, $checkIn, $checkOut, array $occupancy, $discountPercent, array $draft, $conn = null, $companyId = 0) {
    $roomLines = itm_hotel_booking_portal_room_lines_from_draft($draft);
    $lineCount = count($roomLines);
    if ($lineCount < 2) {
      return [];
    }
    $companyId = (int) ($draft['company_id'] ?? $companyId);
    $hotelId = (int) ($draft['hotel_id'] ?? 0);
    $pricing = ($conn && $companyId > 0 && $hotelId > 0)
      ? itm_hotel_booking_portal_hotel_pricing($conn, $companyId, $hotelId)
      : itm_hotel_booking_portal_pricing_defaults();
    $surchargePercent = max(0.0, min(50.0, (float) ($draft['surcharge_percent'] ?? 0)));
    $amounts = [];
    foreach ($roomLines as $idx => $line) {
      $lineOcc = itm_hotel_booking_portal_split_occupancy_for_room_line($occupancy, (int) $idx, $lineCount);
      $base = (float) ($line['base_price_per_night'] ?? 0);
      if ($base <= 0 && $conn && $companyId > 0 && $hotelId > 0) {
        $base = itm_hotel_booking_portal_check_in_display_bar($conn, $companyId, $hotelId, (int) ($line['room_type_id'] ?? 0), $checkIn, 0);
      }
      if ($conn && $companyId > 0 && $hotelId > 0 && (int) ($line['room_type_id'] ?? 0) > 0) {
        $amounts[] = itm_hotel_booking_compute_stay_payment_dated_rates(
          $conn,
          $companyId,
          $hotelId,
          (int) ($line['room_type_id'] ?? 0),
          $base,
          $checkIn,
          $checkOut,
          $lineOcc,
          $discountPercent,
          $pricing,
          $surchargePercent
        );
      } else {
        $amounts[] = itm_hotel_booking_compute_stay_payment($base, $checkIn, $checkOut, $lineOcc, $discountPercent, $pricing, $surchargePercent);
      }
    }
    return array_map(static function ($amount) {
      return round((float) $amount, 2);
    }, $amounts);
  }
}

if (!function_exists('itm_hotel_booking_portal_multi_room_payment_shares')) {
  /**
   * Split checkout total across room lines using each line's room-rate weight (pet/tax/extras allocated proportionally).
   *
   * @param array<int,float> $roomLineRoomOnlyAmounts
   * @return array<int,float>
   */
  function itm_hotel_booking_portal_multi_room_payment_shares($totalAmount, array $roomLineRoomOnlyAmounts) {
    $lineCount = count($roomLineRoomOnlyAmounts);
    $totalAmount = round((float) $totalAmount, 2);
    if ($lineCount < 2) {
      return [$totalAmount];
    }
    $roomSubtotal = round(array_sum($roomLineRoomOnlyAmounts), 2);
    if ($roomSubtotal <= 0) {
      $shares = [];
      $running = 0.0;
      $share = round($totalAmount / $lineCount, 2);
      for ($i = 0; $i < $lineCount; $i++) {
        if ($i === $lineCount - 1) {
          $shares[] = round($totalAmount - $running, 2);
        } else {
          $shares[] = $share;
          $running += $share;
        }
      }
      return $shares;
    }
    $extras = round($totalAmount - $roomSubtotal, 2);
    $shares = [];
    $running = 0.0;
    $idx = 0;
    foreach ($roomLineRoomOnlyAmounts as $roomAmt) {
      $roomAmt = round((float) $roomAmt, 2);
      if ($idx === $lineCount - 1) {
        $shares[] = round($totalAmount - $running, 2);
      } else {
        $weight = $roomAmt / $roomSubtotal;
        $lineShare = round($roomAmt + ($extras * $weight), 2);
        $shares[] = $lineShare;
        $running += $lineShare;
      }
      $idx++;
    }
    return $shares;
  }
}

if (!function_exists('itm_hotel_booking_portal_resolve_multi_room_insert_payment_shares')) {
  /**
   * @return array<int,float>
   */
  function itm_hotel_booking_portal_resolve_multi_room_insert_payment_shares($conn, $companyId, $totalAmount, array $draft, $checkIn, $checkOut, array $occupancy) {
    $roomLines = itm_hotel_booking_portal_room_lines_from_draft($draft);
    $lineCount = count($roomLines);
    if ($lineCount < 2) {
      return [round((float) $totalAmount, 2)];
    }
    $occupancy = itm_hotel_booking_portal_parse_occupancy($occupancy);
    $discountPercent = (float) ($draft['discount_percent'] ?? 0);
    $basePerNight = (float) ($draft['base_price_per_night'] ?? 0);
    $roomOnly = itm_hotel_booking_portal_room_line_stay_charges(
      $basePerNight,
      $checkIn,
      $checkOut,
      $occupancy,
      $discountPercent,
      $draft,
      $conn,
      (int) $companyId
    );
    if (count($roomOnly) !== $lineCount) {
      return itm_hotel_booking_portal_multi_room_payment_shares($totalAmount, array_fill(0, $lineCount, round((float) $totalAmount / $lineCount, 2)));
    }
    return itm_hotel_booking_portal_multi_room_payment_shares($totalAmount, $roomOnly);
  }
}

if (!function_exists('itm_hotel_booking_portal_confirmation_group_room_display_amounts')) {
  /**
   * Per-room room-rate amounts for confirmation UI (excludes pet/tax — same as Step 3 sidebar).
   *
   * @param array<int,array> $groupRows
   * @return array<int,float>
   */
  function itm_hotel_booking_portal_confirmation_group_room_display_amounts($conn, $companyId, array $groupRows, array $occupancy) {
    if (count($groupRows) < 2) {
      return [];
    }
    $companyId = (int) $companyId;
    $primary = $groupRows[0];
    $checkIn = (string) ($primary['check_in'] ?? '');
    $checkOut = (string) ($primary['check_out'] ?? '');
    $hotelId = (int) ($primary['hotel_id'] ?? 0);
    if ($companyId < 1 || $hotelId < 1 || $checkIn === '' || $checkOut === '') {
      return [];
    }
    $occupancy = itm_hotel_booking_portal_parse_occupancy($occupancy);
    $roomLines = [];
    foreach ($groupRows as $row) {
      $roomLines[] = [
        'room_id' => (int) ($row['room_id'] ?? 0),
        'room_type_id' => (int) ($row['room_type_id'] ?? 0),
        'base_price_per_night' => (float) ($row['price_per_night'] ?? 0),
        'type_name' => (string) ($row['type_name'] ?? ''),
        'bed_summary' => (string) ($row['bed_summary'] ?? ''),
      ];
    }
    $planId = (int) ($primary['portal_rate_plan_id'] ?? 0);
    $planRow = $planId > 0 ? itm_hotel_booking_portal_rate_plan_row_by_id($conn, $companyId, $planId) : null;
    $planSlug = strtolower((string) ($primary['portal_rate_plan_slug'] ?? 'room_only'));
    $rateSlug = itm_hotel_booking_portal_resolved_rate_slug($occupancy);
    $specialDiscount = itm_hotel_booking_special_rate_discount($conn, $companyId, $hotelId, $rateSlug);
    $discountPercent = $planRow
      ? itm_hotel_booking_portal_rate_plan_effective_discount($specialDiscount, $planSlug, $planRow)
      : 0.0;
    $draft = [
      'company_id' => $companyId,
      'hotel_id' => $hotelId,
      'room_lines' => $roomLines,
      'surcharge_percent' => $planRow ? itm_hotel_booking_portal_rate_plan_effective_surcharge($planSlug, $planRow) : 0.0,
    ];
    $basePerNight = (float) ($primary['price_per_night'] ?? 0);
    return itm_hotel_booking_portal_room_line_stay_charges(
      $basePerNight,
      $checkIn,
      $checkOut,
      $occupancy,
      $discountPercent,
      $draft,
      $conn,
      $companyId
    );
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

if (!function_exists('itm_hotel_booking_portal_rate_plan_offer')) {
  /**
   * Commercial rules for portal Step 2 rate-plan cards (pay badge, cancel copy, plan discount).
   * DB row fields override slug defaults when non-empty.
   *
   * @param array|null $planRow optional hotel_booking_portal_rate_plans row
   * @return array{discount_percent:float,surcharge_percent:float,pay_badge:string,cancel_template:string,price_label:string,free_cancellation_days:?int}
   */
  function itm_hotel_booking_portal_rate_plan_offer($slug, $planRow = null) {
    $slug = strtolower(preg_replace('/[^a-z0-9_-]/', '', (string) $slug));
    if ($slug === 'breakfast') {
      $offer = [
        'discount_percent' => 0.0,
        'surcharge_percent' => 0.0,
        'pay_badge' => 'Pay when you stay',
        'cancel_template' => 'Change or cancel by {date}.',
        'price_label' => 'With breakfast',
        'free_cancellation_days' => null,
      ];
    } elseif ($slug === 'flexible') {
      $offer = [
        'discount_percent' => 0.0,
        'surcharge_percent' => 0.0,
        'pay_badge' => 'Pay when you stay',
        'cancel_template' => 'Free cancellation until {date}.',
        'price_label' => 'Flexible rate',
        'free_cancellation_days' => null,
      ];
    } elseif ($slug === 'non_refundable') {
      $offer = [
        'discount_percent' => 10.0,
        'surcharge_percent' => 0.0,
        'pay_badge' => 'Non-refundable',
        'cancel_template' => 'Non-refundable. No free cancellation after booking.',
        'price_label' => 'Non-refundable rate',
        'free_cancellation_days' => null,
      ];
    } else {
      $offer = [
        'discount_percent' => 0.0,
        'surcharge_percent' => 0.0,
        'pay_badge' => 'Pay when you stay',
        'cancel_template' => 'Change or cancel by {date}.',
        'price_label' => 'Best available rate',
        'free_cancellation_days' => null,
      ];
    }
    if (is_array($planRow)) {
      $badge = trim((string) ($planRow['pay_badge'] ?? ''));
      $label = trim((string) ($planRow['price_label'] ?? ''));
      $tpl = trim((string) ($planRow['cancel_template'] ?? ''));
      if ($badge !== '') {
        $offer['pay_badge'] = $badge;
      }
      if ($label !== '') {
        $offer['price_label'] = $label;
      }
      if ($tpl !== '') {
        $offer['cancel_template'] = $tpl;
      }
      if (array_key_exists('plan_discount_percent', $planRow) && $planRow['plan_discount_percent'] !== null && $planRow['plan_discount_percent'] !== '') {
        $offer['discount_percent'] = max(0.0, min(50.0, (float) $planRow['plan_discount_percent']));
      }
      if (array_key_exists('plan_surcharge_percent', $planRow) && $planRow['plan_surcharge_percent'] !== null && $planRow['plan_surcharge_percent'] !== '') {
        $offer['surcharge_percent'] = max(0.0, min(50.0, (float) $planRow['plan_surcharge_percent']));
      }
      if (array_key_exists('free_cancellation_days_before_check_in', $planRow) && $planRow['free_cancellation_days_before_check_in'] !== null && $planRow['free_cancellation_days_before_check_in'] !== '') {
        $offer['free_cancellation_days'] = max(0, min(365, (int) $planRow['free_cancellation_days_before_check_in']));
      }
    }
    return $offer;
  }
}

if (!function_exists('itm_hotel_booking_portal_free_cancellation_days_from_settings')) {
  function itm_hotel_booking_portal_free_cancellation_days_from_settings($settingsRow) {
    if (!is_array($settingsRow) || !array_key_exists('free_cancellation_days_before_check_in', $settingsRow)) {
      return 5;
    }
    return max(0, min(365, (int) ($settingsRow['free_cancellation_days_before_check_in'] ?? 5)));
  }
}

if (!function_exists('itm_hotel_booking_portal_calendar_month_advance_days_left_from_settings')) {
  /**
   * Select Dates auto-advances when daysLeftInMonth(check-in) is below this threshold (0 = never).
   */
  function itm_hotel_booking_portal_calendar_month_advance_days_left_from_settings($settingsRow) {
    if (!is_array($settingsRow) || !array_key_exists('calendar_month_advance_days_left', $settingsRow)) {
      return 3;
    }
    return max(0, min(31, (int) ($settingsRow['calendar_month_advance_days_left'] ?? 3)));
  }
}

if (!function_exists('itm_hotel_booking_portal_show_discount_strikethrough_from_settings')) {
  /** Whether portal Step 1/2 show list-price strikethrough next to discounted sale price. */
  function itm_hotel_booking_portal_show_discount_strikethrough_from_settings($settingsRow) {
    if (!is_array($settingsRow) || !array_key_exists('show_discount_strikethrough', $settingsRow)) {
      return true;
    }
    return !empty($settingsRow['show_discount_strikethrough']);
  }
}

if (!function_exists('itm_hotel_booking_portal_price_incl_tourist_tax')) {
  /**
   * Guest-facing nightly display: room amount + tourist tax for occupancy (default 1 adult).
   */
  function itm_hotel_booking_portal_price_incl_tourist_tax($amountExclTax, $touristTaxPerPersonPerNight, array $occupancy = null) {
    $amount = max(0.0, (float) $amountExclTax);
    if (!is_array($occupancy)) {
      $occupancy = ['rooms' => 1, 'adults' => 1, 'children' => 0, 'babies' => 0];
    }
    $tax = itm_hotel_booking_portal_tourist_tax_amount($occupancy, 1, $touristTaxPerPersonPerNight);
    return round($amount + $tax, 2);
  }
}

if (!function_exists('itm_hotel_booking_portal_cheapest_rate_offer_for_hotel')) {
  /**
   * Lowest net room factor among active Step 2 plans (discount then surcharge; usually Non-Refundable).
   *
   * @return array{discount_percent:float,surcharge_percent:float,slug:string,price_label:string,name:string,pay_badge:string}
   */
  function itm_hotel_booking_portal_cheapest_rate_offer_for_hotel($conn, $companyId, $hotelId) {
    $companyId = (int) $companyId;
    $hotelId = (int) $hotelId;
    $best = null;
    if ($companyId > 0 && $hotelId > 0) {
      $plans = itm_hotel_booking_portal_rate_plans_active_for_hotel($conn, $companyId, $hotelId, true);
      foreach ($plans as $plan) {
        $slug = strtolower(preg_replace('/[^a-z0-9_-]/', '', (string) ($plan['rate_plan_slug'] ?? '')));
        if ($slug === '' || $slug === 'breakfast') {
          // Why: Breakfast add-on raises stay total; From/calendar merchandising is room-rate based.
          continue;
        }
        $offer = itm_hotel_booking_portal_rate_plan_offer($slug, $plan);
        $disc = max(0.0, min(50.0, (float) ($offer['discount_percent'] ?? 0)));
        $sur = max(0.0, min(50.0, (float) ($offer['surcharge_percent'] ?? 0)));
        $net = (1.0 - ($disc / 100.0)) * (1.0 + ($sur / 100.0));
        $candidate = [
          'discount_percent' => $disc,
          'surcharge_percent' => $sur,
          'net_factor' => $net,
          'slug' => $slug,
          'price_label' => (string) ($offer['price_label'] ?? 'Best available rate'),
          'name' => (string) ($plan['name'] ?? $offer['price_label'] ?? ''),
          'pay_badge' => (string) ($offer['pay_badge'] ?? ''),
        ];
        if ($best === null
          || $net < (float) $best['net_factor'] - 0.000001
          || (abs($net - (float) $best['net_factor']) < 0.000001 && $slug === 'non_refundable')
        ) {
          $best = $candidate;
        }
      }
    }
    if ($best === null) {
      $offer = itm_hotel_booking_portal_rate_plan_offer('non_refundable');
      $disc = max(0.0, min(50.0, (float) ($offer['discount_percent'] ?? 0)));
      $sur = max(0.0, min(50.0, (float) ($offer['surcharge_percent'] ?? 0)));
      $best = [
        'discount_percent' => $disc,
        'surcharge_percent' => $sur,
        'net_factor' => (1.0 - ($disc / 100.0)) * (1.0 + ($sur / 100.0)),
        'slug' => 'non_refundable',
        'price_label' => (string) ($offer['price_label'] ?? 'Non-refundable rate'),
        'name' => 'Non-Refundable Rate',
        'pay_badge' => (string) ($offer['pay_badge'] ?? 'Non-refundable'),
      ];
    }
    unset($best['net_factor']);
    return $best;
  }
}

if (!function_exists('itm_hotel_booking_portal_rate_plan_effective_discount')) {
  function itm_hotel_booking_portal_rate_plan_effective_discount($specialDiscountPercent, $ratePlanSlug, $planRow = null) {
    $special = max(0.0, min(50.0, (float) $specialDiscountPercent));
    $offer = itm_hotel_booking_portal_rate_plan_offer($ratePlanSlug, $planRow);
    $plan = max(0.0, min(50.0, (float) ($offer['discount_percent'] ?? 0)));
    return min(50.0, $special + $plan);
  }
}

if (!function_exists('itm_hotel_booking_portal_rate_plan_effective_surcharge')) {
  function itm_hotel_booking_portal_rate_plan_effective_surcharge($ratePlanSlug, $planRow = null) {
    $offer = itm_hotel_booking_portal_rate_plan_offer($ratePlanSlug, $planRow);
    return max(0.0, min(50.0, (float) ($offer['surcharge_percent'] ?? 0)));
  }
}

if (!function_exists('itm_hotel_booking_portal_checkout_breakdown')) {
  function itm_hotel_booking_portal_checkout_breakdown($basePerNight, $checkIn, $checkOut, array $occupancy, $discountPercent, array $draft, $touristTaxPerPersonPerNight = 0.0, $conn = null, $companyId = 0) {
    $nights = itm_hotel_booking_portal_stay_nights($checkIn, $checkOut);
    $roomCharges = itm_hotel_booking_portal_room_charges_subtotal($basePerNight, $checkIn, $checkOut, $occupancy, $discountPercent, $draft, $conn, $companyId);
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
  function itm_hotel_booking_portal_compute_checkout_total($basePerNight, $checkIn, $checkOut, array $occupancy, $discountPercent, array $draft, $touristTaxPerPersonPerNight = null, $conn = null, $companyId = 0) {
    if ($touristTaxPerPersonPerNight === null) {
      $touristTaxPerPersonPerNight = (float) ($draft['tourist_tax_per_person_per_night'] ?? 0);
    }
    $breakdown = itm_hotel_booking_portal_checkout_breakdown($basePerNight, $checkIn, $checkOut, $occupancy, $discountPercent, $draft, $touristTaxPerPersonPerNight, $conn, $companyId);
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
  function itm_hotel_booking_portal_find_available_room_for_type($conn, $companyId, $hotelId, $roomTypeId, $checkIn, $checkOut, array $excludeRoomIds = []) {
    $companyId = (int) $companyId;
    $hotelId = (int) $hotelId;
    $roomTypeId = (int) $roomTypeId;
    $excludeRoomIds = array_values(array_unique(array_map('intval', $excludeRoomIds)));
    $sql = 'SELECT r.id, r.hotel_id, r.room_type_id, COALESCE(bp.price_per_night, 0.00) AS price_per_night, r.is_out_of_order, r.is_out_of_service FROM hotel_booking_rooms r
            LEFT JOIN hotel_booking_room_type_base_prices bp ON bp.company_id = r.company_id AND bp.hotel_id = r.hotel_id AND bp.room_type_id = r.room_type_id AND bp.deleted_at IS NULL
            WHERE r.company_id = ? AND r.hotel_id = ? AND r.room_type_id = ? AND r.deleted_at IS NULL AND r.active = 1
            ORDER BY COALESCE(bp.price_per_night, 0.00) ASC, r.id ASC';
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
      if ($rid > 0 && !in_array($rid, $excludeRoomIds, true) && !itm_hotel_booking_room_unavailable_for_stay($conn, $companyId, $rid, $checkIn, $checkOut, 0, $row)) {
        $pick = $row;
        break;
      }
    }
    mysqli_stmt_close($stmt);
    return $pick;
  }
}

if (!function_exists('itm_hotel_booking_portal_split_occupancy_for_room_line')) {
  function itm_hotel_booking_portal_split_occupancy_for_room_line(array $occupancy, $lineIndex, $lineCount) {
    $occ = itm_hotel_booking_portal_parse_occupancy($occupancy);
    $occ['rooms'] = 1;
    $lineCount = max(1, (int) $lineCount);
    $lineIndex = max(0, min($lineCount - 1, (int) $lineIndex));
    $adults = max(1, (int) ($occ['adults'] ?? 1));
    $children = max(0, (int) ($occ['children'] ?? 0));
    $babies = max(0, (int) ($occ['babies'] ?? 0));
    $adultsPer = (int) floor($adults / $lineCount);
    $adultsRem = $adults % $lineCount;
    $childrenPer = (int) floor($children / $lineCount);
    $childrenRem = $children % $lineCount;
    $babiesPer = (int) floor($babies / $lineCount);
    $babiesRem = $babies % $lineCount;
    $occ['adults'] = max(1, $adultsPer + ($lineIndex < $adultsRem ? 1 : 0));
    $occ['children'] = $childrenPer + ($lineIndex < $childrenRem ? 1 : 0);
    $occ['babies'] = $babiesPer + ($lineIndex < $babiesRem ? 1 : 0);
    return $occ;
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
  // Why: Check-in on or before today is segment present; guests still need online cancel until checkout passes.
    return $segment === 'future' || $segment === 'present';
  }
}

if (!function_exists('itm_hotel_booking_portal_cancel_booking_for_guest')) {
  /**
   * @return array{ok:bool,error?:string}
   */
  function itm_hotel_booking_portal_cancel_booking_for_guest($conn, $companyId, $reservationId, $lastName, $auth2 = '') {
    $companyId = (int) $companyId;
    $reservationId = (int) $reservationId;
    $lastName = trim((string) $lastName);
    $auth2 = itm_hotel_booking_normalize_auth2($auth2);
    if ($companyId < 1 || $reservationId < 1 || $lastName === '' || $auth2 === '') {
      return ['ok' => false, 'error' => 'Enter your last name, reservation ID, and auth code.'];
    }
    $booking = itm_hotel_booking_fetch_for_guest_manage($conn, $companyId, $reservationId, $lastName, $auth2);
    if (!$booking) {
      return ['ok' => false, 'error' => 'No reservation found. Check your last name, reservation ID, and auth code.'];
    }
    if (itm_hotel_booking_booking_is_cancelled($conn, $companyId, $booking)) {
      return ['ok' => false, 'error' => 'This reservation is already cancelled.'];
    }
    $groupRows = itm_hotel_booking_portal_load_confirmation_group_rows($conn, $companyId, $booking);
    if ($groupRows === []) {
      $groupRows = [$booking];
    }
    foreach ($groupRows as $groupRow) {
      if (itm_hotel_booking_booking_is_cancelled($conn, $companyId, $groupRow)) {
        continue;
      }
      if (!itm_hotel_booking_portal_guest_can_cancel_booking($conn, $companyId, $groupRow)) {
        return ['ok' => false, 'error' => 'This reservation can no longer be cancelled online. Please contact the hotel.'];
      }
      $segment = itm_hotel_booking_resolve_segment($groupRow['check_in'], $groupRow['check_out']);
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
      $cancelReservationId = (int) ($groupRow['id'] ?? 0);
      $sql = 'UPDATE hotel_bookings SET `' . $statusCol . '` = ?, updated_at = NOW() WHERE id = ? AND company_id = ? AND deleted_at IS NULL';
      $stmt = mysqli_prepare($conn, $sql);
      if (!$stmt) {
        return ['ok' => false, 'error' => 'Unable to cancel this reservation.'];
      }
      mysqli_stmt_bind_param($stmt, 'iii', $cancelId, $cancelReservationId, $companyId);
      $ok = mysqli_stmt_execute($stmt);
      mysqli_stmt_close($stmt);
      if (!$ok) {
        return ['ok' => false, 'error' => 'Unable to cancel this reservation.'];
      }
    }
    return ['ok' => true];
  }
}

if (!function_exists('itm_hotel_booking_portal_stay_night_dates')) {
  function itm_hotel_booking_portal_stay_night_dates($checkIn, $checkOut) {
    $dates = [];
    $in = DateTime::createFromFormat('Y-m-d', (string) $checkIn);
    $out = DateTime::createFromFormat('Y-m-d', (string) $checkOut);
    if (!$in || !$out || $out <= $in) {
      return $dates;
    }
    $cur = clone $in;
    while ($cur < $out) {
      $dates[] = $cur->format('Y-m-d');
      $cur->modify('+1 day');
    }
    return $dates;
  }
}

if (!function_exists('itm_hotel_booking_room_type_rate_override_for_date')) {
  function itm_hotel_booking_room_type_rate_override_for_date($conn, $companyId, $hotelId, $roomTypeId, $nightDate) {
    $companyId = (int) $companyId;
    $hotelId = (int) $hotelId;
    $roomTypeId = (int) $roomTypeId;
    $nightDate = (string) $nightDate;
    if ($companyId < 1 || $hotelId < 1 || $roomTypeId < 1 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $nightDate)) {
      return null;
    }
    $sql = 'SELECT price_per_night FROM hotel_booking_room_type_rate_overrides
            WHERE company_id = ? AND hotel_id = ? AND room_type_id = ? AND deleted_at IS NULL AND active = 1
            AND start_date <= ? AND end_date >= ?
            ORDER BY id DESC LIMIT 1';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
      return null;
    }
    mysqli_stmt_bind_param($stmt, 'iiiss', $companyId, $hotelId, $roomTypeId, $nightDate, $nightDate);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    if (!$row) {
      return null;
    }
    return (float) ($row['price_per_night'] ?? 0);
  }
}

if (!function_exists('itm_hotel_booking_resolve_room_type_nightly_bar')) {
  function itm_hotel_booking_resolve_room_type_nightly_bar($conn, $companyId, $hotelId, $roomTypeId, $nightDate, $defaultBar) {
    $override = itm_hotel_booking_room_type_rate_override_for_date($conn, $companyId, $hotelId, $roomTypeId, $nightDate);
    if ($override !== null) {
      return $override;
    }
    return (float) $defaultBar;
  }
}

if (!function_exists('itm_hotel_booking_room_type_blocked_for_date')) {
  function itm_hotel_booking_room_type_blocked_for_date($conn, $companyId, $hotelId, $roomTypeId, $nightDate) {
    $companyId = (int) $companyId;
    $hotelId = (int) $hotelId;
    $roomTypeId = (int) $roomTypeId;
    $nightDate = (string) $nightDate;
    if ($companyId < 1 || $hotelId < 1 || $roomTypeId < 1 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $nightDate)) {
      return false;
    }
    $sql = 'SELECT 1 FROM hotel_booking_room_type_blocks
            WHERE company_id = ? AND hotel_id = ? AND room_type_id = ? AND deleted_at IS NULL AND active = 1
            AND start_date <= ? AND end_date >= ? LIMIT 1';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
      return false;
    }
    mysqli_stmt_bind_param($stmt, 'iiiss', $companyId, $hotelId, $roomTypeId, $nightDate, $nightDate);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $blocked = $res && mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);
    return (bool) $blocked;
  }
}

if (!function_exists('itm_hotel_booking_room_type_blocked_for_stay')) {
  function itm_hotel_booking_room_type_blocked_for_stay($conn, $companyId, $hotelId, $roomTypeId, $checkIn, $checkOut) {
    foreach (itm_hotel_booking_portal_stay_night_dates($checkIn, $checkOut) as $night) {
      if (itm_hotel_booking_room_type_blocked_for_date($conn, $companyId, $hotelId, $roomTypeId, $night)) {
        return true;
      }
    }
    return false;
  }
}

if (!function_exists('itm_hotel_booking_room_maintenance_blocks_stay')) {
  function itm_hotel_booking_room_maintenance_blocks_stay($conn, $companyId, $roomId, $checkIn, $checkOut) {
    $companyId = (int) $companyId;
    $roomId = (int) $roomId;
    if ($companyId < 1 || $roomId < 1) {
      return false;
    }
    $lastNight = date('Y-m-d', strtotime((string) $checkOut . ' -1 day'));
    $sql = 'SELECT 1 FROM hotel_booking_housekeeping_maintenance
            WHERE company_id = ? AND room_id = ? AND deleted_at IS NULL AND active = 1
            AND from_date <= ? AND through_date >= ? LIMIT 1';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
      return false;
    }
    mysqli_stmt_bind_param($stmt, 'iiss', $companyId, $roomId, $lastNight, $checkIn);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $blocked = $res && mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);
    return (bool) $blocked;
  }
}

if (!function_exists('itm_hotel_booking_room_unavailable_for_stay')) {
  function itm_hotel_booking_room_unavailable_for_stay($conn, $companyId, $roomId, $checkIn, $checkOut, $excludeBookingId = 0, array $roomRow = null) {
    $companyId = (int) $companyId;
    $roomId = (int) $roomId;
    if ($companyId < 1 || $roomId < 1) {
      return true;
    }
    if ($roomRow === null) {
      $stmt = mysqli_prepare($conn, 'SELECT id, hotel_id, room_type_id, is_out_of_order, is_out_of_service FROM hotel_booking_rooms WHERE id = ? AND company_id = ? AND deleted_at IS NULL LIMIT 1');
      if (!$stmt) {
        return true;
      }
      mysqli_stmt_bind_param($stmt, 'ii', $roomId, $companyId);
      mysqli_stmt_execute($stmt);
      $res = mysqli_stmt_get_result($stmt);
      $roomRow = $res ? mysqli_fetch_assoc($res) : null;
      mysqli_stmt_close($stmt);
    }
    if (!$roomRow) {
      return true;
    }
    if (!empty($roomRow['is_out_of_order']) || !empty($roomRow['is_out_of_service'])) {
      return true;
    }
    $hotelId = (int) ($roomRow['hotel_id'] ?? 0);
    $roomTypeId = (int) ($roomRow['room_type_id'] ?? 0);
    if ($hotelId > 0 && $roomTypeId > 0 && itm_hotel_booking_room_type_blocked_for_stay($conn, $companyId, $hotelId, $roomTypeId, $checkIn, $checkOut)) {
      return true;
    }
    if (itm_hotel_booking_room_maintenance_blocks_stay($conn, $companyId, $roomId, $checkIn, $checkOut)) {
      return true;
    }
    return itm_hotel_booking_has_overlap($conn, $companyId, $roomId, $checkIn, $checkOut, $excludeBookingId);
  }
}

if (!function_exists('itm_hotel_booking_room_sellable_for_night')) {
  function itm_hotel_booking_room_sellable_for_night($conn, $companyId, array $room, $nightDate, array $bookingsByRoom = null) {
    $companyId = (int) $companyId;
    $roomId = (int) ($room['id'] ?? 0);
    $nightDate = (string) $nightDate;
    if ($companyId < 1 || $roomId < 1 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $nightDate)) {
      return false;
    }
    if (!empty($room['is_out_of_order']) || !empty($room['is_out_of_service'])) {
      return false;
    }
    $hotelId = (int) ($room['hotel_id'] ?? 0);
    $roomTypeId = (int) ($room['room_type_id'] ?? 0);
    if ($hotelId > 0 && $roomTypeId > 0 && itm_hotel_booking_room_type_blocked_for_date($conn, $companyId, $hotelId, $roomTypeId, $nightDate)) {
      return false;
    }
    $checkOut = date('Y-m-d', strtotime($nightDate . ' +1 day'));
    if (itm_hotel_booking_room_maintenance_blocks_stay($conn, $companyId, $roomId, $nightDate, $checkOut)) {
      return false;
    }
    if ($bookingsByRoom !== null) {
      $list = $bookingsByRoom[$roomId] ?? [];
      foreach ($list as $b) {
        if ($b['check_in'] < $checkOut && $b['check_out'] > $nightDate) {
          return false;
        }
      }
      return true;
    }
    return !itm_hotel_booking_has_overlap($conn, $companyId, $roomId, $nightDate, $checkOut);
  }
}

if (!function_exists('itm_hotel_booking_compute_stay_payment_dated_rates')) {
  function itm_hotel_booking_compute_stay_payment_dated_rates($conn, $companyId, $hotelId, $roomTypeId, $defaultBar, $checkIn, $checkOut, array $occupancy, $discountPercent = 0.0, array $pricing = null, $surchargePercent = 0.0) {
    $total = 0.0;
    foreach (itm_hotel_booking_portal_stay_night_dates($checkIn, $checkOut) as $night) {
      $bar = itm_hotel_booking_resolve_room_type_nightly_bar($conn, $companyId, $hotelId, $roomTypeId, $night, $defaultBar);
      $total += itm_hotel_booking_portal_quote_nightly($bar, $occupancy, $discountPercent, $pricing, $surchargePercent);
    }
    return round($total, 2);
  }
}

if (!function_exists('itm_hotel_booking_portal_check_in_display_bar')) {
  function itm_hotel_booking_portal_check_in_display_bar($conn, $companyId, $hotelId, $roomTypeId, $checkIn, $defaultBar) {
    return itm_hotel_booking_resolve_room_type_nightly_bar($conn, $companyId, $hotelId, $roomTypeId, $checkIn, $defaultBar);
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

if (!function_exists('itm_hotel_booking_planning_sort_rooms')) {
  function itm_hotel_booking_planning_sort_rooms(array $rooms, $sortCol, $dir) {
    $sortCol = in_array($sortCol, ['room', 'hk', 'type'], true) ? $sortCol : 'room';
    $dirMult = (strtolower((string) $dir) === 'desc') ? -1 : 1;
    usort($rooms, function ($a, $b) use ($sortCol, $dirMult) {
      switch ($sortCol) {
        case 'hk':
          $av = strtolower((string) ($a['hk_code'] ?? $a['hk_name'] ?? ''));
          $bv = strtolower((string) ($b['hk_code'] ?? $b['hk_name'] ?? ''));
          break;
        case 'type':
          $av = strtolower((string) ($a['type_code'] ?? $a['type_name'] ?? ''));
          $bv = strtolower((string) ($b['type_code'] ?? $b['type_name'] ?? ''));
          break;
        case 'room':
        default:
          $av = strtolower((string) ($a['room_number'] ?? ''));
          $bv = strtolower((string) ($b['room_number'] ?? ''));
          break;
      }
      $cmp = strcmp($av, $bv);
      if ($cmp === 0) {
        $cmp = strcmp((string) ($a['room_number'] ?? ''), (string) ($b['room_number'] ?? ''));
      }
      return $dirMult * $cmp;
    });
    return $rooms;
  }
}

if (!function_exists('itm_hotel_booking_planning_grid_rows')) {
  function itm_hotel_booking_planning_grid_rows($conn, $companyId, $anchorDate, $hotelId = 0, $roomTypeId = 0, $floor = '', $days = 14, $sortCol = 'room', $sortDir = 'asc') {
    $companyId = (int) $companyId;
    $hotelId = (int) $hotelId;
    $days = max(7, min(31, (int) $days));
    $anchor = DateTime::createFromFormat('Y-m-d', $anchorDate) ?: new DateTime('today');
    $rangeStart = $anchor->format('Y-m-d');
    $rangeEnd = (clone $anchor)->modify('+' . ($days - 1) . ' days')->format('Y-m-d');

    $roomSql = 'SELECT r.*, h.name AS hotel_name, t.code AS type_code, t.name AS type_name, hk.name AS hk_name, hk.code AS hk_code, hk.color_hex AS hk_color
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
    $rooms = itm_hotel_booking_planning_sort_rooms($rooms, $sortCol, $sortDir);

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
    $rstmt = mysqli_prepare($conn, 'SELECT r.id, COALESCE(bp.price_per_night, 0.00) AS price_per_night FROM hotel_booking_rooms r LEFT JOIN hotel_booking_room_type_base_prices bp ON bp.company_id = r.company_id AND bp.hotel_id = r.hotel_id AND bp.room_type_id = r.room_type_id AND bp.deleted_at IS NULL WHERE r.id = ? AND r.company_id = ? AND r.deleted_at IS NULL LIMIT 1');
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
      return ['ok' => false, 'error' => 'No active HSK statuses.'];
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
    $hkStmt = mysqli_prepare($conn, 'SELECT name, code, color_hex FROM hotel_booking_housekeeping_statuses WHERE id = ? AND company_id = ? LIMIT 1');
    $hkName = '';
    $hkCode = '';
    $hkColor = '#6c757d';
    if ($hkStmt) {
      mysqli_stmt_bind_param($hkStmt, 'ii', $nextId, $companyId);
      mysqli_stmt_execute($hkStmt);
      $hkRes = mysqli_stmt_get_result($hkStmt);
      $hkRow = $hkRes ? mysqli_fetch_assoc($hkRes) : null;
      mysqli_stmt_close($hkStmt);
      if ($hkRow) {
        $hkName = (string) ($hkRow['name'] ?? '');
        $hkCode = (string) ($hkRow['code'] ?? '');
        $hkColor = (string) ($hkRow['color_hex'] ?? '#6c757d');
      }
    }
    return ['ok' => true, 'housekeeping_status_id' => $nextId, 'hk_name' => $hkName, 'hk_code' => $hkCode, 'hk_color' => $hkColor];
  }
}

if (!function_exists('itm_hotel_booking_portal_rate_plans_active_for_hotel')) {
  function itm_hotel_booking_portal_rate_plans_active_for_hotel($conn, $companyId, $hotelId, $ensureDefaults = true) {
    $companyId = (int) $companyId;
    $hotelId = (int) $hotelId;
    if ($companyId < 1 || $hotelId < 1) {
      return [];
    }
    if ($ensureDefaults) {
      itm_hotel_booking_ensure_portal_rate_plans_for_hotel($conn, $companyId, $hotelId);
    }
    $rows = [];
    $stmt = mysqli_prepare($conn, 'SELECT id, hotel_id, plan_slot, name, rate_plan_slug, cancellation_policy_url, pay_badge, price_label, cancel_template, plan_discount_percent, plan_surcharge_percent, free_cancellation_days_before_check_in, active FROM hotel_booking_portal_rate_plans WHERE company_id = ? AND hotel_id = ? AND deleted_at IS NULL AND active = 1 ORDER BY plan_slot ASC');
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

if (!function_exists('itm_hotel_booking_get_room_type_base_price')) {
  function itm_hotel_booking_get_room_type_base_price($conn, $companyId, $hotelId, $roomTypeId) {
    $companyId = (int) $companyId;
    $hotelId = (int) $hotelId;
    $roomTypeId = (int) $roomTypeId;
    $sql = 'SELECT price_per_night FROM hotel_booking_room_type_base_prices WHERE company_id = ? AND hotel_id = ? AND room_type_id = ? AND deleted_at IS NULL LIMIT 1';
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
      mysqli_stmt_bind_param($stmt, 'iii', $companyId, $hotelId, $roomTypeId);
      mysqli_stmt_execute($stmt);
      $res = mysqli_stmt_get_result($stmt);
      if ($res && ($row = mysqli_fetch_assoc($res))) {
        mysqli_stmt_close($stmt);
        return (float) $row['price_per_night'];
      }
      mysqli_stmt_close($stmt);
    }
    return 0.0;
  }
}

if (!function_exists('itm_hotel_booking_portal_rate_plan_label')) {
  function itm_hotel_booking_portal_rate_plan_label($conn, $companyId, $planId) {
    $row = itm_hotel_booking_portal_rate_plan_row_by_id($conn, $companyId, (int) $planId);
    return $row ? (string) ($row['name'] ?? '') : '';
  }
}

if (!function_exists('itm_hotel_booking_portal_resolve_cancellation_policy_url_for_booking')) {
  function itm_hotel_booking_portal_resolve_cancellation_policy_url_for_booking($conn, $companyId, array $booking) {
    $companyId = (int) $companyId;
    $hotelId = (int) ($booking['hotel_id'] ?? 0);
    $planId = (int) ($booking['portal_rate_plan_id'] ?? 0);
    if ($planId > 0) {
      $planRow = itm_hotel_booking_portal_rate_plan_row_by_id($conn, $companyId, $planId);
      if ($planRow) {
        $url = itm_hotel_booking_normalize_cancellation_policy_url($planRow['cancellation_policy_url'] ?? '');
        if ($url !== '') {
          return $url;
        }
        $slug = (string) ($planRow['rate_plan_slug'] ?? '');
        if ($slug !== '') {
          return itm_hotel_booking_portal_resolve_cancellation_policy_url($conn, $companyId, (int) ($planRow['hotel_id'] ?? $hotelId), $slug);
        }
      }
    }
    $ratePlan = itm_hotel_booking_portal_parse_rate_plan_from_notes((string) ($booking['notes'] ?? ''));
    return itm_hotel_booking_portal_resolve_cancellation_policy_url($conn, $companyId, $hotelId, $ratePlan);
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

if (!function_exists('itm_hotel_booking_portal_rate_plan_hard_delete')) {
  function itm_hotel_booking_portal_rate_plan_hard_delete($conn, $companyId, $planId) {
    $companyId = (int) $companyId;
    $planId = (int) $planId;
    if ($companyId < 1 || $planId < 1) {
      return ['ok' => false, 'error' => 'Invalid request.'];
    }
    $stmt = mysqli_prepare($conn, 'DELETE FROM hotel_booking_portal_rate_plans WHERE id = ? AND company_id = ? LIMIT 1');
    if (!$stmt) {
      return ['ok' => false, 'error' => 'Delete failed.'];
    }
    mysqli_stmt_bind_param($stmt, 'ii', $planId, $companyId);
    $ok = mysqli_stmt_execute($stmt);
    $affected = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    if (!$ok || $affected < 1) {
      return ['ok' => false, 'error' => 'Rate plan not found or already deleted.'];
    }
    return ['ok' => true, 'id' => $planId];
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
      . '<main class="hb-cancellation-policy-page card">' . $bodyHtml . '</main>'
      . '<button type="button" class="hb-btn hb-checkout-skip hb-cancellation-policy-back" title="Back" onclick="history.go(-1);">🔙</button>'
      . '</body></html>';
    return file_put_contents($full, $doc) !== false;
  }
}

if (!function_exists('itm_hotel_booking_room_number_taken')) {
  /**
   * Whether another live room in the same hotel already uses this room number.
   */
  function itm_hotel_booking_room_number_taken($conn, $companyId, $hotelId, $roomNumber, $excludeRoomId = 0) {
    $companyId = (int) $companyId;
    $hotelId = (int) $hotelId;
    $excludeRoomId = (int) $excludeRoomId;
    $roomNumber = trim((string) $roomNumber);
    if ($companyId < 1 || $hotelId < 1 || $roomNumber === '') {
      return false;
    }
    $sql = 'SELECT id FROM hotel_booking_rooms WHERE company_id = ? AND hotel_id = ? AND room_number = ? AND deleted_at IS NULL';
    if ($excludeRoomId > 0) {
      $sql .= ' AND id <> ?';
    }
    $sql .= ' LIMIT 1';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
      return false;
    }
    if ($excludeRoomId > 0) {
      mysqli_stmt_bind_param($stmt, 'iisi', $companyId, $hotelId, $roomNumber, $excludeRoomId);
    } else {
      mysqli_stmt_bind_param($stmt, 'iis', $companyId, $hotelId, $roomNumber);
    }
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $taken = $res && mysqli_num_rows($res) > 0;
    mysqli_stmt_close($stmt);
    return $taken;
  }
}

if (!function_exists('itm_hotel_booking_room_resolve_duplicate_room_number')) {
  function itm_hotel_booking_room_resolve_duplicate_room_number($conn, $companyId, $hotelId, $baseRoomNumber) {
    $baseRoomNumber = trim((string) $baseRoomNumber);
    if ($baseRoomNumber === '') {
      $baseRoomNumber = 'ROOM';
    }
    for ($i = 0; $i < 50; $i++) {
      $suffix = $i === 0 ? '-C' : '-C' . ($i + 1);
      $maxBaseLen = 20 - strlen($suffix);
      $candidate = ($maxBaseLen > 0 ? substr($baseRoomNumber, 0, $maxBaseLen) : '') . $suffix;
      if (!itm_hotel_booking_room_number_taken($conn, $companyId, $hotelId, $candidate)) {
        return $candidate;
      }
    }
    return substr($baseRoomNumber, 0, 8) . '-' . substr((string) time(), -6);
  }
}

if (!function_exists('itm_hotel_booking_room_resolve_duplicate_name')) {
  function itm_hotel_booking_room_resolve_duplicate_name($baseName) {
    $baseName = trim((string) $baseName);
    if ($baseName === '') {
      $baseName = 'Room';
    }
    for ($i = 0; $i < 50; $i++) {
      $suffix = $i === 0 ? ' Copy' : ' Copy ' . ($i + 1);
      $maxBaseLen = 255 - strlen($suffix);
      $candidate = ($maxBaseLen > 0 ? substr($baseName, 0, $maxBaseLen) : '') . $suffix;
      if ($candidate !== $baseName) {
        return $candidate;
      }
    }
    return substr($baseName, 0, 240) . ' Copy';
  }
}

if (!function_exists('itm_hotel_booking_room_duplicate_record')) {
  /**
   * Clone a hotel room row; assigns a new unique room_number and suffixed name.
   *
   * @return array{ok:bool,new_id:int,message:string}
   */
  function itm_hotel_booking_room_duplicate_record($conn, $companyId, $sourceRoomId) {
    $companyId = (int) $companyId;
    $sourceRoomId = (int) $sourceRoomId;
    if ($companyId < 1 || $sourceRoomId < 1) {
      return ['ok' => false, 'new_id' => 0, 'message' => 'Invalid room.'];
    }

    $stmt = mysqli_prepare(
      $conn,
      'SELECT * FROM hotel_booking_rooms WHERE company_id = ? AND id = ? AND deleted_at IS NULL LIMIT 1'
    );
    if (!$stmt) {
      return ['ok' => false, 'new_id' => 0, 'message' => 'Could not load the source room.'];
    }
    mysqli_stmt_bind_param($stmt, 'ii', $companyId, $sourceRoomId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $source = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    if (!$source) {
      return ['ok' => false, 'new_id' => 0, 'message' => 'Room not found.'];
    }

    $hotelId = (int) ($source['hotel_id'] ?? 0);
    $newRoomNumber = itm_hotel_booking_room_resolve_duplicate_room_number(
      $conn,
      $companyId,
      $hotelId,
      (string) ($source['room_number'] ?? '')
    );
    $newName = itm_hotel_booking_room_resolve_duplicate_name((string) ($source['name'] ?? ''));

    $columnMeta = [];
    $describe = mysqli_query($conn, 'DESCRIBE `hotel_booking_rooms`');
    while ($describe && ($col = mysqli_fetch_assoc($describe))) {
      $columnMeta[(string) ($col['Field'] ?? '')] = $col;
    }

    $skipColumns = ['id', 'created_by', 'created_at', 'updated_by', 'updated_at', 'deleted_by', 'deleted_at'];
    $insertColumns = [];
    $insertValues = [];

    foreach ($source as $column => $value) {
      if (!isset($columnMeta[$column]) || in_array($column, $skipColumns, true)) {
        continue;
      }
      if ($column === 'room_number') {
        $value = $newRoomNumber;
      } elseif ($column === 'name') {
        $value = $newName;
      } elseif ($column === 'active') {
        $value = 1;
      }

      $type = strtolower((string) ($columnMeta[$column]['Type'] ?? ''));
      if ($value === null || $value === '') {
        $nullAllowed = strtoupper((string) ($columnMeta[$column]['Null'] ?? '')) === 'YES';
        $insertValues[] = $nullAllowed ? 'NULL' : "''";
      } elseif (preg_match('/^(tinyint|smallint|mediumint|int|bigint)/', $type)) {
        $insertValues[] = (string) (int) $value;
      } elseif (preg_match('/^(decimal|float|double)/', $type)) {
        $insertValues[] = (string) (0 + $value);
      } else {
        $insertValues[] = "'" . mysqli_real_escape_string($conn, (string) $value) . "'";
      }
      $insertColumns[] = '`' . str_replace('`', '``', $column) . '`';
    }

    $auditData = [];
    $auditSql = [];
    if (function_exists('itm_crud_stamp_create_audit')) {
      itm_crud_stamp_create_audit($auditData, $auditSql);
      foreach (['created_by', 'created_at', 'updated_by', 'updated_at'] as $auditCol) {
        if (!array_key_exists($auditCol, $auditData)) {
          continue;
        }
        $insertColumns[] = '`' . $auditCol . '`';
        if ($auditCol === 'created_by' || $auditCol === 'updated_by') {
          $insertValues[] = (string) (int) $auditData[$auditCol];
        } else {
          $insertValues[] = "'" . mysqli_real_escape_string($conn, (string) $auditData[$auditCol]) . "'";
        }
      }
    }

    $sql = 'INSERT INTO hotel_booking_rooms (' . implode(', ', $insertColumns) . ') VALUES (' . implode(', ', $insertValues) . ')';
    $dbErrorCode = 0;
    $dbErrorMessage = '';
    $ok = itm_run_query($conn, $sql, $dbErrorCode, $dbErrorMessage);
    $newId = 0;
    if ($ok) {
      $lookup = mysqli_prepare(
        $conn,
        'SELECT id FROM hotel_booking_rooms WHERE company_id = ? AND hotel_id = ? AND room_number = ? AND deleted_at IS NULL ORDER BY id DESC LIMIT 1'
      );
      if ($lookup) {
        mysqli_stmt_bind_param($lookup, 'iis', $companyId, $hotelId, $newRoomNumber);
        mysqli_stmt_execute($lookup);
        $lookupRes = mysqli_stmt_get_result($lookup);
        if ($lookupRes && ($lookupRow = mysqli_fetch_assoc($lookupRes))) {
          $newId = (int) ($lookupRow['id'] ?? 0);
        }
        mysqli_stmt_close($lookup);
      }
    }

    if (!$ok || $newId < 1) {
      $message = $dbErrorMessage !== '' && function_exists('itm_format_db_constraint_error')
        ? itm_format_db_constraint_error($dbErrorCode, $dbErrorMessage)
        : 'Could not duplicate the room.';
      return ['ok' => false, 'new_id' => 0, 'message' => $message];
    }

    return ['ok' => true, 'new_id' => $newId, 'message' => ''];
  }
}

if (!function_exists('itm_hotel_booking_room_type_calendar_validate_date_range')) {
  function itm_hotel_booking_room_type_calendar_validate_date_range($startDate, $endDate) {
    $start = itm_parse_date_input($startDate);
    $end = itm_parse_date_input($endDate);
    if ($start === '' || $end === '') {
      return ['ok' => false, 'error' => 'Start and end dates are required.'];
    }
    if ($end < $start) {
      return ['ok' => false, 'error' => 'End date must be on or after start date.'];
    }
    return ['ok' => true, 'start_date' => $start, 'end_date' => $end];
  }
}

if (!function_exists('itm_hotel_booking_room_type_calendar_rate_rows')) {
  function itm_hotel_booking_room_type_calendar_rate_rows($conn, $companyId, $hotelId) {
    $rows = [];
    $sql = 'SELECT o.*, t.name AS room_type_name, t.code AS room_type_code
            FROM hotel_booking_room_type_rate_overrides o
            INNER JOIN booking_rooms_types t ON t.id = o.room_type_id AND t.company_id = o.company_id
            WHERE o.company_id = ? AND o.hotel_id = ? AND o.deleted_at IS NULL
            ORDER BY o.start_date DESC, t.name ASC, o.id DESC';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
      return $rows;
    }
    mysqli_stmt_bind_param($stmt, 'ii', $companyId, $hotelId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($res && ($row = mysqli_fetch_assoc($res))) {
      $rows[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $rows;
  }
}

if (!function_exists('itm_hotel_booking_room_type_calendar_block_rows')) {
  function itm_hotel_booking_room_type_calendar_block_rows($conn, $companyId, $hotelId) {
    $rows = [];
    $sql = 'SELECT b.*, t.name AS room_type_name, t.code AS room_type_code
            FROM hotel_booking_room_type_blocks b
            INNER JOIN booking_rooms_types t ON t.id = b.room_type_id AND t.company_id = b.company_id
            WHERE b.company_id = ? AND b.hotel_id = ? AND b.deleted_at IS NULL
            ORDER BY b.start_date DESC, t.name ASC, b.id DESC';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
      return $rows;
    }
    mysqli_stmt_bind_param($stmt, 'ii', $companyId, $hotelId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($res && ($row = mysqli_fetch_assoc($res))) {
      $rows[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $rows;
  }
}

if (!function_exists('itm_hotel_booking_room_type_options_for_hotel')) {
  function itm_hotel_booking_room_type_options_for_hotel($conn, $companyId, $hotelId) {
    $rows = [];
    $sql = 'SELECT DISTINCT t.id, t.name, t.code
            FROM booking_rooms_types t
            INNER JOIN hotel_booking_rooms r ON r.room_type_id = t.id AND r.company_id = t.company_id
            WHERE t.company_id = ? AND r.hotel_id = ? AND t.deleted_at IS NULL AND t.active = 1
            AND r.deleted_at IS NULL AND r.active = 1
            ORDER BY t.name ASC';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
      return $rows;
    }
    mysqli_stmt_bind_param($stmt, 'ii', $companyId, $hotelId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($res && ($row = mysqli_fetch_assoc($res))) {
      $rows[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $rows;
  }
}

if (!function_exists('itm_hotel_booking_portal_manage_rate_limit_session_key')) {
  function itm_hotel_booking_portal_manage_rate_limit_session_key() {
    return 'hotel_booking_manage_rl_events';
  }
}

if (!function_exists('itm_hotel_booking_portal_manage_rate_limit_ip_dir')) {
  function itm_hotel_booking_portal_manage_rate_limit_ip_dir() {
    return rtrim((string) ROOT_PATH, '/\\') . DIRECTORY_SEPARATOR . 'files' . DIRECTORY_SEPARATOR . 'rate_limits' . DIRECTORY_SEPARATOR . 'hb_manage';
  }
}

if (!function_exists('itm_hotel_booking_portal_manage_rate_limit_prune_events')) {
  function itm_hotel_booking_portal_manage_rate_limit_prune_events(array $events, $now, $windowSeconds) {
    $fresh = [];
    foreach ($events as $ts) {
      $ts = (int) $ts;
      if ($ts > 0 && ($now - $ts) < $windowSeconds) {
        $fresh[] = $ts;
      }
    }
    return $fresh;
  }
}

if (!function_exists('itm_hotel_booking_portal_manage_rate_limit_ip_events')) {
  function itm_hotel_booking_portal_manage_rate_limit_ip_events($windowSeconds, $writeEvents = null) {
    $windowSeconds = max(60, (int) $windowSeconds);
    $ip = function_exists('itm_get_client_ip_address') ? trim((string) itm_get_client_ip_address()) : trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    if ($ip === '') {
      $ip = 'unknown';
    }
    $dir = itm_hotel_booking_portal_manage_rate_limit_ip_dir();
    if (function_exists('itm_ensure_upload_directory')) {
      itm_ensure_upload_directory($dir, 'deny_all');
    } elseif (!is_dir($dir)) {
      @mkdir($dir, 0755, true);
    }
    $path = $dir . DIRECTORY_SEPARATOR . hash('sha256', $ip) . '.json';
    $now = time();
    $events = [];
    if (is_file($path)) {
      $raw = @file_get_contents($path);
      $decoded = $raw !== false ? json_decode($raw, true) : null;
      if (is_array($decoded) && isset($decoded['events']) && is_array($decoded['events'])) {
        $events = itm_hotel_booking_portal_manage_rate_limit_prune_events($decoded['events'], $now, $windowSeconds);
      }
    }
    if ($writeEvents !== null) {
      $events = itm_hotel_booking_portal_manage_rate_limit_prune_events((array) $writeEvents, $now, $windowSeconds);
      @file_put_contents($path, json_encode(['events' => $events], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
    }
    return $events;
  }
}

/**
 * Why: Manage lookup/cancel are public POSTs — throttle by PHP session and client IP before DB verify.
 *
 * @return array{ok:bool,error?:string,count?:int,max?:int}
 */
if (!function_exists('itm_hotel_booking_portal_manage_rate_limit_check')) {
  function itm_hotel_booking_portal_manage_rate_limit_check($maxAttempts = 12, $windowSeconds = 900) {
    $maxAttempts = max(1, (int) $maxAttempts);
    $windowSeconds = max(60, (int) $windowSeconds);
    $now = time();
    $key = itm_hotel_booking_portal_manage_rate_limit_session_key();
    $sessionEvents = (isset($_SESSION[$key]) && is_array($_SESSION[$key])) ? $_SESSION[$key] : [];
    $sessionFresh = itm_hotel_booking_portal_manage_rate_limit_prune_events($sessionEvents, $now, $windowSeconds);
    $_SESSION[$key] = $sessionFresh;
    $ipFresh = itm_hotel_booking_portal_manage_rate_limit_ip_events($windowSeconds);
    $sessionCount = count($sessionFresh);
    $ipCount = count($ipFresh);
    if ($sessionCount >= $maxAttempts || $ipCount >= $maxAttempts) {
      return [
        'ok' => false,
        'error' => 'Too many attempts. Please wait and try again.',
        'count' => max($sessionCount, $ipCount),
        'max' => $maxAttempts,
      ];
    }
    return ['ok' => true, 'count' => max($sessionCount, $ipCount), 'max' => $maxAttempts];
  }
}

if (!function_exists('itm_hotel_booking_portal_manage_rate_limit_record')) {
  function itm_hotel_booking_portal_manage_rate_limit_record() {
    $key = itm_hotel_booking_portal_manage_rate_limit_session_key();
    $events = (isset($_SESSION[$key]) && is_array($_SESSION[$key])) ? $_SESSION[$key] : [];
    $events[] = time();
    $_SESSION[$key] = $events;
    $windowSeconds = 900;
    $ipEvents = itm_hotel_booking_portal_manage_rate_limit_ip_events($windowSeconds);
    $ipEvents[] = time();
    itm_hotel_booking_portal_manage_rate_limit_ip_events($windowSeconds, $ipEvents);
  }
}

if (!function_exists('itm_hotel_booking_portal_manage_otp_session_key')) {
  function itm_hotel_booking_portal_manage_otp_session_key() {
    return 'hotel_booking_manage_otp';
  }
}

if (!function_exists('itm_hotel_booking_portal_mask_email')) {
  function itm_hotel_booking_portal_mask_email($email) {
    $email = trim((string) $email);
    if ($email === '' || strpos($email, '@') === false) {
      return '';
    }
    $parts = explode('@', $email, 2);
    $local = (string) ($parts[0] ?? '');
    $domain = (string) ($parts[1] ?? '');
    if ($local === '' || $domain === '') {
      return '';
    }
    $visible = mb_substr($local, 0, 1, 'UTF-8');
    return $visible . '***@' . $domain;
  }
}

if (!function_exists('itm_hotel_booking_portal_manage_otp_clear')) {
  function itm_hotel_booking_portal_manage_otp_clear() {
    unset($_SESSION[itm_hotel_booking_portal_manage_otp_session_key()]);
  }
}

if (!function_exists('itm_hotel_booking_portal_reservations_email_send_options')) {
  /**
   * Portal transactional mail From header: hotel name + reservations_email (Email column).
   *
   * @return array{from_email?:string,from_name?:string,log_from_email?:string}
   */
  function itm_hotel_booking_portal_reservations_email_send_options($hotelName, $reservationsEmail) {
    $hotelName = trim((string) $hotelName);
    $reservationsEmail = trim((string) $reservationsEmail);
    if ($reservationsEmail === '' || !filter_var($reservationsEmail, FILTER_VALIDATE_EMAIL)) {
      return [];
    }
    if ($hotelName === '') {
      $hotelName = 'Reservations';
    }

    return [
      'from_email' => $reservationsEmail,
      'from_name' => $hotelName,
      'log_from_email' => $reservationsEmail,
    ];
  }
}

if (!function_exists('itm_hotel_booking_portal_manage_otp_issue')) {
  /**
   * @return array{ok:bool,error?:string,masked_email?:string}
   */
  function itm_hotel_booking_portal_manage_otp_issue($conn, $companyId, array $verifiedBookingRow) {
    $companyId = (int) $companyId;
    $reservationId = (int) ($verifiedBookingRow['id'] ?? 0);
    $email = trim((string) ($verifiedBookingRow['customer_email'] ?? ''));
    if ($companyId < 1 || $reservationId < 1 || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
      return ['ok' => false, 'error' => 'No valid email is on file for this reservation.'];
    }
    $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $subject = 'Your booking verification code';
    $body = '<p>Your one-time code to manage reservation <strong>#' . (int) $reservationId . '</strong> is:</p>'
      . '<p style="font-size:1.4em;"><strong>' . htmlspecialchars($otp, ENT_QUOTES, 'UTF-8') . '</strong></p>'
      . '<p>This code expires in 10 minutes. If you did not request this, you can ignore this email.</p>';
    $hotelName = trim((string) ($verifiedBookingRow['hotel_name'] ?? ''));
    if ($hotelName === '') {
      $hotelName = 'Hotel booking';
    }
    $reservationsEmail = trim((string) ($verifiedBookingRow['hotel_reservations_email'] ?? ''));
    $settingsRow = itm_hotel_booking_settings_row($conn, $companyId) ?: [];
    $manageBookingUrl = trim((string) ($settingsRow['urlmybooking'] ?? ''));
    if ($manageBookingUrl === '') {
      $manageBookingUrl = 'https://localhost/it-management/booking/users/bookings.php';
    }
    $manageBookingUrlNorm = itm_hotel_booking_normalize_reviews_url($manageBookingUrl);
    if ($manageBookingUrlNorm !== '') {
      $manageBookingUrl = $manageBookingUrlNorm;
    }
    $emailOptions = array_merge(
      itm_hotel_booking_portal_reservations_email_send_options($hotelName, $reservationsEmail),
      [
        'email_template' => [
          'subtitle' => 'Your booking verification code',
          'app_name' => $hotelName,
          'show_gear_icon' => false,
          'footer_link_text' => 'Manage my booking',
          'footer_link_url' => $manageBookingUrl,
        ],
      ]
    );
    $sent = function_exists('itm_send_email') ? itm_send_email($email, $subject, $body, $companyId, $emailOptions) : false;
    if (!$sent) {
      return ['ok' => false, 'error' => 'Could not send the verification email. Please try again later.'];
    }
    $_SESSION[itm_hotel_booking_portal_manage_otp_session_key()] = [
      'company_id' => $companyId,
      'reservation_id' => $reservationId,
      'otp_hash' => hash('sha256', $otp),
      'expires_at' => time() + 600,
      'verified' => false,
    ];
    return ['ok' => true, 'masked_email' => itm_hotel_booking_portal_mask_email($email)];
  }
}

if (!function_exists('itm_hotel_booking_portal_format_money_display')) {
  function itm_hotel_booking_portal_format_money_display($amount, $currencyCode = 'EUR') {
    $currencyCode = strtoupper(trim((string) $currencyCode));
    if ($currencyCode === '') {
      $currencyCode = 'EUR';
    }
    $formatted = number_format((float) $amount, 2, '.', '');
    if ($currencyCode === 'EUR') {
      return $formatted . '€';
    }
    return $formatted . ' ' . $currencyCode;
  }
}

if (!function_exists('itm_hotel_booking_portal_confirmation_email_manage_url')) {
  function itm_hotel_booking_portal_confirmation_email_manage_url($conn, $companyId) {
    $companyId = (int) $companyId;
    $manageBookingUrl = '';
    if ($conn && $companyId > 0) {
      $settingsRow = itm_hotel_booking_settings_row($conn, $companyId) ?: [];
      $manageBookingUrl = trim((string) ($settingsRow['urlmybooking'] ?? ''));
    }
    if ($manageBookingUrl === '') {
      $manageBookingUrl = 'https://localhost/it-management/booking/users/bookings.php';
    }
    $normalized = itm_hotel_booking_normalize_reviews_url($manageBookingUrl);
    return $normalized !== '' ? $normalized : $manageBookingUrl;
  }
}

if (!function_exists('itm_hotel_booking_portal_confirmation_email_template_options')) {
  /**
   * @return array<string,mixed>
   */
  function itm_hotel_booking_portal_confirmation_email_template_options($hotelName, $reservationsEmail, $manageBookingUrl, $subtitle) {
    $hotelName = trim((string) $hotelName);
    if ($hotelName === '') {
      $hotelName = 'Hotel booking';
    }
    return array_merge(
      itm_hotel_booking_portal_reservations_email_send_options($hotelName, $reservationsEmail),
      [
        'email_template' => [
          'subtitle' => (string) $subtitle,
          'app_name' => $hotelName,
          'show_gear_icon' => false,
          'footer_link_text' => 'Manage my booking',
          'footer_link_url' => (string) $manageBookingUrl,
        ],
      ]
    );
  }
}

if (!function_exists('itm_hotel_booking_portal_confirmation_room_label_from_row')) {
  function itm_hotel_booking_portal_confirmation_room_label_from_row(array $row) {
    $typeName = trim((string) ($row['type_name'] ?? ''));
    $bedSummary = trim((string) ($row['bed_summary'] ?? ''));
    $label = $typeName;
    if ($bedSummary !== '' && stripos($label, $bedSummary) === false) {
      $label = trim($label . ' ' . $bedSummary);
    }
    if ($label === '') {
      $label = trim((string) ($row['room_name'] ?? 'Room'));
    }
    return $label !== '' ? $label : 'Room';
  }
}

if (!function_exists('itm_hotel_booking_portal_confirmation_group_total')) {
  function itm_hotel_booking_portal_confirmation_group_total(array $groupRows) {
    $total = 0.0;
    foreach ($groupRows as $row) {
      $total += (float) ($row['payment_amount'] ?? 0);
    }
    return round($total, 2);
  }
}

if (!function_exists('itm_hotel_booking_portal_confirmation_primary_id')) {
  function itm_hotel_booking_portal_confirmation_primary_id(array $groupRows) {
    $primaryId = 0;
    foreach ($groupRows as $row) {
      $id = (int) ($row['id'] ?? 0);
      if ($id > 0 && ($primaryId < 1 || $id < $primaryId)) {
        $primaryId = $id;
      }
    }
    return $primaryId;
  }
}

if (!function_exists('itm_hotel_booking_portal_fetch_confirmation_booking_row')) {
  function itm_hotel_booking_portal_fetch_confirmation_booking_row($conn, $companyId, $bookingId) {
    $companyId = (int) $companyId;
    $bookingId = (int) $bookingId;
    if ($companyId < 1 || $bookingId < 1) {
      return null;
    }
    $sql = 'SELECT b.id, b.customer_id, b.check_in, b.check_out, b.payment_amount, b.auth2, b.notes, b.room_id, b.portal_rate_plan_id,
            b.future_status_id, b.present_status_id, b.history_status_id,
            c.name AS customer_name, c.email AS customer_email, c.phone AS customer_phone,
            h.id AS hotel_id, h.name AS hotel_name, h.location AS hotel_location, h.phone AS hotel_phone,
            h.contact_email AS hotel_contact_email, h.reservations_email AS hotel_reservations_email,
            h.website_url AS hotel_website_url, h.currency_code,
            r.name AS room_name, r.room_type_id, COALESCE(bp.price_per_night, 0.00) AS price_per_night,
            t.name AS type_name, t.bed_summary,
            rp.name AS portal_rate_plan_name, rp.rate_plan_slug AS portal_rate_plan_slug
            FROM hotel_bookings b
            INNER JOIN customers c ON c.id = b.customer_id AND c.company_id = b.company_id
            INNER JOIN hotel_booking_rooms r ON r.id = b.room_id AND r.company_id = b.company_id
            INNER JOIN hotel_booking_hotels h ON h.id = r.hotel_id AND h.company_id = r.company_id
            LEFT JOIN booking_rooms_types t ON t.id = r.room_type_id AND t.company_id = r.company_id
            LEFT JOIN hotel_booking_room_type_base_prices bp ON bp.company_id = r.company_id AND bp.hotel_id = r.hotel_id AND bp.room_type_id = r.room_type_id AND bp.deleted_at IS NULL
            LEFT JOIN hotel_booking_portal_rate_plans rp ON rp.id = b.portal_rate_plan_id AND rp.company_id = b.company_id AND rp.deleted_at IS NULL
            WHERE b.id = ? AND b.company_id = ? AND b.deleted_at IS NULL LIMIT 1';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
      return null;
    }
    mysqli_stmt_bind_param($stmt, 'ii', $bookingId, $companyId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    return $row ?: null;
  }
}

if (!function_exists('itm_hotel_booking_portal_load_confirmation_group_rows')) {
  /**
   * Multi-room stays share one guest-facing confirmation id (lowest booking id) and auth2.
   *
   * @return array<int,array>
   */
  function itm_hotel_booking_portal_load_confirmation_group_rows($conn, $companyId, array $primaryRow) {
    $companyId = (int) $companyId;
    $primaryId = (int) ($primaryRow['id'] ?? 0);
    if ($primaryId < 1) {
      return [];
    }
    $customerId = (int) ($primaryRow['customer_id'] ?? 0);
    $auth2 = itm_hotel_booking_normalize_auth2($primaryRow['auth2'] ?? '');
    $checkIn = (string) ($primaryRow['check_in'] ?? '');
    $checkOut = (string) ($primaryRow['check_out'] ?? '');
    if ($customerId < 1 || $auth2 === '' || $checkIn === '' || $checkOut === '') {
      $single = itm_hotel_booking_portal_fetch_confirmation_booking_row($conn, $companyId, $primaryId);
      return $single ? [$single] : [];
    }
    $sql = 'SELECT b.id, b.customer_id, b.check_in, b.check_out, b.payment_amount, b.auth2, b.notes, b.room_id, b.portal_rate_plan_id,
            b.future_status_id, b.present_status_id, b.history_status_id,
            c.name AS customer_name, c.email AS customer_email, c.phone AS customer_phone,
            h.id AS hotel_id, h.name AS hotel_name, h.location AS hotel_location, h.phone AS hotel_phone,
            h.contact_email AS hotel_contact_email, h.reservations_email AS hotel_reservations_email,
            h.website_url AS hotel_website_url, h.currency_code,
            r.name AS room_name, r.room_type_id, COALESCE(bp.price_per_night, 0.00) AS price_per_night,
            t.name AS type_name, t.bed_summary,
            rp.name AS portal_rate_plan_name, rp.rate_plan_slug AS portal_rate_plan_slug
            FROM hotel_bookings b
            INNER JOIN customers c ON c.id = b.customer_id AND c.company_id = b.company_id
            INNER JOIN hotel_booking_rooms r ON r.id = b.room_id AND r.company_id = b.company_id
            INNER JOIN hotel_booking_hotels h ON h.id = r.hotel_id AND h.company_id = r.company_id
            LEFT JOIN booking_rooms_types t ON t.id = r.room_type_id AND t.company_id = r.company_id
            LEFT JOIN hotel_booking_room_type_base_prices bp ON bp.company_id = r.company_id AND bp.hotel_id = r.hotel_id AND bp.room_type_id = r.room_type_id AND bp.deleted_at IS NULL
            LEFT JOIN hotel_booking_portal_rate_plans rp ON rp.id = b.portal_rate_plan_id AND rp.company_id = b.company_id AND rp.deleted_at IS NULL
            WHERE b.company_id = ? AND b.customer_id = ? AND b.auth2 = ? AND b.check_in = ? AND b.check_out = ? AND b.deleted_at IS NULL
            ORDER BY b.id ASC';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
      $single = itm_hotel_booking_portal_fetch_confirmation_booking_row($conn, $companyId, $primaryId);
      return $single ? [$single] : [];
    }
    mysqli_stmt_bind_param($stmt, 'iisss', $companyId, $customerId, $auth2, $checkIn, $checkOut);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($res && ($row = mysqli_fetch_assoc($res))) {
      $rows[] = $row;
    }
    mysqli_stmt_close($stmt);
    if ($rows === []) {
      $single = itm_hotel_booking_portal_fetch_confirmation_booking_row($conn, $companyId, $primaryId);
      return $single ? [$single] : [];
    }
    return $rows;
  }
}

if (!function_exists('itm_hotel_booking_portal_build_confirmation_email_rows_html')) {
  /**
   * @param array<int,array> $groupRows
   */
  function itm_hotel_booking_portal_build_confirmation_email_rows_html(array $bookingRow, array $groupRows = [], array $occupancy = null, $conn = null, $companyId = 0) {
    if ($groupRows === []) {
      $groupRows = [$bookingRow];
    }
    $primaryId = itm_hotel_booking_portal_confirmation_primary_id($groupRows);
    if ($primaryId < 1) {
      $primaryId = (int) ($bookingRow['id'] ?? 0);
    }
    $guestName = trim((string) ($bookingRow['customer_name'] ?? ''));
    $guestEmail = trim((string) ($bookingRow['customer_email'] ?? ''));
    $guestPhone = trim((string) ($bookingRow['customer_phone'] ?? ''));
    $hotelName = trim((string) ($bookingRow['hotel_name'] ?? ''));
    $checkInIso = (string) ($bookingRow['check_in'] ?? '');
    $checkOutIso = (string) ($bookingRow['check_out'] ?? '');
    $checkInDisplay = $checkInIso !== '' && function_exists('itm_format_hotel_date_display')
      ? itm_format_hotel_date_display($checkInIso)
      : $checkInIso;
    $checkOutDisplay = $checkOutIso !== '' && function_exists('itm_format_hotel_date_display')
      ? itm_format_hotel_date_display($checkOutIso)
      : $checkOutIso;
    $currency = (string) ($bookingRow['currency_code'] ?? 'EUR');
    $auth2Display = itm_hotel_booking_normalize_auth2($bookingRow['auth2'] ?? '');
    $planLabel = trim((string) ($bookingRow['portal_rate_plan_name'] ?? ''));
    if ($planLabel === '') {
      $slug = strtolower((string) ($bookingRow['portal_rate_plan_slug'] ?? ''));
      $planLabel = $slug === 'breakfast' ? 'Breakfast included' : 'Best available rate';
    }
    if (!is_array($occupancy)) {
      $occupancy = itm_hotel_booking_portal_parse_occupancy_meta_from_notes((string) ($bookingRow['notes'] ?? ''));
    }
    if (!is_array($occupancy)) {
      $occupancy = itm_hotel_booking_portal_parse_occupancy(['rooms' => 1, 'adults' => 2]);
    }
    $occupancy = itm_hotel_booking_portal_parse_occupancy($occupancy);
    $occupancyLabel = itm_hotel_booking_portal_occupancy_label($occupancy);
    $nights = 1;
    if ($checkInIso !== '' && $checkOutIso !== '' && $checkOutIso > $checkInIso) {
      $in = DateTime::createFromFormat('Y-m-d', $checkInIso);
      $out = DateTime::createFromFormat('Y-m-d', $checkOutIso);
      if ($in && $out) {
        $nights = max(1, (int) $in->diff($out)->days);
      }
    }
    $nightsLabel = $nights === 1 ? '1 night' : $nights . ' nights';
    $amountDisplay = itm_hotel_booking_portal_format_money_display(
      itm_hotel_booking_portal_confirmation_group_total($groupRows),
      $currency
    );

    $rows = [];
    $rows[] = ['Confirmation number', (string) $primaryId];
    if ($auth2Display !== '') {
      $rows[] = ['Auth code', $auth2Display];
    }
    if ($hotelName !== '') {
      $rows[] = ['Hotel', $hotelName];
    }
    if ($guestName !== '') {
      $rows[] = ['Guest', $guestName];
    }
    if ($guestEmail !== '') {
      $rows[] = ['Email', $guestEmail];
    }
    if ($guestPhone !== '') {
      $rows[] = ['Phone', $guestPhone];
    }
    if (count($groupRows) > 1) {
      $lineDisplayAmounts = ($conn && $companyId > 0 && function_exists('itm_hotel_booking_portal_confirmation_group_room_display_amounts'))
        ? itm_hotel_booking_portal_confirmation_group_room_display_amounts($conn, $companyId, $groupRows, $occupancy)
        : [];
      foreach ($groupRows as $idx => $lineRow) {
        $lineLabel = itm_hotel_booking_portal_confirmation_room_label_from_row($lineRow);
        $lineAmountValue = isset($lineDisplayAmounts[$idx])
          ? (float) $lineDisplayAmounts[$idx]
          : (float) ($lineRow['payment_amount'] ?? 0);
        $lineAmount = itm_hotel_booking_portal_format_money_display($lineAmountValue, $currency);
        $rows[] = ['Room ' . ((int) $idx + 1), $lineLabel . ' — ' . $lineAmount];
      }
    } else {
      $rows[] = ['Room', itm_hotel_booking_portal_confirmation_room_label_from_row($groupRows[0])];
    }
    $rows[] = ['Rate', $planLabel];
    $rows[] = ['Check-in', $checkInDisplay];
    $rows[] = ['Check-out', $checkOutDisplay];
    $rows[] = ['Nights', $nightsLabel];
    $rows[] = ['Guests', $occupancyLabel];
    $notesMeta = function_exists('itm_hotel_booking_portal_parse_booking_notes_meta')
      ? itm_hotel_booking_portal_parse_booking_notes_meta((string) ($bookingRow['notes'] ?? ''))
      : ['traveling_with_pet' => false, 'service_animal' => false, 'guest_comments' => '', 'room_upgrade' => ['accepted' => false, 'title' => '', 'pitch' => '', 'per_night' => 0.0]];
    $roomsNeeded = max(1, (int) ($occupancy['rooms'] ?? 1));
    if ($roomsNeeded === 1 && !empty($notesMeta['room_upgrade']['accepted'])) {
      $upgradeTitle = trim((string) ($notesMeta['room_upgrade']['title'] ?? ''));
      if ($upgradeTitle !== '') {
        $rows[] = ['Room upgrade', $upgradeTitle];
      }
      $upgradePerNight = (float) ($notesMeta['room_upgrade']['per_night'] ?? 0);
      if ($upgradePerNight > 0) {
        $rows[] = ['Upgrade surcharge', '+' . itm_hotel_booking_portal_format_money_display($upgradePerNight, $currency) . ' per night'];
      }
    }
    if (!empty($notesMeta['service_animal'])) {
      $rows[] = ['Service animal', 'Yes'];
    }
    if (trim((string) ($notesMeta['guest_comments'] ?? '')) !== '') {
      $rows[] = ['Additional comments', trim((string) $notesMeta['guest_comments'])];
    }
    $petFeeTotal = 0.0;
    $companyId = (int) $companyId;
    if ($companyId < 1) {
      $companyId = (int) ($bookingRow['company_id'] ?? ($groupRows[0]['company_id'] ?? 0));
    }
    if ($conn && $companyId > 0 && function_exists('itm_hotel_booking_portal_confirmation_pet_fee')) {
      $petFeeTotal = itm_hotel_booking_portal_confirmation_pet_fee($conn, $companyId, $bookingRow, $checkInIso, $checkOutIso);
    }
    if ($petFeeTotal > 0) {
      $rows[] = ['Traveling with a pet', itm_hotel_booking_portal_format_money_display($petFeeTotal, $currency)];
    }
    $rows[] = ['Total', $amountDisplay];

    $html = '<table role="presentation" cellpadding="0" cellspacing="0" style="border-collapse:collapse;width:100%;max-width:520px;">';
    foreach ($rows as $row) {
      $html .= '<tr><td style="padding:4px 12px 4px 0;font-weight:600;vertical-align:top;">'
        . htmlspecialchars((string) $row[0], ENT_QUOTES, 'UTF-8')
        . '</td><td style="padding:4px 0;vertical-align:top;">'
        . htmlspecialchars((string) $row[1], ENT_QUOTES, 'UTF-8')
        . '</td></tr>';
    }
    $html .= '</table>';
    return $html;
  }
}

if (!function_exists('itm_hotel_booking_portal_send_booking_confirmation_emails')) {
  /**
   * Guest + hotel reservations desk copies after Step 4 INSERT.
   *
   * @param array<int,int> $companionBookingIds
   * @return array{ok:bool,guest_sent:bool,hotel_sent:bool}
   */
  function itm_hotel_booking_portal_send_booking_confirmation_emails($conn, $companyId, array $bookingRow, array $options = []) {
    $companyId = (int) $companyId;
    $reservationId = (int) ($bookingRow['id'] ?? 0);
    $guestEmail = trim((string) ($bookingRow['customer_email'] ?? ''));
    $hotelName = trim((string) ($bookingRow['hotel_name'] ?? ''));
    $reservationsEmail = trim((string) ($bookingRow['hotel_reservations_email'] ?? ''));
    $guestSent = false;
    $hotelSent = false;
    if ($companyId < 1 || $reservationId < 1 || !function_exists('itm_send_email')) {
      // #region agent log
      @file_put_contents(dirname(__DIR__) . '/debug-44bff2.log', json_encode([
        'sessionId' => '44bff2',
        'timestamp' => (int) round(microtime(true) * 1000),
        'location' => 'includes/itm_hotel_booking.php:send_booking_confirmation_emails',
        'message' => 'confirmation email skipped — invalid context',
        'data' => ['companyId' => $companyId, 'reservationId' => $reservationId],
        'hypothesisId' => 'G',
        'runId' => 'step4-email',
      ]) . "\n", FILE_APPEND);
      // #endregion
      return ['ok' => false, 'guest_sent' => false, 'hotel_sent' => false];
    }

    $groupRows = itm_hotel_booking_portal_load_confirmation_group_rows($conn, $companyId, $bookingRow);
    if ($groupRows === []) {
      $groupRows = [$bookingRow];
    }
    $primaryId = itm_hotel_booking_portal_confirmation_primary_id($groupRows);
    if ($primaryId < 1) {
      $primaryId = $reservationId;
    }
    $occupancy = isset($options['occupancy']) && is_array($options['occupancy'])
      ? itm_hotel_booking_portal_parse_occupancy($options['occupancy'])
      : null;
    $manageUrl = itm_hotel_booking_portal_confirmation_email_manage_url($conn, $companyId);
    $detailsHtml = itm_hotel_booking_portal_build_confirmation_email_rows_html($bookingRow, $groupRows, $occupancy, $conn, $companyId);
    $guestName = trim((string) ($bookingRow['customer_name'] ?? ''));

    if ($guestEmail !== '' && filter_var($guestEmail, FILTER_VALIDATE_EMAIL)) {
      $guestSubject = 'Your reservation confirmation'
        . ($hotelName !== '' ? ' — ' . $hotelName : '')
        . ' #' . $primaryId;
      $guestBody = '<p>Thank you'
        . ($guestName !== '' ? ', ' . htmlspecialchars($guestName, ENT_QUOTES, 'UTF-8') : '')
        . '. Your stay is confirmed.</p>'
        . $detailsHtml
        . '<p style="margin-top:16px;">Use your confirmation number, last name, and auth code on <strong>Manage my booking</strong> to view or change this reservation.</p>';
      $guestOptions = itm_hotel_booking_portal_confirmation_email_template_options(
        $hotelName,
        $reservationsEmail,
        $manageUrl,
        'Reservation confirmed'
      );
      $guestSent = (bool) itm_send_email($guestEmail, $guestSubject, $guestBody, $companyId, $guestOptions);
    }

    if ($reservationsEmail !== '' && filter_var($reservationsEmail, FILTER_VALIDATE_EMAIL)) {
      $hotelSubject = 'New portal reservation #' . $primaryId
        . ($guestName !== '' ? ' — ' . $guestName : '');
      $hotelBody = '<p>A new booking was completed on the public portal.</p>' . $detailsHtml;
      $hotelOptions = itm_hotel_booking_portal_confirmation_email_template_options(
        $hotelName,
        $reservationsEmail,
        $manageUrl,
        'New portal reservation'
      );
      $hotelSent = (bool) itm_send_email($reservationsEmail, $hotelSubject, $hotelBody, $companyId, $hotelOptions);
    }

    // #region agent log
    @file_put_contents(dirname(__DIR__) . '/debug-44bff2.log', json_encode([
      'sessionId' => '44bff2',
      'timestamp' => (int) round(microtime(true) * 1000),
      'location' => 'includes/itm_hotel_booking.php:send_booking_confirmation_emails',
      'message' => 'confirmation email dispatch',
        'data' => [
        'reservationId' => $reservationId,
        'primaryId' => $primaryId,
        'groupCount' => count($groupRows),
        'guestSent' => $guestSent,
        'hotelSent' => $hotelSent,
        'hasReservationsEmail' => $reservationsEmail !== '',
      ],
      'hypothesisId' => 'G',
      'runId' => 'step4-email',
    ]) . "\n", FILE_APPEND);
    // #endregion

    return ['ok' => $guestSent || $hotelSent, 'guest_sent' => $guestSent, 'hotel_sent' => $hotelSent];
  }
}

if (!function_exists('itm_hotel_booking_portal_manage_otp_verify')) {
  /**
   * @return array{ok:bool,error?:string,reservation_id?:int,company_id?:int}
   */
  function itm_hotel_booking_portal_manage_otp_verify($otp) {
    $key = itm_hotel_booking_portal_manage_otp_session_key();
    $state = (isset($_SESSION[$key]) && is_array($_SESSION[$key])) ? $_SESSION[$key] : null;
    if (!is_array($state) || time() > (int) ($state['expires_at'] ?? 0)) {
      itm_hotel_booking_portal_manage_otp_clear();
      return ['ok' => false, 'error' => 'Verification expired. Please start again.'];
    }
    $otpDigits = preg_replace('/\D+/', '', (string) $otp);
    if ($otpDigits === null || strlen($otpDigits) !== 6) {
      return ['ok' => false, 'error' => 'Enter the 6-digit code from your email.'];
    }
    if (!hash_equals((string) ($state['otp_hash'] ?? ''), hash('sha256', $otpDigits))) {
      return ['ok' => false, 'error' => 'Invalid verification code.'];
    }
    $_SESSION[$key]['verified'] = true;
    return [
      'ok' => true,
      'reservation_id' => (int) ($state['reservation_id'] ?? 0),
      'company_id' => (int) ($state['company_id'] ?? 0),
    ];
  }
}

if (!function_exists('itm_hotel_booking_portal_manage_otp_is_verified')) {
  function itm_hotel_booking_portal_manage_otp_is_verified($companyId, $reservationId) {
    $key = itm_hotel_booking_portal_manage_otp_session_key();
    $state = (isset($_SESSION[$key]) && is_array($_SESSION[$key])) ? $_SESSION[$key] : null;
    if (!is_array($state) || empty($state['verified']) || time() > (int) ($state['expires_at'] ?? 0)) {
      return false;
    }
    return (int) ($state['company_id'] ?? 0) === (int) $companyId && (int) ($state['reservation_id'] ?? 0) === (int) $reservationId;
  }
}

/**
 * Why: Overlap check + INSERT must be atomic under a room row lock to prevent concurrent double-books.
 *
 * @return array{ok:bool,booking_id?:int,error?:string}
 */
if (!function_exists('itm_hotel_booking_portal_insert_booking_locked')) {
  function itm_hotel_booking_portal_insert_booking_locked(
    $conn,
    $companyId,
    $customerId,
    $roomId,
    $checkIn,
    $checkOut,
    $amount,
    $auth2,
    $portalRatePlanId,
    $notes,
    $bookingColor,
    $futureStatusId,
    $presentStatusId,
    $historyStatusId
  ) {
    $companyId = (int) $companyId;
    $customerId = (int) $customerId;
    $roomId = (int) $roomId;
    $checkIn = (string) $checkIn;
    $checkOut = (string) $checkOut;
    $amount = (float) $amount;
    $auth2 = itm_hotel_booking_normalize_auth2($auth2);
    $portalRatePlanId = (int) $portalRatePlanId;
    $notes = (string) $notes;
    $bookingColor = (string) $bookingColor;
    $futureStatusId = (int) $futureStatusId;
    $presentStatusId = (int) $presentStatusId;
    $historyStatusId = (int) $historyStatusId;

    if ($companyId < 1 || $customerId < 1 || $roomId < 1 || $checkIn === '' || $checkOut === '' || $checkOut <= $checkIn || $auth2 === '') {
      return ['ok' => false, 'error' => 'Invalid booking payload.'];
    }
    if (!mysqli_begin_transaction($conn)) {
      return ['ok' => false, 'error' => 'Booking failed.'];
    }

    $lock = mysqli_prepare($conn, 'SELECT id FROM hotel_booking_rooms WHERE id = ? AND company_id = ? AND deleted_at IS NULL LIMIT 1 FOR UPDATE');
    if (!$lock) {
      mysqli_rollback($conn);
      return ['ok' => false, 'error' => 'Booking failed.'];
    }
    mysqli_stmt_bind_param($lock, 'ii', $roomId, $companyId);
    if (!mysqli_stmt_execute($lock)) {
      mysqli_stmt_close($lock);
      mysqli_rollback($conn);
      return ['ok' => false, 'error' => 'Booking failed.'];
    }
    $lockRes = mysqli_stmt_get_result($lock);
    $lockedRow = $lockRes ? mysqli_fetch_assoc($lockRes) : null;
    mysqli_stmt_close($lock);
    if (!$lockedRow) {
      mysqli_rollback($conn);
      return ['ok' => false, 'error' => 'Room not available.'];
    }

    if (itm_hotel_booking_room_unavailable_for_stay($conn, $companyId, $roomId, $checkIn, $checkOut, 0, null)) {
      mysqli_rollback($conn);
      return ['ok' => false, 'error' => 'Room not available.'];
    }

    $ins = mysqli_prepare(
      $conn,
      'INSERT INTO hotel_bookings (company_id, customer_id, room_id, check_in, check_out, payment_amount, auth2, portal_rate_plan_id, notes, booking_color, future_status_id, present_status_id, history_status_id, active, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NULLIF(?,0), ?, ?, NULLIF(?,0), NULLIF(?,0), NULLIF(?,0), 1, NOW())'
    );
    if (!$ins) {
      mysqli_rollback($conn);
      return ['ok' => false, 'error' => 'Booking failed.'];
    }
    mysqli_stmt_bind_param(
      $ins,
      'iiissdsissiii',
      $companyId,
      $customerId,
      $roomId,
      $checkIn,
      $checkOut,
      $amount,
      $auth2,
      $portalRatePlanId,
      $notes,
      $bookingColor,
      $futureStatusId,
      $presentStatusId,
      $historyStatusId
    );
    if (!mysqli_stmt_execute($ins)) {
      mysqli_stmt_close($ins);
      mysqli_rollback($conn);
      return ['ok' => false, 'error' => 'Booking failed.'];
    }
    $bookingId = (int) mysqli_insert_id($conn);
    mysqli_stmt_close($ins);
    if ($bookingId < 1 || !mysqli_commit($conn)) {
      mysqli_rollback($conn);
      return ['ok' => false, 'error' => 'Booking failed.'];
    }
    return ['ok' => true, 'booking_id' => $bookingId];
  }
}

if (!function_exists('itm_hotel_booking_portal_insert_stay_bookings_locked')) {
  /**
   * @return array{ok:bool,booking_id?:int,booking_ids?:int[],error?:string}
   */
  function itm_hotel_booking_portal_insert_stay_bookings_locked(
    $conn,
    $companyId,
    $customerId,
    array $draft,
    $checkIn,
    $checkOut,
    $totalAmount,
    $auth2,
    $portalRatePlanId,
    $notes,
    $bookingColor,
    $futureStatusId,
    $presentStatusId,
    $historyStatusId
  ) {
    $roomLines = itm_hotel_booking_portal_room_lines_from_draft($draft);
    $hotelId = (int) ($draft['hotel_id'] ?? 0);
    if (count($roomLines) < 2) {
      $roomId = (int) ($draft['room_id'] ?? ($roomLines[0]['room_id'] ?? 0));
      $single = itm_hotel_booking_portal_insert_booking_locked(
        $conn,
        $companyId,
        $customerId,
        $roomId,
        $checkIn,
        $checkOut,
        $totalAmount,
        $auth2,
        $portalRatePlanId,
        $notes,
        $bookingColor,
        $futureStatusId,
        $presentStatusId,
        $historyStatusId
      );
      if (!empty($single['ok'])) {
        $single['booking_ids'] = [(int) ($single['booking_id'] ?? 0)];
      }
      return $single;
    }
    $allocated = [];
    $bookingIds = [];
    $lineCount = count($roomLines);
    $occupancy = is_array($draft['occupancy'] ?? null)
      ? $draft['occupancy']
      : itm_hotel_booking_portal_parse_occupancy_meta_from_notes((string) $notes);
    if (!is_array($occupancy)) {
      $occupancy = itm_hotel_booking_portal_parse_occupancy(['rooms' => $lineCount, 'adults' => 2]);
    }
    $amountShares = itm_hotel_booking_portal_resolve_multi_room_insert_payment_shares(
      $conn,
      $companyId,
      $totalAmount,
      $draft,
      $checkIn,
      $checkOut,
      $occupancy
    );
    if (count($amountShares) !== $lineCount) {
      $amountShares = [];
      $running = 0.0;
      $share = round(((float) $totalAmount) / $lineCount, 2);
      for ($i = 0; $i < $lineCount; $i++) {
        if ($i === $lineCount - 1) {
          $amountShares[] = round((float) $totalAmount - $running, 2);
        } else {
          $amountShares[] = $share;
          $running += $share;
        }
      }
    }
    foreach ($roomLines as $idx => $line) {
      $roomId = itm_hotel_booking_portal_allocate_room_id_for_line($conn, $companyId, $hotelId, $line, $checkIn, $checkOut, $allocated);
      if ($roomId < 1) {
        return ['ok' => false, 'error' => 'Room not available.'];
      }
      $allocated[] = $roomId;
      $lineNotes = (string) $notes;
      $result = itm_hotel_booking_portal_insert_booking_locked(
        $conn,
        $companyId,
        $customerId,
        $roomId,
        $checkIn,
        $checkOut,
        (float) ($amountShares[$idx] ?? 0),
        $auth2,
        $portalRatePlanId,
        $lineNotes,
        $bookingColor,
        $futureStatusId,
        $presentStatusId,
        $historyStatusId
      );
      if (empty($result['ok']) || (int) ($result['booking_id'] ?? 0) < 1) {
        return ['ok' => false, 'error' => (string) ($result['error'] ?? 'Booking failed.')];
      }
      $bookingIds[] = (int) $result['booking_id'];
    }
    if (count($bookingIds) > 1) {
      $primaryId = (int) $bookingIds[0];
      $groupNote = "\nMulti-room stay — confirmation #" . $primaryId . '.';
      $upd = mysqli_prepare($conn, 'UPDATE hotel_bookings SET notes = CONCAT(notes, ?) WHERE company_id = ? AND id = ? LIMIT 1');
      if ($upd) {
        foreach ($bookingIds as $bid) {
          $bid = (int) $bid;
          mysqli_stmt_bind_param($upd, 'sii', $groupNote, $companyId, $bid);
          mysqli_stmt_execute($upd);
        }
        mysqli_stmt_close($upd);
      }
    }
    return ['ok' => true, 'booking_id' => (int) $bookingIds[0], 'booking_ids' => $bookingIds];
  }
}
