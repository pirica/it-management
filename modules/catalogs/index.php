<?php
/**
 * Catalogs Module - Index
 *
 * Standalone catalogs CRUD implementation.
 *
 * Features:
 * - Dynamic Schema Detection: Uses `DESCRIBE` and `information_schema` to build forms 
 *   and tables without hardcoding columns.
 * - CSRF & Prepared Statements: Hardened against common web vulnerabilities.
 * - Foreign Key Integration: Automatically maps ID columns to their parent tables, 
 *   heuristically selecting display labels (e.g., 'name', 'title').
 * - Inline Reference Addition: Allows users to create parent records (like a new 
 *   category) directly from a child record's dropdown via JS.
 * - Bulk Operations: Supports multi-row deletion and table clearing.
 * - Global Search & Pagination: Scopes queries by `company_id` for multi-tenancy.
 */

$crud_table = $crud_table ?? 'catalogs';
$crud_title = $crud_title ?? 'Catalogs';
$crud_action = $crud_action ?? 'index';
$catalogDefaultImageUrl = 'https://media.sweetwater.com/m/products/image/3c5509fab3bELb9Waebi8c1dQ7M237dDRNdrmnkr.jpg';
$catalogDefaultWeblinkUrl = 'https://www.sweetwater.com/store/detail/USW24POE--ubiquiti-networks-unifi-switch-24-poe';
?>
<?php
require '../../config/config.php';
$pk = 'id';

/**
 * Escapes a MySQL identifier (table/column name).
 */
function cr_escape_identifier($name) {
    return '`' . str_replace('`', '``', $name) . '`';
}


/**
 * PHP 7.4-safe string contains helper.
 */
function cr_contains($haystack, $needle) {
    return $needle === '' || strpos((string)$haystack, (string)$needle) !== false;
}

/**
 * PHP 7.4-safe string starts-with helper.
 */
function cr_starts_with($haystack, $needle) {
    $haystack = (string)$haystack;
    $needle = (string)$needle;
    if ($needle === '') {
        return true;
    }
    return substr($haystack, 0, strlen($needle)) === $needle;
}

/**
 * Fetches column definitions for the target table.
 */
function cr_table_columns($conn, $table) {
    $cols = [];
    if (!itm_is_safe_identifier($table)) return $cols;
    $res = mysqli_query($conn, 'DESCRIBE ' . cr_escape_identifier($table));
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $cols[] = $row;
    }
    return $cols;
}

/**
 * Detects foreign key relationships for the table to enable dropdown selection.
 */
function cr_fk_map($conn, $table) {
    $map = [];
    if (!itm_is_safe_identifier($table)) return $map;
    $sql = "SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND REFERENCED_TABLE_NAME IS NOT NULL";
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 's', $table);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $map[$row['COLUMN_NAME']] = $row;
        }
        mysqli_stmt_close($stmt);
    }
    return $map;
}

/**
 * Retrieves the list of valid options for a foreign key dropdown.
 */
function cr_fk_options($conn, $fk, $company_id) {
    $table = $fk['REFERENCED_TABLE_NAME'];
    $col = $fk['REFERENCED_COLUMN_NAME'];
    $rows = [];

    if (!itm_is_safe_identifier($table) || !itm_is_safe_identifier($col)) {
        return $rows;
    }

    $fkMeta = cr_fk_metadata($conn, $table);
    $labelCol = $fkMeta['label_col'];
    $available = $fkMeta['available'];

    // Multi-tenant check: filter options by company if the parent table is scoped.
    $hasCompany = (in_array('company_id', $available, true) && $company_id > 0);

    $loadRows = static function () use ($conn, $table, $col, $labelCol, $hasCompany, $company_id) {
        $loadedRows = [];
        $where = $hasCompany ? ' WHERE company_id=?' : '';
        $sql = 'SELECT ' . cr_escape_identifier($col) . ' AS id, ' . cr_escape_identifier($labelCol) . ' AS label FROM ' . cr_escape_identifier($table) . $where . ' ORDER BY label';

        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return $loadedRows;
        }

        if ($hasCompany) {
            mysqli_stmt_bind_param($stmt, 'i', $company_id);
        }
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $loadedRows[] = $row;
        }
        mysqli_stmt_close($stmt);

        return $loadedRows;
    };

    $rows = $loadRows();

    // Why: catalog edit forms become unusable when company-scoped FK tables are empty.
    // Seeding from database.sql keeps dropdowns populated without exposing company_id in the UI.
    if ($hasCompany && empty($rows) && function_exists('itm_seed_table_from_database_sql')) {
        $seedError = '';
        itm_seed_table_from_database_sql($conn, $table, (int)$company_id, $seedError);
        $rows = $loadRows();
    }

    return $rows;
}

/**
 * Resolves a single FK label for a given id, preferring company-scoped rows.
 */
function cr_fk_option_by_id($conn, $fk, $company_id, $valueId) {
    $table = $fk['REFERENCED_TABLE_NAME'] ?? '';
    $col = $fk['REFERENCED_COLUMN_NAME'] ?? 'id';
    $valueId = (int)$valueId;

    if ($valueId <= 0 || !itm_is_safe_identifier($table) || !itm_is_safe_identifier($col)) {
        return null;
    }

    $fkMeta = cr_fk_metadata($conn, $table);
    $labelCol = $fkMeta['label_col'];
    $available = $fkMeta['available'];
    $hasCompany = (in_array('company_id', $available, true) && (int)$company_id > 0);

    $queryRow = static function ($withCompanyFilter) use ($conn, $table, $col, $labelCol, $company_id, $valueId) {
        $sql = 'SELECT ' . cr_escape_identifier($col) . ' AS id, ' . cr_escape_identifier($labelCol) . ' AS label FROM ' . cr_escape_identifier($table)
            . ' WHERE ' . cr_escape_identifier($col) . '=?';
        if ($withCompanyFilter) {
            $sql .= ' AND company_id=?';
        }
        $sql .= ' LIMIT 1';

        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return null;
        }

        if ($withCompanyFilter) {
            $companyBind = (int)$company_id;
            mysqli_stmt_bind_param($stmt, 'ii', $valueId, $companyBind);
        } else {
            mysqli_stmt_bind_param($stmt, 'i', $valueId);
        }

        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = ($res && ($fetched = mysqli_fetch_assoc($res))) ? $fetched : null;
        mysqli_stmt_close($stmt);

        return $row;
    };

    if ($hasCompany) {
        $scoped = $queryRow(true);
        if ($scoped) {
            return $scoped;
        }
    }

    return $queryRow(false);
}

/**
 * Preloads foreign-key display labels so list views show human names instead of numeric IDs.
 */
function cr_fk_display_maps($conn, $fkMap, $uiColumns, $company_id) {
    $maps = [];
    foreach ($uiColumns as $col) {
        $field = (string)($col['Field'] ?? '');
        if ($field === '' || !isset($fkMap[$field])) {
            continue;
        }

        $maps[$field] = [];
        $options = cr_fk_options($conn, $fkMap[$field], (int)$company_id);
        foreach ($options as $option) {
            $optionId = (string)($option['id'] ?? '');
            if ($optionId === '') {
                continue;
            }
            $maps[$field][$optionId] = (string)($option['label'] ?? '');
        }
    }

    return $maps;
}

/**
 * Heuristically finds the best column to use as a display label for a reference table.
 */
function cr_fk_metadata($conn, $table) {
    $labelCol = 'name';
    $des = mysqli_query($conn, 'DESCRIBE ' . cr_escape_identifier($table));
    $available = [];
    $typeMap = [];
    while ($des && ($d = mysqli_fetch_assoc($des))) {
        $fieldName = (string)($d['Field'] ?? '');
        if ($fieldName === '') {
            continue;
        }
        $available[] = $fieldName;
        $typeMap[$fieldName] = strtolower((string)($d['Type'] ?? ''));
    }
    // Preferred candidate labels in order of priority.
    foreach (['name', 'title', 'label', 'display_name', 'supplier_name', 'manufacturer_name', 'equipment_type', 'username', 'code', 'mode_name'] as $candidate) {
        if (in_array($candidate, $available, true)) {
            $labelCol = $candidate;
            break;
        }
    }
    // Why: some legacy lookup tables do not use conventional "name/title" columns.
    // Selecting a readable text-like column prevents empty FK dropdowns in edit/create views.
    if (!in_array($labelCol, $available, true)) {
        foreach ($available as $fieldName) {
            if (in_array($fieldName, ['id', 'company_id', 'active', 'created_at', 'updated_at'], true)) {
                continue;
            }
            $fieldType = $typeMap[$fieldName] ?? '';
            if (cr_contains($fieldType, 'char') || cr_contains($fieldType, 'text') || cr_contains($fieldType, 'enum')) {
                $labelCol = $fieldName;
                break;
            }
        }
    }
    if (!in_array($labelCol, $available, true) && in_array('id', $available, true)) {
        $labelCol = 'id';
    }
    return [
        'label_col' => $labelCol,
        'available' => $available,
    ];
}

/**
 * Resolves a company-scoped reference ID by label and optionally inserts when missing.
 */
function cr_catalog_resolve_fk_id_by_name($conn, $table, $name, $companyId, $allowInsert = true) {
    $label = trim((string)$name);
    $companyId = (int)$companyId;
    if ($label === '' || $companyId <= 0 || !itm_is_safe_identifier($table)) {
        return null;
    }

    $meta = cr_fk_metadata($conn, $table);
    $labelCol = $meta['label_col'];
    $available = $meta['available'];
    if (!in_array('company_id', $available, true)) {
        return null;
    }

    $selectSql = 'SELECT `id` FROM ' . cr_escape_identifier($table) . ' WHERE ' . cr_escape_identifier($labelCol) . '=? AND `company_id`=? LIMIT 1';
    $selectStmt = mysqli_prepare($conn, $selectSql);
    if ($selectStmt) {
        mysqli_stmt_bind_param($selectStmt, 'si', $label, $companyId);
        mysqli_stmt_execute($selectStmt);
        $result = mysqli_stmt_get_result($selectStmt);
        if ($result && ($row = mysqli_fetch_assoc($result))) {
            mysqli_stmt_close($selectStmt);
            return (int)$row['id'];
        }
        mysqli_stmt_close($selectStmt);
    }

    if (!$allowInsert) {
        return null;
    }

    $fields = ['`company_id`', cr_escape_identifier($labelCol)];
    $placeholders = ['?', '?'];
    $types = 'is';
    $params = [$companyId, $label];
    if (in_array('active', $available, true)) {
        $fields[] = '`active`';
        $placeholders[] = '?';
        $types .= 'i';
        $params[] = 1;
    }

    $insertSql = 'INSERT INTO ' . cr_escape_identifier($table) . ' (' . implode(',', $fields) . ') VALUES (' . implode(',', $placeholders) . ')';
    $insertStmt = mysqli_prepare($conn, $insertSql);
    if (!$insertStmt) {
        return null;
    }
    mysqli_stmt_bind_param($insertStmt, $types, ...$params);
    $ok = mysqli_stmt_execute($insertStmt);
    mysqli_stmt_close($insertStmt);
    if (!$ok) {
        return null;
    }
    return (int)mysqli_insert_id($conn);
}

