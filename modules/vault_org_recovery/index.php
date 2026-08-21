<?php
/**
 * Vault Org Recovery — admin request inbox (tenant policy + consent + audit).
 */
$crud_action = $crud_action ?? 'index';
require_once '../../config/config.php';
require_once ROOT_PATH . 'includes/itm_vault_org_recovery.php';

$company_id = (int)($_SESSION['company_id'] ?? 0);
$employee_id = (int)($_SESSION['employee_id'] ?? 0);
if (!itm_is_admin($conn, $employee_id)) {
    header('Location: ' . BASE_URL . 'dashboard.php');
    exit;
}

$csrfToken = itm_get_csrf_token();
$moduleSlug = basename(dirname($_SERVER['PHP_SELF']));
$resolvedModuleIcon = itm_resolve_module_sidebar_icon($conn, $company_id, $employee_id, $moduleSlug);
$moduleListHeading = trim($resolvedModuleIcon . ' ' . itm_module_access_strip_catalog_label_prefix('Vault Org Recovery'));
$ui_config = itm_get_ui_configuration($conn, $company_id, $employee_id);
$perPage = itm_resolve_records_per_page($ui_config ?? null);
$companyRow = itm_vault_org_recovery_company_row($conn, $company_id);
$policyEnabled = is_array($companyRow) && itm_vault_org_recovery_company_enabled($companyRow);

require_once ROOT_PATH . 'includes/itm_crud_browser_title.php';
$crud_title = itm_crud_apply_module_icon_to_browser_title($conn, $company_id, $employee_id, $moduleSlug, $moduleListHeading);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['vor_create_request'])) {
    itm_require_post_csrf();
    $targetEmployeeId = (int)($_POST['employee_id'] ?? 0);
    $legalReference = trim((string)($_POST['legal_reference'] ?? ''));
    $requestNotes = trim((string)($_POST['request_notes'] ?? ''));
    $result = itm_vault_org_recovery_create_request($conn, $company_id, $employee_id, $targetEmployeeId, $legalReference, $requestNotes);
    if (!empty($result['ok'])) {
        $_SESSION['crud_success'] = (string)($result['message'] ?? 'Request created.');
    } else {
        $_SESSION['crud_error'] = (string)($result['message'] ?? 'Unable to create request.');
    }
    header('Location: index.php');
    exit;
}

