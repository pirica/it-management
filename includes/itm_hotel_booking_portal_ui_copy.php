<?php

/**
 * Admin-configurable guest-facing UI copy for the public booking portal.
 */

if (!function_exists('itm_hotel_booking_portal_ui_copy_registry')) {
  function itm_hotel_booking_portal_ui_copy_registry() {
    static $registry = null;
    if ($registry === null) {
      $dataFile = __DIR__ . '/itm_hotel_booking_portal_ui_copy_registry_data.php';
      $registry = is_file($dataFile) ? require $dataFile : [];
      if (!is_array($registry)) {
        $registry = [];
      }
    }
    return $registry;
  }
}

if (!function_exists('itm_hotel_booking_portal_ui_copy_registry_by_column')) {
  function itm_hotel_booking_portal_ui_copy_registry_by_column() {
    static $map = null;
    if ($map === null) {
      $map = [];
      foreach (itm_hotel_booking_portal_ui_copy_registry() as $row) {
        $col = (string) ($row['column'] ?? '');
        if ($col !== '') {
          $map[$col] = $row;
        }
      }
    }
    return $map;
  }
}

if (!function_exists('itm_hotel_booking_portal_ui_copy_default_for_column')) {
  function itm_hotel_booking_portal_ui_copy_default_for_column($column) {
    $column = (string) $column;
    $map = itm_hotel_booking_portal_ui_copy_registry_by_column();
    return isset($map[$column]['default']) ? (string) $map[$column]['default'] : '';
  }
}

if (!function_exists('itm_hotel_booking_portal_ui_copy_from_settings')) {
  /**
   * @param array<string,mixed> $settings
   * @param string $column portal_ui_* column name
   * @param array<string,string|int|float> $vars placeholder replacements
   */
  function itm_hotel_booking_portal_ui_copy_from_settings($settings, $column, array $vars = []) {
    $column = (string) $column;
    $settings = is_array($settings) ? $settings : [];
    $map = itm_hotel_booking_portal_ui_copy_registry_by_column();
    $default = isset($map[$column]['default']) ? (string) $map[$column]['default'] : '';
    $raw = trim((string) ($settings[$column] ?? ''));
    $text = $raw !== '' ? $raw : $default;
    if ($vars !== []) {
      foreach ($vars as $key => $value) {
        $placeholder = (string) $key;
        if ($placeholder !== '' && strpos($placeholder, '{') !== 0) {
          $placeholder = '{' . $placeholder . '}';
        }
        $text = str_replace($placeholder, (string) $value, $text);
      }
    }
    return $text;
  }
}

if (!function_exists('itm_hotel_booking_portal_ui_copy_short_key')) {
  function itm_hotel_booking_portal_ui_copy_short_key($column) {
    $column = (string) $column;
    if (strpos($column, 'portal_ui_') === 0) {
      return substr($column, strlen('portal_ui_'));
    }
    return $column;
  }
}

if (!function_exists('itm_hotel_booking_portal_ui_copy_map_for_js')) {
  /**
   * Flat map for HB_SETTINGS.ui_copy — keys without portal_ui_ prefix.
   *
   * @param array<string,mixed> $settings
   * @return array<string,string>
   */
  function itm_hotel_booking_portal_ui_copy_map_for_js($settings) {
    $settings = is_array($settings) ? $settings : [];
    $out = [];
    foreach (itm_hotel_booking_portal_ui_copy_registry() as $row) {
      $column = (string) ($row['column'] ?? '');
      if ($column === '') {
        continue;
      }
      $out[itm_hotel_booking_portal_ui_copy_short_key($column)] = itm_hotel_booking_portal_ui_copy_from_settings($settings, $column);
    }
    return $out;
  }
}