/**
 * Removes internal/automatic columns from the manageable field set.
 */
function cr_manageable_columns($columns) {
    return array_values(array_filter($columns, function ($c) {
        return !in_array($c['Field'], ['id', 'created_at', 'updated_at'], true);
    }));
}

/**
 * Builds a fast lookup for tinyint(1) fields used as boolean UI controls.
 */
function cr_tinyint_field_map($columns) {
    $map = [];
    foreach ($columns as $col) {
        $field = (string)($col['Field'] ?? '');
        $type = strtolower((string)($col['Type'] ?? ''));
        if ($field !== '' && (cr_starts_with($type, 'tinyint(1)') || $field === 'active')) {
            $map[$field] = true;
        }
    }
    return $map;
}

/**
 * Converts DB column names to user-friendly titles.
 */
function cr_humanize_field($field) {
    $label = trim((string)$field);
    if ($label === '') { return ''; }

    $map = [
        'department_id' => 'Department Name',
        'office_key_card_department_id' => 'Office Key Card Department',
        'opera_username' => 'OPERA Username',
        'onq_ri' => 'OnQ R&I',
        'hu_the_lobby' => 'HU & The Lobby',
    ];

    if (isset($map[$label])) { return $map[$label]; }
    if ($label === 'id') { return 'ID'; }

    $label = preg_replace('/_id$/', '', $label);
    $label = str_replace('_', ' ', (string)$label);
    return ucwords($label);
}

/**
 * Privacy filter for employee-related modules.
 */
function cr_is_hidden_employee_field($field) {
    if (($GLOBALS['crud_table'] ?? '') !== 'employees') { return false; }
    $hidden = ['company_id', 'user_id', 'location_id', 'phone', 'location', 'employee_code'];
    return in_array($field, $hidden, true);
}

/**
 * Formats database values for UI display (badges, icons, clickable links).
 */
function cr_render_cell_value($table, $field, $value) {
    $currentAction = (string)($GLOBALS['crud_action'] ?? 'index');

    // Status badges for the 'active' flag must stay consistent across list/view pages.
    if ($field === 'active') {
        $isActive = ((int)$value === 1);
        return '<span class="badge ' . ($isActive ? 'badge-success' : 'badge-danger') . '">' . ($isActive ? 'Active' : 'Inactive') . '</span>';
    }

    // View mode uses icon-style booleans (1=✅, 0=❌) only for known tinyint(1) fields.
    if ($currentAction === 'view') {
        $tinyIntFields = $GLOBALS['cr_tinyint_fields'] ?? [];
        if (isset($tinyIntFields[$field])) {
            $normalized = strtolower(trim((string)($value ?? '')));
            if (in_array($normalized, ['1', '0', 'true', 'false'], true)) {
                return (in_array($normalized, ['1', 'true'], true) ? '✅' : '❌');
            }
        }
    }

    // Special boolean mapping for Employee Access module.
    if (($GLOBALS['crud_table'] ?? '') === 'employees') {
        $employeeBoolFields = ['network_access', 'micros_emc', 'opera_username', 'micros_card', 'pms_id', 'synergy_mms', 'hu_the_lobby', 'navision', 'onq_ri', 'birchstreet', 'delphi', 'omina', 'vingcard_system', 'digital_rev', 'office_key_card'];
        if (in_array($field, $employeeBoolFields, true)) {
            return ((int)$value === 1) ? '✅' : '❌';
        }
    }

    $text = (string)($value ?? '');
    if ($table === 'catalogs' && in_array($field, ['image', 'image_url'], true) && $text !== '') {
        $safeUrl = cr_normalize_external_url($text);
        if ($safeUrl === '') {
            return sanitize($text);
        }
        return '<a href="' . sanitize($safeUrl) . '" target="_blank" rel="noopener noreferrer">🖼️ Open image</a>';
    }
    if ($table === 'catalogs' && in_array($field, ['weblink', 'source_url', 'product_url'], true) && $text !== '') {
        $safeUrl = cr_normalize_external_url($text);
        if ($safeUrl === '') {
            return sanitize($text);
        }
        return '<a href="' . sanitize($safeUrl) . '" target="_blank" rel="noopener noreferrer">🔗 Open</a>';
    }

    // Interactive email links with Outlook deep-link support.
    if ($table === 'employees' && $field === 'email' && $text !== '') {
        $safeEmail = sanitize($text);
        $mailto = 'mailto:' . $text;
        $outlook = 'ms-outlook://compose?to=' . $text;
        return '<a href="' . sanitize($mailto) . '" data-outlook-link="1" data-outlook-href="' . sanitize($outlook) . '">' . $safeEmail . '</a>';
    }

    return sanitize($text);
}

/**
 * Builds a safe external search link for catalog product lookups.
 */
function cr_catalog_search_url($query) {
    $trimmed = trim((string)$query);
    if ($trimmed === '') {
        return '';
    }
    return 'https://www.google.com/search?q=' . rawurlencode($trimmed);
}

/**
 * Fetches a remote URL with a small timeout for catalog discovery workflows.
 */
function cr_catalog_fetch_remote_body($url, $timeoutSeconds = 8) {
    $normalizedUrl = cr_normalize_external_url($url);
    if ($normalizedUrl === '') {
        return '';
    }

    $baseTimeout = max(3, (int)$timeoutSeconds);
    $requestHeaders = implode("\r\n", [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36',
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Language: en-US,en;q=0.9',
        'Connection: close',
    ]) . "\r\n";

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => $baseTimeout,
            'ignore_errors' => true,
            'header' => $requestHeaders,
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);

    $body = @file_get_contents($normalizedUrl, false, $context);
    if ($body !== false && trim((string)$body) !== '') {
        return (string)$body;
    }

    // Why: some legacy environments lack updated certificate bundles.
    $fallbackContext = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => $baseTimeout,
            'ignore_errors' => true,
            'header' => $requestHeaders,
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ],
    ]);

    $fallbackBody = @file_get_contents($normalizedUrl, false, $fallbackContext);
    if ($fallbackBody === false) {
        return '';
    }
    return (string)$fallbackBody;
}

/**
 * Guesses a supplier name from a URL host.
 */
function cr_catalog_guess_supplier_from_url($url) {
    $parts = parse_url((string)$url);
    $host = strtolower((string)($parts['host'] ?? ''));
    if ($host === '') {
        return '';
    }
    $host = preg_replace('/^www\./i', '', $host);
    if ($host === null || $host === '') {
        return '';
    }
    $segments = explode('.', $host);
    $label = $segments[0] ?? '';
    $label = str_replace(['-', '_'], ' ', $label);
    return ucwords(trim((string)$label));
}

/**
 * Extracts a visible price token from text snippets.
 */
function cr_catalog_extract_price($text) {
    $rawText = (string)$text;
    if ($rawText === '') {
        return '';
    }
    $compactText = str_replace(["\xc2\xa0", '&nbsp;'], ' ', $rawText);
    if (preg_match('/([$€£])\s*([0-9]{1,3}(?:[.,][0-9]{3})*(?:[.,][0-9]{2})?)/u', $compactText, $m)) {
        return trim((string)($m[1] . $m[2]));
    }
    if (preg_match('/([0-9]{1,3}(?:[.,][0-9]{3})*(?:[.,][0-9]{2})?)\s*([$€£])/u', $compactText, $m)) {
        return trim((string)($m[1] . $m[2]));
    }
    return '';
}

/**
 * Converts extracted display price tokens into decimal unit prices.
 */
function cr_catalog_extract_unit_price($text) {
    $priceToken = cr_catalog_extract_price($text);
    if ($priceToken === '') {
        return null;
    }

    $normalized = preg_replace('/[^0-9,.\-]/', '', $priceToken);
    if ($normalized === null || $normalized === '') {
        return null;
    }

    $lastDot = strrpos($normalized, '.');
    $lastComma = strrpos($normalized, ',');

    if ($lastDot !== false && $lastComma !== false) {
        if ($lastDot > $lastComma) {
            $normalized = str_replace(',', '', $normalized);
        } else {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        }
    } elseif ($lastComma !== false) {
        $commaDecimals = strlen($normalized) - $lastComma - 1;
        if ($commaDecimals === 2) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        } else {
            $normalized = str_replace(',', '', $normalized);
        }
    } elseif ($lastDot !== false) {
        $dotDecimals = strlen($normalized) - $lastDot - 1;
        if ($dotDecimals !== 2) {
            $normalized = str_replace('.', '', $normalized);
        }
    }

    if ($normalized === '' || !is_numeric($normalized)) {
        return null;
    }

    $unitPrice = (float)$normalized;
    if ($unitPrice <= 0) {
        return null;
    }
    return $unitPrice;
}

/**
 * Attempts to detect a product price directly from the product page HTML.
 */
function cr_catalog_detect_price_from_page($url) {
    $pageHtml = cr_catalog_fetch_remote_body($url, 10);
    if ($pageHtml === '') {
        return null;
    }

    $metaPricePatterns = [
        '/property=["\']product:price:amount["\'][^>]*content=["\']([^"\']+)["\']/i',
        '/property=["\']og:price:amount["\'][^>]*content=["\']([^"\']+)["\']/i',
        '/itemprop=["\']price["\'][^>]*content=["\']([^"\']+)["\']/i',
    ];
    foreach ($metaPricePatterns as $pattern) {
        if (preg_match($pattern, $pageHtml, $priceMatch)) {
            $candidate = trim((string)($priceMatch[1] ?? ''));
            $metaUnitPrice = cr_catalog_extract_unit_price($candidate);
            if ($metaUnitPrice !== null && $metaUnitPrice > 0) {
                return $metaUnitPrice;
            }
        }
    }

    return cr_catalog_extract_unit_price(strip_tags($pageHtml));
}

/**
 * Uses lightweight keyword matching to classify equipment type.
 */
