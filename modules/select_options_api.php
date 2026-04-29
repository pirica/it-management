<?php
/**
 * Shared Select Options API
 * 
 * Provides a generic endpoint for creating new reference records (options) 
 * on-the-fly from within other forms. 
 * Used primarily by the frontend script `js/select-add-option.js`.
 * 
 * Logic:
 * - Dynamic Schema Mapping: Scans the target table's columns to identify 
 *   required fields.
 * - Validation: Ensures all required non-nullable columns without defaults 
 *   are provided in the `extra_fields` payload.
 * - Multi-tenant Safety: Handles `company_id` scoping for both lookups 
 *   and insertions.
 * - Response: Returns the newly created ID and the full refreshed list of 
 *   options for the dropdown.
 */

require '../config/config.php';

header('Content-Type: application/json; charset=utf-8');

// POST strictly required for state change
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

/**
 * Ensures CSRF validation for AJAX requests.
 */
function so_require_valid_csrf_token() {
    $token = (string)($_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    if (!itm_validate_csrf_token($token)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Forbidden: invalid CSRF token.']);
        exit;
    }
}

// Validate CSRF to prevent unauthorized remote additions
so_require_valid_csrf_token();

/**
 * Validates that a string is a safe DB identifier.
 * This is critical as these values are used in SQL query building.
 */
function so_identifier($value) {
    return is_string($value) && preg_match('/^[a-zA-Z0-9_]+$/', $value);
}

/**
 * Escapes a DB identifier.
 */
function so_escape_identifier($name) {
    return '`' . str_replace('`', '``', $name) . '`';
}

/**
 * Retrieves column metadata for schema introspection.
 */

function so_hex_to_color_name($hexColor) {
    $hex = strtoupper(trim((string)$hexColor));
    if (!preg_match('/^#[0-9A-F]{6}$/', $hex)) {
        return '';
    }

    $r = hexdec(substr($hex, 1, 2));
    $g = hexdec(substr($hex, 3, 2));
    $b = hexdec(substr($hex, 5, 2));

    $max = max($r, $g, $b);
    $min = min($r, $g, $b);
    $delta = $max - $min;
    $lightness = ($max + $min) / 510;

    if ($delta < 12) {
        if ($lightness < 0.2) { return 'Black'; }
        if ($lightness > 0.86) { return 'White'; }
        return 'Gray';
    }

    if ($max === $r) {
        $hue = 60 * fmod((($g - $b) / max($delta, 1)), 6);
    } elseif ($max === $g) {
        $hue = 60 * ((($b - $r) / max($delta, 1)) + 2);
    } else {
        $hue = 60 * ((($r - $g) / max($delta, 1)) + 4);
    }
    if ($hue < 0) { $hue += 360; }

    $baseName = 'Red';
    if ($hue < 20 || $hue >= 345) {
        $baseName = 'Red';
    } elseif ($hue < 45) {
        $baseName = 'Orange';
    } elseif ($hue < 70) {
        $baseName = 'Yellow';
    } elseif ($hue < 160) {
        $baseName = 'Green';
    } elseif ($hue < 200) {
        $baseName = 'Cyan';
    } elseif ($hue < 255) {
        $baseName = 'Blue';
    } elseif ($hue < 290) {
        $baseName = 'Purple';
    } else {
        $baseName = 'Pink';
    }

    $prefix = '';
    if ($lightness >= 0.72) {
        $prefix = 'Light ';
    } elseif ($lightness <= 0.28) {
        $prefix = 'Dark ';
    }

    return trim($prefix . $baseName);
}

function so_table_columns($conn, $table) {
    $columns = [];
    $res = mysqli_query($conn, 'DESCRIBE ' . so_escape_identifier($table));
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $columns[$row['Field']] = $row;
    }
    return $columns;
}

// Extract parameters from request. These define which reference table we are appending to.
$table = $_POST['table'] ?? '';
$idCol = $_POST['id_col'] ?? 'id';
$labelCol = $_POST['label_col'] ?? 'name';
$newValue = trim((string)($_POST['new_value'] ?? ''));
$companyScoped = (int)($_POST['company_scoped'] ?? 0) === 1;
$extraFieldsRaw = (string)($_POST['extra_fields'] ?? '');

// Parse extra metadata (e.g. hex colors, codes). 
// This allows the API to handle tables with more than just a name/label.
$extraFields = [];
if ($extraFieldsRaw !== '') {
    $decoded = json_decode($extraFieldsRaw, true);
    if (is_array($decoded)) {
        foreach ($decoded as $field => $value) {
            if (so_identifier((string)$field)) {
                $extraFields[(string)$field] = trim((string)$value);
            }
        }
    }
}

// Basic security/input validation
if (!so_identifier($table) || !so_identifier($idCol) || !so_identifier($labelCol)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid table configuration.']);
    exit;
}




if ($table === 'racks' && isset($extraFields['status_id'])) {
    $statusName = trim((string)$extraFields['status_id']);
    if ($statusName !== '' && !ctype_digit($statusName)) {
        $statusSql = "SELECT `id` FROM `rack_statuses` WHERE `name`='" . mysqli_real_escape_string($conn, $statusName) . "'";
        if ($company_id > 0) {
            $statusSql .= ' AND (`company_id`=' . (int)$company_id . ' OR `company_id` IS NULL)';
        }
        $statusSql .= ' ORDER BY CASE WHEN `company_id`=' . (int)$company_id . ' THEN 0 ELSE 1 END, `id` ASC LIMIT 1';
        $statusRes = mysqli_query($conn, $statusSql);
        if ($statusRes && ($statusRow = mysqli_fetch_assoc($statusRes))) {
            $extraFields['status_id'] = (string)(int)$statusRow['id'];
        }
    }
}

