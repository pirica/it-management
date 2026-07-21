<?php
/**
 * Admin matrix: enable or disable QR / code share per module and company.
 */

if (!isset($crud_title)) {
    $crud_title = 'Share Modules';
}

require '../../config/config.php';

if (!itm_is_admin($conn, (int)($_SESSION['employee_id'] ?? 0))) {
    header('Location: ' . BASE_URL . 'dashboard.php');
    exit;
}

itm_sync_modules_registry_from_filesystem($conn);

$modulePath = dirname($_SERVER['PHP_SELF']);
$csrfToken = itm_get_csrf_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json; charset=utf-8');
    itm_require_post_csrf();

    $ajaxAction = trim((string)($_POST['ajax_action'] ?? ''));
    if ($ajaxAction === 'toggle_share') {
        $targetCompanyId = (int)($_POST['company_id'] ?? 0);
        $targetModuleId = (int)($_POST['module_id'] ?? 0);
        $enabled = (int)((string)($_POST['enabled'] ?? '0') === '1');
        if ($targetCompanyId <= 0 || $targetModuleId <= 0) {
            echo json_encode(['ok' => false, 'error' => 'Invalid company or module.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        $registryRow = null;
        $stmtRegistry = mysqli_prepare($conn, 'SELECT id, active FROM modules_registry WHERE id = ? LIMIT 1');
        if ($stmtRegistry) {
            mysqli_stmt_bind_param($stmtRegistry, 'i', $targetModuleId);
            mysqli_stmt_execute($stmtRegistry);
            $registryRes = mysqli_stmt_get_result($stmtRegistry);
            $registryRow = $registryRes ? mysqli_fetch_assoc($registryRes) : null;
            mysqli_stmt_close($stmtRegistry);
        }

        if (!$registryRow || (int)($registryRow['active'] ?? 0) !== 1) {
            echo json_encode(['ok' => false, 'error' => 'Module is inactive in the registry.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        $ok = itm_set_company_module_share($conn, $targetCompanyId, $targetModuleId, $enabled);
        echo json_encode(['ok' => (bool)$ok, 'enabled' => $enabled], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($ajaxAction === 'bulk_toggle_share') {
        $enabled = (int)((string)($_POST['enabled'] ?? '0') === '1');
        $pairsRaw = trim((string)($_POST['pairs_json'] ?? ''));
        $pairs = json_decode($pairsRaw, true);
        if (!is_array($pairs)) {
            echo json_encode(['ok' => false, 'error' => 'Invalid bulk payload.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        $updated = 0;
        foreach ($pairs as $pair) {
            if (!is_array($pair)) {
                continue;
            }
            $targetCompanyId = (int)($pair['company_id'] ?? 0);
            $targetModuleId = (int)($pair['module_id'] ?? 0);
            if ($targetCompanyId <= 0 || $targetModuleId <= 0) {
                continue;
            }
            if (itm_set_company_module_share($conn, $targetCompanyId, $targetModuleId, $enabled)) {
                $updated++;
            }
        }

        echo json_encode(['ok' => true, 'updated' => $updated, 'enabled' => $enabled], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    echo json_encode(['ok' => false, 'error' => 'Unknown action.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$companies = [];
$companiesRes = mysqli_query($conn, 'SELECT id, company FROM companies WHERE active = 1 ORDER BY company ASC');
while ($companiesRes && ($companyRow = mysqli_fetch_assoc($companiesRes))) {
    $companies[] = $companyRow;
}

$registryRows = itm_module_share_matrix_rows($conn);
$shareMap = itm_company_module_share_map($conn);
require_once ROOT_PATH . 'includes/itm_qr_share.php';
$capableSlugs = itm_qr_share_capable_module_slugs();

$searchRaw = trim((string)($_GET['search'] ?? ''));
if ($searchRaw !== '') {
    $needle = strtolower($searchRaw);
    $registryRows = array_values(array_filter($registryRows, static function ($row) use ($needle) {
        $haystack = strtolower((string)($row['module_slug'] ?? '') . ' ' . (string)($row['module_name'] ?? ''));

        return strpos($haystack, $needle) !== false;
    }));
}

$modulePathEsc = sanitize($modulePath);
$moduleListHeading = itm_sidebar_label_for_module(basename(dirname($_SERVER['PHP_SELF']))) ?: $crud_title;
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
            <div data-itm-new-button-managed="server" style="position:relative;display:flex;justify-content:center;align-items:center;margin-bottom:16px;min-height:40px;">
                <h1 style="margin:0;text-align:center;"><?php echo sanitize($moduleListHeading); ?></h1>
            </div>

            <div class="card" style="margin-bottom:16px;">
                <div class="card-body">
                    <p style="margin-top:0;">Enable or disable temporary QR / 6-digit share per company and module. Modules without a share implementation show as <span class="badge">No share UI</span> — toggles apply when share is added. Opt-out: no row or enabled = share allowed; explicit disable blocks <code>create_share_session</code>.</p>
                    <form method="GET" action="index.php" style="margin-bottom:16px;display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;">
                        <div class="form-group" style="margin:0;min-width:260px;flex:1;">
                            <label for="moduleSearch">Search (all fields)</label>
                            <input type="text" id="moduleSearch" name="search" value="<?php echo sanitize($searchRaw); ?>" placeholder="Type to search records...">
                        </div>
                        <div class="form-actions" style="margin:0;display:flex;gap:8px;">
                            <button type="submit" class="btn btn-sm btn-primary">Search</button>
                            <a href="index.php" class="btn btn-sm" title="Clear">🔙</a>
                        </div>
                    </form>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                        <button type="button" class="btn btn-sm" id="sms-select-all">Select All</button>
                        <button type="button" class="btn btn-sm" id="sms-cancel-select" style="display:none;">Cancel Select</button>
                        <button type="button" class="btn btn-sm" id="sms-unselect-all" style="display:none;">Unselect All</button>
                        <button type="button" class="btn btn-sm btn-primary" id="sms-enable-selected" style="display:none;">Enable Selected</button>
                        <button type="button" class="btn btn-sm btn-danger" id="sms-disable-selected" style="display:none;">Disable Selected</button>
                        <input type="text" id="sms-matrix-filter" class="form-control" style="max-width:280px;margin-left:auto;" placeholder="Filter modules...">
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body" style="overflow:auto;">
                    <table class="table" id="sms-share-matrix" data-itm-no-import-excel="1" data-itm-no-export-excel="1" data-itm-no-export-pdf="1">
                        <thead>
                        <tr>
                            <th>Modules</th>
                            <?php foreach ($companies as $companyRow): ?>
                                <th style="text-align:center;min-width:110px;"><?= sanitize((string)$companyRow['company']) ?></th>
                            <?php endforeach; ?>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($registryRows as $registryRow): ?>
                            <?php
                            $moduleId = (int)($registryRow['id'] ?? 0);
                            $moduleSlug = (string)($registryRow['module_slug'] ?? '');
                            $moduleName = (string)($registryRow['module_name'] ?? $moduleSlug);
                            $isActive = (int)($registryRow['active'] ?? 0) === 1;
                            $hasShareImpl = in_array($moduleSlug, $capableSlugs, true);
                            $rowSearch = strtolower($moduleSlug . ' ' . $moduleName);
                            ?>
                            <tr data-sms-search="<?= sanitize($rowSearch) ?>">
                                <td>
                                    <strong><?= sanitize($moduleName) ?></strong>
                                    <div style="color:var(--text-secondary);font-size:12px;"><?= sanitize($moduleSlug) ?></div>
                                    <?php if (!$hasShareImpl): ?><span class="badge">No share UI</span><?php endif; ?>
                                    <?php if (!$isActive): ?><span class="badge badge-danger">Inactive</span><?php endif; ?>
                                </td>
                                <?php foreach ($companies as $companyRow): ?>
                                    <?php
                                    $companyRowId = (int)($companyRow['id'] ?? 0);
                                    $effectiveEnabled = itm_module_share_effective_enabled($conn, $companyRowId, $moduleId, $shareMap);
                                    $toggleDisabled = !$isActive;
                                    ?>
                                    <td style="text-align:center;">
                                        <label class="itm-checkbox-control" title="<?= $toggleDisabled ? 'Inactive registry rows cannot be toggled.' : 'Toggle share for this company' ?>">
                                            <input
                                                type="checkbox"
                                                class="sms-share-toggle"
                                                data-company-id="<?= $companyRowId ?>"
                                                data-module-id="<?= $moduleId ?>"
                                                <?= $effectiveEnabled ? 'checked' : '' ?>
                                                <?= $toggleDisabled ? 'disabled' : '' ?>
                                            >
                                            <span class="itm-check-indicator" aria-hidden="true"><?= $effectiveEnabled ? '✅' : '❌' ?></span>
                                        </label>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
window.ITM_BASE_URL = <?= json_encode(BASE_URL) ?>;
window.ITM_SMS_CSRF = <?= json_encode($csrfToken) ?>;
window.ITM_SMS_ENDPOINT = <?= json_encode($modulePath . '/index.php') ?>;
</script>
<script src="../../js/share-modules-matrix.js"></script>
</body>
</html>
