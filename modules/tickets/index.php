<?php
/**
 * Tickets Module - Index
 * 
 * Provides a central list of support tickets.
 * Features:
 * - Filterable and sortable ticket grid
 * - Priority and Status color coding via lookup tables
 * - Direct links to ticket details and editing
 */
$crud_table = 'tickets';
$crud_title = 'Tickets';
$crud_action = $crud_action ?? 'index';

require '../../config/config.php';
require_once ROOT_PATH . 'includes/itm_role_module_permissions.php';
itm_require_role_module_permission(
    $conn,
    (int)($_SESSION['employee_id'] ?? 0),
    (int)($_SESSION['company_id'] ?? 0),
    'Tickets',
    'view'
);
require_once ROOT_PATH . 'includes/itm_crud_record_share.php';
itm_crud_record_share_handle_ajax_request($conn, 'tickets');

require_once ROOT_PATH . 'includes/itm_employee_employment_status.php';
require __DIR__ . '/sample_seed_helpers.php';

// Why: tickets.is_archived is defined in db/ CREATE TABLE — no runtime ALTER.

// Handle Excel/CSV database import requests from table-tools.js.
if ((string)($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $itmImportRawBody = file_get_contents('php://input');
    $itmImportJsonBody = json_decode((string)$itmImportRawBody, true);
    if (is_array($itmImportJsonBody) && isset($itmImportJsonBody['import_excel_rows'])) {
        $itmImportJsonBody['import_excel_rows'] = tickets_prepare_import_excel_rows(
            $conn,
            (int)($company_id ?? 0),
            $itmImportJsonBody['import_excel_rows']
        );
        itm_handle_json_table_import($conn, 'tickets', (int)($company_id ?? 0), $itmImportJsonBody);
    }
}


/**
 * Resolve a tenant-scoped lookup row id from a human-readable label (Excel import).
 */
function tickets_resolve_fk_id_by_name(mysqli $conn, string $table, int $companyId, string $name): string
{
    if (!itm_is_safe_identifier($table) || $companyId <= 0 || trim($name) === '') {
        return '';
    }

    $tableEsc = '`' . str_replace('`', '``', $table) . '`';
    $stmt = mysqli_prepare($conn, 'SELECT id FROM ' . $tableEsc . ' WHERE company_id = ? AND name = ? LIMIT 1');
    if (!$stmt) {
        return '';
    }

    $lookupName = trim($name);
    mysqli_stmt_bind_param($stmt, 'is', $companyId, $lookupName);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);

    return $row ? (string)(int)($row['id'] ?? 0) : '';
}

/**
 * Default created_by_employee_id for imports when Excel omits audit user columns.
 */
function tickets_import_default_user_id(mysqli $conn, int $companyId): int
{
    $sessionUserId = isset($_SESSION['employee_id']) ? (int)$_SESSION['employee_id'] : 0;
    if ($sessionUserId > 0) {
        return $sessionUserId;
    }

    if ($companyId > 0) {
        $stmt = mysqli_prepare(
            $conn,
            'SELECT u.id FROM employees u
             INNER JOIN employee_companies uc ON uc.employee_id = u.id AND uc.company_id = ?
             WHERE u.active = 1
             ORDER BY u.id ASC
             LIMIT 1'
        );
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $companyId);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $row = $result ? mysqli_fetch_assoc($result) : null;
            mysqli_stmt_close($stmt);
            if ($row) {
                return (int)($row['id'] ?? 0);
            }
        }
    }

    $join = itm_employee_active_employment_status_join_sql('e', 'es');
    $predicate = itm_employee_active_employment_status_predicate_sql('es');
    $fallback = mysqli_query(
        $conn,
        'SELECT e.id FROM employees e' . $join . ' WHERE ' . $predicate . ' ORDER BY e.id ASC LIMIT 1'
    );
    $fallbackRow = $fallback ? mysqli_fetch_assoc($fallback) : null;

    return $fallbackRow ? (int)($fallbackRow['id'] ?? 0) : 0;
}

/**
 * Map list export labels (Status/Priority names) to FK ids and inject required ticket columns.
 *
 * @param array<int, array<int, string>> $importRows
 * @return array<int, array<int, string>>
 */