if ($table === 'cable_colors') {
    $hexFromExtra = trim((string)($extraFields['hex_color'] ?? ''));
    if ($hexFromExtra !== '' && $newValue === '') {
        $newValue = so_hex_to_color_name($hexFromExtra);
    }
    if ($newValue === '' && $hexFromExtra !== '') {
        $newValue = strtoupper($hexFromExtra);
    }
}

if ($newValue === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Please type a value before adding.']);
    exit;
}
// Ensure the requested table and columns actually exist.
$columns = so_table_columns($conn, $table);
if (!$columns || !isset($columns[$idCol]) || !isset($columns[$labelCol])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Unknown target table/columns.']);
    exit;
}

if ($companyScoped && !isset($columns['company_id'])) {
    $companyScoped = false;
}

$companyWhere = '';
if ($companyScoped && $company_id > 0) {
    $companyWhere = ' AND `company_id`=' . (int)$company_id;
}

// Check for duplicates before inserting. 
// If it exists, we just return the existing ID to avoid DB errors and redundant data.
$newValueEsc = mysqli_real_escape_string($conn, $newValue);
$findSql = 'SELECT ' . so_escape_identifier($idCol) . ' AS id FROM ' . so_escape_identifier($table)
    . ' WHERE ' . so_escape_identifier($labelCol) . "='" . $newValueEsc . "'" . $companyWhere . ' LIMIT 1';
$existing = mysqli_query($conn, $findSql);

if ($existing && mysqli_num_rows($existing) > 0) {
    $selectedId = (int)mysqli_fetch_assoc($existing)['id'];
} else {
    // Prepare the insertion query for the new reference record.
    $insertFields = [so_escape_identifier($labelCol)];
    $insertValues = ["'" . $newValueEsc . "'"];

    // Automatically scope the new record to the current company if the table supports it.
    if (isset($columns['company_id']) && $companyScoped && $company_id > 0) {
        $insertFields[] = '`company_id`';
        $insertValues[] = (string)(int)$company_id;
    }

    if (isset($columns['active'])) {
        $insertFields[] = '`active`';
        $insertValues[] = '1';
    }

    /**
     * Dynamic Requirement Check
     * 
     * Since this is a generic API, we must ensure we don't try to insert while 
     * missing fields that the DB requires (NOT NULL, no default).
     * If a required field is missing, we bail so the user can use the full form.
     */
    $missingRequiredFields = [];
    foreach ($columns as $field => $meta) {
        $isAutoIncrement = stripos((string)$meta['Extra'], 'auto_increment') !== false;
        $hasDefault = $meta['Default'] !== null;
        $isNullable = strtoupper((string)$meta['Null']) === 'YES';

        if ($isAutoIncrement || $hasDefault || $isNullable) { continue; }
        if (in_array($field, [$idCol, $labelCol, 'company_id', 'active'], true)) { continue; }

        if (!array_key_exists($field, $extraFields) || $extraFields[$field] === '') {
            $missingRequiredFields[] = $field;
        }
    }

    if (!empty($missingRequiredFields)) {
        http_response_code(422);
        echo json_encode([
            'ok' => false,
            'error' => 'Cannot auto-add for this list because required field "' . $missingRequiredFields[0] . '" needs manual input.',
            'required_fields' => $missingRequiredFields,
        ]);
        exit;
    }

    // Apply extra field data
    foreach ($extraFields as $field => $value) {
        if (!isset($columns[$field]) || $value === '') { continue; }
        if (in_array($field, [$idCol, $labelCol, 'company_id', 'active'], true)) { continue; }
        $insertFields[] = so_escape_identifier($field);
        $insertValues[] = "'" . mysqli_real_escape_string($conn, $value) . "'";
    }

    $insertSql = 'INSERT INTO ' . so_escape_identifier($table)
        . ' (' . implode(', ', $insertFields) . ') VALUES (' . implode(', ', $insertValues) . ')';

    if (!mysqli_query($conn, $insertSql)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Insert failed: ' . mysqli_error($conn)]);
        exit;
    }

    $selectedId = (int)mysqli_insert_id($conn);
}

// Fetch refreshed list of options to return to the UI.
$where = ' WHERE 1=1';
if ($companyScoped && $company_id > 0 && isset($columns['company_id'])) {
    $where .= ' AND `company_id`=' . (int)$company_id;
}
if (isset($columns['active'])) {
    $where .= ' AND `active`=1';
}

$listSql = 'SELECT ' . so_escape_identifier($idCol) . ' AS id, ' . so_escape_identifier($labelCol) . ' AS label'
    . ' FROM ' . so_escape_identifier($table) . $where . ' ORDER BY label';
$listRes = mysqli_query($conn, $listSql);
$options = [];
while ($listRes && ($row = mysqli_fetch_assoc($listRes))) {
    $options[] = [
        'id' => (int)$row['id'],
        'label' => (string)$row['label'],
    ];
}

echo json_encode([
    'ok' => true,
    'selected_id' => $selectedId,
    'options' => $options,
]);
