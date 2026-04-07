<?php
/**
 * Settings Module
 * 
 * Central hub for system-wide configuration and maintenance.
 * Features:
 * - UI Customization: Button positioning and list pagination.
 * - Sidebar Management: Visibility toggles and drag-and-drop reordering (via buttons).
 * - Database Maintenance: One-click system table creation/verification.
 * - Backup & Recovery: Manual SQL dump generation, export, and import.
 **/

require '../../config/config.php';

$message = '';
$error = '';

// Human-friendly labels for UI positioning settings stored in the database.
$uiFieldLabels = [
    'table_actions_position' => 'Table Actions',
    'new_button_position' => '+ New Button',
    'export_buttons_position' => 'Export Buttons',
    'back_save_position' => 'Back & Save Buttons',
];

$uiFieldOptions = [
    'table_actions_position' => [
        'left_right' => 'Left & Right (default)',
        'left' => 'Left',
        'right' => 'Right',
    ],
    'new_button_position' => [
        'left_right' => 'Left & Right (default)',
        'left' => 'Left',
        'right' => 'Right',
    ],
    'export_buttons_position' => [
        'left_right' => 'Left & Right (default)',
        'left' => 'Left',
        'right' => 'Right',
        'bottom_right' => 'Bottom Right',
        'bottom_left' => 'Bottom Left',
        'top_right' => 'Top Right',
        'top_left' => 'Top Left',
        'top_bottom_right' => 'Top & Bottom Right',
        'top_bottom_left' => 'Top & Bottom Left',
    ],
    'back_save_position' => [
        'left_right' => 'Left & Right (default)',
        'left' => 'Left',
        'right' => 'Right',
        'bottom_right' => 'Bottom Right',
        'bottom_left' => 'Bottom Left',
        'top_right' => 'Top Right',
        'top_left' => 'Top Left',
        'top_bottom_right' => 'Top & Bottom Right',
        'top_bottom_left' => 'Top & Bottom Left',
    ],
];

$sidebarStructure = itm_sidebar_structure();
$equipmentTypeRows = [];
$hasEquipmentTypesCompanyId = itm_table_has_column($conn, 'equipment_types', 'company_id');
if ($hasEquipmentTypesCompanyId && $company_id > 0) {
    $equipmentTypeStmt = mysqli_prepare($conn, 'SELECT id, name FROM equipment_types WHERE company_id = ? ORDER BY name ASC');
    if ($equipmentTypeStmt) {
        mysqli_stmt_bind_param($equipmentTypeStmt, 'i', $company_id);
        mysqli_stmt_execute($equipmentTypeStmt);
        $equipmentTypeRes = mysqli_stmt_get_result($equipmentTypeStmt);
        while ($equipmentTypeRes && ($row = mysqli_fetch_assoc($equipmentTypeRes))) {
            $equipmentTypeRows[] = $row;
        }
        mysqli_stmt_close($equipmentTypeStmt);
    }
} else {
    $equipmentTypeRes = mysqli_query($conn, 'SELECT id, name FROM equipment_types ORDER BY name ASC');
    if ($equipmentTypeRes) {
        while ($row = mysqli_fetch_assoc($equipmentTypeRes)) {
            $equipmentTypeRows[] = $row;
        }
    }
}
$recordsPerPageOptions = [
    '25' => '25',
    '50' => '50',
    '100' => '100',
    'all' => 'ALL',
];

// Display flash messages from previous POST-redirect cycles.
if (isset($_SESSION['settings_flash_message'])) {
    $message = (string)$_SESSION['settings_flash_message'];
    unset($_SESSION['settings_flash_message']);
}

$csrfToken = itm_get_csrf_token();

/**
 * Generates a timestamped filename for SQL backups.
 */
function backup_filename() {
    return 'backup_' . date('d_M_Y') . '_' . date('His') . '.sql';
}

/**
 * Manually constructs a full SQL dump of the database.
 * 
 * Iterates through all tables, fetches CREATE TABLE statements, 
 * and exports all rows as INSERT statements. 
 * Note: Uses mysqli_real_escape_string for data safety during dump.
 */