function cr_catalog_guess_equipment_type($title, $snippet, $fallbackType) {
    $defaultType = trim((string)$fallbackType);
    if ($defaultType !== '') {
        return $defaultType;
    }

    $haystack = strtolower(trim((string)$title . ' ' . (string)$snippet));
    $typeMap = [
        'switch' => ['switch', 'poe switch', 'ethernet switch'],
        'router' => ['router', 'gateway'],
        'access point' => ['access point', 'wifi', 'wireless ap', 'wi-fi'],
        'server' => ['server', 'rack server'],
        'firewall' => ['firewall', 'utm appliance'],
        'laptop' => ['laptop', 'notebook'],
        'desktop' => ['desktop', 'tower pc', 'workstation'],
        'printer' => ['printer', 'multifunction'],
        'monitor' => ['monitor', 'display'],
    ];

    foreach ($typeMap as $typeLabel => $keywords) {
        foreach ($keywords as $keyword) {
            if ($keyword !== '' && cr_contains($haystack, $keyword)) {
                return ucwords($typeLabel);
            }
        }
    }

    return 'Other';
}

/**
 * Parses Google search result links from HTML markup.
 */
function cr_catalog_extract_google_result_links($searchHtml) {
    $rows = [];
    $html = (string)$searchHtml;
    if ($html === '') {
        return $rows;
    }

    preg_match_all('/<a[^>]+href="([^"]+)"[^>]*>(.*?)<\/a>/is', $html, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
    foreach ($matches as $match) {
        $rawHref = html_entity_decode((string)($match[1][0] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $anchorHtml = (string)($match[2][0] ?? '');
        $anchorOffset = (int)($match[0][1] ?? 0);
        if ($rawHref === '') {
            continue;
        }

        $candidateUrl = '';
        if (cr_starts_with($rawHref, '/url?')) {
            $hrefParts = parse_url('https://www.google.com' . $rawHref);
            parse_str((string)($hrefParts['query'] ?? ''), $queryParts);
            $candidateUrl = (string)($queryParts['q'] ?? '');
        } elseif (preg_match('#^https?://#i', $rawHref)) {
            $candidateUrl = $rawHref;
        }

        $candidateUrl = cr_normalize_external_url($candidateUrl);
        if ($candidateUrl === '') {
            continue;
        }

        $host = strtolower((string)(parse_url($candidateUrl, PHP_URL_HOST) ?? ''));
        if ($host === '' || cr_contains($host, 'google.com') || cr_contains($host, 'gstatic.com')) {
            continue;
        }

        $title = trim(strip_tags(html_entity_decode($anchorHtml, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        if ($title === '') {
            continue;
        }

        $rows[] = [
            'url' => $candidateUrl,
            'title' => $title,
            'snippet' => trim((string)preg_replace(
                '/\s+/',
                ' ',
                strip_tags(html_entity_decode((string)substr($html, $anchorOffset, 1000), ENT_QUOTES | ENT_HTML5, 'UTF-8'))
            )),
        ];
    }

    return $rows;
}

/**
 * Parses DuckDuckGo HTML result links from lightweight markup.
 */
function cr_catalog_extract_duckduckgo_result_links($searchHtml) {
    $rows = [];
    $html = (string)$searchHtml;
    if ($html === '') {
        return $rows;
    }

    preg_match_all('/<a[^>]+class="[^"]*result__a[^"]*"[^>]+href="([^"]+)"[^>]*>(.*?)<\/a>/is', $html, $matches, PREG_SET_ORDER);
    foreach ($matches as $match) {
        $rawHref = html_entity_decode((string)($match[1] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $title = trim(strip_tags(html_entity_decode((string)($match[2] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        if (cr_starts_with($rawHref, '//')) {
            $rawHref = 'https:' . $rawHref;
        }

        $redirectParts = parse_url((string)$rawHref);
        $redirectHost = strtolower((string)($redirectParts['host'] ?? ''));
        if ($redirectHost === 'duckduckgo.com' && isset($redirectParts['query'])) {
            parse_str((string)$redirectParts['query'], $redirectQuery);
            $decodedTarget = trim((string)($redirectQuery['uddg'] ?? ''));
            if ($decodedTarget !== '') {
                $rawHref = $decodedTarget;
            }
        }

        $targetUrl = cr_normalize_external_url($rawHref);
        if ($targetUrl === '' || $title === '') {
            continue;
        }
        $rows[] = [
            'url' => $targetUrl,
            'title' => $title,
            'snippet' => '',
        ];
    }

    return $rows;
}

/**
 * Runs online catalog discovery using Google search HTML results.
 */
function cr_catalog_search_online_products($modelQuery, $manufacturerName, $supplierFilter, $equipmentTypeFilter, $quantity, $conn = null, $companyId = 0) {
    $results = [];
    // Why: keep search_query identical to the operator-supplied input so operators can
    // trust/reuse the exact phrase they entered without implicit keyword mutations.
    $searchQuery = trim((string)$modelQuery);
    if ($searchQuery === '') {
        return $results;
    }

    // Why: keep online discovery predictable for operators by always returning
    // up to a fixed 20-result batch regardless of form input variations.
    $maxRows = 20;
    $seenLinks = [];

    // Why: keep payload `search_query` unchanged, but widen actual engine queries
    // when pricing metadata is hard to locate from the exact phrase alone.
    $engineQueries = [$searchQuery];
    if (!cr_contains(strtolower($searchQuery), 'buy')) {
        $engineQueries[] = $searchQuery . ' buy';
    }
    $manufacturerName = trim((string)$manufacturerName);
    if ($manufacturerName !== '' && stripos($searchQuery, $manufacturerName) === false) {
        $engineQueries[] = $manufacturerName . ' ' . $searchQuery;
    }
    $engineQueries = array_values(array_unique(array_filter($engineQueries, function ($query) {
        return trim((string)$query) !== '';
    })));

    foreach ($engineQueries as $engineQuery) {
        if (count($results) >= $maxRows) {
            break;
        }

        $searchUrl = 'https://www.google.com/search?hl=en&num=30&q=' . rawurlencode((string)$engineQuery);
        $html = cr_catalog_fetch_remote_body($searchUrl, 10);
        if ($html === '') {
            continue;
        }

        $googleRows = cr_catalog_extract_google_result_links($html);
        if (empty($googleRows)) {
            continue;
        }

        foreach ($googleRows as $googleRow) {
            if (count($results) >= $maxRows) {
                break;
            }

            $targetUrl = cr_normalize_external_url((string)($googleRow['url'] ?? ''));
            $title = trim((string)($googleRow['title'] ?? ''));
            if ($targetUrl === '' || isset($seenLinks[$targetUrl])) {
                continue;
            }
            $seenLinks[$targetUrl] = 1;

            $supplier = trim((string)$supplierFilter);
            if ($supplier === '') {
                $supplier = cr_catalog_guess_supplier_from_url($targetUrl);
            }

            $snippet = trim((string)($googleRow['snippet'] ?? ''));

            $equipmentType = cr_catalog_guess_equipment_type($title, $snippet, $equipmentTypeFilter);
            $unitPrice = cr_catalog_extract_unit_price($title . ' ' . $snippet);
            if ($unitPrice === null || $unitPrice <= 0) {
                $unitPrice = cr_catalog_detect_price_from_page($targetUrl);
            }
            if ($unitPrice === null || $unitPrice <= 0) {
                continue;
            }
            $imageUrl = cr_catalog_detect_image_url_from_page($targetUrl);

            $results[] = [
                'model' => $title,
                'equipment_type' => $equipmentType,
                'product_image_url' => $imageUrl,
                'unit_price' => $unitPrice,
                'supplier' => $supplier,
                'weblink' => $targetUrl,
                'json' => [
                    'model' => $title,
                    'equipment_type' => $equipmentType,
                    'product_image_url' => $imageUrl,
                    'unit_price' => $unitPrice,
                    'supplier' => $supplier,
                    'weblink' => $targetUrl,
                    'manufacturer' => $manufacturerName,
                    'search_query' => $searchQuery,
                ],
            ];
        }
    }

    if (empty($results)) {
        foreach ($engineQueries as $engineQuery) {
            if (count($results) >= $maxRows) {
                break;
            }

            $duckSearchUrl = 'https://html.duckduckgo.com/html/?q=' . rawurlencode((string)$engineQuery);
            $duckHtml = cr_catalog_fetch_remote_body($duckSearchUrl, 10);
            if ($duckHtml === '') {
                continue;
            }

            $duckRows = cr_catalog_extract_duckduckgo_result_links($duckHtml);
            foreach ($duckRows as $duckRow) {
                if (count($results) >= $maxRows) {
                    break;
                }

                $targetUrl = cr_normalize_external_url((string)($duckRow['url'] ?? ''));
                $title = trim((string)($duckRow['title'] ?? ''));
                if ($targetUrl === '' || $title === '' || isset($seenLinks[$targetUrl])) {
                    continue;
                }
                $seenLinks[$targetUrl] = 1;

                $supplier = trim((string)$supplierFilter);
                if ($supplier === '') {
                    $supplier = cr_catalog_guess_supplier_from_url($targetUrl);
                }

                $snippet = trim((string)($duckRow['snippet'] ?? ''));
                $equipmentType = cr_catalog_guess_equipment_type($title, $snippet, $equipmentTypeFilter);
                $unitPrice = cr_catalog_extract_unit_price($title . ' ' . $snippet);
                if ($unitPrice === null || $unitPrice <= 0) {
                    $unitPrice = cr_catalog_detect_price_from_page($targetUrl);
                }
                if ($unitPrice === null || $unitPrice <= 0) {
                    continue;
                }
                $imageUrl = cr_catalog_detect_image_url_from_page($targetUrl);

                $results[] = [
                    'model' => $title,
                    'equipment_type' => $equipmentType,
                    'product_image_url' => $imageUrl,
                    'unit_price' => $unitPrice,
                    'supplier' => $supplier,
                    'weblink' => $targetUrl,
                    'json' => [
                        'model' => $title,
                        'equipment_type' => $equipmentType,
                        'product_image_url' => $imageUrl,
                        'unit_price' => $unitPrice,
                        'supplier' => $supplier,
                        'weblink' => $targetUrl,
                        'manufacturer' => $manufacturerName,
                        'search_query' => $searchQuery,
                    ],
                ];
            }
        }
    }

    if (empty($results)) {
        foreach ($engineQueries as $engineQuery) {
            if (count($results) >= $maxRows) {
                break;
            }
            $bingRows = cr_catalog_search_online_products_bing_rss((string)$engineQuery, $supplierFilter, $equipmentTypeFilter, $manufacturerName, $quantity, $searchQuery);
            foreach ($bingRows as $bingRow) {
                if (count($results) >= $maxRows) {
                    break;
                }
                $targetUrl = cr_normalize_external_url((string)($bingRow['weblink'] ?? ''));
                if ($targetUrl === '' || isset($seenLinks[$targetUrl])) {
                    continue;
                }
                $seenLinks[$targetUrl] = 1;
                $results[] = $bingRow;
            }
        }
    }

    if (empty($results) && $conn instanceof mysqli) {
        $results = cr_catalog_search_local_products($conn, (int)$companyId, $searchQuery, $manufacturerName, $supplierFilter, $equipmentTypeFilter, $maxRows);
    }

    return $results;
}

/**
 * Uses Bing RSS as a resilient fallback when HTML scraping returns no rows.
 */
function cr_catalog_search_online_products_bing_rss($searchQuery, $supplierFilter, $equipmentTypeFilter, $manufacturerName, $quantity, $reportedSearchQuery = '') {
    $results = [];
    $query = trim((string)$searchQuery);
    if ($query === '') {
        return $results;
    }

    $searchUrl = 'https://www.bing.com/search?format=rss&q=' . rawurlencode($query);
    $xmlBody = cr_catalog_fetch_remote_body($searchUrl, 10);
    if ($xmlBody === '') {
        return $results;
    }

    libxml_use_internal_errors(true);
    $rss = simplexml_load_string($xmlBody);
    if ($rss === false || !isset($rss->channel->item)) {
        return $results;
    }

    // Why: mirror primary search behavior so fallback results keep the same
    // operator-visible batch size.
    $maxRows = 20;
    $seenLinks = [];
    foreach ($rss->channel->item as $item) {
        if (count($results) >= $maxRows) {
            break;
        }

        $title = trim((string)($item->title ?? ''));
        $link = cr_normalize_external_url((string)($item->link ?? ''));
        $snippet = trim((string)($item->description ?? ''));

        if ($title === '' || $link === '' || isset($seenLinks[$link])) {
            continue;
        }
        $seenLinks[$link] = 1;

        $supplier = trim((string)$supplierFilter);
        if ($supplier === '') {
            $supplier = cr_catalog_guess_supplier_from_url($link);
        }
        $equipmentType = cr_catalog_guess_equipment_type($title, $snippet, $equipmentTypeFilter);
        $unitPrice = cr_catalog_extract_unit_price($title . ' ' . $snippet);
        if ($unitPrice === null || $unitPrice <= 0) {
            $unitPrice = cr_catalog_detect_price_from_page($link);
        }
        if ($unitPrice === null || $unitPrice <= 0) {
            continue;
        }
        $imageUrl = cr_catalog_detect_image_url_from_page($link);

        $results[] = [
            'model' => $title,
            'equipment_type' => $equipmentType,
            'product_image_url' => $imageUrl,
            'unit_price' => $unitPrice,
            'supplier' => $supplier,
            'weblink' => $link,
            'json' => [
                'model' => $title,
                'equipment_type' => $equipmentType,
                'product_image_url' => $imageUrl,
                'unit_price' => $unitPrice,
                'supplier' => $supplier,
                'weblink' => $link,
                'manufacturer' => trim((string)$manufacturerName),
                'search_query' => ($reportedSearchQuery !== '' ? (string)$reportedSearchQuery : $query),
            ],
        ];
    }

    return $results;
}

/**
 * Uses current tenant catalog rows as a fallback when external search returns zero priced products.
 */
function cr_catalog_search_local_products($conn, $companyId, $searchQuery, $manufacturerName, $supplierFilter, $equipmentTypeFilter, $maxRows = 20) {
    $results = [];
    $query = trim((string)$searchQuery);
    if ($query === '') {
        return $results;
    }

    $sql = 'SELECT c.`model`, et.`name` AS equipment_type, c.`image_url` AS image, c.`price`, s.`name` AS supplier, c.`product_url` AS weblink'
        . ' FROM `catalogs` c'
        . ' LEFT JOIN `equipment_types` et ON et.`id`=c.`equipment_type_id`'
        . ' LEFT JOIN `suppliers` s ON s.`id`=c.`supplier_id`'
        . ' WHERE c.`model` LIKE ?';
    $types = 's';
    $params = ['%' . $query . '%'];

    if ((int)$companyId > 0) {
        $sql .= ' AND c.`company_id`=?';
        $types .= 'i';
        $params[] = (int)$companyId;
    }

    $supplier = trim((string)$supplierFilter);
    if ($supplier !== '') {
        $sql .= ' AND s.`name`=?';
        $types .= 's';
        $params[] = $supplier;
    }

    $equipmentType = trim((string)$equipmentTypeFilter);
    if ($equipmentType !== '') {
        $sql .= ' AND et.`name`=?';
        $types .= 's';
        $params[] = $equipmentType;
    }

    $safeMaxRows = max(1, min(50, (int)$maxRows));
    $sql .= ' ORDER BY c.`updated_at` DESC, c.`id` DESC LIMIT ' . $safeMaxRows;

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return $results;
    }
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $model = trim((string)($row['model'] ?? ''));
        if ($model === '') {
            continue;
        }

        $priceRaw = (string)($row['price'] ?? '');
        $unitPrice = cr_catalog_extract_unit_price($priceRaw);
        if ($unitPrice === null && $priceRaw !== '' && is_numeric($priceRaw)) {
            $unitPrice = (float)$priceRaw;
        }
        if ($unitPrice === null || $unitPrice <= 0) {
            continue;
        }

        $rowSupplier = trim((string)($row['supplier'] ?? ''));
        if ($rowSupplier === '') {
            $rowSupplier = cr_catalog_guess_supplier_from_url((string)($row['weblink'] ?? ''));
        }
        if ($rowSupplier === '') {
            $rowSupplier = 'Catalog';
        }

        $rowType = trim((string)($row['equipment_type'] ?? ''));
        if ($rowType === '') {
            $rowType = cr_catalog_guess_equipment_type($model, '', $equipmentTypeFilter);
        }

        $weblink = cr_normalize_external_url((string)($row['weblink'] ?? ''));
        $imageUrl = cr_normalize_external_url((string)($row['image'] ?? ''));

        $results[] = [
            'model' => $model,
            'equipment_type' => $rowType,
            'product_image_url' => $imageUrl,
            'unit_price' => $unitPrice,
            'supplier' => $rowSupplier,
            'weblink' => $weblink,
            'json' => [
                'model' => $model,
                'equipment_type' => $rowType,
                'product_image_url' => $imageUrl,
                'unit_price' => $unitPrice,
                'supplier' => $rowSupplier,
                'weblink' => $weblink,
                'manufacturer' => trim((string)$manufacturerName),
                'search_query' => $query,
                'source' => 'catalogs_fallback',
            ],
        ];
    }

    mysqli_stmt_close($stmt);
    return $results;
}

/**
 * Normalizes user-provided URLs into safe absolute HTTP(S) links.
 */
function cr_normalize_external_url($rawUrl) {
    $url = trim((string)$rawUrl);
    if ($url === '') {
        return '';
    }

    if (!preg_match('#^https?://#i', $url)) {
        $url = 'https://' . ltrim($url, '/');
    }

    $valid = filter_var($url, FILTER_VALIDATE_URL);
    if ($valid === false) {
        return '';
    }

    $parts = parse_url((string)$valid);
    $scheme = strtolower((string)($parts['scheme'] ?? ''));
    if (!in_array($scheme, ['http', 'https'], true)) {
        return '';
    }

    return (string)$valid;
}

/**
 * Resolves relative image URLs against a base page URL.
 */
function cr_resolve_relative_url($baseUrl, $relativeUrl) {
    $relative = trim((string)$relativeUrl);
    if ($relative === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $relative)) {
        return cr_normalize_external_url($relative);
    }
    if (cr_starts_with($relative, '//')) {
        $baseParts = parse_url((string)$baseUrl);
        $scheme = (string)($baseParts['scheme'] ?? 'https');
        return cr_normalize_external_url($scheme . ':' . $relative);
    }

    $baseParts = parse_url((string)$baseUrl);
    $scheme = (string)($baseParts['scheme'] ?? 'https');
    $host = (string)($baseParts['host'] ?? '');
    if ($host === '') {
        return '';
    }
    $port = isset($baseParts['port']) ? ':' . (int)$baseParts['port'] : '';
    $path = (string)($baseParts['path'] ?? '/');
    $baseDir = preg_replace('#/[^/]*$#', '/', $path);
    if ($baseDir === null || $baseDir === '') {
        $baseDir = '/';
    }

    if (cr_starts_with($relative, '/')) {
        return cr_normalize_external_url($scheme . '://' . $host . $port . $relative);
    }
    return cr_normalize_external_url($scheme . '://' . $host . $port . $baseDir . $relative);
}

/**
 * Fetches an image URL candidate from a catalog product page.
 */
function cr_catalog_detect_image_url_from_page($pageUrl) {
    $normalizedPageUrl = cr_normalize_external_url($pageUrl);
    if ($normalizedPageUrl === '') {
        return '';
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 8,
            'ignore_errors' => true,
            'header' => "User-Agent: ITManagementCatalogBot/1.0\r\n",
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);

    $html = @file_get_contents($normalizedPageUrl, false, $context);
    if ($html === false || trim((string)$html) === '') {
        return '';
    }

    $patterns = [
        '/<meta[^>]+property=["\']og:image(?:secure_url)?["\'][^>]+content=["\']([^"\']+)["\']/i',
        '/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:image(?:secure_url)?["\']/i',
        '/<meta[^>]+name=["\']twitter:image(?::src)?["\'][^>]+content=["\']([^"\']+)["\']/i',
        '/<meta[^>]+content=["\']([^"\']+)["\'][^>]+name=["\']twitter:image(?::src)?["\']/i',
        '/<meta[^>]+itemprop=["\']image["\'][^>]+content=["\']([^"\']+)["\']/i',
        '/<meta[^>]+content=["\']([^"\']+)["\'][^>]+itemprop=["\']image["\']/i',
        '/<link[^>]+rel=["\']image_src["\'][^>]+href=["\']([^"\']+)["\']/i',
        '/<link[^>]+href=["\']([^"\']+)["\'][^>]+rel=["\']image_src["\']/i',
        '/<img[^>]+src=["\']([^"\']+)["\']/i',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, (string)$html, $matches)) {
            $candidate = cr_resolve_relative_url($normalizedPageUrl, (string)($matches[1] ?? ''));
            if ($candidate !== '') {
                return $candidate;
            }
        }
    }

    if (preg_match('/"image"\s*:\s*"([^"]+)"/i', $html, $jsonLdImageMatch)) {
        $candidate = cr_resolve_relative_url($normalizedPageUrl, (string)($jsonLdImageMatch[1] ?? ''));
        if ($candidate !== '') {
            return $candidate;
        }
    }

    return '';
}

/**
 * Renders a constrained image preview to keep table/detail layouts stable.
 */
function cr_catalog_image_preview_html($value, $maxWidth = 120, $maxHeight = 80) {
    $normalizedUrl = cr_normalize_external_url((string)$value);
    if ($normalizedUrl === '') {
        return sanitize((string)$value);
    }

    $width = max(40, (int)$maxWidth);
    $height = max(40, (int)$maxHeight);
    return '<a href="' . sanitize($normalizedUrl) . '" target="_blank" rel="noopener noreferrer" style="display:inline-flex;align-items:center;gap:8px;">'
        . '<img src="' . sanitize($normalizedUrl) . '" alt="Catalog image" loading="lazy" style="max-width:' . $width . 'px;max-height:' . $height . 'px;width:auto;height:auto;object-fit:contain;border:1px solid #d5d9e2;border-radius:6px;background:#fff;">'
        . '<span>🖼️</span>'
        . '</a>';
}


function cr_get_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['csrf_token'];
}

function cr_require_valid_csrf_token() {
    $token = (string)($_POST['csrf_token'] ?? '');
    $sessionToken = (string)($_SESSION['csrf_token'] ?? '');
    if ($token === '' || $sessionToken === '' || !hash_equals($sessionToken, $token)) {
        http_response_code(403);
        exit('Forbidden: invalid CSRF token.');
    }
}

function cr_numeric_validation_error($field, $message) {
    return cr_humanize_field($field) . ' ' . $message . '.';
}

/**
 * Validates inputs against strict MySQL numeric ranges (e.g. tinyint vs bigint).
 */
function cr_validate_numeric_value($rawValue, $column, $fieldName, &$normalizedValue, &$error) {
    $type = strtolower((string)$column['Type']);
    $isUnsigned = cr_contains($type, 'unsigned');
    $raw = trim((string)$rawValue);

    if (preg_match('/^(tinyint|smallint|mediumint|int|bigint)\b/', $type, $match)) {
        $intVal = filter_var($raw, FILTER_VALIDATE_INT);
        if ($intVal === false) {
            $error = cr_numeric_validation_error($fieldName, 'must be a valid integer');
            return false;
        }

        $ranges = [
            'tinyint' => [-128, 127, 0, 255],
            'smallint' => [-32768, 32767, 0, 65535],
            'mediumint' => [-8388608, 8388607, 0, 16777215],
            'int' => [-2147483648, 2147483647, 0, 4294967295],
        ];
        $typeName = $match[1];

        if (isset($ranges[$typeName])) {
            [$signedMin, $signedMax, $unsignedMin, $unsignedMax] = $ranges[$typeName];
            $min = $isUnsigned ? $unsignedMin : $signedMin;
            $max = $isUnsigned ? $unsignedMax : $signedMax;
            if ($intVal < $min || $intVal > $max) {
                $error = cr_numeric_validation_error($fieldName, 'is out of range');
                return false;
            }
        } elseif ($typeName === 'bigint' && $isUnsigned && $intVal < 0) {
            $error = cr_numeric_validation_error($fieldName, 'must be zero or greater');
            return false;
        }

        $normalizedValue = (string)$intVal;
        return true;
    }

    if (preg_match('/^(decimal|float|double)\b/', $type)) {
        if (!is_numeric($raw)) {
            $error = cr_numeric_validation_error($fieldName, 'must be a valid number');
            return false;
        }

        $floatVal = (float)$raw;
        if (!is_finite($floatVal)) {
            $error = cr_numeric_validation_error($fieldName, 'must be a finite number');
            return false;
        }

        if ($isUnsigned && $floatVal < 0) {
            $error = cr_numeric_validation_error($fieldName, 'must be zero or greater');
            return false;
        }

        $normalizedValue = (string)$raw;
        return true;
    }

    $error = cr_numeric_validation_error($fieldName, 'has an unsupported numeric type');
    return false;
}

// DATA LOADING & INITIALIZATION
$columns = cr_table_columns($conn, $crud_table);
$fkMap = cr_fk_map($conn, $crud_table);
$fieldColumns = cr_manageable_columns($columns);
$fieldColumns = array_values(array_filter($fieldColumns, function ($col) {
    return !cr_is_hidden_employee_field($col['Field']);
}));
$hasCompany = false;
foreach ($fieldColumns as $c) {
    if ($c['Field'] === 'company_id') { $hasCompany = true; break; }
}


$hideCompanyIdTables = ['workstation_ram', 'workstation_os_versions', 'workstation_os_types', 'workstation_office', 'workstation_modes', 'workstation_device_types', 'warranty_types', 'user_roles', 'ui_configuration', 'switch_port_types', 'switch_port_numbering_layout', 'sidebar_layout', 'role_module_permissions', 'role_hierarchy', 'role_assignment_rights', 'printer_device_types', 'inventory_items', 'inventory_categories', 'idf_positions', 'idf_ports', 'idf_links', 'equipment_rj45', 'equipment_poe', 'equipment_fiber_rack', 'equipment_fiber_patch', 'equipment_fiber_count', 'equipment_fiber', 'equipment_environment', 'assignment_types', 'access_levels', 'employee_statuses', 'ticket_priorities', 'ticket_statuses', 'ticket_categories', 'switch_status', 'rack_statuses', 'racks', 'supplier_statuses', 'suppliers', 'manufacturers', 'catalogs', 'equipment_statuses', 'equipment_types', 'location_types', 'it_locations', 'users', 'departments'];
$uiColumns = array_values(array_filter($fieldColumns, function ($col) use ($hideCompanyIdTables) {
    if (($col['Field'] ?? '') !== 'company_id') {
        return true;
    }
    return !in_array((string)($GLOBALS['crud_table'] ?? ''), $hideCompanyIdTables, true);
}));
$formColumns = array_values(array_filter($fieldColumns, function ($col) {
    return (string)($col['Field'] ?? '') !== 'company_id';
}));
$GLOBALS['cr_tinyint_fields'] = cr_tinyint_field_map($fieldColumns);
$fkDisplayMaps = cr_fk_display_maps($conn, $fkMap, $uiColumns, (int)$company_id);

$modulePath = dirname($_SERVER['PHP_SELF']);
$listUrl = $modulePath . '/index.php';
$csrfToken = cr_get_csrf_token();

// HANDLE DELETIONS (via POST)
if ($crud_action === 'delete') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        exit('Method not allowed.');
    }

    cr_require_valid_csrf_token();

    $bulkAction = (string)($_POST['bulk_action'] ?? 'single_delete');
    
    // Clear whole table (scoped by company)
    if ($bulkAction === 'clear_table') {
        $hasCompanyFilter = ($hasCompany && $company_id > 0);
        $where = $hasCompanyFilter ? ' WHERE company_id=?' : '';
        $deleteSql = 'DELETE FROM ' . cr_escape_identifier($crud_table) . $where;
        
        $stmt = mysqli_prepare($conn, $deleteSql);
        if ($stmt) {
            if ($hasCompanyFilter) { mysqli_stmt_bind_param($stmt, 'i', $company_id); }
            if (!mysqli_stmt_execute($stmt)) {
                $_SESSION['crud_error'] = itm_format_db_constraint_error(mysqli_stmt_errno($stmt), mysqli_stmt_error($stmt));
            }
            mysqli_stmt_close($stmt);
        }
        header('Location: ' . $listUrl);
        exit;
    }

    // Bulk delete selected IDs
    if ($bulkAction === 'bulk_delete') {
        $ids = $_POST['ids'] ?? [];
        if (!is_array($ids)) { $ids = []; }
        $idList = [];
        foreach ($ids as $rawId) {
            $id = (int)$rawId;
            if ($id > 0) { $idList[] = $id; }
        }

        if (!empty($idList)) {
            $placeholders = implode(',', array_fill(0, count($idList), '?'));
            $hasCompanyFilter = ($hasCompany && $company_id > 0);
            $where = ' WHERE id IN (' . $placeholders . ')';
            if ($hasCompanyFilter) { $where .= ' AND company_id=?'; }
            $deleteSql = 'DELETE FROM ' . cr_escape_identifier($crud_table) . $where;
            
            $stmt = mysqli_prepare($conn, $deleteSql);
            if ($stmt) {
                $types = str_repeat('i', count($idList));
                if ($hasCompanyFilter) {
                    $types .= 'i';
                    $idList[] = (int)$company_id;
                }
                mysqli_stmt_bind_param($stmt, $types, ...$idList);
                if (!mysqli_stmt_execute($stmt)) {
                    $_SESSION['crud_error'] = itm_format_db_constraint_error(mysqli_stmt_errno($stmt), mysqli_stmt_error($stmt));
                }
                mysqli_stmt_close($stmt);
            }
        } else {
            $_SESSION['crud_error'] = 'No records selected for deletion.';
        }
        header('Location: ' . $listUrl);
        exit;
    }

    // Single row delete
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($id > 0) {
        $hasCompanyFilter = ($hasCompany && $company_id > 0);
        $where = ' WHERE id=?';
        if ($hasCompanyFilter) { $where .= ' AND company_id=?'; }
        $deleteSql = 'DELETE FROM ' . cr_escape_identifier($crud_table) . $where . ' LIMIT 1';
        
        $stmt = mysqli_prepare($conn, $deleteSql);
        if ($stmt) {
            if ($hasCompanyFilter) { mysqli_stmt_bind_param($stmt, 'ii', $id, $company_id); }
            else { mysqli_stmt_bind_param($stmt, 'i', $id); }
            if (!mysqli_stmt_execute($stmt)) {
                $_SESSION['crud_error'] = itm_format_db_constraint_error(mysqli_stmt_errno($stmt), mysqli_stmt_error($stmt));
            }
            mysqli_stmt_close($stmt);
        }
    }
    header('Location: ' . $listUrl);
    exit;
}

$errors = [];
if (!empty($_SESSION['crud_error'])) {
    $errors[] = (string)$_SESSION['crud_error'];
    unset($_SESSION['crud_error']);
}
$data = [];
foreach ($fieldColumns as $col) { $data[$col['Field']] = ''; }

if ($crud_table === 'catalogs' && $crud_action === 'create') {
    if (array_key_exists('image_url', $data) && trim((string)$data['image_url']) === '') {
        $data['image_url'] = $catalogDefaultImageUrl;
    }
    if (array_key_exists('product_url', $data) && trim((string)$data['product_url']) === '') {
        $data['product_url'] = $catalogDefaultWeblinkUrl;
    }
}

// HANDLE FETCH FOR EDIT/VIEW
$editId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (in_array($crud_action, ['edit', 'view'], true) && $editId > 0) {
    $hasCompanyFilter = ($hasCompany && $company_id > 0);
    $where = ' WHERE id=?';
    if ($hasCompanyFilter) { $where .= ' AND company_id=?'; }
    $sql = 'SELECT * FROM ' . cr_escape_identifier($crud_table) . $where . ' LIMIT 1';
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        if ($hasCompanyFilter) { mysqli_stmt_bind_param($stmt, 'ii', $editId, $company_id); }
        else { mysqli_stmt_bind_param($stmt, 'i', $editId); }
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $data = ($res && mysqli_num_rows($res) === 1) ? mysqli_fetch_assoc($res) : [];
        mysqli_stmt_close($stmt);
    }
    
    if (!$data) { $errors[] = 'Record not found.'; }
}

// HANDLE FORM SUBMISSION (CREATE/EDIT)

// Handle sample data seeding for empty companies in list view
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($crud_action, ['index', 'list_all'], true) && isset($_POST['add_sample_data'])) {
    cr_require_valid_csrf_token();

    if (!$hasCompany || $company_id <= 0) {
        $_SESSION['crud_error'] = 'Sample data requires an active company.';
        header('Location: ' . $listUrl);
        exit;
    }

    $where = ' WHERE company_id=' . (int)$company_id;
    $countSql = 'SELECT COUNT(*) AS total_rows FROM ' . cr_escape_identifier($crud_table) . $where;
    $countResult = mysqli_query($conn, $countSql);
    $existingRows = 0;
    if ($countResult && ($countRow = mysqli_fetch_assoc($countResult))) {
        $existingRows = (int)($countRow['total_rows'] ?? 0);
    }

    if ($existingRows > 0) {
        $_SESSION['crud_error'] = 'Sample data can only be added when no records exist.';
        header('Location: ' . $listUrl);
        exit;
    }

    $seedError = '';
    $insertedRows = itm_seed_table_from_database_sql($conn, $crud_table, (int)$company_id, $seedError);
    if ($insertedRows <= 0 && $seedError !== '') {
        $_SESSION['crud_error'] = $seedError;
    }

    header('Location: ' . $listUrl);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($crud_action, ['create', 'edit'], true)) {
    cr_require_valid_csrf_token();

    foreach ($fieldColumns as $col) {
        $name = $col['Field'];
        $isTinyInt = cr_starts_with($col['Type'], 'tinyint(1)');
        
        // Logical Booleans
        if ($isTinyInt) {
            $data[$name] = isset($_POST[$name]) ? 1 : 0;
            continue;
        }

        // Auto-assign company ownership
        if ($name === 'company_id' && $company_id > 0) {
            $data[$name] = (int)$company_id;
            continue;
        }

        // Handle Foreign Keys with inline "add new parent" support
        if (isset($fkMap[$name])) {
            $value = $_POST[$name] ?? null;
            $newKey = $name . '__new_value';
            $newValueRaw = trim((string)($_POST[$newKey] ?? ''));

            if ($value === '__add_new__') {
                $errors[] = 'Please wait for the new value to be created before saving.';
                $data[$name] = null;
                continue;
            }

            if ($value === '__new__' && $newValueRaw !== '') {
                // The JS bridge requested an inline insert of a missing reference.
                $fk = $fkMap[$name];
                $fkTable = $fk['REFERENCED_TABLE_NAME'];
                $fkCol = $fk['REFERENCED_COLUMN_NAME'];
                $meta = cr_fk_metadata($conn, $fkTable);
                $labelCol = $meta['label_col'];
                $available = $meta['available'];

                $hasCompanyFilter = (in_array('company_id', $available, true) && $company_id > 0);
                $findSql = 'SELECT ' . cr_escape_identifier($fkCol) . ' AS id FROM ' . cr_escape_identifier($fkTable)
                    . ' WHERE ' . cr_escape_identifier($labelCol) . "=?";
                if ($hasCompanyFilter) { $findSql .= ' AND company_id=?'; }
                $findSql .= ' LIMIT 1';
                
                $stmtFind = mysqli_prepare($conn, $findSql);
                $existingId = null;
                if ($stmtFind) {
                    if ($hasCompanyFilter) { mysqli_stmt_bind_param($stmtFind, 'si', $newValueRaw, $company_id); }
                    else { mysqli_stmt_bind_param($stmtFind, 's', $newValueRaw); }
                    mysqli_stmt_execute($stmtFind);
                    $resEx = mysqli_stmt_get_result($stmtFind);
                    if ($resEx && mysqli_num_rows($resEx) > 0) {
                        $row = mysqli_fetch_assoc($resEx);
                        $existingId = (int)$row['id'];
                    }
                    mysqli_stmt_close($stmtFind);
                }

                if ($existingId !== null) {
                    $data[$name] = $existingId;
                } else {
                    $insertFields = [cr_escape_identifier($labelCol)];
                    $placeholders = ['?'];
                    $params = [$newValueRaw];
                    $types = 's';
                    if ($hasCompanyFilter) {
                        $insertFields[] = '`company_id`';
                        $placeholders[] = '?';
                        $params[] = (int)$company_id;
                        $types .= 'i';
                    }
                    $insertSql = 'INSERT INTO ' . cr_escape_identifier($fkTable)
                        . ' (' . implode(',', $insertFields) . ') VALUES (' . implode(',', $placeholders) . ')';
                    
                    $stmtIns = mysqli_prepare($conn, $insertSql);
                    if ($stmtIns) {
                        mysqli_stmt_bind_param($stmtIns, $types, ...$params);
                        if (mysqli_stmt_execute($stmtIns)) {
                            $data[$name] = (int)mysqli_insert_id($conn);
                        } else {
                            $errors[] = 'Could not add related value for ' . $name . '. ' . itm_format_db_constraint_error(mysqli_stmt_errno($stmtIns), mysqli_stmt_error($stmtIns));
                            $data[$name] = null;
                        }
                        mysqli_stmt_close($stmtIns);
                    }
                }
                continue;
            }
        }

        // Generic value processing and numeric validation
        $value = $_POST[$name] ?? null;
        if ($value === '' || $value === null) {
            $data[$name] = null;
        } elseif (preg_match('/int|decimal|float|double/', $col['Type'])) {
            $normalizedNumeric = null; $numericError = '';
            if (!cr_validate_numeric_value($value, $col, $name, $normalizedNumeric, $numericError)) {
                $errors[] = $numericError;
                $data[$name] = null;
            } else {
                $data[$name] = $normalizedNumeric;
            }
        } else {
            $data[$name] = (string)$value;
        }
    }

    // Why: reduce manual catalog work by auto-filling the image URL from the product page when possible.
    if ($crud_table === 'catalogs' && array_key_exists('image_url', $data)) {
        $rawImage = trim((string)($data['image_url'] ?? ''));
        $sourceUrl = trim((string)($data['product_url'] ?? ''));

        if ($rawImage === '' && $sourceUrl !== '') {
            $detectedImage = cr_catalog_detect_image_url_from_page($sourceUrl);
            if ($detectedImage !== '') {
                $data['image_url'] = $detectedImage;
            }
        } elseif ($rawImage !== '') {
            $normalizedImage = cr_normalize_external_url($rawImage);
            if ($normalizedImage !== '') {
                $data['image_url'] = $normalizedImage;
            }
        }
    }

    // PERSISTENCE (Prepared Statements)
    if (empty($errors)) {
        $fields = []; $placeholders = []; $params = []; $types = '';

        foreach ($fieldColumns as $col) {
            $name = $col['Field'];
            $fields[] = cr_escape_identifier($name);
            $placeholders[] = '?';
            $params[] = $data[$name];
            
            $colType = strtolower($col['Type']);
            if (cr_contains($colType, 'int') || cr_contains($colType, 'decimal') || cr_contains($colType, 'float') || cr_contains($colType, 'double')) {
                $types .= ($data[$name] === null) ? 's' : (cr_contains($colType, 'int') ? 'i' : 'd');
            } else {
                $types .= 's';
            }
        }

        if ($crud_action === 'create') {
            $sql = 'INSERT INTO ' . cr_escape_identifier($crud_table) . ' (' . implode(',', $fields) . ') VALUES (' . implode(',', $placeholders) . ')';
            $stmt = mysqli_prepare($conn, $sql);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, $types, ...$params);
                if (mysqli_stmt_execute($stmt)) {
                    mysqli_stmt_close($stmt);
                    header('Location: ' . $listUrl);
                    exit;
                }
                $errors[] = itm_format_db_constraint_error(mysqli_stmt_errno($stmt), mysqli_stmt_error($stmt));
                mysqli_stmt_close($stmt);
            }
        } else {
            $sets = [];
            foreach ($fields as $f) { $sets[] = $f . '=?'; }
            $hasCompanyFilter = ($hasCompany && $company_id > 0);
            $where = ' WHERE id=?';
            if ($hasCompanyFilter) { $where .= ' AND company_id=?'; }
            $sql = 'UPDATE ' . cr_escape_identifier($crud_table) . ' SET ' . implode(',', $sets) . $where . ' LIMIT 1';
            
            $stmt = mysqli_prepare($conn, $sql);
            if ($stmt) {
                $types .= 'i';
                $params[] = $editId;
                if ($hasCompanyFilter) {
                    $types .= 'i';
                    $params[] = $company_id;
                }
                mysqli_stmt_bind_param($stmt, $types, ...$params);
                if (mysqli_stmt_execute($stmt)) {
                    mysqli_stmt_close($stmt);
                    header('Location: ' . $listUrl);
                    exit;
                }
                $errors[] = itm_format_db_constraint_error(mysqli_stmt_errno($stmt), mysqli_stmt_error($stmt));
                mysqli_stmt_close($stmt);
            }
        }
    }
}

