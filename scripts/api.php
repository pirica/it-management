<?php
/**
 * IT Management System - API Documentation (project-aligned)
 **/

declare(strict_types=1);

// Why: Programmatic clients need a lightweight JSON probe without loading the HTML catalogue.
if (isset($_GET['rate_limit']) && (string)$_GET['rate_limit'] === '1') {
    require_once dirname(__DIR__) . '/config/config.php';
    if (!isset($conn) || !($conn instanceof mysqli)) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Database connection failed.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    itm_api_handle_rate_limit_probe_request($conn);
}

header('Content-Type: text/html; charset=UTF-8');

$itmDocGeneratedAt = gmdate('Y-m-d H:i:s') . ' UTC';
$itmRootPath = realpath(__DIR__ . '/..') ?: dirname(__DIR__);

function itmDocEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function itmDocCollectModuleImportEndpoints(string $rootPath): array
{
    $endpoints = [];
    $pattern = $rootPath . '/modules/*/index.php';
    foreach (glob($pattern) ?: [] as $indexFile) {
        $content = @file_get_contents($indexFile);
        if (!is_string($content) || $content === '') {
            continue;
        }

        $hasImportRowsMarker = strpos($content, 'import_excel_rows') !== false;
        $hasInlineImportHandler = $hasImportRowsMarker
            && strpos($content, 'Content-Type: application/json') !== false;
        $hasSharedImportHandler = strpos($content, 'itm_handle_json_table_import(') !== false;
        $hasGuardedSharedImportHandler = $hasSharedImportHandler && $hasImportRowsMarker;
        if (!$hasInlineImportHandler && !$hasSharedImportHandler) {
            continue;
        }

        $handlerType = 'shared';
        if ($hasInlineImportHandler) {
            $handlerType = 'inline';
        } elseif ($hasGuardedSharedImportHandler) {
            $handlerType = 'shared_guarded';
        }

        $module = basename(dirname($indexFile));
        $endpoints[] = [
            'module' => $module,
            'path' => 'modules/' . $module . '/index.php',
            'method' => 'POST',
            'handler_type' => $handlerType,
            'purpose' => 'Excel/CSV save-to-database import endpoint used by js/table-tools.js',
        ];
    }

    foreach (glob($rootPath . '/modules/*/list_all.php') ?: [] as $listAllFile) {
        $content = @file_get_contents($listAllFile);
        if (!is_string($content) || strpos($content, 'import_excel_rows') === false) {
            continue;
        }
        $module = basename(dirname($listAllFile));
        $path = 'modules/' . $module . '/list_all.php';
        $exists = false;
        foreach ($endpoints as $endpoint) {
            if (($endpoint['path'] ?? '') === $path) {
                $exists = true;
                break;
            }
        }
        if (!$exists) {
            $endpoints[] = [
                'module' => $module,
                'path' => $path,
                'method' => 'POST',
                'handler_type' => 'list_all',
                'purpose' => 'Excel/CSV save-to-database import on list_all.php (table-tools.js)',
            ];
        }
    }

    usort($endpoints, static function (array $a, array $b): int {
        return strcmp($a['module'], $b['module']);
    });

    return $endpoints;
}

function itmDocCollectModulesWithoutImportEndpoint(string $rootPath, array $importEndpoints): array
{
    $hasImport = [];
    foreach ($importEndpoints as $endpoint) {
        $moduleName = (string)($endpoint['module'] ?? '');
        if ($moduleName !== '') {
            $hasImport[$moduleName] = true;
        }
    }

    $modules = [];
    foreach (glob($rootPath . '/modules/*', GLOB_ONLYDIR) ?: [] as $moduleDir) {
        $moduleName = basename($moduleDir);
        $indexFile = $moduleDir . '/index.php';
        if (!is_file($indexFile)) {
            continue;
        }
        if (!isset($hasImport[$moduleName])) {
            $modules[] = $moduleName;
        }
    }

    sort($modules, SORT_NATURAL | SORT_FLAG_CASE);
    return $modules;
}

function itmDocCollectIdfApiEndpoints(string $rootPath): array
{
    $purposes = [
        'cable_color_add.php' => 'Create tenant cable_colors row for IDF link UI.',
        'link_create.php' => 'Create bidirectional idf_links; sync idf_ports and switch_ports.',
        'link_delete.php' => 'Delete link; reset ports to Unknown/Gray defaults.',
        'port_update.php' => 'Update idf_ports; mirror to switch_ports and linked peers.',
        'position_copy.php' => 'Copy rack position, device metadata, and ports.',
        'position_delete.php' => 'Delete rack position and dependent ports/links.',
        'position_get.php' => 'Load position metadata and effective port counts.',
        'position_move.php' => 'Move position up/down within an IDF.',
        'position_reorder.php' => 'Batch reorder positions by ID array.',
        'position_remove_slot.php' => 'Remove empty rack slot and compact positions.',
        'position_save.php' => 'Create/update idf_positions; seed or prune ports; sync equipment.',
        'ports_regen.php' => 'Regenerate idf_ports from position capacity.',
        'ports_sync.php' => 'Ensure idf_ports rows exist for a position.',
        'switch_port_row.php' => 'Fetch single switch_ports row for modal hydration.',
        'switch_ports_by_equipment.php' => 'List switch_ports for equipment link UI.',
        'switch_status_add.php' => 'Create tenant switch_status row if missing.',
    ];

    $endpoints = [];
    $pattern = $rootPath . '/modules/idfs/api/*.php';
    foreach (glob($pattern) ?: [] as $apiFile) {
        $name = basename($apiFile);
        if ($name === '_bootstrap.php' || $name === 'index.php') {
            continue;
        }

        $endpoints[] = [
            'name' => $name,
            'path' => 'modules/idfs/api/' . $name,
            'method' => 'POST',
            'purpose' => $purposes[$name] ?? 'IDF JSON handler (POST, application/json, csrf_token).',
        ];
    }

    usort($endpoints, static function (array $a, array $b): int {
        return strcmp($a['name'], $b['name']);
    });

    return $endpoints;
}