if (!function_exists('itm_hotel_booking_portal_ui_copy_sql_column_definition')) {
  function itm_hotel_booking_portal_ui_copy_sql_column_definition(array $row) {
    $column = (string) ($row['column'] ?? '');
    if ($column === '' || !preg_match('/^[a-z0-9_]+$/', $column)) {
      return '';
    }
    // Why: hotel_booking_settings is already wide — store portal_ui_* as TEXT so row size stays under 65535.
    return '  `' . $column . '` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,';
  }
}

if (!function_exists('itm_hotel_booking_portal_ui_copy_validate_post_values')) {
  /**
   * @param array<string,mixed> $post
   * @return array{values:array<string,string>,errors:list<string>}
   */
  function itm_hotel_booking_portal_ui_copy_validate_post_values(array $post) {
    $values = [];
    $errors = [];
    foreach (itm_hotel_booking_portal_ui_copy_registry() as $row) {
      $column = (string) ($row['column'] ?? '');
      if ($column === '') {
        continue;
      }
      $default = (string) ($row['default'] ?? '');
      $maxlen = $row['maxlen'] ?? 255;
      $raw = isset($post[$column]) ? (string) $post[$column] : $default;
      if ($maxlen === 'text') {
        $value = trim($raw);
      } else {
        $value = mb_substr(trim($raw), 0, max(1, min(500, (int) $maxlen)));
      }
      if (!empty($row['required']) && $value === '') {
        $errors[] = (string) ($row['label'] ?? $column) . ' is required.';
        $value = $default;
      }
      if ($value === '' && $default !== '') {
        $value = $default;
      }
      $placeholders = $row['placeholders'] ?? [];
      if (is_array($placeholders)) {
        foreach ($placeholders as $ph) {
          $ph = (string) $ph;
          if ($ph !== '' && strpos($value, $ph) === false) {
            $errors[] = (string) ($row['label'] ?? $column) . ' must include ' . $ph . '.';
            break;
          }
        }
      }
      $values[$column] = $value;
    }
    return ['values' => $values, 'errors' => $errors];
  }
}

if (!function_exists('itm_hotel_booking_portal_ui_copy_storage_table_for_section')) {
  /**
   * InnoDB limits variable-length columns per table (~8126 bytes) — split 267 portal_ui_* across four 1:1 tables.
   */
  function itm_hotel_booking_portal_ui_copy_storage_table_for_section($section) {
    $section = (string) $section;
    if ($section === 'home' || $section === 'chrome') {
      return 'hotel_booking_portal_ui_copy_home';
    }
    if ($section === 'step1') {
      return 'hotel_booking_portal_ui_copy_step1';
    }
    if ($section === 'step2' || $section === 'step3' || $section === 'step4') {
      return 'hotel_booking_portal_ui_copy_checkout';
    }
    if ($section === 'confirm' || $section === 'manage') {
      return 'hotel_booking_portal_ui_copy_confirm';
    }
    return 'hotel_booking_portal_ui_copy_confirm';
  }
}

if (!function_exists('itm_hotel_booking_portal_ui_copy_storage_tables')) {
  /** @return list<string> */
  function itm_hotel_booking_portal_ui_copy_storage_tables() {
    return [
      'hotel_booking_portal_ui_copy_home',
      'hotel_booking_portal_ui_copy_step1',
      'hotel_booking_portal_ui_copy_checkout',
      'hotel_booking_portal_ui_copy_confirm',
    ];
  }
}

if (!function_exists('itm_hotel_booking_portal_ui_copy_columns_for_table')) {
  /** @return list<string> */
  function itm_hotel_booking_portal_ui_copy_columns_for_table($table) {
    $table = (string) $table;
    $cols = [];
    foreach (itm_hotel_booking_portal_ui_copy_registry() as $row) {
      $column = (string) ($row['column'] ?? '');
      $section = (string) ($row['section'] ?? '');
      if ($column === '' || itm_hotel_booking_portal_ui_copy_storage_table_for_section($section) !== $table) {
        continue;
      }
      $cols[] = $column;
    }
    return $cols;
  }
}