function tickets_prepare_import_excel_rows(mysqli $conn, int $companyId, array $importRows): array
{
    if (count($importRows) < 2 || $companyId <= 0) {
        return $importRows;
    }

    $importHeader = static function (string $field): string {
        return ucwords(str_replace('_', ' ', $field));
    };

    $headers = $importRows[0];
    $headerNorms = [];
    foreach ($headers as $i => $header) {
        $norm = strtolower(trim(preg_replace('/\s+/', ' ', (string)$header)));
        $headerNorms[$i] = $norm;

        if ($norm === 'status' || $norm === 'status id' || $norm === 'status name') {
            $headers[$i] = $importHeader('status_id');
        } elseif ($norm === 'priority' || $norm === 'priority id' || $norm === 'priority name') {
            $headers[$i] = $importHeader('priority_id');
        } elseif ($norm === 'external code' || $norm === 'ticket external code') {
            $headers[$i] = $importHeader('ticket_external_code');
        }
    }

    $present = [];
    foreach ($headers as $header) {
        $norm = strtolower(trim(preg_replace('/\s+/', ' ', (string)$header)));
        $present[$norm] = true;
    }

    $appendValues = [];
    if (empty($present['created by user id']) && empty($present['created by'])) {
        $employeeId = tickets_import_default_user_id($conn, $companyId);
        if ($employeeId > 0) {
            $headers[] = $importHeader('created_by_employee_id');
            $appendValues[] = (string)$employeeId;
        }
    }

    if (empty($present['category id']) && empty($present['category'])) {
        $categoryStmt = mysqli_prepare($conn, 'SELECT id FROM ticket_categories WHERE company_id = ? ORDER BY id ASC LIMIT 1');
        if ($categoryStmt) {
            mysqli_stmt_bind_param($categoryStmt, 'i', $companyId);
            mysqli_stmt_execute($categoryStmt);
            $categoryResult = mysqli_stmt_get_result($categoryStmt);
            $categoryRow = $categoryResult ? mysqli_fetch_assoc($categoryResult) : null;
            mysqli_stmt_close($categoryStmt);
            if ($categoryRow) {
                $headers[] = $importHeader('category_id');
                $appendValues[] = (string)(int)($categoryRow['id'] ?? 0);
            }
        }
    }

    $prepared = [$headers];
    for ($rowIndex = 1, $rowCount = count($importRows); $rowIndex < $rowCount; $rowIndex++) {
        if (!is_array($importRows[$rowIndex])) {
            continue;
        }

        $values = array_values($importRows[$rowIndex]);
        foreach ($headerNorms as $i => $norm) {
            $raw = trim((string)($values[$i] ?? ''));
            if ($raw === '' || strcasecmp($raw, 'null') === 0) {
                continue;
            }

            if (($norm === 'status' || $norm === 'status id' || $norm === 'status name') && !ctype_digit($raw)) {
                $resolved = tickets_resolve_fk_id_by_name($conn, 'ticket_statuses', $companyId, $raw);
                if ($resolved !== '') {
                    $values[$i] = $resolved;
                }
            } elseif (($norm === 'priority' || $norm === 'priority id' || $norm === 'priority name') && !ctype_digit($raw)) {
                $resolved = tickets_resolve_fk_id_by_name($conn, 'ticket_priorities', $companyId, $raw);
                if ($resolved !== '') {
                    $values[$i] = $resolved;
                }
            }
        }

        foreach ($appendValues as $appendValue) {
            $values[] = $appendValue;
        }

        $prepared[] = $values;
    }

    return $prepared;
}

/**
 * Renders a lookup label badge tinted by ticket_statuses/ticket_priorities hex color.
 */
function ticket_render_lookup_badge(string $label, string $color, string $fallbackLabel = '-'): string
{
    $name = trim($label);
    if ($name === '') {
        $name = $fallbackLabel;
    }

    $hex = trim($color);
    if ($hex === '' || !preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $hex)) {
        $hex = '#9aa4b2';
    }

    return '<span class="badge" style="background-color:' . sanitize($hex) . '33;color:' . sanitize($hex) . ';">' . sanitize($name) . '</span>';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_sample_data'])) {
    itm_require_post_csrf();

    if ($company_id <= 0) {
        $_SESSION['crud_error'] = 'Sample data requires an active company.';
        header('Location: index.php');
        exit;
    }

    tickets_repair_invisible_sample_rows($conn, (int)$company_id);
    tickets_ensure_sample_rows_active($conn, (int)$company_id);

    $companyTotalRows = tickets_tenant_active_row_count($conn, (int)$company_id);

    if ($companyTotalRows > 0) {
        $_SESSION['crud_error'] = 'Sample data can only be added when no records exist.';
        header('Location: index.php');
        exit;
    }

    $seedError = '';
    $insertedRows = itm_seed_table_from_database_sql($conn, 'tickets', (int)$company_id, $seedError);
    if ($insertedRows > 0) {
        tickets_repair_sample_equipment_links($conn, (int)$company_id);
        tickets_ensure_sample_rows_active($conn, (int)$company_id);
        tickets_repair_invisible_sample_rows($conn, (int)$company_id);
    }
    if ($insertedRows <= 0) {
        $_SESSION['crud_error'] = $seedError !== ''
            ? $seedError
            : 'Sample data could not be inserted for this company.';
    } else {
        $_SESSION['crud_success'] = 'Sample data added successfully.';
    }

    header('Location: index.php');
    exit;
}