/**
 * Why: Explorer actions are parsed from the live router so docs stay aligned with modules/explorer/api.php.
 */
function itmDocCollectExplorerApiActions(string $rootPath): array
{
    $catalog = [
        'list' => [
            'method' => 'POST',
            'params' => 'action, path, csrf_token',
            'response' => '{"items":[{"name","type","size","mtime"},...]}',
            'purpose' => 'List folder contents for the current scoped path.',
        ],
        'open' => [
            'method' => 'POST',
            'params' => 'action, path, item, csrf_token',
            'response' => '{"preview":"image|pdf|text|unsupported",...}',
            'purpose' => 'Preview metadata for a file (routes images/PDFs through file.php).',
        ],
        'createFolder' => [
            'method' => 'POST',
            'params' => 'action, path, name, csrf_token',
            'response' => '{"ok":1} or {"ok":0,"error":"..."}',
            'purpose' => 'Create a folder (blocked in Home, Private, and Departments roots).',
        ],
        'delete' => [
            'method' => 'POST',
            'params' => 'action, path, item, csrf_token',
            'response' => '{"ok":1} or {"ok":0,"error":"..."}',
            'purpose' => 'Soft-delete item to Trash (rename into files/{company_id}/Trash/).',
        ],
        'rename' => [
            'method' => 'POST',
            'params' => 'action, path, item, name, csrf_token',
            'response' => '{"ok":1} or {"ok":0,"error":"..."}',
            'purpose' => 'Rename file or folder in the current directory.',
        ],
        'copy' => [
            'method' => 'POST',
            'params' => 'action, src_path, dest, item, csrf_token',
            'response' => '{"ok":1} or {"ok":0,"error":"..."}',
            'purpose' => 'Copy file/folder; UI paste-via-clipboard uses this action.',
        ],
        'move' => [
            'method' => 'POST',
            'params' => 'action, src_path, dest, item, csrf_token',
            'response' => '{"ok":1} or {"ok":0,"error":"..."}',
            'purpose' => 'Move file/folder; UI cut-paste uses this action.',
        ],
        'zip' => [
            'method' => 'POST',
            'params' => 'action, path, item, csrf_token',
            'response' => '{"ok":1} or {"ok":0,"error":"..."}',
            'purpose' => 'Create a .zip archive beside the selected item.',
        ],
        'unzip' => [
            'method' => 'POST',
            'params' => 'action, path, item, csrf_token',
            'response' => '{"ok":1} or {"ok":0}',
            'purpose' => 'Extract a .zip archive into the current folder.',
        ],
        'upload' => [
            'method' => 'POST',
            'params' => 'action, path, files[] (multipart), csrf_token',
            'response' => '{"ok":1} or {"ok":0,"error":"..."}',
            'purpose' => 'Upload one or more files; blocks executables and dotfiles.',
        ],
        'createYear' => [
            'method' => 'POST',
            'params' => 'action, path, csrf_token',
            'response' => '{"ok":1}',
            'purpose' => 'Create year folders (previous, current, next).',
        ],
        'createMonths' => [
            'method' => 'POST',
            'params' => 'action, path, csrf_token',
            'response' => '{"ok":1}',
            'purpose' => 'Create 12 month folders (01 - January … 12 - December).',
        ],
        'createDays' => [
            'method' => 'POST',
            'params' => 'action, path, csrf_token',
            'response' => '{"ok":1}',
            'purpose' => 'Create day-of-month folders (01–28/29/30/31) for the resolved month.',
        ],
        'createYearMonthDay' => [
            'method' => 'POST',
            'params' => 'action, path, csrf_token',
            'response' => '{"ok":1}',
            'purpose' => 'Create today\'s year/month/day folder chain in one call.',
        ],
        'listRecycle' => [
            'method' => 'POST',
            'params' => 'action, csrf_token',
            'response' => '{"items":[{"name","type"},...]}',
            'purpose' => 'List Trash items visible to the current user ACL.',
        ],
        'restore' => [
            'method' => 'POST',
            'params' => 'action, item, csrf_token',
            'response' => '{"ok":1} or {"ok":0,"error":"..."}',
            'purpose' => 'Restore a Trash item back to its original live path.',
        ],
        'emptyRecycle' => [
            'method' => 'POST',
            'params' => 'action, csrf_token',
            'response' => '{"ok":1}',
            'purpose' => 'Permanently delete Trash items the user is allowed to see.',
        ],
    ];

    $apiFile = $rootPath . '/modules/explorer/api.php';
    $content = @file_get_contents($apiFile);
    if (!is_string($content)) {
        return array_values(array_map(static function (string $action, array $meta): array {
            return array_merge(['action' => $action], $meta);
        }, array_keys($catalog), $catalog));
    }

    if (!preg_match_all('/case\s+"([^"]+)":/', $content, $matches)) {
        return [];
    }

    $actions = [];
    foreach ($matches[1] as $action) {
        if (!isset($catalog[$action])) {
            $actions[] = [
                'action' => $action,
                'method' => 'POST',
                'params' => 'action, csrf_token, …',
                'response' => 'JSON',
                'purpose' => 'Undocumented Explorer action — update scripts/api.php catalog.',
            ];
            continue;
        }
        $actions[] = array_merge(['action' => $action], $catalog[$action]);
    }

    return $actions;
}

