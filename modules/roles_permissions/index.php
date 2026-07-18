<?php
require '../../config/config.php';
require_once ROOT_PATH . 'includes/itm_employee_employment_status.php';

$activeCompanyId = itm_resolve_active_company_id((int)($company_id ?? 0));
$rpCanManage = itm_is_admin($conn, (int)($_SESSION['employee_id'] ?? 0));

if ($activeCompanyId <= 0 || (int)($_SESSION['employee_id'] ?? 0) <= 0) {
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}
itm_sync_modules_registry_from_filesystem($conn);

$csrfToken = itm_get_csrf_token();
$modulePath = dirname($_SERVER['PHP_SELF']);
$modulePathEsc = sanitize($modulePath);
$selectedRoleId = (int)($_GET['role_id'] ?? 0);

/**
 * @return array<int,array<string,mixed>>
 */
function rp_load_roles(mysqli $conn, int $companyId): array
{
    $roles = [];
    // Why: Sidebar count = employees.role_id + tenant company_id + HR employment status Active.
    $empJoin = itm_employee_active_employment_status_join_sql('e', 'es');
    $empActive = itm_employee_active_employment_status_predicate_sql('es');
    $sql = 'SELECT er.id, er.name, er.active,
                   COALESCE(rh.hierarchy_order, 999) AS hierarchy_order,
                   (SELECT COUNT(*)
                    FROM employees e' . $empJoin . '
                    WHERE e.company_id = er.company_id
                      AND e.role_id = er.id
                      AND ' . $empActive . '
                   ) AS active_count
            FROM employee_roles er
            LEFT JOIN role_hierarchy rh
              ON rh.role_id = er.id AND rh.company_id = er.company_id
            WHERE er.company_id = ? AND er.active = 1
            ORDER BY hierarchy_order ASC, er.name ASC';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return $roles;
    }
    mysqli_stmt_bind_param($stmt, 'i', $companyId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $roles[] = $row;
    }
    mysqli_stmt_close($stmt);

    return $roles;
}

/**
 * @return array<int,array<string,mixed>>
 */
function rp_load_registry_modules(mysqli $conn): array
{
    return itm_list_all_modules_registry($conn);
}

/**
 * @return array<string,array<string,int>>
 */
function rp_load_permission_map(mysqli $conn, int $companyId, int $roleId): array
{
    $map = [];
    if ($roleId <= 0) {
        return $map;
    }

    $sql = 'SELECT module_name, can_view, can_create, can_edit, can_delete, can_import, can_export
            FROM role_module_permissions
            WHERE company_id = ? AND role_id = ?';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return $map;
    }
    mysqli_stmt_bind_param($stmt, 'ii', $companyId, $roleId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $moduleName = trim((string)($row['module_name'] ?? ''));
        if ($moduleName !== '') {
            $map[$moduleName] = $row;
        }
    }
    mysqli_stmt_close($stmt);

    return $map;
}

/**
 * @param array<string,array<string,int>> $permissionMap
 * @return array<string,int>
 */
function rp_effective_flags(array $permissionMap, string $moduleName, ?array $allRow): array
{
    $moduleName = trim($moduleName);
    if ($moduleName !== '' && isset($permissionMap[$moduleName]) && strcasecmp((string)($permissionMap[$moduleName]['module_name'] ?? ''), 'ALL') !== 0) {
        $row = $permissionMap[$moduleName];
    } elseif (is_array($allRow)) {
        $row = $allRow;
    } else {
        $row = [];
    }

    return [
        'can_view' => (int)($row['can_view'] ?? 0),
        'can_create' => (int)($row['can_create'] ?? 0),
        'can_edit' => (int)($row['can_edit'] ?? 0),
        'can_delete' => (int)($row['can_delete'] ?? 0),
        'can_import' => (int)($row['can_import'] ?? 0),
        'can_export' => (int)($row['can_export'] ?? 0),
    ];
}

function rp_role_is_system(array $roleRow): bool
{
    return strcasecmp(trim((string)($roleRow['name'] ?? '')), 'Admin') === 0;
}

function rp_fetch_role(mysqli $conn, int $companyId, int $roleId): ?array
{
    if ($roleId <= 0) {
        return null;
    }

    $stmt = mysqli_prepare(
        $conn,
        'SELECT id, name, active FROM employee_roles WHERE company_id = ? AND id = ? LIMIT 1'
    );
    if (!$stmt) {
        return null;
    }
    mysqli_stmt_bind_param($stmt, 'ii', $companyId, $roleId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);

    return is_array($row) ? $row : null;
}