if (!function_exists('itm_hotel_booking_portal_ui_copy_sql_create_table')) {
  function itm_hotel_booking_portal_ui_copy_sql_create_table($table) {
    $table = (string) $table;
    if (!in_array($table, itm_hotel_booking_portal_ui_copy_storage_tables(), true)) {
      return '';
    }
    $lines = [
      'DROP TABLE IF EXISTS `' . $table . '`;',
      'CREATE TABLE `' . $table . '` (',
      '  `id` int NOT NULL AUTO_INCREMENT,',
      '  `company_id` int NOT NULL,',
    ];
    foreach (itm_hotel_booking_portal_ui_copy_registry() as $row) {
      $section = (string) ($row['section'] ?? '');
      if (itm_hotel_booking_portal_ui_copy_storage_table_for_section($section) !== $table) {
        continue;
      }
      $ddl = itm_hotel_booking_portal_ui_copy_sql_column_definition($row);
      if ($ddl !== '') {
        $lines[] = $ddl;
      }
    }
    $lines[] = '  `active` tinyint(1) DEFAULT \'1\',';
    $lines[] = '  `deleted_by` int DEFAULT NULL,';
    $lines[] = '  `deleted_at` timestamp NULL DEFAULT NULL,';
    $lines[] = '  `created_by` int DEFAULT NULL,';
    $lines[] = '  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,';
    $lines[] = '  `updated_by` int DEFAULT NULL,';
    $lines[] = '  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,';
    $lines[] = '  PRIMARY KEY (`id`),';
    $lines[] = '  UNIQUE KEY `uq_' . $table . '_company` (`company_id`),';
    $lines[] = '  CONSTRAINT `' . $table . '_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE';
    $lines[] = ') ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;';
    return implode("\n", $lines);
  }
}

if (!function_exists('itm_hotel_booking_portal_ui_copy_merge_into_settings')) {
  /**
   * @param array<string,mixed> $settings
   */
  function itm_hotel_booking_portal_ui_copy_merge_into_settings($conn, $companyId, array $settings) {
    $companyId = (int) $companyId;
    if (!$conn || $companyId <= 0) {
      return $settings;
    }
    foreach (itm_hotel_booking_portal_ui_copy_storage_tables() as $table) {
      $sql = 'SELECT * FROM `' . $table . '` WHERE company_id = ? AND deleted_at IS NULL LIMIT 1';
      $stmt = mysqli_prepare($conn, $sql);
      if (!$stmt) {
        continue;
      }
      mysqli_stmt_bind_param($stmt, 'i', $companyId);
      mysqli_stmt_execute($stmt);
      $res = mysqli_stmt_get_result($stmt);
      $row = $res ? mysqli_fetch_assoc($res) : null;
      mysqli_stmt_close($stmt);
      if (is_array($row)) {
        foreach ($row as $key => $value) {
          if (strpos((string) $key, 'portal_ui_') === 0) {
            $settings[$key] = $value;
          }
        }
      }
    }
    return $settings;
  }
}

if (!function_exists('itm_hotel_booking_portal_ui_copy_build_update_sql_for_table')) {
  /**
   * @param array<string,string> $values
   * @return array{sql:string,types:string,params:list<string>}|null
   */
  function itm_hotel_booking_portal_ui_copy_build_update_sql_for_table($table, array $values) {
    $table = (string) $table;
    $allowed = itm_hotel_booking_portal_ui_copy_columns_for_table($table);
    if ($allowed === []) {
      return null;
    }
    $columns = [];
    $types = '';
    $params = [];
    foreach ($allowed as $column) {
      if (!array_key_exists($column, $values)) {
        continue;
      }
      $columns[] = '`' . $column . '` = ?';
      $types .= 's';
      $params[] = (string) $values[$column];
    }
    if ($columns === []) {
      return null;
    }
    return [
      'sql' => implode(', ', $columns),
      'types' => $types,
      'params' => $params,
    ];
  }
}