function itmDocExplorerDownloadEndpoints(): array
{
    return [
        [
            'method' => 'GET',
            'path' => 'modules/explorer/api.php?downloadZip=1&path={relative_path}',
            'params' => 'downloadZip, path (session cookie)',
            'response' => 'application/zip binary (folder.zip)',
            'purpose' => 'Download folder as ZIP; blocked for Home, Private, Departments, and Trash roots.',
        ],
        [
            'method' => 'GET',
            'path' => 'modules/explorer/file.php?path={relative_path}',
            'params' => 'path (session cookie)',
            'response' => 'File bytes (image, PDF, text, or attachment)',
            'purpose' => 'Serve authorised files from files/{company_id}/ after ACL checks (deny_http bypass).',
        ],
    ];
}

function itmDocProjectJsonEndpoints(): array
{
    return [
        [
            'group' => 'Shared includes',
            'method' => 'POST',
            'path' => 'includes/get_ports.php',
            'params' => 'switch_id, csrf_token',
            'purpose' => 'Fetch/seed switch_ports and lookup metadata for equipment port manager.',
        ],
        [
            'group' => 'Shared includes',
            'method' => 'POST',
            'path' => 'includes/update_port.php',
            'params' => 'id, switch_id, csrf_token, port fields',
            'purpose' => 'Update switch_ports; sync linked idf_ports when To IDF changes.',
        ],
        [
            'group' => 'Quick-add selects',
            'method' => 'POST',
            'path' => 'modules/select_options_api.php',
            'params' => 'table, id_col, label_col, new_value, company_scoped, extra_fields, csrf_token',
            'purpose' => 'Create reference row from js/select-add-option.js; table whitelist enforced.',
        ],
        [
            'group' => 'IDF helpers',
            'method' => 'GET',
            'path' => 'modules/idfs/index.php?refresh_select_options=rack|location',
            'params' => 'refresh_select_options',
            'purpose' => 'Refresh rack/location select options JSON.',
        ],
        [
            'group' => 'Org chart',
            'method' => 'POST',
            'path' => 'modules/org_chart/index.php',
            'params' => 'action=update_hierarchy, employee_id, reports_to, csrf_token',
            'purpose' => 'Update employees.reports_to with cycle detection (drag-and-drop).',
        ],
        [
            'group' => 'Floor designer',
            'method' => 'POST',
            'path' => 'modules/floor_designer/index.php',
            'params' => 'ajax_action, csrf_token, action-specific fields',
            'purpose' => 'AJAX: save_point, update_point_pos, update_comment_pos, delete_point, get_switch_ports, save_as_floor_plan.',
        ],
        [
            'group' => 'Rack planner',
            'method' => 'POST',
            'path' => 'modules/rack_planner/index.php',
            'params' => 'ajax_update_layout=1, id, rack_units, layout_json, csrf_token',
            'purpose' => 'Autosave rack layout JSON; sync catalog/equipment/idf_unlinked prices to source tables.',
        ],
        [
            'group' => 'Contacts',
            'method' => 'POST',
            'path' => 'modules/contacts/api/inline_edit.php',
            'params' => 'type, id, field, value, csrf_token',
            'purpose' => 'Inline edit whitelisted contact fields (JSON).',
        ],
        [
            'group' => 'IPAM',
            'method' => 'POST',
            'path' => 'modules/ip_subnets/index.php',
            'params' => 'ping_ip_check | network_discovery_scan | network_discovery_import, csrf_token',
            'purpose' => 'Ping/port check and network discovery scan/import.',
        ],
        [
            'group' => 'IPAM',
            'method' => 'POST',
            'path' => 'modules/ip_addresses/index.php',
            'params' => 'inline_notes_save, id, notes, csrf_token',
            'purpose' => 'Inline notes save on IP address rows.',
        ],
        [
            'group' => 'Grid inline edit',
            'method' => 'POST',
            'path' => 'modules/visitors_access_log/index.php',
            'params' => 'ajax_inline_edit=1 | action_timestamp=1, csrf_token',
            'purpose' => 'Inline cell edit (today only) or stamp in/out times.',
        ],
        [
            'group' => 'Grid inline edit',
            'method' => 'POST',
            'path' => 'modules/backup_tape_log/index.php',
            'params' => 'ajax_inline_edit=1 | action_timestamp=1, csrf_token',
            'purpose' => 'Inline grid edit (today only) or insert/return timestamps.',
        ],
        [
            'group' => 'Alerts',
            'method' => 'POST',
            'path' => 'modules/alerts/index.php',
            'params' => 'import_excel_rows or ics_file upload, csrf_token',
            'purpose' => 'JSON table import or ICS calendar import.',
        ],
        [
            'group' => 'Employee assignment',
            'method' => 'POST',
            'path' => 'modules/employee_assignment_history/index.php',
            'params' => 'import_excel_rows, csrf_token',
            'purpose' => 'Save-to-database import for assignment history.',
        ],
        [
            'group' => 'Patches',
            'method' => 'POST',
            'path' => 'modules/patches_updates/list_all.php',
            'params' => 'import_excel_rows, csrf_token',
            'purpose' => 'Save-to-database import on list_all view.',
        ],
        [
            'group' => 'Floor designer points',
            'method' => 'POST',
            'path' => 'modules/floor_designer_points/index.php',
            'params' => 'import_excel_rows, csrf_token',
            'purpose' => 'Save-to-database import for floor designer points.',
        ],
        [
            'group' => 'Scripts / tooling',
            'method' => 'POST',
            'path' => 'scripts/test_sql_injection.php',
            'params' => 'audit payload',
            'purpose' => 'Security test helper during SQLi audits.',
        ],
        [
            'group' => 'Scripts / tooling',
            'method' => 'GET',
            'path' => 'scripts/DBdesign.php?format=html|mermaid|json',
            'params' => 'format',
            'purpose' => 'Database ER diagram (HTML viewer, Mermaid, or JSON).',
        ],
        [
            'group' => 'Scripts / tooling',
            'method' => 'GET',
            'path' => 'scripts/compare_database_sql_modules.php',
            'params' => '—',
            'purpose' => 'Compare database.sql schema with module definitions (JSON).',
        ],
        [
            'group' => 'Scripts / tooling',
            'method' => 'GET',
            'path' => 'scripts/list_modules_not_on_sidebar.php',
            'params' => '—',
            'purpose' => 'List modules missing from sidebar (JSON).',
        ],
        [
            'group' => 'Scripts / tooling',
            'method' => 'POST',
            'path' => 'scripts/check_audit_logs_coverage.php',
            'params' => 'ajax=1',
            'purpose' => 'Static audit-log coverage report (JSON when ajax=1).',
        ],
        [
            'group' => 'Scripts / tooling',
            'method' => 'POST',
            'path' => 'scripts/module_browser_qa_runner.php',
            'params' => 'action, credentials, csrf_token',
            'purpose' => 'Full-module browser QA automation runner.',
        ],
    ];
}

