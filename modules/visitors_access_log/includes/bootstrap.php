<?php
/**
 * Bootstrap for Visitors Access Log Module
 */

$company_id = (int)($_SESSION['company_id'] ?? 0);
$csrf_token = itm_get_csrf_token();

$page = (int)($_GET['page'] ?? 1);
$search = trim((string)($_GET['search'] ?? ''));
$sort = trim((string)($_GET['sort'] ?? 'date_time_in'));
$dir = strtoupper(trim((string)($_GET['dir'] ?? 'DESC'))) === 'ASC' ? 'ASC' : 'DESC';

$perPage = itm_resolve_records_per_page($ui_config ?? null);
$offset = ($page - 1) * $perPage;

$totalRows = val_count_logs($conn, $company_id, $search);
$logs = val_fetch_logs($conn, $company_id, $search, $sort, $dir, $perPage, $offset);

$showBulkActions = ($totalRows >= $perPage);

// Column definitions for UI and Search
$uiColumns = [
    'visitor_name' => 'Name',
    'company_department' => 'Company / Department',
    'reason_for_visit' => 'Reason for Visit',
    'pre_approved_by' => 'Pre-Approved by',
    'room_opened_by' => 'Computer Room Opened By',
    'date_time_in' => 'Date & Time IN',
    'date_time_out' => 'Date & Time OUT'
];

$displayFieldColumns = $uiColumns;
$modulePath = dirname($_SERVER['PHP_SELF']);
