<?php
require '../../config/config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

function so_require_valid_csrf_token() {
    $token = (string)($_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    if (!itm_validate_csrf_token($token)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Forbidden: invalid CSRF token.']);
        exit;
    }
}

so_require_valid_csrf_token();

function so_identifier($value) {
    return is_string($value) && preg_match('/^[a-zA-Z0-9_]+$/', $value);
}

function so_escape_identifier($name) {
    return '`' . str_replace('`', '``', $name) . '`';
}

function so_table_columns($conn, $table) {
    $columns = [];
    $res = mysqli_query($conn, 'DESCRIBE ' . so_escape_identifier($table));
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $columns[$row['Field']] = $row;
    }
    return $columns;
}

$table = $_POST['table'] ?? '';
$idCol = $_POST['id_col'] ?? 'id';
$labelCol = $_POST['label_col'] ?? 'name';
$newValue = trim((string)($_POST['new_value'] ?? ''));
$companyScoped = (int)($_POST['company_scoped'] ?? 0) === 1;

if (!so_identifier($table) || !so_identifier($idCol) || !so_identifier($labelCol)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid table configuration.']);
    exit;
}

if ($newValue === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Please type a value before adding.']);
    exit;
}

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

$newValueEsc = mysqli_real_escape_string($conn, $newValue);
$findSql = 'SELECT ' . so_escape_identifier($idCol) . ' AS id FROM ' . so_escape_identifier($table)
    . ' WHERE ' . so_escape_identifier($labelCol) . "='" . $newValueEsc . "'" . $companyWhere . ' LIMIT 1';
$existing = mysqli_query($conn, $findSql);
if ($existing && mysqli_num_rows($existing) > 0) {
    $selectedId = (int)mysqli_fetch_assoc($existing)['id'];
} else {
    $insertFields = [so_escape_identifier($labelCol)];
    $insertValues = ["'" . $newValueEsc . "'"];

    if (isset($columns['company_id']) && $companyScoped && $company_id > 0) {
        $insertFields[] = '`company_id`';
        $insertValues[] = (string)(int)$company_id;
    }

    if (isset($columns['active'])) {
        $insertFields[] = '`active`';
        $insertValues[] = '1';
    }

    foreach ($columns as $field => $meta) {
        $isAutoIncrement = stripos((string)$meta['Extra'], 'auto_increment') !== false;
        $hasDefault = $meta['Default'] !== null;
        $isNullable = strtoupper((string)$meta['Null']) === 'YES';

        if ($isAutoIncrement || $hasDefault || $isNullable) {
            continue;
        }

        if (in_array($field, [$idCol, $labelCol, 'company_id', 'active'], true)) {
            continue;
        }

        http_response_code(422);
        echo json_encode([
            'ok' => false,
            'error' => 'Cannot auto-add for this list because required field "' . $field . '" needs manual input.'
        ]);
        exit;
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