function itmDocPasswordsApiActions(): array
{
    return [
        ['action' => 'list_folders', 'params' => 'csrf_token', 'purpose' => 'Folder tree for vault UI.'],
        ['action' => 'save_folder', 'params' => 'id?, name, parent_id?, csrf_token', 'purpose' => 'Create or update password folder.'],
        ['action' => 'delete_folder', 'params' => 'id, csrf_token', 'purpose' => 'Delete password folder.'],
        ['action' => 'list_entries', 'params' => 'folder_id?, search?, csrf_token', 'purpose' => 'List decrypted entries (vault unlocked).'],
        ['action' => 'get_entry', 'params' => 'id, csrf_token', 'purpose' => 'Fetch single decrypted entry.'],
        ['action' => 'save_entry', 'params' => 'id?, folder_id, account, login_name, password, website, comments, csrf_token', 'purpose' => 'Create/update encrypted entry.'],
        ['action' => 'delete_entry', 'params' => 'id, csrf_token', 'purpose' => 'Delete password entry.'],
        ['action' => 'import_rows', 'params' => 'folder_id, rows (JSON string), csrf_token', 'purpose' => 'Spreadsheet row import into vault.'],
        ['action' => 'import_csv', 'params' => 'target_folder_id, csv_file (multipart), csrf_token', 'purpose' => 'CSV/KeePass import into vault.'],
    ];
}

function itmDocNotesAjaxActions(): array
{
    return [
        ['action' => 'quick_add', 'params' => 'title, body, tags, csrf_token', 'purpose' => 'Create note from quick-add UI.'],
        ['action' => 'toggle_pinned', 'params' => 'id, is_pinned, csrf_token', 'purpose' => 'Toggle pinned flag.'],
        ['action' => 'toggle_archived', 'params' => 'id, is_archived, csrf_token', 'purpose' => 'Toggle archived flag.'],
        ['action' => 'toggle_important', 'params' => 'id, is_important, csrf_token', 'purpose' => 'Toggle important flag.'],
        ['action' => 'restore', 'params' => 'id, csrf_token', 'purpose' => 'Restore archived note.'],
        ['action' => 'single_delete', 'params' => 'id, csrf_token', 'purpose' => 'Delete single note.'],
        ['action' => 'rename_tag', 'params' => 'old_name, new_name, csrf_token', 'purpose' => 'Rename note tag.'],
        ['action' => 'delete_tag', 'params' => 'name, csrf_token', 'purpose' => 'Delete note tag.'],
        ['action' => 'add_tag', 'params' => 'name, csrf_token', 'purpose' => 'Add note tag.'],
        ['action' => 'download_all_images', 'params' => 'id, csrf_token', 'purpose' => 'Zip note images; returns {ok, zip_url}.'],
    ];
}

function itmDocTodoAjaxActions(): array
{
    return [
        ['action' => 'quick_add', 'params' => 'title, due_date, reminder_at, repeat_pattern, category_id[], department_id[], assigned_to_user_id[], importance, csrf_token', 'purpose' => 'Create task from inline row.'],
        ['action' => 'toggle_completed', 'params' => 'id, completed, csrf_token', 'purpose' => 'Toggle task completion.'],
        ['action' => 'toggle_importance', 'params' => 'id, importance, csrf_token', 'purpose' => 'Toggle task importance.'],
    ];
}

/**
 * Why: api-examples/*.php is the canonical integration sample set; scan every file so docs never omit a script.
 */
function itmDocApiRateLimitTiers(): array
{
    return [
        ['tier' => 'Free', 'hourly_limit' => 'No limit'],
        ['tier' => 'Basic', 'hourly_limit' => 300],
        ['tier' => 'Pro', 'hourly_limit' => 1000],
        ['tier' => 'Enterprise', 'hourly_limit' => 10000],
    ];
}

