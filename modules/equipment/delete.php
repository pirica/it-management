<?php
require '../../config/config.php';

if (!function_exists('equipment_table_has_column')) {
    function equipment_table_has_column(mysqli $conn, string $table, string $column): bool
    {
        $tableEsc = mysqli_real_escape_string($conn, $table);
        $columnEsc = mysqli_real_escape_string($conn, $column);
        $res = mysqli_query($conn, "SHOW COLUMNS FROM `{$tableEsc}` LIKE '{$columnEsc}'");
        return $res && mysqli_num_rows($res) > 0;
    }
}

function equipment_delete_idf_data(mysqli $conn, int $companyId, int $equipmentId): void
{
    if ($equipmentId <= 0 || $companyId <= 0) {
        return;
    }

    $hasCompanyColumn = equipment_table_has_column($conn, 'idf_positions', 'company_id');
    $companyFilter = $hasCompanyColumn ? " AND company_id = {$companyId}" : '';
    mysqli_query(
        $conn,
        "DELETE FROM idf_positions WHERE equipment_id = {$equipmentId}{$companyFilter}"
    );
}

function equipment_parse_photo_filenames($rawValue): array
{
    if ($rawValue === null) {
        return [];
    }

    $value = trim((string)$rawValue);
    if ($value === '') {
        return [];
    }

    $decoded = json_decode($value, true);
    if (is_array($decoded)) {
        $items = $decoded;
    } elseif (str_contains($value, ',')) {
        $items = explode(',', $value);
    } else {
        $items = [$value];
    }

    $filenames = [];
    foreach ($items as $item) {
        $filename = basename((string)$item);
        if ($filename !== '') {
            $filenames[$filename] = $filename;
        }
    }

    return array_values($filenames);
}

$debugRequestUri = $_SERVER['REQUEST_URI'] ?? '';
$debugQueryString = $_SERVER['QUERY_STRING'] ?? '';
$debugPost = $_POST;
$debugGet = $_GET;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

itm_require_post_csrf();

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if ($id <= 0) {
    $_SESSION['crud_error'] = 'Invalid equipment ID.'
        . ' REQUEST_URI=' . $debugRequestUri
        . ' | QUERY_STRING=' . $debugQueryString
        . ' | POST=' . json_encode($debugPost)
        . ' | GET=' . json_encode($debugGet);
    header('Location: index.php');
    exit;
}

$usageError = '';

if (!itm_can_delete_record($conn, 'equipment', 'id', $id, $company_id, $usageError)) {
    $_SESSION['crud_error'] = $usageError !== '' ? $usageError : 'This record is in use and cannot be deleted.';
    header('Location: index.php');
    exit;
}

$checkSql = "SELECT photo_filename FROM equipment WHERE id = $id AND company_id = $company_id LIMIT 1";
$checkResult = mysqli_query($conn, $checkSql);

if (!$checkResult) {
    $_SESSION['crud_error'] = 'Unable to check record before delete: ' . mysqli_error($conn);
    header('Location: index.php');
    exit;
}

if (mysqli_num_rows($checkResult) !== 1) {
    $_SESSION['crud_error'] = 'Record not found, or it does not belong to this company.';
    header('Location: index.php');
    exit;
}

$row = mysqli_fetch_assoc($checkResult);

equipment_delete_idf_data($conn, (int)$company_id, $id);

foreach (equipment_parse_photo_filenames($row['photo_filename'] ?? '') as $photoFilename) {
    $path = UPLOAD_PATH . $photoFilename;
    if (is_file($path)) {
        @unlink($path);
    }
}

$deleteSql = "DELETE FROM equipment WHERE id = $id AND company_id = $company_id LIMIT 1";
$deleteResult = mysqli_query($conn, $deleteSql);

if (!$deleteResult) {
    $_SESSION['crud_error'] = 'Delete failed: ' . mysqli_error($conn);
    header('Location: index.php');
    exit;
}

if (mysqli_affected_rows($conn) < 1) {
    $_SESSION['crud_error'] = 'Nothing was deleted.';
    header('Location: index.php');
    exit;
}

//$_SESSION['crud_success'] = 'Record deleted successfully.';
header('Location: index.php');
exit;
