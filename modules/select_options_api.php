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
    $sessionToken = (string)($_SESSION['csrf_token'] ?? '');
    if ($token === '' || $sessionToken === '' || !hash_equals($sessionToken, $token)) {
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
    $tableEsc = mysqli_real_escape_string($conn, $table);
    $res = mysqli_query($conn, 'DESCRIBE ' . so_escape_identifier($tableEsc));
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
$params = [$newValue];
$types = 's';

if ($companyScoped && $company_id > 0) {
    $companyWhere = ' AND `company_id` = ?';
    $params[] = (int)$company_id;
    $types .= 'i';
}

$findSql = 'SELECT ' . so_escape_identifier($idCol) . ' AS id FROM ' . so_escape_identifier($table)
    . ' WHERE ' . so_escape_identifier($labelCol) . " = ?" . $companyWhere . ' LIMIT 1';

$stmt = mysqli_prepare($conn, $findSql);
$selectedId = 0;
if ($stmt) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($res && mysqli_num_rows($res) > 0) {
        $selectedId = (int)mysqli_fetch_assoc($res)['id'];
    }
    mysqli_stmt_close($stmt);
}

if ($selectedId === 0) {
    $insertFields = [so_escape_identifier($labelCol)];
    $insertPlaceholders = ["?"];
    $insertParams = [$newValue];
    $insertTypes = "s";

    if (isset($columns['company_id']) && $companyScoped && $company_id > 0) {
        $insertFields[] = '`company_id`';
        $insertPlaceholders[] = '?';
        $insertParams[] = (int)$company_id;
        $insertTypes .= 'i';
    }

    if (isset($columns['active'])) {
        $insertFields[] = '`active`';
        $insertPlaceholders[] = '1';
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
        . ' (' . implode(', ', $insertFields) . ') VALUES (' . implode(', ', $insertPlaceholders) . ')';

    $stmt = mysqli_prepare($conn, $insertSql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, $insertTypes, ...$insertParams);
        if (!mysqli_stmt_execute($stmt)) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'Insert failed: ' . mysqli_stmt_error($stmt)]);
            exit;
        }
        $selectedId = (int)mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);
    } else {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Prepare insert failed: ' . mysqli_error($conn)]);
        exit;
    }
}

$where = ' WHERE 1=1';
$listParams = [];
$listTypes = '';
if ($companyScoped && $company_id > 0 && isset($columns['company_id'])) {
    $where .= ' AND `company_id` = ?';
    $listParams[] = (int)$company_id;
    $listTypes .= 'i';
}
if (isset($columns['active'])) {
    $where .= ' AND `active` = 1';
}

$listSql = 'SELECT ' . so_escape_identifier($idCol) . ' AS id, ' . so_escape_identifier($labelCol) . ' AS label'
    . ' FROM ' . so_escape_identifier($table) . $where . ' ORDER BY label';

$stmt = mysqli_prepare($conn, $listSql);
$options = [];
if ($stmt) {
    if (!empty($listParams)) {
        mysqli_stmt_bind_param($stmt, $listTypes, ...$listParams);
    }
    mysqli_stmt_execute($stmt);
    $listRes = mysqli_stmt_get_result($stmt);
    while ($listRes && ($row = mysqli_fetch_assoc($listRes))) {
        $options[] = [
            'id' => (int)$row['id'],
            'label' => (string)$row['label'],
        ];
    }
    mysqli_stmt_close($stmt);
}

echo json_encode([
    'ok' => true,
    'selected_id' => $selectedId,
    'options' => $options,
]);