function build_sql_backup($conn) {
    $dump = "-- IT Management SQL Backup\n";
    $dump .= '-- Generated at: ' . date('Y-m-d H:i:s') . " UTC\n";
    // Disable foreign keys during import to allow tables to be dropped/recreated in any order.
    $dump .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

    $tablesRes = mysqli_query($conn, 'SHOW TABLES');
    if (!$tablesRes) {
        return false;
    }

    while ($tableRow = mysqli_fetch_array($tablesRes)) {
        $table = $tableRow[0];

        $createRes = mysqli_query($conn, 'SHOW CREATE TABLE `' . $table . '`');
        $createRow = $createRes ? mysqli_fetch_assoc($createRes) : null;
        if (!$createRow || !isset($createRow['Create Table'])) {
            continue;
        }

        $dump .= '-- Table structure for `' . $table . "`\n";
        $dump .= 'DROP TABLE IF EXISTS `' . $table . "`;\n";
        $dump .= $createRow['Create Table'] . ";\n\n";

        $dataRes = mysqli_query($conn, 'SELECT * FROM `' . $table . '`');
        if (!$dataRes) {
            continue;
        }

        if (mysqli_num_rows($dataRes) > 0) {
            $dump .= '-- Data for `' . $table . "`\n";
        }

        while ($dataRow = mysqli_fetch_assoc($dataRes)) {
            $columns = array_map(static function ($col) {
                return '`' . $col . '`';
            }, array_keys($dataRow));

            $values = [];
            foreach ($dataRow as $value) {
                if ($value === null) {
                    $values[] = 'NULL';
                } else {
                    $values[] = "'" . mysqli_real_escape_string($conn, (string)$value) . "'";
                }
            }

            $dump .= 'INSERT INTO `' . $table . '` (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ");\n";
        }
        if (mysqli_num_rows($dataRes) > 0) {
            $dump .= "\n";
        }
    }

    $dump .= "SET FOREIGN_KEY_CHECKS=1;\n";
    return $dump;
}

/**
 * Parses and executes a large multi-statement SQL string.
 * 
 * Splits the input by lines and identifies statement boundaries using semicolons.
 * Filters out comments and empty lines to ensure only valid SQL reaches the server.
 */
function apply_sql_file($conn, $sqlText) {
    $lines = preg_split('/\R/', $sqlText);
    if ($lines === false) {
        return false;
    }

    $statement = '';
    foreach ($lines as $line) {
        $trimmed = trim($line);
        // Ignore empty lines and various comment styles.
        if ($trimmed === '' || strpos($trimmed, '--') === 0 || strpos($trimmed, '/*') === 0 || strpos($trimmed, '*/') === 0) {
            continue;
        }

        $statement .= $line . "\n";
        // If the line ends with a semicolon, we assume the statement is complete.
        if (substr(rtrim($line), -1) === ';') {
            if (!itm_run_query($conn, $statement)) {
                return false;
            }
            $statement = '';
        }
    }

    if (trim($statement) !== '') {
        if (!itm_run_query($conn, $statement)) {
            return false;
        }
    }

    return true;
}