// FETCH LIST DATA (Pagination, Search, and Sort)
$where = '';
if ($hasCompany && $company_id > 0) { $where = ' WHERE company_id=' . (int)$company_id; }
$catalogNewProductsDays = 30;
$showCatalogNewProducts = ($crud_table === 'catalogs' && (string)($_GET['new_products'] ?? '') === '1');
if ($showCatalogNewProducts) {
    $newProductsCondition = '(created_at >= DATE_SUB(NOW(), INTERVAL ' . (int)$catalogNewProductsDays . ' DAY) OR (updated_at IS NOT NULL AND updated_at >= DATE_SUB(NOW(), INTERVAL ' . (int)$catalogNewProductsDays . ' DAY)))';
    $where .= ($where === '' ? ' WHERE ' : ' AND ') . $newProductsCondition;
}

// SEARCH
$searchRaw = trim((string)($_GET['search'] ?? ''));
if ($searchRaw !== '') {
    $searchPattern = (cr_contains($searchRaw, '%') || cr_contains($searchRaw, '_')) ? $searchRaw : '%' . $searchRaw . '%';
    $searchEsc = mysqli_real_escape_string($conn, $searchPattern);
    $searchConditions = ["CAST(`id` AS CHAR) LIKE '{$searchEsc}'"];
    foreach ($fieldColumns as $col) {
        $fieldName = (string)($col['Field'] ?? '');
        if ($fieldName === '') { continue; }
        $searchConditions[] = 'CAST(' . cr_escape_identifier($fieldName) . " AS CHAR) LIKE '{$searchEsc}'";
    }
    if (!empty($searchConditions)) {
        $where .= ($where === '' ? ' WHERE ' : ' AND ') . '(' . implode(' OR ', $searchConditions) . ')';
    }
}