$statusFilter = trim((string)($_GET['status'] ?? ''));
if ($statusFilter !== '' && !in_array($statusFilter, ['pending', 'completed', 'rejected', 'cancelled'], true)) {
    $statusFilter = '';
}
$searchRaw = trim((string)($_GET['search'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;
$listOptions = [
    'status' => $statusFilter,
    'search' => $searchRaw,
    'limit' => $perPage,
    'offset' => $offset,
];
$totalRows = itm_vault_org_recovery_count_requests($conn, $company_id, $listOptions);
$totalPages = max(1, (int)ceil($totalRows / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
    $listOptions['offset'] = $offset;
}
$rows = itm_vault_org_recovery_list_requests($conn, $company_id, $listOptions);

$employeeOptions = [];
$empStmt = mysqli_prepare(
    $conn,
    'SELECT id, first_name, last_name, username, vault_org_recovery_consent_at, vault_key_escrow_encrypted
     FROM employees WHERE company_id = ? AND deleted_at IS NULL ORDER BY first_name, last_name'
);
if ($empStmt) {
    mysqli_stmt_bind_param($empStmt, 'i', $company_id);
    mysqli_stmt_execute($empStmt);
    $empRes = mysqli_stmt_get_result($empStmt);
    while ($empRes && ($empRow = mysqli_fetch_assoc($empRes))) {
        $employeeOptions[] = $empRow;
    }
    mysqli_stmt_close($empStmt);
}

$successMessage = $_SESSION['crud_success'] ?? '';
$errorMessage = $_SESSION['crud_error'] ?? '';
unset($_SESSION['crud_success'], $_SESSION['crud_error']);

function vor_index_query_string(array $overrides = []): string
{
    $params = array_merge([
        'status' => trim((string)($_GET['status'] ?? '')),
        'search' => trim((string)($_GET['search'] ?? '')),
        'page' => max(1, (int)($_GET['page'] ?? 1)),
    ], $overrides);
    $built = http_build_query($params);

    return $built === '' ? '' : ('?' . $built);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= sanitize($crud_title) ?> - <?php echo sanitize($app_name ?? itm_ui_config_app_name($ui_config ?? null)); ?></title>
    <?php echo itm_render_head_favicon_link($favicon_url ?? null); ?>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/styles.css">
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <div class="card">
                <h1 title="Vault org recovery" data-itm-new-button-managed="server"><?php echo sanitize($moduleListHeading); ?></h1>
                <?php if ($successMessage !== ''): ?><div class="alert alert-success"><?php echo sanitize($successMessage); ?></div><?php endif; ?>
                <?php if ($errorMessage !== ''): ?><div class="alert alert-danger"><?php echo sanitize($errorMessage); ?></div><?php endif; ?>

                <?php if (!$policyEnabled): ?>
                    <div class="alert alert-warning">
                        Org recovery is disabled for this company. Enable it under
                        <a class="itm-plain-link" href="<?php echo sanitize(BASE_URL . 'modules/companies/create.php?id=' . $company_id); ?>" target="_blank" rel="noopener noreferrer">Companies → edit</a>
                        (Vault Org Recovery policy card).
                    </div>
                <?php else: ?>
                    <p>Legal/HR-driven admin-assisted vault recovery. Requires employee consent and an escrow snapshot created when the employee last saved their vault key.</p>
                    <div class="card" style="margin-bottom:16px;">
                        <h2 style="margin-top:0;">New recovery request</h2>
                        <form method="POST" action="index.php">
                            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                            <input type="hidden" name="vor_create_request" value="1">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="vor-employee-id">Employee</label>
                                    <select name="employee_id" id="vor-employee-id" required>
                                        <option value="">-- Select --</option>
                                        <?php foreach ($employeeOptions as $emp): ?>
                                            <?php
                                            $hasConsent = itm_vault_org_recovery_employee_has_consent($emp);
                                            $hasEscrow = itm_vault_org_recovery_employee_has_escrow($emp);
                                            $eligible = $hasConsent && $hasEscrow;
                                            ?>
                                            <option value="<?php echo (int)$emp['id']; ?>"<?php echo $eligible ? '' : ' disabled'; ?>>
                                                <?php echo sanitize(itm_vault_org_recovery_employee_label($emp)); ?>
                                                <?php if (!$hasConsent): ?> (no consent)<?php elseif (!$hasEscrow): ?> (no escrow)<?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="vor-legal-reference">Legal / HR reference</label>
                                    <input type="text" name="legal_reference" id="vor-legal-reference" required placeholder="Ticket #, policy clause, case ID…">
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="vor-request-notes">Request notes (optional)</label>
                                <textarea name="request_notes" id="vor-request-notes" rows="2"></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary" title="Create">➕</button>
                        </form>
                    </div>
                <?php endif; ?>

                <div class="card" style="margin-bottom:16px;">
                    <form method="GET" action="index.php" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
                        <div class="form-group" style="margin:0;min-width:160px;">
                            <label for="vor-status-filter">Status</label>
                            <select id="vor-status-filter" name="status" class="form-control">
                                <option value="">All</option>
                                <?php foreach (['pending', 'completed', 'rejected', 'cancelled'] as $st): ?>
                                    <option value="<?php echo sanitize($st); ?>"<?php echo $statusFilter === $st ? ' selected' : ''; ?>><?php echo sanitize(ucfirst($st)); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="margin:0;min-width:260px;flex:1;">
                            <label for="vor-search">Search</label>
                            <input type="text" id="vor-search" name="search" value="<?php echo sanitize($searchRaw); ?>" placeholder="Employee, legal reference, consent reference…">
                        </div>
                        <div class="form-actions" style="margin:0;display:flex;gap:8px;">
                            <button type="submit" class="btn btn-primary">Search</button>
                            <a href="index.php" class="btn" title="Clear">🔙</a>
                        </div>
                    </form>
                </div>

                <table data-itm-no-import-excel="1" data-itm-no-export-excel="1" data-itm-no-export-pdf="1">
                    <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Legal reference</th>
                        <th>Consent reference</th>
                        <th>Status</th>
                        <th>Requested</th>
                        <th class="itm-actions-cell" data-itm-actions-origin="1">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($rows === []): ?>
                        <tr><td colspan="6">No recovery requests found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td><?php echo sanitize(itm_vault_org_recovery_employee_label($row)); ?></td>
                                <td><?php echo sanitize((string)($row['legal_reference'] ?? '')); ?></td>
                                <td><?php echo sanitize((string)($row['consent_reference'] ?? '')); ?></td>
                                <td><span class="badge badge-<?php echo ($row['status'] ?? '') === 'completed' ? 'success' : (($row['status'] ?? '') === 'pending' ? 'warning' : 'danger'); ?>"><?php echo sanitize(ucfirst((string)($row['status'] ?? ''))); ?></span></td>
                                <td><?php echo sanitize(itm_format_audit_timestamp_display($row['created_at'] ?? '')); ?></td>
                                <td class="itm-actions-cell" data-itm-actions-origin="1">
                                    <div class="itm-actions-wrap">
                                        <a class="btn btn-sm" href="view.php?id=<?php echo (int)$row['id']; ?>" title="View">🔎</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>

                <?php if ($totalPages > 1): ?>
                    <div style="margin-top:12px;display:flex;gap:8px;align-items:center;">
                        <?php if ($page > 1): ?>
                            <a class="btn btn-sm" href="<?php echo sanitize('index.php' . vor_index_query_string(['page' => 1])); ?>" title="First page">⏮️</a>
                            <a class="btn btn-sm" href="<?php echo sanitize('index.php' . vor_index_query_string(['page' => $page - 1])); ?>" title="Previous page">◀️</a>
                        <?php endif; ?>
                        <span class="btn btn-sm" style="pointer-events:none;opacity:.8;">Page <?php echo (int)$page; ?> of <?php echo (int)$totalPages; ?></span>
                        <?php if ($page < $totalPages): ?>
                            <a class="btn btn-sm" href="<?php echo sanitize('index.php' . vor_index_query_string(['page' => $page + 1])); ?>" title="Next page">▶️</a>
                            <a class="btn btn-sm" href="<?php echo sanitize('index.php' . vor_index_query_string(['page' => $totalPages])); ?>" title="Last page">⏭️</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</body>
</html>