function rp_upsert_permission_row(
    mysqli $conn,
    int $companyId,
    int $roleId,
    string $moduleName,
    array $flags
): bool {
    $moduleName = trim($moduleName);
    if ($moduleName === '' || strcasecmp($moduleName, 'ALL') === 0) {
        return false;
    }

    $canView = (int)!empty($flags['can_view']);
    $canCreate = (int)!empty($flags['can_create']);
    $canEdit = (int)!empty($flags['can_edit']);
    $canDelete = (int)!empty($flags['can_delete']);
    $canImport = (int)!empty($flags['can_import']);
    $canExport = (int)!empty($flags['can_export']);

    $sql = 'INSERT INTO role_module_permissions
            (company_id, role_id, module_name, can_view, can_create, can_edit, can_delete, can_import, can_export)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
              can_view = VALUES(can_view),
              can_create = VALUES(can_create),
              can_edit = VALUES(can_edit),
              can_delete = VALUES(can_delete),
              can_import = VALUES(can_import),
              can_export = VALUES(can_export)';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return false;
    }
    mysqli_stmt_bind_param(
        $stmt,
        'iisiiiiii',
        $companyId,
        $roleId,
        $moduleName,
        $canView,
        $canCreate,
        $canEdit,
        $canDelete,
        $canImport,
        $canExport
    );
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return (bool)$ok;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json; charset=utf-8');
    itm_require_post_csrf();

    if (!$rpCanManage) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Administrator access required.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $ajaxAction = trim((string)($_POST['ajax_action'] ?? ''));

    if ($ajaxAction === 'save_permissions') {
        $roleId = (int)($_POST['role_id'] ?? 0);
        $roleRow = rp_fetch_role($conn, $activeCompanyId, $roleId);
        if (!$roleRow) {
            echo json_encode(['ok' => false, 'error' => 'Role not found.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
        if (rp_role_is_system($roleRow)) {
            echo json_encode(['ok' => false, 'error' => 'System roles use the ALL wildcard and cannot be edited here.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        $permissionsRaw = trim((string)($_POST['permissions_json'] ?? ''));
        $permissions = json_decode($permissionsRaw, true);
        if (!is_array($permissions)) {
            echo json_encode(['ok' => false, 'error' => 'Invalid permissions payload.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        $updated = 0;
        foreach ($permissions as $permissionRow) {
            if (!is_array($permissionRow)) {
                continue;
            }
            $moduleName = trim((string)($permissionRow['module_name'] ?? ''));
            if ($moduleName === '') {
                continue;
            }
            if (rp_upsert_permission_row($conn, $activeCompanyId, $roleId, $moduleName, $permissionRow)) {
                $updated++;
            }
        }

        echo json_encode(['ok' => true, 'updated' => $updated], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($ajaxAction === 'create_role') {
        $roleName = trim((string)($_POST['role_name'] ?? ''));
        if ($roleName === '') {
            echo json_encode(['ok' => false, 'error' => 'Role name is required.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        $stmtInsert = mysqli_prepare(
            $conn,
            'INSERT INTO employee_roles (company_id, name, active) VALUES (?, ?, 1)'
        );
        if (!$stmtInsert) {
            echo json_encode(['ok' => false, 'error' => 'Could not create role.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
        mysqli_stmt_bind_param($stmtInsert, 'is', $activeCompanyId, $roleName);
        if (!mysqli_stmt_execute($stmtInsert)) {
            mysqli_stmt_close($stmtInsert);
            echo json_encode(['ok' => false, 'error' => 'Role name may already exist for this company.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
        $newRoleId = (int)mysqli_insert_id($conn);
        mysqli_stmt_close($stmtInsert);

        $nextOrder = 999;
        $orderRes = mysqli_query(
            $conn,
            'SELECT COALESCE(MAX(hierarchy_order), 0) + 1 AS next_order
             FROM role_hierarchy WHERE company_id = ' . (int)$activeCompanyId
        );
        if ($orderRes && ($orderRow = mysqli_fetch_assoc($orderRes))) {
            $nextOrder = (int)($orderRow['next_order'] ?? 999);
        }

        $stmtHierarchy = mysqli_prepare(
            $conn,
            'INSERT INTO role_hierarchy (company_id, role_id, hierarchy_order) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE hierarchy_order = VALUES(hierarchy_order)'
        );
        if ($stmtHierarchy) {
            mysqli_stmt_bind_param($stmtHierarchy, 'iii', $activeCompanyId, $newRoleId, $nextOrder);
            mysqli_stmt_execute($stmtHierarchy);
            mysqli_stmt_close($stmtHierarchy);
        }

        echo json_encode(['ok' => true, 'role_id' => $newRoleId], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($ajaxAction === 'update_role') {
        $roleId = (int)($_POST['role_id'] ?? 0);
        $roleName = trim((string)($_POST['role_name'] ?? ''));
        $roleRow = rp_fetch_role($conn, $activeCompanyId, $roleId);
        if (!$roleRow) {
            echo json_encode(['ok' => false, 'error' => 'Role not found.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
        if (rp_role_is_system($roleRow)) {
            echo json_encode(['ok' => false, 'error' => 'System roles cannot be renamed here.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
        if ($roleName === '') {
            echo json_encode(['ok' => false, 'error' => 'Role name is required.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        $stmtUpdate = mysqli_prepare(
            $conn,
            'UPDATE employee_roles SET name = ? WHERE company_id = ? AND id = ? LIMIT 1'
        );
        if (!$stmtUpdate) {
            echo json_encode(['ok' => false, 'error' => 'Could not update role.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
        mysqli_stmt_bind_param($stmtUpdate, 'sii', $roleName, $activeCompanyId, $roleId);
        $ok = mysqli_stmt_execute($stmtUpdate);
        mysqli_stmt_close($stmtUpdate);

        if (!$ok) {
            echo json_encode(['ok' => false, 'error' => 'Role name may already exist for this company.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    echo json_encode(['ok' => false, 'error' => 'Unknown action.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$roles = rp_load_roles($conn, $activeCompanyId);
if ($selectedRoleId <= 0 && $roles !== []) {
    $selectedRoleId = (int)($roles[0]['id'] ?? 0);
}

$selectedRole = null;
foreach ($roles as $roleRow) {
    if ((int)($roleRow['id'] ?? 0) === $selectedRoleId) {
        $selectedRole = $roleRow;
        break;
    }
}

$registryModules = rp_load_registry_modules($conn);
usort($registryModules, static function ($a, $b) {
    return strcmp((string)($a['module_slug'] ?? ''), (string)($b['module_slug'] ?? ''));
});

$permissionMap = rp_load_permission_map($conn, $activeCompanyId, $selectedRoleId);
$allPermissionRow = $permissionMap['ALL'] ?? null;
$selectedIsSystem = is_array($selectedRole) && rp_role_is_system($selectedRole);
$matrixReadOnly = !$rpCanManage || $selectedIsSystem;

$permissionColumns = [
    'can_view' => 'View',
    'can_create' => 'Add',
    'can_edit' => 'Edit',
    'can_delete' => 'Delete',
    'can_import' => 'Import',
    'can_export' => 'Export',
];

$crud_title = 'Roles & Permissions';
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
    $crud_title = 'Roles & Permissions';
}
?>
<title><?= sanitize($crud_title) ?> - <?php echo sanitize($app_name ?? itm_ui_config_app_name($currentUiConfig)); ?></title>
    <?php echo itm_render_head_favicon_link($favicon_url ?? null); ?>
    <link rel="stylesheet" href="../../css/styles.css">
    <style>
        .rp-modal-backdrop {
            position:fixed; inset:0; background:rgba(0,0,0,.35); display:none;
            align-items:center; justify-content:center; z-index:1000; padding:16px;
        }
        .rp-modal-backdrop.is-open { display:flex; }
    </style>
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;gap:12px;flex-wrap:wrap;">
                <h1 style="margin:0;"><?= sanitize($crud_title) ?></h1>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <?php if ($rpCanManage && is_array($selectedRole) && !$selectedIsSystem): ?>
                        <button type="button" class="btn btn-sm" id="rp-open-edit-role" title="Edit role">✏️</button>
                    <?php endif; ?>
                    <?php if ($rpCanManage): ?>
                        <button type="button" class="btn btn-sm btn-primary" id="rp-open-add-role" title="Add role">➕</button>
                    <?php endif; ?>
                </div>
            </div>

            <div style="display:flex;gap:16px;align-items:flex-start;flex-wrap:wrap;">
                <div style="flex:0 0 280px;max-width:100%;">
                    <div class="card">
                        <div class="card-body">
                            <p style="margin-top:0;">Select a role to load its permission matrix. Roles are ordered by hierarchy.</p>
                            <?php if ($roles === []): ?>
                                <p>No roles found for this company.</p>
                            <?php else: ?>
                                <?php foreach ($roles as $roleRow): ?>
                                    <?php
                                    $roleId = (int)($roleRow['id'] ?? 0);
                                    $isSelected = $roleId === $selectedRoleId;
                                    $isSystem = rp_role_is_system($roleRow);
                                    $activeCount = (int)($roleRow['active_count'] ?? 0);
                                    ?>
                                    <a
                                        href="<?= $modulePathEsc ?>/index.php?role_id=<?= $roleId ?>"
                                        title="Select role"
                                        style="display:block;border:1px solid var(--border-color,#ddd);border-radius:8px;padding:12px 14px;margin-bottom:10px;text-decoration:none;color:inherit;<?= $isSelected ? 'border-color:var(--accent,#0366d6);box-shadow:0 0 0 1px var(--accent,#0366d6);' : '' ?>"
                                    >
                                        <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;">
                                            <strong><?= sanitize((string)($roleRow['name'] ?? '')) ?></strong>
                                            <span aria-hidden="true">›</span>
                                        </div>
                                        <div style="color:var(--text-secondary,#666);font-size:12px;margin-top:4px;" title="Active employees with this role"><?= (int)$activeCount ?> active</div>
                                        <?php if ($isSystem): ?>
                                            <div style="margin-top:6px;"><span class="badge">System</span></div>
                                        <?php endif; ?>
                                    </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div style="flex:1 1 520px;min-width:0;">
                    <?php if (!is_array($selectedRole)): ?>
                        <div class="card">
                            <div class="card-body">
                                <p style="margin:0;">Select a role to manage permissions.</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="card" style="margin-bottom:16px;">
                            <div class="card-body">
                                <p style="margin-top:0;">
                                    Configure module permissions for <strong><?= sanitize((string)$selectedRole['name']) ?></strong>.
                                    <?php if (!$rpCanManage): ?>
                                        Administrators can edit permissions; your view is read-only.
                                    <?php elseif ($selectedIsSystem): ?>
                                        The Admin role uses the ALL wildcard row and cannot be edited here.
                                    <?php else: ?>
                                        Use Check All / Uncheck All, then save. All registry modules are listed below, including inactive and system modules.
                                    <?php endif; ?>
                                </p>
                                <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                                    <button type="button" class="btn btn-sm" id="rp-check-all"<?= $matrixReadOnly ? ' disabled' : '' ?>>Check All</button>
                                    <button type="button" class="btn btn-sm" id="rp-uncheck-all"<?= $matrixReadOnly ? ' disabled' : '' ?>>Uncheck All</button>
                                    <?php if ($rpCanManage && !$matrixReadOnly): ?>
                                        <button type="button" class="btn btn-sm btn-primary" id="rp-save-permissions" data-role-id="<?= (int)$selectedRoleId ?>" title="Save permissions">💾</button>
                                    <?php endif; ?>
                                    <input type="text" id="rp-matrix-filter" class="form-control" style="max-width:280px;margin-left:auto;" placeholder="Filter modules...">
                                </div>
                                <div id="rp-save-status" style="min-height:18px;font-size:13px;margin-top:8px;color:var(--text-secondary,#666);"></div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-body" style="overflow:auto;">
                                <table
                                    class="table"
                                    id="rp-permission-matrix"
                                    data-rp-readonly="<?= $matrixReadOnly ? '1' : '0' ?>"
                                    data-itm-no-export-excel="1"
                                    data-itm-no-export-pdf="1"
                                    data-itm-no-import-excel="1"
                                >
                                    <thead>
                                    <tr>
                                        <th>Modules</th>
                                        <?php foreach ($permissionColumns as $columnKey => $columnLabel): ?>
                                            <th style="text-align:center;min-width:90px;"><?= sanitize($columnLabel) ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($registryModules as $registryRow): ?>
                                        <?php
                                        $moduleSlug = (string)($registryRow['module_slug'] ?? '');
                                        $moduleName = trim((string)($registryRow['module_name'] ?? $moduleSlug));
                                        if ($moduleName === '') {
                                            continue;
                                        }
                                        $isRegistryActive = (int)($registryRow['active'] ?? 0) === 1;
                                        $flags = rp_effective_flags($permissionMap, $moduleName, $allPermissionRow);
                                        $rowSearch = strtolower($moduleSlug . ' ' . $moduleName);
                                        ?>
                                        <tr data-rp-search="<?= sanitize($rowSearch) ?>">
                                            <td>
                                                <strong><?= sanitize($moduleName) ?></strong>
                                                <div style="color:var(--text-secondary);font-size:12px;"><a
                                                    href="../<?= sanitize($moduleSlug) ?>"
                                                    target="_blank"
                                                    rel="noopener noreferrer nofollow"
                                                    style="color:var(--accent); text-decoration:none;"
                                                ><?= sanitize($moduleSlug) ?></a></div>
                                                <?php if ((int)($registryRow['is_system_module'] ?? 0) === 1): ?>
                                                    <span class="badge">System</span>
                                                <?php endif; ?>
                                                <?php if (!$isRegistryActive): ?>
                                                    <span class="badge badge-danger">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <?php foreach ($permissionColumns as $columnKey => $columnLabel): ?>
                                                <?php $checked = (int)($flags[$columnKey] ?? 0) === 1; ?>
                                                <td style="text-align:center;">
                                                    <label class="itm-checkbox-control" title="<?= sanitize($columnLabel) ?>">
                                                        <input
                                                            type="checkbox"
                                                            class="rp-perm-toggle"
                                                            data-module-name="<?= sanitize($moduleName) ?>"
                                                            data-perm="<?= sanitize($columnKey) ?>"
                                                            <?= $checked ? 'checked' : '' ?>
                                                            <?= $matrixReadOnly ? 'disabled' : '' ?>
                                                        >
                                                        <span class="itm-check-indicator" aria-hidden="true"><?= $checked ? '✅' : '❌' ?></span>
                                                    </label>
                                                </td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($rpCanManage): ?>
<div class="rp-modal-backdrop" id="rp-add-role-modal" aria-hidden="true">
    <div class="card" style="width:100%;max-width:420px;">
        <div class="card-body">
            <h1 style="margin-top:0;font-size:1.25rem;" title="Add role">➕</h1>
            <form id="rp-add-role-form">
                <div class="form-group">
                    <label for="rp-add-role-name">Name</label>
                    <input type="text" id="rp-add-role-name" name="role_name" required maxlength="50">
                </div>
                <button type="submit" class="btn btn-primary" title="Create">➕</button>
                <button type="button" class="btn" id="rp-close-add-role" title="Cancel">🔙</button>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($rpCanManage && is_array($selectedRole) && !$selectedIsSystem): ?>
<div class="rp-modal-backdrop" id="rp-edit-role-modal" aria-hidden="true">
    <div class="card" style="width:100%;max-width:420px;">
        <div class="card-body">
            <h1 style="margin-top:0;font-size:1.25rem;" title="Edit role">✏️</h1>
            <form id="rp-edit-role-form">
                <input type="hidden" name="role_id" value="<?= (int)$selectedRoleId ?>">
                <div class="form-group">
                    <label for="rp-edit-role-name">Name</label>
                    <input type="text" id="rp-edit-role-name" name="role_name" required maxlength="50" value="<?= sanitize((string)$selectedRole['name']) ?>">
                </div>
                <button type="submit" class="btn btn-primary" title="Save">💾</button>
                <button type="button" class="btn" id="rp-close-edit-role" title="Cancel">🔙</button>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
window.ITM_RP_CSRF = <?= json_encode($csrfToken) ?>;
window.ITM_RP_ENDPOINT = <?= json_encode($modulePath . '/index.php') ?>;
(function () {
    function bindModal(openId, modalId, closeId) {
        var openBtn = document.getElementById(openId);
        var modal = document.getElementById(modalId);
        var closeBtn = document.getElementById(closeId);
        if (!openBtn || !modal) {
            return;
        }
        openBtn.addEventListener('click', function () {
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
        });
        function closeModal() {
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
        }
        if (closeBtn) {
            closeBtn.addEventListener('click', closeModal);
        }
        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                closeModal();
            }
        });
    }
    bindModal('rp-open-add-role', 'rp-add-role-modal', 'rp-close-add-role');
    bindModal('rp-open-edit-role', 'rp-edit-role-modal', 'rp-close-edit-role');
})();
</script>
<script src="../../js/roles-permissions-matrix.js"></script>
</body>
</html>