function itmDocCollectApiExamples(string $rootPath): array
{
    $categoryByFile = [
        'authenticate.php' => 'Authentication',
        'sessionCookie.php' => 'Authentication',
        'csrfToken.php' => 'Authentication',
        'equipment.php' => 'JSON import',
        'employees.php' => 'JSON import',
        'tickets.php' => 'JSON import',
        'catalogs.php' => 'JSON import',
        'events.php' => 'JSON import',
        'catalog_delete.php' => 'CRUD',
        'equipment_edit.php' => 'CRUD',
        'ticket_archive.php' => 'Action (form POST)',
        'employees_singleview.php' => 'List / read (HTML)',
        'tickets_listall_open.php' => 'List / read (HTML)',
        'catalogs_listall_active.php' => 'List / read (HTML)',
    ];

    $examples = [];
    $pattern = rtrim($rootPath, '/\\') . '/api-examples/*.php';
    foreach (glob($pattern) ?: [] as $filePath) {
        $basename = basename($filePath);
        $content = @file_get_contents($filePath);
        $title = $basename;
        $purpose = 'Standalone CLI/browser integration example.';

        if (is_string($content)) {
            if (preg_match('/API Example:\s*([^\n\*]+)/', $content, $titleMatch)) {
                $title = trim($titleMatch[1]);
            }
            if (preg_match('/API Example:[^\n\*]+\n(?: \*[^\n]*\n)*? \*\s*\n \*\s*([^\n\*]+)/', $content, $purposeMatch)) {
                $purpose = trim($purposeMatch[1]);
            } elseif (preg_match('/API Example:[^\n\*]+\n \*\s*\n \*\s*([^\n\*]+)/', $content, $purposeMatch)) {
                $purpose = trim($purposeMatch[1]);
            }
        }

        $examples[] = [
            'file' => 'api-examples/' . $basename,
            'title' => $title,
            'category' => $categoryByFile[$basename] ?? 'Integration',
            'purpose' => $purpose,
        ];
    }

    usort($examples, static function (array $a, array $b): int {
        return strcmp((string)$a['file'], (string)$b['file']);
    });

    return $examples;
}

