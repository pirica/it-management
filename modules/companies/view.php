<?php
/**
 * Companies Module - View
 * 
 * Displays the full profile of a single company, including contact details,
 * VAT information, and audit meta (created/updated/deleted by/at).
 */

require '../../config/config.php';
if (!itm_is_admin($conn, $_SESSION['employee_id'] ?? 0)) {
    header('Location: ' . BASE_URL . 'dashboard.php');
    exit;
}
itm_ensure_companies_company_unique($conn);

$id = (int)($_GET['id'] ?? 0);
$item = null;
$itemNormalized = null;
$error = '';

/**
 * Robust value retrieval with support for case-insensitive keys and aliases
 *
 * @param array|null $row The data row
 * @param string[] $keys List of keys to check
 * @param string $default Fallback value
 * @return string The found value or default
 */
function itm_company_view_value(?array $row, array $keys, string $default = ''): string
{
    if ($row === null) {
        return $default;
    }

    foreach ($keys as $key) {
        $lookup = strtolower($key);
        if (array_key_exists($lookup, $row) && $row[$lookup] !== null) {
            return (string)$row[$lookup];
        }
    }

    return $default;
}

// Fetch the company record by ID
if ($id > 0) {
    $stmt = mysqli_prepare($conn, 'SELECT * FROM companies WHERE id = ? AND id > 0 LIMIT 1');
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($result && mysqli_num_rows($result) === 1) {
            $item = mysqli_fetch_assoc($result);
            if (is_array($item)) {
                // Normalize keys to lowercase for robust lookup
                $itemNormalized = [];
                foreach ($item as $key => $value) {
                    if (is_string($key)) {
                        $itemNormalized[strtolower($key)] = $value;
                    }
                }
            }
        }
        mysqli_stmt_close($stmt);
    } else {
        $error = 'Failed to load company.';
    }
}

// Validation reporting
if ($item === null && $error === '') {
    if ($id > 0) {
        $error = 'Company not found for ID ' . $id . '.';
    } else {
        $error = 'Invalid company id.';
    }
}
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
    $crud_title = 'View Company';
}
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
            <h1>🔎 Company Information</h1>
            <div class="card">
                <?php if ($item === null): ?>
                    <?php echo itm_render_alert_errors($error ?? ''); ?>
                <?php else: ?>
                    <table>
                        <tbody>
                        <tr><th style="width:220px;">ID</th><td><?php echo (int)itm_company_view_value($itemNormalized, ['id']); ?></td></tr>
                        <tr><th>Company</th><td><?php echo sanitize(itm_company_view_value($itemNormalized, ['company'])); ?></td></tr>
                        <tr><th>InCode</th><td><?php echo sanitize(itm_company_view_value($itemNormalized, ['incode'])); ?></td></tr>
                        <tr><th>City</th><td><?php echo sanitize(itm_company_view_value($itemNormalized, ['city'])); ?></td></tr>
                        <tr><th>Country</th><td><?php echo sanitize(itm_company_view_value($itemNormalized, ['country'])); ?></td></tr>
                        <tr><th>Phone</th><td><?php echo sanitize(itm_company_view_value($itemNormalized, ['phone'])); ?></td></tr>
                        <tr><th>Email</th><td><?php echo sanitize(itm_company_view_value($itemNormalized, ['email'])); ?></td></tr>
                        <tr><th>Website</th><td><?php echo sanitize(itm_company_view_value($itemNormalized, ['website'])); ?></td></tr>
                        <tr><th>VAT</th><td><?php echo sanitize(itm_company_view_value($itemNormalized, ['vat'])); ?></td></tr>
                        <?php $unitNo = itm_company_view_value($itemNormalized, ['unit_no']); ?>
                        <?php if ($unitNo !== ''): ?>
                            <tr><th>Unit No.</th><td><?php echo sanitize($unitNo); ?></td></tr>
                        <?php endif; ?>
                        <tr><th>Comments</th><td><?php echo nl2br(sanitize(itm_company_view_value($itemNormalized, ['comments']))); ?></td></tr>
                        <?php $activeValue = (int)itm_company_view_value($itemNormalized, ['active', 'status'], '0'); ?>
                        <tr><th>Status</th><td><?php echo $activeValue === 1 ? '✅' : '❌'; ?></td></tr>
                        <?php $companyAuditScopeId = (int)itm_company_view_value($itemNormalized, ['id']); ?>
                        <tr><th>Deleted By</th><td><?php echo itm_crud_render_audit_cell_value($conn, $companyAuditScopeId, 'deleted_by', $itemNormalized['deleted_by'] ?? null); ?></td></tr>
                        <tr><th>Deleted At</th><td><?php echo itm_crud_render_audit_cell_value($conn, $companyAuditScopeId, 'deleted_at', $itemNormalized['deleted_at'] ?? null); ?></td></tr>
                        <tr><th>Created By</th><td><?php echo itm_crud_render_audit_cell_value($conn, $companyAuditScopeId, 'created_by', $itemNormalized['created_by'] ?? null); ?></td></tr>
                        <tr><th>Created At</th><td><?php echo itm_crud_render_audit_cell_value($conn, $companyAuditScopeId, 'created_at', $itemNormalized['created_at'] ?? null); ?></td></tr>
                        <tr><th>Updated By</th><td><?php echo itm_crud_render_audit_cell_value($conn, $companyAuditScopeId, 'updated_by', $itemNormalized['updated_by'] ?? null); ?></td></tr>
                        <tr><th>Updated At</th><td><?php echo itm_crud_render_audit_cell_value($conn, $companyAuditScopeId, 'updated_at', $itemNormalized['updated_at'] ?? null); ?></td></tr>
                        </tbody>
                    </table>
                <?php endif; ?>
                <div style="display:flex;gap:10px;margin-top:20px;">
                    <a href="index.php" class="btn">🔙</a>
                    <?php if ($item !== null): ?>
                        <a href="edit.php?id=<?php echo (int)itm_company_view_value($itemNormalized, ['id']); ?>" class="btn btn-primary">✏️</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="../../js/theme.js"></script>
</body>
</html>