if (!function_exists('itm_hotel_booking_portal_ui_copy_save_values')) {
  /**
   * @param array<string,string> $values
   */
  function itm_hotel_booking_portal_ui_copy_save_values($conn, $companyId, array $values, $employeeId) {
    $companyId = (int) $companyId;
    $employeeId = (int) $employeeId;
    if (!$conn || $companyId <= 0) {
      return false;
    }
    foreach (itm_hotel_booking_portal_ui_copy_storage_tables() as $table) {
      $update = itm_hotel_booking_portal_ui_copy_build_update_sql_for_table($table, $values);
      if ($update === null) {
        continue;
      }
      $existsStmt = mysqli_prepare($conn, 'SELECT id FROM `' . $table . '` WHERE company_id = ? AND deleted_at IS NULL LIMIT 1');
      if (!$existsStmt) {
        return false;
      }
      mysqli_stmt_bind_param($existsStmt, 'i', $companyId);
      mysqli_stmt_execute($existsStmt);
      $existsRes = mysqli_stmt_get_result($existsStmt);
      $existsRow = $existsRes ? mysqli_fetch_assoc($existsRes) : null;
      mysqli_stmt_close($existsStmt);
      $rowId = (int) ($existsRow['id'] ?? 0);
      if ($rowId > 0) {
        $sql = 'UPDATE `' . $table . '` SET ' . $update['sql'] . ', updated_by = ?, updated_at = NOW() WHERE id = ? AND company_id = ?';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
          return false;
        }
        $types = $update['types'] . 'iii';
        $params = array_merge($update['params'], [$employeeId, $rowId, $companyId]);
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        continue;
      }
      $insertCols = ['company_id', 'created_by'];
      $insertMarks = ['?', '?'];
      $insertTypes = 'ii';
      $insertParams = [$companyId, $employeeId];
      foreach (itm_hotel_booking_portal_ui_copy_columns_for_table($table) as $column) {
        if (!array_key_exists($column, $values)) {
          continue;
        }
        $insertCols[] = '`' . $column . '`';
        $insertMarks[] = '?';
        $insertTypes .= 's';
        $insertParams[] = (string) $values[$column];
      }
      $sql = 'INSERT INTO `' . $table . '` (' . implode(', ', $insertCols) . ') VALUES (' . implode(', ', $insertMarks) . ')';
      $stmt = mysqli_prepare($conn, $sql);
      if (!$stmt) {
        return false;
      }
      mysqli_stmt_bind_param($stmt, $insertTypes, ...$insertParams);
      mysqli_stmt_execute($stmt);
      mysqli_stmt_close($stmt);
    }
    return true;
  }
}

if (!function_exists('itm_hotel_booking_portal_ui_copy_build_update_sql')) {
  /**
   * @param array<string,string> $values
   * @return array{sql:string,types:string,params:list<string>}|null
   */
  function itm_hotel_booking_portal_ui_copy_build_update_sql(array $values) {
    $columns = [];
    $types = '';
    $params = [];
    foreach (itm_hotel_booking_portal_ui_copy_registry() as $row) {
      $column = (string) ($row['column'] ?? '');
      if ($column === '' || !array_key_exists($column, $values)) {
        continue;
      }
      $columns[] = '`' . $column . '` = ?';
      $types .= 's';
      $params[] = (string) $values[$column];
    }
    if ($columns === []) {
      return null;
    }
    return [
      'sql' => implode(', ', $columns),
      'types' => $types,
      'params' => $params,
    ];
  }
}

if (!function_exists('itm_hotel_booking_portal_ui_copy_section_labels')) {
  function itm_hotel_booking_portal_ui_copy_section_labels() {
    return [
      'home' => 'Home and discovery',
      'chrome' => 'Header and stay bar',
      'step1' => 'Step 1 — Select room',
      'step2' => 'Step 2 — Select rate',
      'step3' => 'Step 3 — Customize',
      'step4' => 'Step 4 — Guest details',
      'confirm' => 'Confirmation and checkout summary',
      'manage' => 'Manage booking',
      'auth' => 'Legacy portal auth',
    ];
  }
}