$moduleImportEndpoints = itmDocCollectModuleImportEndpoints($itmRootPath);
$modulesWithoutImportEndpoint = itmDocCollectModulesWithoutImportEndpoint($itmRootPath, $moduleImportEndpoints);
$idfApiEndpoints = itmDocCollectIdfApiEndpoints($itmRootPath);
$explorerApiActions = itmDocCollectExplorerApiActions($itmRootPath);
$explorerDownloadEndpoints = itmDocExplorerDownloadEndpoints();
$projectJsonEndpoints = itmDocProjectJsonEndpoints();
$passwordsApiActions = itmDocPasswordsApiActions();
$notesAjaxActions = itmDocNotesAjaxActions();
$todoAjaxActions = itmDocTodoAjaxActions();
$apiExamples = itmDocCollectApiExamples($itmRootPath);
$apiRateLimitTiers = itmDocApiRateLimitTiers();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IT Management API Documentation</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f6f8fa; color: #1f2328; margin: 0; }
        .container { max-width: 1200px; margin: 0 auto; padding: 24px; }
        .card { background: #fff; border: 1px solid #d0d7de; border-radius: 8px; padding: 18px; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #d0d7de; padding: 8px; text-align: left; vertical-align: top; }
        th { background: #f6f8fa; }
        code, pre { background: #f0f3f6; border-radius: 6px; }
        pre { padding: 12px; overflow-x: auto; }
        .muted { color: #57606a; font-size: .9rem; }
        h3 { margin-top: 1.25rem; margin-bottom: .5rem; }
    </style>
</head>
<body>
<?php require_once __DIR__ . '/lib/script_browser_nav.php'; ?>
<div class="container">
    <?php itm_script_browser_nav_echo('../'); ?>
    <div class="card">
        <h1>IT Management API Documentation</h1>
        <p>Comprehensive documentation for <strong>JSON and AJAX endpoints implemented in this repository</strong>, including authentication, validation, error handling, and curl examples.</p>
        <p class="muted">Generated: <?= itmDocEscape($itmDocGeneratedAt); ?></p>
    </div>

    <div class="card">
        <h2>Overview</h2>
        <ul>
            <li>Session-based authentication and CSRF — not JWT bearer tokens.</li>
            <li>Most JSON APIs are internal AJAX endpoints used by module pages.</li>
            <li>Multi-tenancy is enforced via session <code>company_id</code>; do <strong>not</strong> send <code>company_id</code> in client payloads.</li>
            <li>Explorer storage is under <code>files/{company_id}/</code> with <code>deny_http</code>; authorised downloads use <code>modules/explorer/file.php</code>.</li>
            <li>Passwords vault endpoints require <code>$_SESSION['vault_key']</code> after unlock.</li>
            <li>Module Excel imports use <code>import_excel_rows</code> at <code>modules/&lt;module&gt;/index.php</code> (auto-detected below).</li>
            <li>Optional API key auth uses per-user rows in <code>ui_configuration</code> with tier-based hourly rate limits (see below).</li>
        </ul>
    </div>

    <div class="card">
        <h2>API key authentication and rate limits</h2>
        <p>Each signed-in user can store an integration key on <strong>Settings → API Access</strong> (<code>ui_configuration.api_key</code>). Keys are scoped to <code>company_id</code> + <code>user_id</code>. Tier caps apply per rolling hour (<code>rate_limit_window_start</code> + <code>rate_limit_request_count</code>).</p>
        <table>
            <thead><tr><th>Tier</th><th>Hourly limit</th></tr></thead>
            <tbody>
            <?php foreach ($apiRateLimitTiers as $tierRow): ?>
                <tr>
                    <td><?= itmDocEscape((string)($tierRow['tier'] ?? '')); ?></td>
                    <td><?= is_numeric($tierRow['hourly_limit'] ?? null) ? (int)$tierRow['hourly_limit'] : itmDocEscape((string)($tierRow['hourly_limit'] ?? '')); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p>Send the key as header <code>X-API-Key: &lt;key&gt;</code> or query/body <code>api_key</code>. Browser-friendly probe: <code>scripts/api.php?rate_limit=1&amp;api_key=&lt;key&gt;</code>. In PHP handlers, call <code>itm_api_enforce_rate_limit_or_exit($conn)</code> from <code>includes/itm_api_rate_limit.php</code> before business logic.</p>
<pre><code># Probe current quota (does not consume a request)
curl "http://localhost/it-management/scripts/api.php?rate_limit=1&amp;api_key=&lt;api_key&gt;"

# Or with header
curl -H "X-API-Key: &lt;api_key&gt;" \
  "http://localhost/it-management/scripts/api.php?rate_limit=1"

# Example success payload (Free tier)
{"ok":true,"tier":"Free","unlimited":true,"limit":0,"remaining":null,"reset_at":0}</code></pre>
        <p>Errors: <code>401</code> missing/invalid key, <code>403</code> inactive key, <code>429</code> quota exceeded (<code>limit</code>, <code>remaining</code>, <code>reset_at</code> included).</p>
        <h3>Tier regression scripts</h3>
        <table>
            <thead><tr><th>Script</th><th>Purpose</th><th>CLI</th></tr></thead>
            <tbody>
                <tr>
                    <td><code>scripts/apitest_tier_free.php</code></td>
                    <td>Seeds disposable Free-tier <code>ui_configuration</code>; expects unlimited status and non-blocking consumes.</td>
                    <td><code>php scripts/apitest_tier_free.php</code></td>
                </tr>
                <tr>
                    <td><code>scripts/apitest_tier_basic.php</code></td>
                    <td>Seeds Basic-tier row at hourly cap − 1; expects allow then block on consecutive consumes.</td>
                    <td><code>php scripts/apitest_tier_basic.php</code></td>
                </tr>
            </tbody>
        </table>
        <p class="muted">Helpers: <code>scripts/lib/itm_api_tier_test_helpers.php</code>. Catalog: <code>scripts/scripts.php</code>.</p>
    </div>

    <div class="card">
        <h2>Authentication</h2>
        <p>Protected endpoints require:</p>
        <ol>
            <li>Authenticated PHP session cookie (login via <code>login.php</code>).</li>
            <li>Valid CSRF token in <code>csrf_token</code> (POST body) or <code>X-CSRF-Token</code> where supported.</li>
            <li>Active <code>company_id</code> in session for tenant-scoped modules.</li>
        </ol>
<pre><code># Login and store session cookies
curl -c cookies.txt -X POST "http://localhost/it-management/login.php" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  --data "email=Admin&amp;password=Admin&amp;csrf_token=&lt;token_from_login_form&gt;"

# Protected JSON POST
curl -b cookies.txt -X POST "http://localhost/it-management/includes/get_ports.php" \
  -H "Content-Type: application/json" \
  -d '{"switch_id":1,"csrf_token":"&lt;csrf_token&gt;"}'</code></pre>
    </div>

    <div class="card">
        <h2>Explorer API (<code>modules/explorer/api.php</code>)</h2>
        <p>Multi-tenant file manager for <code>files/{company_id}/</code>. ACL: <code>Common/</code> (all company users), <code>Departments/{dept_id}/</code> (department members), <code>Private/{username}_{user_id}/</code> (owner only), <code>Trash/</code> (soft-delete mirror). Protected roots cannot be listed via API for <code>Private</code> or <code>Departments</code> — UI uses scoped paths.</p>
        <p>All POST actions require <code>csrf_token</code>. Responses are JSON (<code>Content-Type: application/json; charset=utf-8</code>) unless noted.</p>
        <table>
            <thead><tr><th>Method</th><th>Action</th><th>Parameters</th><th>Response</th><th>Purpose</th></tr></thead>
            <tbody>
            <?php foreach ($explorerApiActions as $row): ?>
                <tr>
                    <td><?= itmDocEscape((string)($row['method'] ?? 'POST')); ?></td>
                    <td><code><?= itmDocEscape((string)($row['action'] ?? '')); ?></code></td>
                    <td><code><?= itmDocEscape((string)($row['params'] ?? '')); ?></code></td>
                    <td><code><?= itmDocEscape((string)($row['response'] ?? '')); ?></code></td>
                    <td><?= itmDocEscape((string)($row['purpose'] ?? '')); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <h3>Explorer downloads (non-JSON)</h3>
        <table>
            <thead><tr><th>Method</th><th>Endpoint</th><th>Parameters</th><th>Response</th><th>Purpose</th></tr></thead>
            <tbody>
            <?php foreach ($explorerDownloadEndpoints as $row): ?>
                <tr>
                    <td><?= itmDocEscape((string)$row['method']); ?></td>
                    <td><code><?= itmDocEscape((string)$row['path']); ?></code></td>
                    <td><code><?= itmDocEscape((string)$row['params']); ?></code></td>
                    <td><?= itmDocEscape((string)$row['response']); ?></td>
                    <td><?= itmDocEscape((string)$row['purpose']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

<pre><code># List folder contents
curl -b cookies.txt -X POST "http://localhost/it-management/modules/explorer/api.php" \
  -d "action=list&amp;path=Common&amp;csrf_token=&lt;csrf_token&gt;"

# Upload files (multipart)
curl -b cookies.txt -X POST "http://localhost/it-management/modules/explorer/api.php" \
  -F "action=upload" -F "path=Common/Reports" -F "csrf_token=&lt;csrf_token&gt;" \
  -F "files[]=@/path/to/report.pdf"

# Copy / paste (UI clipboard uses copy or move)
curl -b cookies.txt -X POST "http://localhost/it-management/modules/explorer/api.php" \
  -d "action=copy&amp;src_path=Common&amp;dest=Common/Archive&amp;item=report.pdf&amp;csrf_token=&lt;csrf_token&gt;"

# Date folder scaffolding (year / month / day)
curl -b cookies.txt -X POST "http://localhost/it-management/modules/explorer/api.php" \
  -d "action=createYearMonthDay&amp;path=Common/Photos&amp;csrf_token=&lt;csrf_token&gt;"

# Download folder ZIP
curl -b cookies.txt -OJ "http://localhost/it-management/modules/explorer/api.php?downloadZip=1&amp;path=Common/Reports"</code></pre>
    </div>

    <div class="card">
        <h2>Core JSON / AJAX Endpoints</h2>
        <table>
            <thead><tr><th>Group</th><th>Method</th><th>Endpoint</th><th>Parameters</th><th>Purpose</th></tr></thead>
            <tbody>
            <?php foreach ($projectJsonEndpoints as $endpoint): ?>
                <tr>
                    <td><?= itmDocEscape((string)($endpoint['group'] ?? '')); ?></td>
                    <td><?= itmDocEscape((string)$endpoint['method']); ?></td>
                    <td><code><?= itmDocEscape((string)$endpoint['path']); ?></code></td>
                    <td><code><?= itmDocEscape((string)($endpoint['params'] ?? '')); ?></code></td>
                    <td><?= itmDocEscape((string)$endpoint['purpose']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h2>Passwords vault (<code>modules/passwords/ajax_handler.php</code>)</h2>
        <p>POST <code>action</code> + <code>csrf_token</code>. Data encrypted per <code>user_id</code>; requires unlocked vault (<code>$_SESSION['vault_key']</code>).</p>
        <table>
            <thead><tr><th>Action</th><th>Parameters</th><th>Purpose</th></tr></thead>
            <tbody>
            <?php foreach ($passwordsApiActions as $row): ?>
                <tr>
                    <td><code><?= itmDocEscape((string)$row['action']); ?></code></td>
                    <td><code><?= itmDocEscape((string)$row['params']); ?></code></td>
                    <td><?= itmDocEscape((string)$row['purpose']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h2>Notes AJAX (<code>modules/notes/index.php?ajax_action=…</code>)</h2>
        <p>POST with <code>csrf_token</code>. JSON responses <code>{"ok":true}</code> or <code>{"ok":false,"error":"…"}</code>.</p>
        <table>
            <thead><tr><th>ajax_action</th><th>Parameters</th><th>Purpose</th></tr></thead>
            <tbody>
            <?php foreach ($notesAjaxActions as $row): ?>
                <tr>
                    <td><code><?= itmDocEscape((string)$row['action']); ?></code></td>
                    <td><code><?= itmDocEscape((string)$row['params']); ?></code></td>
                    <td><?= itmDocEscape((string)$row['purpose']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p>JSON import: POST <code>Content-Type: application/json</code> with <code>import_excel_rows</code> to <code>modules/notes/index.php</code> (custom handler with tag/user resolution).</p>
    </div>

    <div class="card">
        <h2>To-Do AJAX (<code>modules/todo/index.php?ajax_action=…</code>)</h2>
        <table>
            <thead><tr><th>ajax_action</th><th>Parameters</th><th>Purpose</th></tr></thead>
            <tbody>
            <?php foreach ($todoAjaxActions as $row): ?>
                <tr>
                    <td><code><?= itmDocEscape((string)$row['action']); ?></code></td>
                    <td><code><?= itmDocEscape((string)$row['params']); ?></code></td>
                    <td><?= itmDocEscape((string)$row['purpose']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h2>IDF API Endpoints (<code>modules/idfs/api/</code>)</h2>
        <p>POST-only JSON via <code>_bootstrap.php</code>: <code>Content-Type: application/json</code>, <code>csrf_token</code>, tenant <code>company_id</code> from session. Response <code>{"ok":true,…}</code> or <code>{"ok":false,"error":"…"}</code>.</p>
        <table>
            <thead><tr><th>Method</th><th>Endpoint</th><th>Purpose</th></tr></thead>
            <tbody>
            <?php foreach ($idfApiEndpoints as $endpoint): ?>
                <tr>
                    <td><?= itmDocEscape($endpoint['method']); ?></td>
                    <td><code><?= itmDocEscape($endpoint['path']); ?></code></td>
                    <td><?= itmDocEscape((string)($endpoint['purpose'] ?? '')); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
<pre><code>curl -b cookies.txt -X POST "http://localhost/it-management/modules/idfs/api/position_save.php" \
  -H "Content-Type: application/json" \
  -d '{"csrf_token":"&lt;csrf_token&gt;","idf_id":1,"position_no":12,"device_name":"Switch-A"}'</code></pre>
    </div>

    <div class="card">
        <h2>Module Import APIs (auto-detected)</h2>
        <p>Detected <strong><?= (int)count($moduleImportEndpoints); ?></strong> module import JSON endpoints. Used by 📥 Import Excel in <code>js/table-tools.js</code>.</p>
        <p>Contract: POST <code>Content-Type: application/json</code> with <code>csrf_token</code> and <code>import_excel_rows</code> (header row + data rows). Typical success: <code>{"ok":true,"inserted":N}</code>.</p>
        <table>
            <thead><tr><th>Method</th><th>Endpoint</th><th>Handler</th><th>Purpose</th></tr></thead>
            <tbody>
            <?php foreach ($moduleImportEndpoints as $endpoint): ?>
                <tr>
                    <td><?= itmDocEscape($endpoint['method']); ?></td>
                    <td><code><?= itmDocEscape($endpoint['path']); ?></code></td>
                    <td><?= itmDocEscape((string)($endpoint['handler_type'] ?? 'shared')); ?></td>
                    <td><?= itmDocEscape($endpoint['purpose']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
<pre><code>curl -b cookies.txt -X POST "http://localhost/it-management/modules/departments/index.php" \
  -H "Content-Type: application/json" \
  -d '{"csrf_token":"&lt;csrf_token&gt;","import_excel_rows":[["Name","Active"],["IT","1"]]}'</code></pre>
    </div>

    <div class="card">
        <h2>Modules without detected JSON Import API</h2>
        <?php if (!empty($modulesWithoutImportEndpoint)): ?>
            <ul>
                <?php foreach ($modulesWithoutImportEndpoint as $moduleName): ?>
                    <li><code>modules/<?= itmDocEscape($moduleName); ?>/index.php</code></li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p>All modules with index.php currently expose a detected JSON import endpoint.</p>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>API Examples (<code>api-examples/</code>)</h2>
        <p>Standalone PHP scripts demonstrating session authentication, CSRF, JSON imports, CRUD, and HTML list parsing. Detected <strong><?= (int)count($apiExamples); ?></strong> example file(s).</p>
        <table>
            <thead><tr><th>File</th><th>Title</th><th>Category</th><th>Purpose</th></tr></thead>
            <tbody>
            <?php foreach ($apiExamples as $example): ?>
                <tr>
                    <td><code><?= itmDocEscape((string)$example['file']); ?></code></td>
                    <td><?= itmDocEscape((string)$example['title']); ?></td>
                    <td><?= itmDocEscape((string)$example['category']); ?></td>
                    <td><?= itmDocEscape((string)$example['purpose']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p class="muted">Run from CLI: <code>php api-examples/authenticate.php</code> (after setting base URL and credentials in the script). Directory placeholder: <code>api-examples/index.html</code>.</p>
    </div>

    <div class="card">
        <h2>Response and error conventions</h2>
        <table>
            <thead><tr><th>Pattern</th><th>Example</th><th>When</th></tr></thead>
            <tbody>
            <tr><td>Explorer success</td><td><code>{"ok":1}</code></td><td>Mutation succeeded.</td></tr>
            <tr><td>Explorer failure</td><td><code>{"ok":0,"error":"…"}</code></td><td>ACL block or invalid path.</td></tr>
            <tr><td>IDF / AJAX</td><td><code>{"ok":true}</code> / <code>{"ok":false,"error":"…"}</code></td><td>Standard module AJAX.</td></tr>
            <tr><td>Import</td><td><code>{"ok":true,"inserted":N}</code></td><td>Table-tools save-to-database.</td></tr>
            <tr><td>CSRF</td><td>HTTP 403 / JSON error</td><td>Missing or invalid <code>csrf_token</code>.</td></tr>
            <tr><td>Session</td><td><code>{"error":"No company selected."}</code></td><td>Missing company context (Explorer).</td></tr>
            <tr><td>API key</td><td><code>{"ok":false,"error":"…"}</code></td><td>HTTP 401/403/429 from <code>itm_api_enforce_rate_limit_or_exit()</code>.</td></tr>
            <tr><td>Unknown action</td><td><code>{"error":"Unknown action"}</code></td><td>Invalid Explorer <code>action</code>.</td></tr>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h2>Validation rules (summary)</h2>
        <ul>
            <li><code>csrf_token</code> mandatory on protected POST endpoints.</li>
            <li>Explorer <code>path</code> is normalised (trimmed slashes); <code>..</code> traversal blocked.</li>
            <li>Explorer uploads block executable extensions and dot-prefixed filenames.</li>
            <li><code>import_excel_rows</code> requires header row + at least one data row.</li>
            <li>Passwords writes require per-user vault key in session.</li>
            <li>IDF endpoints require JSON body and tenant-scoped <code>company_id</code>.</li>
            <li>API key requests must pass <code>itm_api_enforce_rate_limit_or_exit()</code> when bypassing session auth.</li>
        </ul>
    </div>

    <div class="card">
        <h2>Non-JSON endpoints (documented for clarity)</h2>
        <ul>
            <li><code>modules/tickets/archive.php</code> — form POST with redirect flash (not JSON).</li>
            <li><code>modules/calendar/index.php?date=</code> — full HTML calendar page navigation.</li>
            <li><code>modules/bookmarks/index.php</code> — <code>action=move_folder</code> returns empty body on success.</li>
        </ul>
    </div>

    <div class="card">
        <h2>Rate limiting and versioning</h2>
        <p>Per-user API keys on <code>ui_configuration</code> enforce tier-based hourly quotas via <code>includes/itm_api_rate_limit.php</code>. Probe quota with <code>GET scripts/api.php?rate_limit=1</code> and <code>X-API-Key</code>. Session-based AJAX endpoints remain unchanged. Endpoints are file-path based (no <code>/api/v1</code> prefix).</p>
    </div>
</div>
</body>
</html>