// SORTING
$sortableColumns = array_map(static function ($col) { return $col['Field']; }, $fieldColumns);
$sort = (string)($_GET['sort'] ?? 'id');
$dir = strtoupper((string)($_GET['dir'] ?? 'DESC'));
if (!in_array($sort, $sortableColumns, true)) { $sort = 'id'; }
if (!in_array($dir, ['ASC', 'DESC'], true)) { $dir = 'DESC'; }
$sortSql = cr_escape_identifier($sort) . ' ' . $dir;
$catalogNewProductsQuery = $showCatalogNewProducts ? '&new_products=1' : '';

// PAGINATION
$perPage = itm_resolve_records_per_page($ui_config ?? null);
$countResult = mysqli_query($conn, 'SELECT COUNT(*) AS total_rows FROM ' . cr_escape_identifier($crud_table) . $where);
$totalRows = 0;
if ($countResult && ($countRow = mysqli_fetch_assoc($countResult))) { $totalRows = (int)($countRow['total_rows'] ?? 0); }
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) { $page = 1; }
if ($page > $totalPages) { $page = $totalPages; }
$offset = ($page - 1) * $perPage;

$rows = mysqli_query($conn, 'SELECT * FROM ' . cr_escape_identifier($crud_table) . $where . ' ORDER BY ' . $sortSql . ' LIMIT ' . $offset . ', ' . $perPage);
$moduleListHeading = itm_sidebar_label_for_module(basename(dirname($_SERVER['PHP_SELF']))) ?: $crud_title;
$newButtonPosition = (string)($ui_config['new_button_position'] ?? 'left_right');
if (!in_array($newButtonPosition, ['left', 'right', 'left_right'], true)) { $newButtonPosition = 'left_right'; }