// HANDLE CONFIGURATION UPDATES AND BACKUP ACTIONS
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    itm_require_post_csrf();
    $action = $_POST['action'] ?? '';

    // Action: Generate a new SQL dump file in the backups/ directory.
    if ($action === 'create_backup') {
        $filename = backup_filename();
        $fullPath = BACKUP_PATH . $filename;
        $dump = build_sql_backup($conn);

        if ($dump === false) {
            $error = 'Unable to generate SQL backup.';
        } elseif (file_put_contents($fullPath, $dump) === false) {
            $error = 'Unable to write backup file.';
        } else {
            $message = 'Backup created: ' . $filename;
        }
    }

    // Action: Remove an existing backup file.
    if ($action === 'delete_backup') {
        $file = basename((string)($_POST['file'] ?? ''));
        $target = BACKUP_PATH . $file;
        if ($file === '' || !is_file($target)) {
            $error = 'Backup file not found.';
        } elseif (!unlink($target)) {
            $error = 'Unable to delete backup.';
        } else {
            $message = 'Deleted backup: ' . $file;
        }
    }

    // Action: Restore database state from an uploaded .sql file.
    if ($action === 'import_backup') {
        if (!isset($_FILES['sql_file']) || (int)($_FILES['sql_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($_FILES['sql_file']['tmp_name'] ?? '')) {
            $error = 'Please upload a valid SQL file to import.';
        } else {
            $name = $_FILES['sql_file']['name'] ?? '';
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if ($ext !== 'sql') {
                $error = 'Only .sql files are supported.';
            } else {
                $content = file_get_contents($_FILES['sql_file']['tmp_name']);
                if ($content === false || !apply_sql_file($conn, $content)) {
                    $error = 'Import failed. Please verify SQL file syntax.';
                } else {
                    $message = 'Import completed successfully.';
                }
            }
        }
    }

    // Action: Persist UI preferences (button positions, sidebar order, system flags).
    if ($action === 'save_ui_config') {
        $newConfig = [];
        // Map form fields back to config keys.
        foreach (array_keys($uiFieldLabels) as $key) {
            $newConfig[$key] = $_POST[$key] ?? '';
        }
        $newConfig['enable_all_error_reporting'] = isset($_POST['enable_all_error_reporting']) ? 1 : 0;
        $newConfig['enable_audit_logs'] = isset($_POST['enable_audit_logs']) ? 1 : 0;
        $newConfig['equipment_type_sidebar_visibility'] = [];
        foreach ($equipmentTypeRows as $equipmentTypeRow) {
            $typeName = (string)($equipmentTypeRow['name'] ?? '');
            $itemId = itm_equipment_type_sidebar_item_id($typeName);
            if ($itemId === '') {
                continue;
            }
            $newConfig['equipment_type_sidebar_visibility'][$itemId] = isset($_POST['equipment_sidebar_visibility'][$itemId]) ? 1 : 0;
        }
        $newConfig['records_per_page'] = strtolower((string)($_POST['records_per_page'] ?? '25'));

        // Sidebar config is received as JSON strings from the hidden inputs populated by JS.
        $sidebarVisibilityInput = json_decode((string)($_POST['sidebar_visibility'] ?? ''), true);
        $sidebarMainOrderInput = json_decode((string)($_POST['sidebar_main_order'] ?? ''), true);
        $sidebarSubmenuOrderInput = json_decode((string)($_POST['sidebar_submenu_order'] ?? ''), true);

        $newConfig['sidebar_visibility'] = is_array($sidebarVisibilityInput) ? $sidebarVisibilityInput : [];
        $newConfig['sidebar_main_order'] = is_array($sidebarMainOrderInput) ? $sidebarMainOrderInput : [];
        $newConfig['sidebar_submenu_order'] = is_array($sidebarSubmenuOrderInput) ? $sidebarSubmenuOrderInput : [];

        if (!itm_save_ui_configuration($conn, $company_id, $newConfig)) {
            $error = 'Unable to save UI configuration.';
        } else {
            $_SESSION['settings_flash_message'] = 'UI configuration saved successfully.';
            header('Location: index.php?ui_saved=1');
            exit;
        }
    }

    // Action: Ensure required settings tables exist (useful for fresh installs).
    if ($action === 'create_system_tables') {
        if (!itm_ensure_ui_configuration_table($conn) || !itm_ensure_sidebar_layout_table($conn)) {
            $error = 'Unable to create required system tables.';
        } else {
            $message = 'System tables verified/created successfully.';
        }
    }
}

// HANDLE FILE EXPORT REQUESTS
if (isset($_GET['download'])) {
    $downloadFile = basename((string)$_GET['download']);
    $downloadPath = BACKUP_PATH . $downloadFile;
    if ($downloadFile !== '' && is_file($downloadPath)) {
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="' . $downloadFile . '"');
        header('Content-Length: ' . filesize($downloadPath));
        readfile($downloadPath);
        exit;
    }
}

// POPULATE BACKUP LIST FOR THE VIEW
$backupFiles = [];
if (is_dir(BACKUP_PATH)) {
    $files = scandir(BACKUP_PATH);
    if ($files !== false) {
        foreach ($files as $file) {
            if (substr($file, -4) === '.sql' && is_file(BACKUP_PATH . $file)) {
                $backupFiles[] = [
                    'name' => $file,
                    'size' => filesize(BACKUP_PATH . $file) ?: 0,
                    'modified' => filemtime(BACKUP_PATH . $file) ?: 0,
                ];
            }
        }
    }
}

usort($backupFiles, static function ($a, $b) {
    return $b['modified'] <=> $a['modified'];
});

// Initialize configuration for form pre-filling.
$currentUiConfig = itm_get_ui_configuration($conn, $company_id);
$currentRecordsPerPage = strtolower((string)($currentUiConfig['records_per_page'] ?? '25'));
// If the database has a custom pagination value not in our default array, add it to the dropdown.
if (!array_key_exists($currentRecordsPerPage, $recordsPerPageOptions) && ctype_digit($currentRecordsPerPage) && (int)$currentRecordsPerPage > 0) {
    $recordsPerPageOptions[$currentRecordsPerPage] = $currentRecordsPerPage;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings</title>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <div style="position:relative;display:flex;justify-content:flex-end;align-items:center;margin-bottom:20px;min-height:40px;">
                <h1 style="position:absolute;left:50%;transform:translateX(-50%);margin:0;text-align:center;">⚙️ Settings</h1>
            </div>
            <p style="margin-bottom:20px;">Options: configure UI button positions, create required SQL tables, and manage full SQL backups.</p>

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo sanitize($message); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo sanitize($error); ?></div>
            <?php endif; ?>


            <div class="card" style="margin-bottom:20px;">
                <div class="card-header"><h2>UI Configuration</h2></div>
                <div class="card-body">
                    <form method="post" id="ui-config-form">
                        <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                        <input type="hidden" name="action" value="save_ui_config">
                        <input type="hidden" id="sidebar_visibility" name="sidebar_visibility">
                        <input type="hidden" id="sidebar_main_order" name="sidebar_main_order">
                        <input type="hidden" id="sidebar_submenu_order" name="sidebar_submenu_order">

                        <div class="form-row">
                            <?php foreach ($uiFieldLabels as $field => $label): ?>
                                <div class="form-group">
                                    <label for="<?php echo sanitize($field); ?>"><?php echo sanitize($label); ?></label>
                                    <select id="<?php echo sanitize($field); ?>" name="<?php echo sanitize($field); ?>" required>
                                        <?php foreach ($uiFieldOptions[$field] as $value => $optionLabel): ?>
                                            <option value="<?php echo sanitize($value); ?>" <?php echo ($currentUiConfig[$field] ?? '') === $value ? 'selected' : ''; ?>>
                                                <?php echo sanitize($optionLabel); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endforeach; ?>
                        </div>


                        <div class="form-group" style="max-width:220px;margin-top:8px;">
                            <label for="records_per_page">Records per page</label>
                            <select id="records_per_page" name="records_per_page">
                                <?php foreach ($recordsPerPageOptions as $value => $label): ?>
                                    <?php $optionValue = strtolower((string)$value); ?>
                                    <option value="<?php echo sanitize($optionValue); ?>" <?php echo $currentRecordsPerPage === $optionValue ? 'selected' : ''; ?>>
                                        <?php echo sanitize($label); ?>
                                    </option>
                                <?php endforeach; ?>
                                <option value="__add_new__">➕</option>
                            </select>
                        </div>
                        <div class="itm-form-actions itm-align-left">
                            <button class="btn btn-primary" type="submit">Save Configuration</button>
                        </div>
                        <h3 style="margin-top:6px;">SideMenu (Sidebar)</h3>
                        <p class="form-hint" style="margin-bottom:10px;">Show/Hide items and use ↑ / ↓ to reorder main sections and submenu links (including moving submenu items between sections).</p>
                        <div class="sidebar-settings-list" id="sidebar-settings-list">
                            <?php foreach ($sidebarStructure as $section): ?>
                                <?php $sectionId = $section['id']; ?>
                                <div class="sidebar-setting-section" data-section-id="<?php echo sanitize($sectionId); ?>">
                                    <div class="sidebar-setting-row sidebar-setting-main" data-main-id="<?php echo sanitize($sectionId); ?>">
                                        <label class="role-flag-option">
                                            <input type="checkbox" class="sidebar-visible-toggle" data-target-id="<?php echo sanitize($sectionId); ?>" <?php echo (($currentUiConfig['sidebar_visibility'][$sectionId] ?? 1) === 1) ? 'checked' : ''; ?>>
                                            <span><?php echo sanitize($section['title']); ?></span>
                                        </label>
                                        <div>
                                            <button type="button" class="btn btn-sm sidebar-move-up">↑</button>
                                            <button type="button" class="btn btn-sm sidebar-move-down">↓</button>
                                        </div>
                                    </div>
                                    <div class="sidebar-setting-children">
                                        <?php foreach ($section['items'] as $item): ?>
                                            <?php $itemId = $item['id']; ?>
                                            <div class="sidebar-setting-row" data-item-id="<?php echo sanitize($itemId); ?>">
                                                <label class="role-flag-option">
                                                    <input type="checkbox" class="sidebar-visible-toggle" data-target-id="<?php echo sanitize($itemId); ?>" <?php echo (($currentUiConfig['sidebar_visibility'][$itemId] ?? 1) === 1) ? 'checked' : ''; ?>>
                                                    <a
                                                        href="<?php echo BASE_URL . ($item['href'] ?? ''); ?>"
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        style="text-decoration:none;color:inherit;"
                                                    >
                                                        <?php echo sanitize($item['label']); ?>
                                                    </a>
                                                </label>
                                                <div>
                                                    <button type="button" class="btn btn-sm sidebar-submove-up">↑</button>
                                                    <button type="button" class="btn btn-sm sidebar-submove-down">↓</button>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px;margin-top:16px;">
                            <div>
                                <h3 style="margin-top:0;">System</h3>
                                <div class="form-group">
                                    <label class="role-flag-option" for="enable_all_error_reporting">
                                        <input type="checkbox" id="enable_all_error_reporting" name="enable_all_error_reporting" value="1" <?php echo (($currentUiConfig['enable_all_error_reporting'] ?? 1) === 1) ? 'checked' : ''; ?>>
                                        <span>Enable all error reporting</span>
                                    </label>
                                </div>
                                <div class="form-group">
                                    <label class="role-flag-option" for="enable_audit_logs">
                                        <input type="checkbox" id="enable_audit_logs" name="enable_audit_logs" value="1" <?php echo (($currentUiConfig['enable_audit_logs'] ?? 1) === 1) ? 'checked' : ''; ?>>
                                        <span>Enable Audit Logs</span>
                                    </label>
                                </div>
                            </div>
                            <div>
                                <h3 style="margin-top:0;">Equipment Type Sidebar</h3>
                                <?php if (empty($equipmentTypeRows)): ?>
                                    <p class="form-hint">No records found in equipment_types.</p>
                                <?php else: ?>
                                    <?php foreach ($equipmentTypeRows as $equipmentTypeRow): ?>
                                        <?php
                                        $typeName = (string)($equipmentTypeRow['name'] ?? '');
                                        $itemId = itm_equipment_type_sidebar_item_id($typeName);
                                        if ($itemId === '') {
                                            continue;
                                        }
                                        $isChecked = (($currentUiConfig['equipment_type_sidebar_visibility'][$itemId] ?? 1) === 1);
                                        ?>
                                        <div class="form-group" style="margin-bottom:8px;">
                                            <label class="role-flag-option" for="equipment_type_<?php echo sanitize($itemId); ?>">
                                                <input
                                                    type="checkbox"
                                                    id="equipment_type_<?php echo sanitize($itemId); ?>"
                                                    name="equipment_sidebar_visibility[<?php echo sanitize($itemId); ?>]"
                                                    value="1"
                                                    <?php echo $isChecked ? 'checked' : ''; ?>
                                                >
                                                <span>Is <?php echo sanitize($typeName); ?></span>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="itm-form-actions itm-align-left">
                            <button class="btn btn-primary" type="submit">Save Configuration</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card" style="margin-bottom:20px;">
                <div class="card-header"><h2>SQL Database Setup</h2></div>
                <div class="card-body">
                    <p style="margin-bottom:10px;">Create/verify required system tables (idempotent).</p>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                        <input type="hidden" name="action" value="create_system_tables">
                        <button class="btn" type="submit">Create Missing Tables</button>
                    </form>
                </div>
            </div>

            <div class="card" style="margin-bottom:20px;">
                <div class="card-header"><h2>Create Full SQL Backup</h2></div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                        <input type="hidden" name="action" value="create_backup">
                        <button class="btn btn-primary" type="submit">Create Backup Now</button>
                    </form>
                </div>
            </div>

            <div class="card" style="margin-bottom:20px;">
                <div class="card-header"><h2>Import Backup</h2></div>
                <div class="card-body">
                    <form method="post" enctype="multipart/form-data" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap;">
                        <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                        <input type="hidden" name="action" value="import_backup">
                        <div class="form-group" style="margin:0;min-width:260px;">
                            <label for="sql_file">SQL File</label>
                            <input type="file" id="sql_file" name="sql_file" accept=".sql" required>
                        </div>
                        <button class="btn" type="submit">Import SQL</button>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h2>All Backups</h2></div>
                <div class="card-body" style="overflow:auto;">
                    <table>
                        <thead>
                        <tr>
                            <th>File Name</th>
                            <th>Size (KB)</th>
                            <th>Last Modified (UTC)</th>
                            <th>Options</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($backupFiles)): ?>
                            <tr><td colspan="4" style="text-align:center;">No backups yet. Example: backup_01_MAR_2026.sql</td></tr>
                        <?php else: ?>
                            <?php foreach ($backupFiles as $backup): ?>
                                <tr>
                                    <td><?php echo sanitize($backup['name']); ?></td>
                                    <td><?php echo number_format($backup['size'] / 1024, 2); ?></td>
                                    <td><?php echo gmdate('Y-m-d H:i:s', (int)$backup['modified']); ?></td>
                                    <td style="display:flex;gap:8px;flex-wrap:wrap;">
                                        <a class="btn btn-sm" href="index.php?download=<?php echo urlencode($backup['name']); ?>">Export</a>
                                        <form method="post" onsubmit="return confirm('Delete this backup file?');" style="display:inline;">
                                            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                                            <input type="hidden" name="action" value="delete_backup">
                                            <input type="hidden" name="file" value="<?php echo sanitize($backup['name']); ?>">
                                            <button class="btn btn-sm btn-danger" type="submit">🗑️</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    const form = document.getElementById('ui-config-form');
    if (!form) return;

    const recordsPerPageSelect = document.getElementById('records_per_page');
    const addOptionValue = '__add_new__';

    function isValidRecordsPerPageInput(raw) {
        const normalized = String(raw || '').trim().toLowerCase();
        if (normalized === 'all') {
            return 'all';
        }
        if (!/^\d+$/.test(normalized)) {
            return '';
        }

        const numeric = parseInt(normalized, 10);
        if (!Number.isFinite(numeric) || numeric <= 0 || numeric > 1000000) {
            return '';
        }

        return String(numeric);
    }

    function ensureRecordsPerPageOption(value) {
        if (!recordsPerPageSelect || !value || value === addOptionValue) return;

        const exists = Array.from(recordsPerPageSelect.options).find((option) => option.value === value);
        if (exists) {
            return;
        }

        const customOption = document.createElement('option');
        customOption.value = value;
        customOption.textContent = value === 'all' ? 'ALL' : value;

        const addOption = Array.from(recordsPerPageSelect.options).find((option) => option.value === addOptionValue);
        if (addOption) {
            recordsPerPageSelect.insertBefore(customOption, addOption);
        } else {
            recordsPerPageSelect.appendChild(customOption);
        }
    }

    if (recordsPerPageSelect) {
        recordsPerPageSelect.dataset.previousValue = recordsPerPageSelect.value;

        recordsPerPageSelect.addEventListener('focus', () => {
            if (recordsPerPageSelect.value !== addOptionValue) {
                recordsPerPageSelect.dataset.previousValue = recordsPerPageSelect.value;
            }
        });

        recordsPerPageSelect.addEventListener('change', () => {
            if (recordsPerPageSelect.value !== addOptionValue) {
                recordsPerPageSelect.dataset.previousValue = recordsPerPageSelect.value;
                return;
            }

            const input = window.prompt('Enter records per page (positive number) or "all":', recordsPerPageSelect.dataset.previousValue || '25');
            if (input === null) {
                recordsPerPageSelect.value = recordsPerPageSelect.dataset.previousValue || '25';
                return;
            }

            const normalized = isValidRecordsPerPageInput(input);
            if (!normalized) {
                window.alert('Please enter a positive number (e.g., 25) or "all".');
                recordsPerPageSelect.value = recordsPerPageSelect.dataset.previousValue || '25';
                return;
            }

            ensureRecordsPerPageOption(normalized);
            recordsPerPageSelect.value = normalized;
            recordsPerPageSelect.dataset.previousValue = normalized;
        });
    }

    const root = document.getElementById('sidebar-settings-list');
    const initialMainOrder = <?php echo json_encode($currentUiConfig['sidebar_main_order'] ?? itm_default_sidebar_main_order()); ?>;
    const initialSubmenuOrder = <?php echo json_encode($currentUiConfig['sidebar_submenu_order'] ?? itm_default_sidebar_submenu_order()); ?>;

    function moveRow(row, direction) {
        if (!row || !row.parentElement) return;
        const sibling = direction === 'up' ? row.previousElementSibling : row.nextElementSibling;
        if (!sibling) return;
        if (direction === 'up') {
            row.parentElement.insertBefore(row, sibling);
        } else {
            row.parentElement.insertBefore(sibling, row);
        }
    }

    function moveSubmenuRow(row, direction) {
        if (!row) return;
        const sibling = direction === 'up' ? row.previousElementSibling : row.nextElementSibling;
        if (sibling) {
            moveRow(row, direction);
            return;
        }

        const currentSection = row.closest('.sidebar-setting-section');
        if (!currentSection) return;

        const targetSection = direction === 'up'
            ? currentSection.previousElementSibling
            : currentSection.nextElementSibling;

        if (!targetSection) return;

        const targetChildren = targetSection.querySelector('.sidebar-setting-children');
        if (!targetChildren) return;

        if (direction === 'up') {
            targetChildren.appendChild(row);
        } else {
            targetChildren.insertBefore(row, targetChildren.firstElementChild || null);
        }
    }

    function applyInitialOrder() {
        const sectionsById = {};
        root.querySelectorAll('.sidebar-setting-section').forEach((section) => {
            sectionsById[section.dataset.sectionId] = section;
        });

        initialMainOrder.forEach((sectionId) => {
            const section = sectionsById[sectionId];
            if (section) {
                root.appendChild(section);
            }
        });

        const allRowsById = {};
        root.querySelectorAll('[data-item-id]').forEach((row) => {
            allRowsById[row.dataset.itemId] = row;
        });

        root.querySelectorAll('.sidebar-setting-section').forEach((section) => {
            const sectionId = section.dataset.sectionId;
            const childRoot = section.querySelector('.sidebar-setting-children');
            const order = initialSubmenuOrder[sectionId] || [];
            order.forEach((itemId) => {
                const row = allRowsById[itemId];
                if (row) childRoot.appendChild(row);
            });
        });
    }

    function collectAndSetHiddenFields() {
        const visibility = {};
        root.querySelectorAll('.sidebar-visible-toggle').forEach((toggle) => {
            visibility[toggle.dataset.targetId] = toggle.checked ? 1 : 0;
        });

        const mainOrder = [];
        const submenuOrder = {};
        root.querySelectorAll('.sidebar-setting-section').forEach((section) => {
            const sectionId = section.dataset.sectionId;
            mainOrder.push(sectionId);
            submenuOrder[sectionId] = [];
            section.querySelectorAll('.sidebar-setting-children [data-item-id]').forEach((itemRow) => {
                submenuOrder[sectionId].push(itemRow.dataset.itemId);
            });
        });

        document.getElementById('sidebar_visibility').value = JSON.stringify(visibility);
        document.getElementById('sidebar_main_order').value = JSON.stringify(mainOrder);
        document.getElementById('sidebar_submenu_order').value = JSON.stringify(submenuOrder);
    }

    root.addEventListener('click', (event) => {
        const up = event.target.closest('.sidebar-move-up');
        const down = event.target.closest('.sidebar-move-down');
        const sup = event.target.closest('.sidebar-submove-up');
        const sdown = event.target.closest('.sidebar-submove-down');

        if (up) {
            moveRow(up.closest('.sidebar-setting-section'), 'up');
        }
        if (down) {
            moveRow(down.closest('.sidebar-setting-section'), 'down');
        }
        if (sup) {
            moveSubmenuRow(sup.closest('[data-item-id]'), 'up');
        }
        if (sdown) {
            moveSubmenuRow(sdown.closest('[data-item-id]'), 'down');
        }

        if (up || down || sup || sdown) {
            collectAndSetHiddenFields();
        }
    });

    root.addEventListener('change', (event) => {
        if (event.target.matches('.sidebar-visible-toggle')) {
            collectAndSetHiddenFields();
        }
    });

    form.addEventListener('submit', collectAndSetHiddenFields);

    applyInitialOrder();
    collectAndSetHiddenFields();
})();
</script>
<?php if (isset($_GET['ui_saved']) && $_GET['ui_saved'] === '1'): ?>
<script>
window.setTimeout(function () {
    window.location.href = 'index.php';
}, 150);
</script>
<?php endif; ?>
<script src="../../js/theme.js"></script>
</body>
</html>
