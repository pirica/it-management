<?php
if (!isset($crud_table)) {
    $crud_table = 'modules_registry';
}
if (!isset($crud_title)) {
    $crud_title = 'Company Module Access';
}
if (!isset($crud_action)) {
    $crud_action = $crud_action ?? 'index';
}

require '../../config/config.php';

// Why: Only administrators manage company-level module visibility.
if (!itm_is_admin($conn, (int)($_SESSION['employee_id'] ?? 0))) {
    header('Location: ' . BASE_URL . 'dashboard.php');
    exit;
}

itm_sync_modules_registry_from_filesystem($conn);

$crud_action = $crud_action ?? 'index';
$csrfToken = itm_get_csrf_token();
$modulePath = dirname($_SERVER['PHP_SELF']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json; charset=utf-8');
    itm_require_post_csrf();

    $ajaxAction = trim((string)($_POST['ajax_action'] ?? ''));
    if ($ajaxAction === 'toggle_access') {
        $targetCompanyId = (int)($_POST['company_id'] ?? 0);
        $targetModuleId = (int)($_POST['module_id'] ?? 0);
        $enabled = (int)((string)($_POST['enabled'] ?? '0') === '1');
        if ($targetCompanyId <= 0 || $targetModuleId <= 0) {
            echo json_encode(['ok' => false, 'error' => 'Invalid company or module.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        $registryRow = null;
        $stmtRegistry = mysqli_prepare($conn, 'SELECT id, is_system_module, active FROM modules_registry WHERE id = ? LIMIT 1');
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

        $ok = itm_set_company_module_access($conn, $targetCompanyId, $targetModuleId, $enabled);
        echo json_encode(['ok' => (bool)$ok, 'enabled' => $enabled], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($ajaxAction === 'set_icon') {
        $targetCompanyId = (int)($_POST['company_id'] ?? 0);
        $targetModuleId = (int)($_POST['module_id'] ?? 0);
        $icon = trim((string)($_POST['icon'] ?? ''));
        if ($targetCompanyId <= 0 || $targetModuleId <= 0) {
            echo json_encode(['ok' => false, 'error' => 'Invalid company or module.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        $ok = itm_set_company_module_icon($conn, $targetCompanyId, $targetModuleId, $icon);
        echo json_encode(['ok' => (bool)$ok, 'icon' => itm_module_access_normalize_icon($icon) ?? ''], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($ajaxAction === 'bulk_toggle_access') {
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
            if (itm_set_company_module_access($conn, $targetCompanyId, $targetModuleId, $enabled)) {
                $updated++;
            }
        }

        echo json_encode(['ok' => true, 'updated' => $updated, 'enabled' => $enabled], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    echo json_encode(['ok' => false, 'error' => 'Unknown action.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_sample_data'])) {
    itm_require_post_csrf();
    header('Location: ' . $modulePath . '/index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $crud_action === 'delete') {
    itm_require_post_csrf();
    $deleteRegistryId = (int)($_POST['id'] ?? 0);
    if ($deleteRegistryId > 0) {
        $oldValues = itm_fetch_audit_record($conn, 'modules_registry', $deleteRegistryId, (int)$company_id);
        $stmtDelete = mysqli_prepare($conn, 'DELETE FROM modules_registry WHERE id = ? LIMIT 1');
        if ($stmtDelete) {
            mysqli_stmt_bind_param($stmtDelete, 'i', $deleteRegistryId);
            if (mysqli_stmt_execute($stmtDelete)) {
                itm_log_audit($conn, 'modules_registry', $deleteRegistryId, 'DELETE', $oldValues, null);
            }
            mysqli_stmt_close($stmtDelete);
        }
    }
    header('Location: ' . $modulePath . '/list_all.php');
    exit;
}

$formError = '';
$formValues = [
    'module_slug' => '',
    'module_name' => '',
    'icon' => '',
    'is_system_module' => 0,
    'active' => 1,
];
$editId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$viewId = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($crud_action, ['create', 'edit'], true)) {
    itm_require_post_csrf();
    $formValues['module_slug'] = trim((string)($_POST['module_slug'] ?? ''));
    $formValues['module_name'] = trim((string)($_POST['module_name'] ?? ''));
    $formValues['icon'] = trim((string)($_POST['icon'] ?? ''));
    $formValues['is_system_module'] = !empty($_POST['is_system_module']) ? 1 : 0;
    $formValues['active'] = isset($_POST['active']) ? (int)$_POST['active'] : 1;
    $editId = (int)($_POST['id'] ?? 0);

    if ($formValues['module_slug'] === '' || !preg_match('/^[a-z][a-z0-9_]*$/', $formValues['module_slug'])) {
        $formError = 'Module slug is required and must use lowercase letters, numbers, and underscores.';
    } elseif ($formValues['module_name'] === '') {
        $formError = 'Module name is required.';
    } else {
        if ($crud_action === 'create') {
            $iconValue = itm_module_access_normalize_icon($formValues['icon']);
            $stmtInsert = mysqli_prepare(
                $conn,
                'INSERT INTO modules_registry (module_slug, module_name, icon, is_system_module, active) VALUES (?, ?, ?, ?, ?)'
            );
            if ($stmtInsert) {
                mysqli_stmt_bind_param(
                    $stmtInsert,
                    'sssii',
                    $formValues['module_slug'],
                    $formValues['module_name'],
                    $iconValue,
                    $formValues['is_system_module'],
                    $formValues['active']
                );
                if (mysqli_stmt_execute($stmtInsert)) {
                    $newId = (int)mysqli_insert_id($conn);
                    $newValues = itm_fetch_audit_record($conn, 'modules_registry', $newId, (int)$company_id);
                    itm_log_audit($conn, 'modules_registry', $newId, 'INSERT', null, $newValues);
                    itm_seed_company_module_access_for_module($conn, $newId);
                    header('Location: ' . $modulePath . '/list_all.php');
                    exit;
                }
                $formError = 'Could not create registry row. The slug may already exist.';
                mysqli_stmt_close($stmtInsert);
            }
        } elseif ($crud_action === 'edit' && $editId > 0) {
            $oldValues = itm_fetch_audit_record($conn, 'modules_registry', $editId, (int)$company_id);
            $iconValue = itm_module_access_normalize_icon($formValues['icon']);
            $stmtUpdate = mysqli_prepare(
                $conn,
                'UPDATE modules_registry SET module_slug = ?, module_name = ?, icon = ?, is_system_module = ?, active = ? WHERE id = ? LIMIT 1'
            );
            if ($stmtUpdate) {
                mysqli_stmt_bind_param(
                    $stmtUpdate,
                    'sssiii',
                    $formValues['module_slug'],
                    $formValues['module_name'],
                    $iconValue,
                    $formValues['is_system_module'],
                    $formValues['active'],
                    $editId
                );
                if (mysqli_stmt_execute($stmtUpdate)) {
                    $newValues = itm_fetch_audit_record($conn, 'modules_registry', $editId, (int)$company_id);
                    itm_log_audit($conn, 'modules_registry', $editId, 'UPDATE', $oldValues, $newValues);
                    header('Location: ' . $modulePath . '/view.php?id=' . $editId);
                    exit;
                }
                $formError = 'Could not update registry row.';
                mysqli_stmt_close($stmtUpdate);
            }
        }
    }
}

if ($crud_action === 'edit' && $editId > 0 && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $stmtEdit = mysqli_prepare($conn, 'SELECT * FROM modules_registry WHERE id = ? LIMIT 1');
    if ($stmtEdit) {
        mysqli_stmt_bind_param($stmtEdit, 'i', $editId);
        mysqli_stmt_execute($stmtEdit);
        $editRes = mysqli_stmt_get_result($stmtEdit);
        $editRow = $editRes ? mysqli_fetch_assoc($editRes) : null;
        mysqli_stmt_close($stmtEdit);
        if ($editRow) {
            $formValues = [
                'module_slug' => (string)($editRow['module_slug'] ?? ''),
                'module_name' => (string)($editRow['module_name'] ?? ''),
                'icon' => (string)($editRow['icon'] ?? ''),
                'is_system_module' => (int)($editRow['is_system_module'] ?? 0),
                'active' => (int)($editRow['active'] ?? 0),
            ];
        }
    }
}

$viewRow = null;
if ($crud_action === 'view' && $viewId > 0) {
    $stmtView = mysqli_prepare($conn, 'SELECT * FROM modules_registry WHERE id = ? LIMIT 1');
    if ($stmtView) {
        mysqli_stmt_bind_param($stmtView, 'i', $viewId);
        mysqli_stmt_execute($stmtView);
        $viewRes = mysqli_stmt_get_result($stmtView);
        $viewRow = $viewRes ? mysqli_fetch_assoc($viewRes) : null;
        mysqli_stmt_close($stmtView);
    }
}

$companies = [];
$companiesRes = mysqli_query($conn, 'SELECT id, company FROM companies WHERE active = 1 ORDER BY company ASC');
while ($companiesRes && ($companyRow = mysqli_fetch_assoc($companiesRes))) {
    $companies[] = $companyRow;
}

$registryRows = itm_list_all_modules_registry($conn);
$accessMap = itm_company_module_access_map($conn);
$iconMap = itm_company_module_icon_map($conn);

$searchRaw = trim((string)($_GET['search'] ?? ''));
if ($searchRaw !== '' && $crud_action === 'list_all') {
    $registryRows = array_values(array_filter($registryRows, static function ($row) use ($searchRaw) {
        $haystack = strtolower((string)($row['module_slug'] ?? '') . ' ' . (string)($row['module_name'] ?? ''));
        return strpos($haystack, strtolower($searchRaw)) !== false;
    }));
}

$modulePathEsc = sanitize($modulePath);
// Why: List h1 must use Settings sidebar label so per-user emoji overrides apply in the matrix header.
$moduleListHeading = itm_sidebar_label_for_module(basename(dirname($_SERVER['PHP_SELF']))) ?: $crud_title;
$newButtonPosition = (string)($ui_config['new_button_position'] ?? 'left_right');
if (!in_array($newButtonPosition, ['left', 'right', 'left_right'], true)) {
    $newButtonPosition = 'left_right';
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
    $crud_title = 'Company Module Access';
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
            <div data-itm-new-button-managed="server" style="position:relative;display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;gap:12px;flex-wrap:wrap;min-height:40px;">
                <?php if (in_array($newButtonPosition, ['left', 'left_right'], true)): ?>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                        <a class="btn btn-sm" href="<?= $modulePathEsc ?>/index.php" title="Matrix">Matrix</a>
                        <a class="btn btn-sm" href="<?= $modulePathEsc ?>/list_all.php" title="Registry List">Registry List</a>
                        <a href="<?= $modulePathEsc ?>/create.php" class="btn btn-primary itm-list-new-button" title="Create">➕</a>
                    </div>
                <?php else: ?>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                        <a class="btn btn-sm" href="<?= $modulePathEsc ?>/index.php" title="Matrix">Matrix</a>
                        <a class="btn btn-sm" href="<?= $modulePathEsc ?>/list_all.php" title="Registry List">Registry List</a>
                    </div>
                <?php endif; ?>
                <h1 style="position:absolute;left:50%;transform:translateX(-50%);margin:0;text-align:center;"><?php echo sanitize($moduleListHeading); ?></h1>
                <?php if (in_array($newButtonPosition, ['right', 'left_right'], true)): ?>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                        <a href="<?= $modulePathEsc ?>/create.php" class="btn btn-primary itm-list-new-button" title="Create">➕</a>
                    </div>
                <?php else: ?>
                    <span></span>
                <?php endif; ?>
            </div>

            <?php if ($crud_action === 'index'): ?>
                <div class="card" style="margin-bottom:16px;">
                    <div class="card-body">
                        <p style="margin-top:0;">Enable or disable modules per company. Set a company-default sidebar emoji per cell (empty uses registry/catalog fallback). All registry modules are listed below, including hidden, inactive, and system modules.</p>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                            <button type="button" class="btn btn-sm" id="cma-select-all">Select All</button>
                            <button type="button" class="btn btn-sm" id="cma-cancel-select" style="display:none;">Cancel Select</button>
                            <button type="button" class="btn btn-sm" id="cma-unselect-all" style="display:none;">Unselect All</button>
                            <button type="button" class="btn btn-sm btn-primary" id="cma-enable-selected" style="display:none;">Enable Selected</button>
                            <button type="button" class="btn btn-sm btn-danger" id="cma-disable-selected" style="display:none;">Disable Selected</button>
                            <input type="text" id="cma-matrix-filter" class="form-control" style="max-width:280px;margin-left:auto;" placeholder="Filter modules...">
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body" style="overflow:auto;">
                        <table class="table" id="cma-access-matrix" data-itm-no-export-excel="1" data-itm-no-export-pdf="1">
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
                                $isSystem = (int)($registryRow['is_system_module'] ?? 0) === 1;
                                $isActive = (int)($registryRow['active'] ?? 0) === 1;
                                $rowSearch = strtolower($moduleSlug . ' ' . $moduleName);
                                ?>
                                <tr data-cma-search="<?= sanitize($rowSearch) ?>">
                                    <td>
                                        <strong><?= sanitize($moduleName) ?></strong>
                                        <div style="color:var(--text-secondary);font-size:12px;"><a 
    href="../<?= sanitize($moduleSlug) ?>" 
    target="_blank" 
    rel="noopener noreferrer nofollow"
    style="color:var(--accent); text-decoration:none;"
>
    <?= sanitize($moduleSlug) ?>
</a></div>
                                        <?php if ($isSystem): ?><span class="badge">System</span><?php endif; ?>
                                        <?php if (!$isActive): ?><span class="badge badge-danger">Inactive</span><?php endif; ?>
                                    </td>
                                    <?php foreach ($companies as $companyRow): ?>
                                        <?php
                                        $companyRowId = (int)($companyRow['id'] ?? 0);
                                        $effectiveEnabled = itm_module_access_effective_enabled($conn, $companyRowId, $moduleId, $accessMap);
                                        $toggleDisabled = !$isActive;
                                        $companyIcon = (string)($iconMap[$companyRowId][$moduleId] ?? '');
                                        $inheritedIcon = itm_module_access_inherited_icon_for_slug($conn, $moduleSlug, $registryRow);
                                        $displayIcon = $companyIcon !== '' ? $companyIcon : $inheritedIcon;
                                        ?>
                                        <td style="text-align:center;">
                                            <label class="itm-checkbox-control" title="<?= $toggleDisabled ? 'Inactive registry rows cannot be toggled.' : ($isSystem ? 'System module (admins always retain access).' : 'Toggle company access') ?>">
                                                <input
                                                    type="checkbox"
                                                    class="cma-access-toggle"
                                                    data-company-id="<?= $companyRowId ?>"
                                                    data-module-id="<?= $moduleId ?>"
                                                    data-system-module="<?= $isSystem ? '1' : '0' ?>"
                                                    <?= $effectiveEnabled ? 'checked' : '' ?>
                                                    <?= $toggleDisabled ? 'disabled' : '' ?>
                                                >
                                                <span class="itm-check-indicator" aria-hidden="true"><?= $effectiveEnabled ? '✅' : '❌' ?></span>
                                            </label>
                                            <input
                                                type="text"
                                                class="itm-module-icon-input cma-icon-input"
                                                maxlength="16"
                                                value="<?= sanitize($displayIcon) ?>"
                                                data-inherited-icon="<?= sanitize($inheritedIcon) ?>"
                                                data-company-id="<?= $companyRowId ?>"
                                                data-module-id="<?= $moduleId ?>"
                                                data-last-saved="<?= sanitize($companyIcon) ?>"
                                                autocomplete="off"
                                                spellcheck="false"
                                                title="Company sidebar emoji (match inherited value to reset)"
                                            >
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php elseif ($crud_action === 'list_all'): ?>
                <div class="card">
                    <div class="card-body">
                        <form method="GET" action="list_all.php" style="margin-bottom:16px;display:flex;gap:8px;align-items:flex-end;">
                            <div class="form-group" style="margin:0;">
                                <label for="moduleSearch">Search (all fields)</label>
                                <input type="text" id="moduleSearch" name="search" value="<?= sanitize($searchRaw) ?>" placeholder="Type to search records...">
                            </div>
                            <button type="submit" class="btn btn-sm btn-primary">Search</button>
                        </form>
                        <table class="table" data-itm-db-import-endpoint="index.php">
                            <thead>
                            <tr>
                                <th>Module</th>
                                <th>Slug</th>
                                <th>System</th>
                                <th class="itm-actions-cell" data-itm-actions-origin="1">Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($registryRows as $registryRow): ?>
                                <tr>
                                    <td><?= sanitize((string)($registryRow['module_name'] ?? '')) ?></td>
                                    <td><a href="../<?= sanitize((string)($registryRow['module_slug'] ?? '')) ?>" rel="nofollow noreferrer noopener" target="_blank" style="color:var(--accent); text-decoration:none;">
                                        <?= sanitize((string)($registryRow['module_slug'] ?? '')) ?></a>
                                    </td>
                                    <td><?= ((int)($registryRow['is_system_module'] ?? 0) === 1) ? '<span class="badge">System</span>' : '<span class="badge badge-danger">No</span>' ?></td>
                                    <td class="itm-actions-cell" data-itm-actions-origin="1">
                                        <div class="itm-actions-wrap">
                                            <a class="btn btn-sm" href="view.php?id=<?= (int)$registryRow['id'] ?>">🔎</a>
                                            <a class="btn btn-sm" href="edit.php?id=<?= (int)$registryRow['id'] ?>">✏️</a>
                                            <form method="POST" action="delete.php" style="display:inline;" onsubmit="return confirm('Delete this registry row?');">
                                                <input type="hidden" name="csrf_token" value="<?= sanitize($csrfToken) ?>">
                                                <input type="hidden" name="id" value="<?= (int)$registryRow['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-danger">🗑️</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php elseif (in_array($crud_action, ['create', 'edit'], true)): ?>
                <div class="card">
                    <div class="card-body">
                        <?php if ($formError !== ''): ?>
                            <div class="alert alert-danger"><?= sanitize($formError) ?></div>
                        <?php endif; ?>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= sanitize($csrfToken) ?>">
                            <?php if ($crud_action === 'edit'): ?>
                                <input type="hidden" name="id" value="<?= (int)$editId ?>">
                            <?php endif; ?>
                            <div class="form-group">
                                <label for="module_slug">Module Slug</label>
                                <input type="text" name="module_slug" id="module_slug" value="<?= sanitize($formValues['module_slug']) ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="module_name">Module Name</label>
                                <input type="text" name="module_name" id="module_name" value="<?= sanitize($formValues['module_name']) ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="icon">Global Sidebar Icon</label>
                                <input type="text" name="icon" id="icon" value="<?= sanitize($formValues['icon']) ?>" maxlength="16" placeholder="e.g. 🧩">
                                <p class="form-hint">Optional emoji seed for all companies (company matrix and user Settings can override).</p>
                            </div>
                            <div class="form-group">
                                <label><?= sanitize('System Module') ?></label>
                                <label class="itm-checkbox-control">
                                    <input type="checkbox" name="is_system_module" value="1" <?= ((int)$formValues['is_system_module'] === 1) ? 'checked' : '' ?>>
                                    <span>System Module <span class="itm-check-indicator" aria-hidden="true"><?= ((int)$formValues['is_system_module'] === 1) ? '✅' : '❌' ?></span></span>
                                </label>
                            </div>
                            <input type="hidden" name="active" value="<?= (int)$formValues['active'] ?>">
                            <button type="submit" class="btn btn-primary" title="<?php echo $crud_action === 'create' ? 'Create' : 'Save'; ?>"><?php echo $crud_action === 'create' ? '➕' : '💾'; ?></button>
                            <a class="btn" href="list_all.php" title="Cancel">🔙</a>
                        </form>
                    </div>
                </div>
            <?php elseif ($crud_action === 'view'): ?>
                <div class="card">
                    <div class="card-body">
                        <?php if (!$viewRow): ?>
                            <p>Registry row not found.</p>
                        <?php else: ?>
                            <p><strong>Module Name:</strong> <?= sanitize((string)$viewRow['module_name']) ?></p>
                            <p><strong>Slug:</strong> <a href="../<?= sanitize((string)($viewRow['module_slug'] ?? '')) ?>" rel="nofollow noreferrer noopener" target="_blank" style="color:var(--accent); text-decoration:none;">
                                                      <?= sanitize((string)($viewRow['module_slug'] ?? '')) ?></a></p>
                            <p><strong>Global Icon:</strong> <?= sanitize((string)($viewRow['icon'] ?? '')) !== '' ? sanitize((string)$viewRow['icon']) : '—' ?></p>
                            <p><strong>System Module:</strong> <span class="itm-check-indicator" aria-hidden="true"><?= ((int)$viewRow['is_system_module'] === 1) ? '✅' : '❌' ?></span></p>
                            <a class="btn btn-sm" href="edit.php?id=<?= (int)$viewRow['id'] ?>">✏️</a>
                            <a class="btn btn-sm" href="list_all.php">🔙</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script>
window.ITM_BASE_URL = <?= json_encode(BASE_URL) ?>;
window.ITM_CMA_CSRF = <?= json_encode($csrfToken) ?>;
window.ITM_CMA_ENDPOINT = <?= json_encode($modulePath . '/index.php') ?>;
</script>
<script src="../../js/company-module-access-matrix.js"></script>
</body>
</html>