$successMessage = '';
if (!empty($_SESSION['crud_success'])) {
    $successMessage = (string)$_SESSION['crud_success'];
    unset($_SESSION['crud_success']);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo sanitize($crud_title); ?> Management</title>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <?php if ($successMessage !== ''): ?>
                <div class="alert alert-success"><?php echo sanitize($successMessage); ?></div>
            <?php endif; ?>
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error"><?php echo sanitize(implode(' ', $errors)); ?></div>
            <?php endif; ?>

            <?php if (in_array($crud_action, ['index', 'list_all'], true)): ?>
                <!-- LIST VIEW -->
                <div data-itm-new-button-managed="server" style="position:relative;display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;min-height:40px;">
                    <?php if (in_array($newButtonPosition, ['left', 'left_right'], true)): ?>
                        <div style="display:flex;gap:8px;">
                            <a href="create.php" class="btn btn-primary">➕</a>
                        </div>
                    <?php else: ?>
                        <span></span>
                    <?php endif; ?>
                    <h1 style="position:absolute;left:50%;transform:translateX(-50%);margin:0;text-align:center;"><?php echo sanitize($moduleListHeading); ?></h1>
                    <?php if (in_array($newButtonPosition, ['right', 'left_right'], true)): ?>
                        <div style="display:flex;gap:8px;">
                            <a href="create.php" class="btn btn-primary">➕</a>
                        </div>
                    <?php else: ?>
                        <span></span>
                    <?php endif; ?>
                </div>
                <?php if ($showCatalogNewProducts): ?>
                    <div class="card" style="margin-bottom:16px;">
                        <strong>Showing products added or updated in the last <?php echo (int)$catalogNewProductsDays; ?> days.</strong>
                    </div>
                <?php endif; ?>

                <!-- TABLE MAINTENANCE -->
                <div class="card" style="margin-bottom:16px;">
                    <form id="bulk-delete-form" method="POST" action="delete.php" style="display:flex;gap:8px;">
                        <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                        <button type="submit" name="bulk_action" value="bulk_delete" class="btn btn-sm btn-danger" id="bulk-delete-toggle">Select to Delete</button>
                        <button type="submit" name="bulk_action" value="clear_table" class="btn btn-sm btn-danger" onclick="return confirm('Clear all records in this table? This cannot be undone.');">Clear Table</button>
                    </form>
                </div>

                <!-- SEARCH BAR -->
                <div class="card" style="margin-bottom:16px;">
                    <form method="GET" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
                        <input type="hidden" name="sort" value="<?php echo sanitize($sort); ?>">
                        <input type="hidden" name="dir" value="<?php echo sanitize($dir); ?>">
                        <?php if ($showCatalogNewProducts): ?>
                            <input type="hidden" name="new_products" value="1">
                        <?php endif; ?>
                        <input type="hidden" name="page" value="1">
                        <div class="form-group" style="margin:0;min-width:260px;flex:1;">
                            <label for="moduleSearch">Search (all fields)</label>
                            <input type="text" id="moduleSearch" name="search" value="<?php echo sanitize($searchRaw); ?>" placeholder="Type to search records...">
                        </div>
                        <div class="form-actions" style="margin:0;display:flex;gap:8px;">
                            <button type="submit" class="btn btn-primary">Search</button>
                            <a href="index.php" class="btn">🔙</a>
                        </div>
                    </form>
                </div>

                <!-- DATA TABLE -->
                <div class="card" style="overflow:auto;">
                    <table>
                        <thead>
                        <tr>
                            <th style="width:36px;"><input type="checkbox" id="select-all-rows" aria-label="Select all rows"></th>
                            <?php foreach ($uiColumns as $col): ?>
                                <?php $field = (string)$col['Field']; ?>
                                <?php $nextDir = ($sort === $field && $dir === 'ASC') ? 'DESC' : 'ASC'; ?>
                                <th>
                                    <a href="?search=<?php echo urlencode($searchRaw); ?>&sort=<?php echo urlencode($field); ?>&dir=<?php echo $nextDir; ?>&page=<?php echo (int)$page; ?><?php echo $catalogNewProductsQuery; ?>" style="text-decoration:none;color:inherit;">
                                        <?php echo sanitize(cr_humanize_field($field)); ?>
                                        <?php if ($sort === $field): ?> <?php echo $dir === 'ASC' ? '▲' : '▼'; ?><?php endif; ?>
                                    </a>
                                </th>
                            <?php endforeach; ?>
                            <th data-itm-actions-origin="1">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if ($rows && mysqli_num_rows($rows) > 0): while ($row = mysqli_fetch_assoc($rows)): ?>
                            <tr>
                                <td><input type="checkbox" name="ids[]" value="<?php echo (int)$row['id']; ?>" form="bulk-delete-form"></td>
                                <?php foreach ($uiColumns as $col): $f = $col['Field']; ?>
                                    <td>
                                        <?php
                                            $displayValue = $row[$f] ?? '';
                                            $fkDisplayKey = trim((string)$displayValue);
                                            if ($fkDisplayKey !== '' && isset($fkDisplayMaps[$f][$fkDisplayKey])) {
                                                $displayValue = $fkDisplayMaps[$f][$fkDisplayKey];
                                            }
                                        ?>
                                        <?php if ($f === 'comments' && trim((string)$displayValue) !== ''): ?>
                                            <span title="<?php echo sanitize((string)$row[$f]); ?>">💬</span>
                                        <?php else: ?>
                                            <?php echo cr_render_cell_value($crud_table, $f, $displayValue); ?>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                                <td class="itm-actions-cell" data-itm-actions-origin="1">
                                    <div class="itm-actions-wrap">
                                        <a class="btn btn-sm" href="view.php?id=<?php echo (int)$row['id']; ?>">🔎</a>
                                        <a class="btn btn-sm" href="edit.php?id=<?php echo (int)$row['id']; ?>">✏️</a>
                                        <form method="POST" action="delete.php" style="display:inline;" onsubmit="return confirm('Delete this record?');">
                                            <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                                            <input type="hidden" name="bulk_action" value="single_delete">
                                            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                                            <button class="btn btn-sm btn-danger" type="submit">🗑️</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; else: ?>
                            <tr><td colspan="<?php echo count($fieldColumns) + 2; ?>" style="text-align:center;">No records found.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($hasCompany && $company_id > 0 && $totalRows === 0): ?>
                    <div class="card" style="margin-top:12px;">
                        <form method="POST" style="display:flex;justify-content:center;">
                            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                            <button type="submit" name="add_sample_data" value="1" class="btn btn-primary">Add sample data</button>
                        </form>
                    </div>
                <?php endif; ?>

                <!-- PAGINATION -->
                <?php if ($totalRows > $perPage): ?>
                    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-top:12px;">
                        <div>Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $perPage, $totalRows); ?> of <?php echo $totalRows; ?></div>
                        <div style="display:flex;gap:6px;flex-wrap:wrap;">
                            <?php if ($page > 1): ?>
                                <a class="btn btn-sm" href="?search=<?php echo urlencode($searchRaw); ?>&sort=<?php echo urlencode($sort); ?>&dir=<?php echo urlencode($dir); ?>&page=<?php echo $page - 1; ?><?php echo $catalogNewProductsQuery; ?>">Previous</a>
                            <?php endif; ?>
                            <span class="btn btn-sm" style="pointer-events:none;opacity:.8;">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
                            <?php if ($page < $totalPages): ?>
                                <a class="btn btn-sm" href="?search=<?php echo urlencode($searchRaw); ?>&sort=<?php echo urlencode($sort); ?>&dir=<?php echo urlencode($dir); ?>&page=<?php echo $page + 1; ?><?php echo $catalogNewProductsQuery; ?>">Next</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

            <?php elseif (in_array($crud_action, ['create', 'edit'], true)): ?>
                <!-- EDIT/CREATE VIEW -->
                <h1><?php echo $crud_action === 'create' ? 'New ' : 'Edit '; ?><?php echo sanitize($crud_title); ?></h1>
                <form method="POST" class="form-grid" style="max-width:980px;">
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                    <?php foreach ($formColumns as $col): $name = $col['Field'];
                        $isTinyInt = cr_starts_with($col['Type'], 'tinyint(1)') || $name === 'active';
                        $isDate = cr_starts_with($col['Type'], 'date');
                        $isDateTime = cr_starts_with($col['Type'], 'datetime');
                        $isText = cr_contains($col['Type'], 'text');
                        $val = $data[$name] ?? '';
                        $displayVal = ($val === null) ? '' : (string)$val;
                    ?>
                        <div class="form-group">
                            <label><?php echo sanitize(cr_humanize_field($name)); ?></label>
                            <?php if ($isTinyInt): ?>
                                <label class="itm-checkbox-control">
                                    <input type="checkbox" name="<?php echo sanitize($name); ?>" value="1" <?php echo ((int)$displayVal === 1) ? 'checked' : ''; ?>>
                                    <span><?php echo sanitize(cr_humanize_field($name)); ?> <span class="itm-check-indicator" aria-hidden="true"><?php echo ((int)$displayVal === 1) ? '✅' : '❌'; ?></span></span>
                                </label>
                            <?php elseif (isset($fkMap[$name])): ?>
                                <?php
                                    $opts = cr_fk_options($conn, $fkMap[$name], (int)$company_id);
                                    $fkMeta = cr_fk_metadata($conn, $fkMap[$name]['REFERENCED_TABLE_NAME']);
                                    $isCompanyScoped = in_array('company_id', $fkMeta['available'], true) ? 1 : 0;

                                    $hasSelectedOption = false;
                                    foreach ($opts as $itmOpt) {
                                        if ((string)($itmOpt['id'] ?? '') === (string)$displayVal) {
                                            $hasSelectedOption = true;
                                            break;
                                        }
                                    }
                                    if (!$hasSelectedOption && trim((string)$displayVal) !== '') {
                                        $selectedFkOption = cr_fk_option_by_id($conn, $fkMap[$name], (int)$company_id, (int)$displayVal);
                                        if ($selectedFkOption) {
                                            $opts[] = $selectedFkOption;
                                        }
                                    }
                                ?>
                                <select
                                    name="<?php echo sanitize($name); ?>"
                                    data-addable-select="1"
                                    data-add-table="<?php echo sanitize($fkMap[$name]['REFERENCED_TABLE_NAME']); ?>"
                                    data-add-id-col="<?php echo sanitize($fkMap[$name]['REFERENCED_COLUMN_NAME']); ?>"
                                    data-add-label-col="<?php echo sanitize($fkMeta['label_col']); ?>"
                                    data-add-company-scoped="<?php echo $isCompanyScoped; ?>"
                                    data-add-friendly="<?php echo sanitize(strtolower(cr_humanize_field($name))); ?>"
                                >
                                    <option value="">-- Select --</option>
                                    <?php foreach ($opts as $opt): ?>
                                        <option value="<?php echo (int)$opt['id']; ?>" <?php echo ((string)$displayVal === (string)$opt['id']) ? 'selected' : ''; ?>><?php echo sanitize($opt['label']); ?></option>
                                    <?php endforeach; ?>
                                    <option value="__add_new__">➕</option>
                                </select>
                            <?php elseif ($isDateTime): ?>
                                <input type="datetime-local" name="<?php echo sanitize($name); ?>" value="<?php echo sanitize(str_replace(' ', 'T', substr($displayVal, 0, 16))); ?>">
                            <?php elseif ($isDate): ?>
                                <input type="date" name="<?php echo sanitize($name); ?>" value="<?php echo sanitize(substr($displayVal, 0, 10)); ?>">
                            <?php elseif ($isText): ?>
                                <textarea name="<?php echo sanitize($name); ?>" rows="4"><?php echo sanitize($displayVal); ?></textarea>
                            <?php elseif ($crud_table === 'catalogs' && in_array($name, ['image', 'image_url'], true)): ?>
                                <input type="url" name="<?php echo sanitize($name); ?>" value="<?php echo sanitize($displayVal); ?>" placeholder="https://media.sweetwater.com/m/products/image/3c5509fab3bELb9Waebi8c1dQ7M237dDRNdrmnkr.jpg" inputmode="url">
                                <?php if ($displayVal !== ''): ?>
                                    <div style="margin-top:8px;max-width:100%;overflow:auto;">
                                        <?php echo cr_catalog_image_preview_html($displayVal, 220, 140); ?>
                                    </div>
                                <?php endif; ?>
                            <?php elseif ($crud_table === 'catalogs' && in_array($name, ['weblink', 'source_url', 'product_url'], true)): ?>
                                <input type="url" name="<?php echo sanitize($name); ?>" value="<?php echo sanitize($displayVal); ?>" placeholder="https://www.sweetwater.com/store/detail/USW24POE--ubiquiti-networks-unifi-switch-24-poe" inputmode="url">
                            <?php else: ?>
                                <input type="text" name="<?php echo sanitize($name); ?>" value="<?php echo sanitize($displayVal); ?>">
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <div class="form-actions">
                        <button class="btn btn-primary" type="submit">💾</button>
                        <a href="index.php" class="btn">🔙</a>
                    </div>
                </form>

            <?php elseif ($crud_action === 'view'): ?>
                <!-- VIEW (DETAILS) -->
                <?php
                    $detailPreviewMaxWidth = isset($crud_catalog_image_preview_max_width) ? max(200, (int)$crud_catalog_image_preview_max_width) : 560;
                    $detailPreviewMaxHeight = isset($crud_catalog_image_preview_max_height) ? max(160, (int)$crud_catalog_image_preview_max_height) : 360;
                ?>
                <h1>View <?php echo sanitize($crud_title); ?></h1>
                <div class="card">
                    <table>
                        <tbody>
                        <?php foreach ($uiColumns as $col): $f = $col['Field']; ?>
                            <tr>
                                <th style="width:240px;"><?php echo sanitize(cr_humanize_field($f)); ?></th>
                                <td>
                                    <?php
                                        $detailDisplayValue = $data[$f] ?? '';
                                        $detailFkKey = trim((string)$detailDisplayValue);
                                        if ($detailFkKey !== '' && isset($fkDisplayMaps[$f][$detailFkKey])) {
                                            $detailDisplayValue = $fkDisplayMaps[$f][$detailFkKey];
                                        } elseif ($detailFkKey !== '' && isset($fkMap[$f])) {
                                            $fallbackFkOption = cr_fk_option_by_id($conn, $fkMap[$f], (int)$company_id, (int)$detailFkKey);
                                            if ($fallbackFkOption && isset($fallbackFkOption['label'])) {
                                                $detailDisplayValue = (string)$fallbackFkOption['label'];
                                            }
                                        }
                                    ?>
                                    <?php if ($crud_table === 'catalogs' && in_array($f, ['image', 'image_url'], true) && trim((string)($data[$f] ?? '')) !== ''): ?>
                                        <div style="max-width:100%;overflow:auto;">
                                            <?php echo cr_catalog_image_preview_html((string)$data[$f], $detailPreviewMaxWidth, $detailPreviewMaxHeight); ?>
                                        </div>
                                    <?php else: ?>
                                        <?php echo cr_render_cell_value($crud_table, $f, $detailDisplayValue); ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p style="margin-top:16px;">
                        <a href="index.php" class="btn">🔙</a> 
                        <a class="btn btn-primary" href="edit.php?id=<?php echo (int)($data['id'] ?? 0); ?>">✏️</a>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