// Extraction of search and sorting parameters
require_once ROOT_PATH . 'includes/itm_tickets_list_query.php';
// Why: list SQL applies deleted_at IS NULL in itm_tickets_list_build_sql_base().
$ticketListFilters = itm_tickets_list_parse_filters($_GET);
$searchRaw = (string) ($ticketListFilters['search'] ?? '');
$showArchived = (int) ($ticketListFilters['show_archived'] ?? 0) === 1;
$sort = (string)($_GET['sort'] ?? 'id');
$dir = strtoupper((string)($_GET['dir'] ?? 'DESC'));
$sort = (string) ($ticketListFilters['sort'] ?? $sort);
$dir = (string) ($ticketListFilters['dir'] ?? $dir);
// Why: itm_tickets_list_fetch() applies ORDER BY $sort / $dir via itm_tickets_list_resolve_sort_sql().

if ($company_id > 0) {
    tickets_repair_invisible_sample_rows($conn, (int)$company_id);
}

$uiColumns = itm_tickets_list_ui_columns();
// Why: Search and list share visible columns; alias matches role/ui_configuration modules.
$displayFieldColumns = $uiColumns;

$perPage = itm_resolve_records_per_page($ui_config ?? null);
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$totalRows = itm_tickets_list_count($conn, (int) $company_id, $ticketListFilters);
$companyTotalRows = tickets_tenant_active_row_count($conn, (int)$company_id);
$totalPages = max(1, (int)ceil($totalRows / max(1, $perPage)));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

$ticketRows = itm_tickets_list_fetch($conn, (int) $company_id, $ticketListFilters, $perPage, $offset, true);
$items = $ticketRows;

$ticketFilterOptions = itm_tickets_list_load_filter_options($conn, (int) $company_id);

$showBulkActions = $totalRows >= $perPage;
$newButtonPosition = itm_resolve_new_button_position($ui_config);
$moduleListHeading = itm_sidebar_label_for_module(basename(dirname($_SERVER['PHP_SELF']))) ?: $crud_title;

require_once ROOT_PATH . 'includes/itm_saved_reports.php';
$itmSavedReportsModuleSlug = 'tickets';
$itmSavedReportsFilters = $ticketListFilters;
$itmSavedReportsColumns = $uiColumns;
$ticketListQueryString = itm_saved_reports_filters_query_string($ticketListFilters);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
if (!isset($currentUiConfig)) {
    $currentUiConfig = $ui_config ?? [];
}
if (!isset($crud_title)) {
    $crud_title = 'Tickets';
}
    require_once ROOT_PATH . 'includes/itm_crud_browser_title.php';
        $crud_title = itm_crud_apply_module_icon_to_browser_title($conn, (int)($company_id ?? 0), (int)($_SESSION['employee_id'] ?? 0), basename(dirname($_SERVER['PHP_SELF'])), (string)($crud_title ?? ''));
    ?>
