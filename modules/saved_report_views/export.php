<?php
/**
 * Download a saved view as Excel (CSV) or printable HTML (PDF workflow).
 */
require_once __DIR__ . '/../../config/config.php';
require_once ROOT_PATH . 'includes/itm_saved_reports.php';

$companyId = (int) ($_SESSION['company_id'] ?? 0);
$employeeId = (int) ($_SESSION['employee_id'] ?? 0);
$viewId = (int) ($_GET['id'] ?? 0);
$format = strtolower(trim((string) ($_GET['format'] ?? 'xlsx')));

if ($companyId <= 0 || $employeeId <= 0) {
    http_response_code(401);
    echo 'Authentication required.';
    exit;
}
if ($viewId <= 0) {
    http_response_code(400);
    echo 'Missing saved view id.';
    exit;
}
if (!in_array($format, ['xlsx', 'pdf'], true)) {
    http_response_code(400);
    echo 'Invalid format.';
    exit;
}

$pack = itm_saved_reports_get_export_pack($conn, $viewId, $companyId, $employeeId);
if (empty($pack['ok'])) {
    http_response_code(404);
    echo htmlspecialchars((string) ($pack['error'] ?? 'Export failed.'), ENT_QUOTES, 'UTF-8');
    exit;
}

$viewName = (string) (($pack['view']['name'] ?? '') ?: 'saved-view');
$dataset = $pack['dataset'] ?? [];
$total = (int) (($pack['query']['total'] ?? 0));

if ($format === 'xlsx') {
    $body = (string) ($dataset['tabular_csv'] ?? '');
    $filename = itm_saved_reports_safe_export_filename($viewName, 'csv');
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store');
    if ($total > itm_saved_reports_export_row_limit()) {
        echo "\xEF\xBB\xBF# Exported first " . itm_saved_reports_export_row_limit() . " of {$total} rows\r\n";
    }
    echo $body;
    exit;
}

$html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>'
    . htmlspecialchars($viewName, ENT_QUOTES, 'UTF-8')
    . '</title><style>body{font-family:Arial,sans-serif;padding:24px;} table{border-collapse:collapse;width:100%;} th,td{border:1px solid #ccc;padding:6px 8px;font-size:12px;} th{background:#f4f4f4;}</style></head><body>'
    . '<h1>' . htmlspecialchars($viewName, ENT_QUOTES, 'UTF-8') . '</h1>'
    . '<p>Generated: ' . htmlspecialchars(date('d/m/Y H:i'), ENT_QUOTES, 'UTF-8') . '</p>';
if ($total > itm_saved_reports_export_row_limit()) {
    $html .= '<p><strong>Showing first ' . itm_saved_reports_export_row_limit() . ' of ' . $total . ' rows.</strong></p>';
} else {
    $html .= '<p><strong>Total rows:</strong> ' . $total . '</p>';
}
$html .= (string) ($dataset['html_table'] ?? '') . '</body></html>';

$filename = itm_saved_reports_safe_export_filename($viewName, 'html');
header('Content-Type: text/html; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store');
echo $html;