/**
 * UI script for managing bulk selection and multi-delete mode.
 */
(function () {
    const selectAllRows = document.getElementById('select-all-rows') || document.getElementById('select-all-departments');
    const bulkDeleteForm = document.querySelector('form[id="bulk-delete-form"], form[id="department-bulk-form"]');
    const toggleButton = bulkDeleteForm ? bulkDeleteForm.querySelector('button[name="bulk_action"][value="bulk_delete"]') : null;
    const rowCheckboxes = bulkDeleteForm ? document.querySelectorAll('input[name="ids[]"][form="' + bulkDeleteForm.id + '"]') : [];
    const deleteCells = Array.from(rowCheckboxes).map(function (checkbox) { return checkbox.closest('td'); }).filter(Boolean);
    const selectAllHeaderCell = selectAllRows ? selectAllRows.closest('th') : null;
    let selectionMode = false;

    function setSelectionVisibility(visible) {
        if (selectAllHeaderCell) { selectAllHeaderCell.style.display = visible ? '' : 'none'; }
        deleteCells.forEach(function (cell) { cell.style.display = visible ? '' : 'none'; });
    }

    if (selectAllRows) {
        selectAllRows.addEventListener('change', function () {
            rowCheckboxes.forEach(function (checkbox) { checkbox.checked = selectAllRows.checked; });
        });
    }

    if (bulkDeleteForm && toggleButton) {
        setSelectionVisibility(false);
        bulkDeleteForm.addEventListener('submit', function (event) {
            if (event.submitter !== toggleButton) { return; }
            if (!selectionMode) {
                event.preventDefault();
                selectionMode = true;
                setSelectionVisibility(true);
                toggleButton.textContent = 'Delete Selected';
                return;
            }
            const anySelected = Array.from(rowCheckboxes).some(function (checkbox) { return checkbox.checked; });
            if (!anySelected) {
                event.preventDefault();
                alert('Please select at least one record to delete.');
                return;
            }
            if (!confirm('Delete selected records?')) { event.preventDefault(); }
        });
    }

})();
</script>
<script src="../../js/theme.js"></script>
<script> window.ITM_CSRF_TOKEN = <?php echo json_encode($csrfToken); ?>; </script>
<script src="../../js/select-add-option.js"></script>
<script>
/**
 * Helper to handle Outlook mailto links and dynamic checkbox visual indicators.
 */
document.addEventListener('click', function (event) {
    const link = event.target.closest('a[data-outlook-link="1"]');
    if (!link) return;
    const outlookHref = link.getAttribute('data-outlook-href');
    if (outlookHref) { window.location.href = outlookHref; }
});
document.addEventListener('change', function (event) {
    if (!event.target.matches('.itm-checkbox-control input[type="checkbox"]')) return;
    const indicator = event.target.closest('.itm-checkbox-control')?.querySelector('.itm-check-indicator');
    if (indicator) { indicator.textContent = event.target.checked ? '✅' : '❌'; }
});
</script>
</body>
</html>