<title><?= sanitize($crud_title) ?> - <?php echo sanitize($app_name ?? itm_ui_config_app_name($currentUiConfig)); ?></title>
    <?php echo itm_render_head_favicon_link($favicon_url ?? null); ?>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <?php include ROOT_PATH . 'includes/itm_saved_reports_list_banner.php'; ?>
            <!-- HEADER SECTION -->
            <div data-itm-new-button-managed="server" style="position:relative;display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;min-height:40px;">
                <?php if (in_array($newButtonPosition, ['left', 'left_right'], true)): ?><a href="create.php" class="btn btn-primary itm-list-new-button" title="Create">➕</a><?php else: ?><span></span><?php endif; ?>
                <h1 style="position:absolute;left:50%;transform:translateX(-50%);margin:0;text-align:center;"><?php echo sanitize($moduleListHeading); ?></h1>
                <?php if (in_array($newButtonPosition, ['right', 'left_right'], true)): ?><a href="create.php" class="btn btn-primary itm-list-new-button" title="Create">➕</a><?php else: ?><span></span><?php endif; ?>
            </div>

            <!-- SEARCH BAR -->
            <div class="card" style="margin-bottom:16px;">
                <form method="GET" data-itm-saved-reports-list-form="1" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
                    <div class="form-group" style="margin:0;min-width:200px;flex:1;">
                        <label for="ticketSearch">Search (all fields)</label>
                        <input type="text" id="ticketSearch" name="search" value="<?php echo sanitize($searchRaw); ?>" placeholder="Type to search..." data-itm-saved-report-filter="1">
                    </div>
                    <div class="form-group" style="margin:0;min-width:140px;">
                        <label for="ticketStatusFilter">Status</label>
                        <select id="ticketStatusFilter" name="status_id" data-itm-saved-report-filter="1">
                            <option value="">All</option>
                            <?php foreach ($ticketFilterOptions['statuses'] as $opt): ?>
                                <option value="<?php echo (int) $opt['id']; ?>"<?php echo (int)($ticketListFilters['status_id'] ?? 0) === (int)$opt['id'] ? ' selected' : ''; ?>><?php echo sanitize((string) $opt['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="margin:0;min-width:140px;">
                        <label for="ticketPriorityFilter">Priority</label>
                        <select id="ticketPriorityFilter" name="priority_id" data-itm-saved-report-filter="1">
                            <option value="">All</option>
                            <?php foreach ($ticketFilterOptions['priorities'] as $opt): ?>
                                <option value="<?php echo (int) $opt['id']; ?>"<?php echo (int)($ticketListFilters['priority_id'] ?? 0) === (int)$opt['id'] ? ' selected' : ''; ?>><?php echo sanitize((string) $opt['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="margin:0;min-width:160px;">
                        <label for="ticketAssigneeFilter">Assignee</label>
                        <select id="ticketAssigneeFilter" name="assigned_to_employee_id" data-itm-saved-report-filter="1">
                            <option value="">All</option>
                            <?php foreach ($ticketFilterOptions['assignees'] as $opt): ?>
                                <option value="<?php echo (int) $opt['id']; ?>"<?php echo (int)($ticketListFilters['assigned_to_employee_id'] ?? 0) === (int)$opt['id'] ? ' selected' : ''; ?>><?php echo sanitize((string) ($opt['label'] ?? '')); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="margin:0;min-width:130px;">
                        <label for="ticketDueFrom">Due from</label>
                        <input type="date" id="ticketDueFrom" name="due_date_from" value="<?php echo sanitize((string)($ticketListFilters['due_date_from'] ?? '')); ?>" data-itm-saved-report-filter="1">
                    </div>
                    <div class="form-group" style="margin:0;min-width:130px;">
                        <label for="ticketDueTo">Due to</label>
                        <input type="date" id="ticketDueTo" name="due_date_to" value="<?php echo sanitize((string)($ticketListFilters['due_date_to'] ?? '')); ?>" data-itm-saved-report-filter="1">
                    </div>
                    <div class="form-group" style="margin:0;min-width:140px;">
                        <label for="ticketSurveyStatusFilter">Survey</label>
                        <select id="ticketSurveyStatusFilter" name="survey_status" data-itm-saved-report-filter="1">
                            <option value="">All</option>
                            <?php foreach (['none' => 'None', 'pending' => 'Pending', 'completed' => 'Completed'] as $surveyVal => $surveyLabel): ?>
                                <option value="<?php echo sanitize($surveyVal); ?>"<?php echo (string)($ticketListFilters['survey_status'] ?? '') === $surveyVal ? ' selected' : ''; ?>><?php echo sanitize($surveyLabel); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="margin:0;min-width:120px;">
                        <label for="ticketCsatMinFilter">Min CSAT</label>
                        <select id="ticketCsatMinFilter" name="csat_min" data-itm-saved-report-filter="1">
                            <option value="">All</option>
                            <?php for ($csatOpt = 1; $csatOpt <= 5; $csatOpt++): ?>
                                <option value="<?php echo $csatOpt; ?>"<?php echo (int)($ticketListFilters['csat_min'] ?? 0) === $csatOpt ? ' selected' : ''; ?>><?php echo $csatOpt; ?>+</option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <input type="hidden" name="sort" value="<?php echo sanitize($sort); ?>" data-itm-saved-report-filter="1">
                    <input type="hidden" name="dir" value="<?php echo sanitize($dir); ?>" data-itm-saved-report-filter="1">
                    <?php if ($showArchived): ?><input type="hidden" name="show_archived" value="1" data-itm-saved-report-filter="1"><?php endif; ?>
                    <div class="form-actions" style="margin:0;display:flex;gap:8px;flex-wrap:wrap;">
                        <button type="submit" class="btn btn-primary">Search</button>
                        <a href="index.php" class="btn" title="Reset Filters">🔙</a>
                        <?php if ($showArchived): ?>
                            <a href="index.php" class="btn btn-success" title="Switch to Active Tickets View">✅ Show Active</a>
                        <?php else: ?>
                            <a href="index.php?show_archived=1" class="btn" title="Switch to Archived Tickets View">🔓 Show Archived</a>
                        <?php endif; ?>
                        <?php include ROOT_PATH . 'includes/itm_saved_reports_ui.php'; ?>
                    </div>
                </form>
            </div>


            <?php if ($showBulkActions): ?>
                <!-- Guard bulk actions behind per-page threshold to align with global UX settings. -->
                <div class="card" style="margin-bottom:16px;">
                    <form id="bulk-delete-form" method="POST" action="delete.php" style="display:flex;gap:8px;" data-itm-bulk-delete-bound="1">
                        <input type="hidden" name="csrf_token" value="<?php echo sanitize(itm_get_csrf_token()); ?>">
                        <?php itm_crud_render_delete_hidden_audit_inputs(); ?>
                        <button type="submit" name="bulk_action" value="bulk_delete" class="btn btn-sm btn-danger" id="bulk-delete-toggle">Select to Delete</button>
                        <button type="button" class="btn btn-sm" data-itm-bulk-cancel="1">Cancel</button>
                        <button type="submit" name="bulk_action" value="clear_table" class="btn btn-sm btn-danger" onclick="return confirm('Clear all tickets? This cannot be undone.');">Clear Table</button>
                    </form>
                </div>
            <?php endif; ?>

            <!-- DATA TABLE -->
            <div class="card">
                <table data-itm-db-import-endpoint="index.php">
                    <thead>
                    <tr>
                        <?php if ($showBulkActions): ?><th>Select</th><?php endif; ?>
                        <?php foreach (['id' => 'ID', 'ticket_external_code' => 'External Code', 'title' => 'Title', 'status_name' => 'Status', 'priority_name' => 'Priority', 'sla_status' => 'SLA', 'master_ticket_id' => 'Master Ticket', 'due_date' => 'Due Date', 'survey_summary' => 'Survey'] as $field => $label): ?>
                            <?php $nextDir = ($sort === $field && $dir === 'ASC') ? 'DESC' : 'ASC'; ?>
                            <th><a href="?<?php echo sanitize(itm_saved_reports_filters_query_string($ticketListFilters, ['sort' => $field, 'dir' => $nextDir])); ?>" style="text-decoration:none;color:inherit;"><?php echo sanitize($label); ?><?php if ($sort === $field): ?> <?php echo $dir === 'ASC' ? '▲' : '▼'; ?><?php endif; ?></a></th>
                        <?php endforeach; ?>
                        <th class="itm-actions-cell" data-itm-actions-origin="1">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($items)): foreach ($items as $t): ?>
                        <tr>
                            <?php if ($showBulkActions): ?><td><input type="checkbox" name="ids[]" value="<?php echo (int)$t['id']; ?>" form="bulk-delete-form"></td><?php endif; ?>
                            <td><?php echo (int)$t['id']; ?></td>
                            <td><?php echo sanitize($t['ticket_external_code'] ?? '—'); ?></td>
                            <td><?php echo sanitize($t['title']); ?></td>
                            <td><?php echo ticket_render_lookup_badge((string)($t['status_name'] ?? ''), (string)($t['status_color'] ?? ''), 'Open'); ?></td>
                            <td><?php echo ticket_render_lookup_badge((string)($t['priority_name'] ?? ''), (string)($t['priority_color'] ?? '')); ?></td>
                            <td><?php echo itm_ticket_sla_render_badge($t); ?></td>
                            <td>
                                <?php $masterTicketId = (int)($t['master_ticket_id'] ?? 0); ?>
                                <?php if ($masterTicketId > 0): ?>
                                    <a class="itm-plain-link" href="../master_tickets/view.php?id=<?php echo $masterTicketId; ?>" title="View master ticket">#<?php echo $masterTicketId; ?></a>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td><?php echo sanitize(itm_format_date_display($t['due_date'] ?? '') ?: '—'); ?></td>
                            <td><?php echo sanitize($t['survey_summary'] ?? '—'); ?></td>
                            <td class="itm-actions-cell" data-itm-actions-origin="1">
                                <div class="itm-actions-wrap">
                                    <a class="btn btn-sm" href="view.php?id=<?php echo (int)$t['id']; ?>">🔎</a>
                                    <a class="btn btn-sm" href="edit.php?id=<?php echo (int)$t['id']; ?>">✏️</a>
                                    <?php if ((int)($t['is_archived'] ?? 0) === 1): ?>
                                        <form method="POST" action="archive.php" style="display:inline;">
                                            <input type="hidden" name="csrf_token" value="<?php echo sanitize(itm_get_csrf_token()); ?>">
                                            <input type="hidden" name="id" value="<?php echo (int)$t['id']; ?>">
                                            <input type="hidden" name="archive_action" value="unarchive">
                                            <input type="hidden" name="redirect_archived" value="<?php echo $showArchived ? '1' : '0'; ?>">
                                            <button type="submit" class="btn btn-sm" title="Un-archive">🔓</button>
                                        </form>
                                    <?php else: ?>
                                        <?php
                                        $isClosed = (int)($t['status_is_closed'] ?? 0) === 1 || strcasecmp((string)($t['status_name'] ?? ''), 'Closed') === 0;
                                        if ($isClosed): ?>
                                            <form method="POST" action="archive.php" style="display:inline;">
                                                <input type="hidden" name="csrf_token" value="<?php echo sanitize(itm_get_csrf_token()); ?>">
                                                <input type="hidden" name="id" value="<?php echo (int)$t['id']; ?>">
                                                <input type="hidden" name="archive_action" value="archive">
                                                <button type="submit" class="btn btn-sm" title="Archive">🔐</button>
                                            </form>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <form method="POST" action="delete.php" style="display:inline;" onsubmit="return confirm('Delete ticket?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo sanitize(itm_get_csrf_token()); ?>">
                                        <?php itm_crud_render_delete_hidden_audit_inputs(); ?>
                                        <input type="hidden" name="id" value="<?php echo (int)$t['id']; ?>">
                                        <input type="hidden" name="bulk_action" value="single_delete">
                                        <button type="submit" class="btn btn-sm btn-danger">🗑️</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="<?php echo $showBulkActions ? 11 : 10; ?>" style="text-align:center;">No records found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
                <?php if ($totalPages > 1): ?>
                    <div style="display:flex;justify-content:center;gap:8px;margin-top:14px;flex-wrap:wrap;">
                        <?php if ($page > 1): ?>
                            <a class="btn btn-sm" href="?<?php echo sanitize(itm_saved_reports_filters_query_string($ticketListFilters, ['sort' => $sort, 'dir' => $dir, 'page' => 1])); ?>" title="First page">⏮️</a>
                            <a class="btn btn-sm" href="?<?php echo sanitize(itm_saved_reports_filters_query_string($ticketListFilters, ['sort' => $sort, 'dir' => $dir, 'page' => (int)$page - 1])); ?>" title="Previous page">◀️</a>
                        <?php endif; ?>
                        <span class="btn btn-sm" style="pointer-events:none;opacity:.85;">Page <?php echo (int)$page; ?> of <?php echo (int)$totalPages; ?></span>
                        <?php if ($page < $totalPages): ?>
                            <a class="btn btn-sm" href="?<?php echo sanitize(itm_saved_reports_filters_query_string($ticketListFilters, ['sort' => $sort, 'dir' => $dir, 'page' => (int)$page + 1])); ?>" title="Next page">▶️</a>
                            <a class="btn btn-sm" href="?<?php echo sanitize(itm_saved_reports_filters_query_string($ticketListFilters, ['sort' => $sort, 'dir' => $dir, 'page' => $totalPages])); ?>" title="Last page">⏭️</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php if ($company_id > 0 && $companyTotalRows === 0): ?>
                <div style="margin-top:16px;text-align:center;">
                    <form method="POST" action="index.php">
                        <input type="hidden" name="csrf_token" value="<?php echo sanitize(itm_get_csrf_token()); ?>">
                        <button type="submit" name="add_sample_data" value="1" class="btn btn-primary">Add sample data</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="../../js/theme.js"></script>
<script src="../../js/bulk-delete-selection.js"></script>
<?php itm_crud_record_share_include_modal(); ?>
</body>
</html>
<?php
if (isset($dataStmt)) { mysqli_stmt_close($dataStmt); }
?>
