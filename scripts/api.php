<?php
/**
 * IT Management System - API Documentation (project-aligned)
 **/

declare(strict_types=1);

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
        ];
    }

    usort($endpoints, static function (array $a, array $b): int {
        return strcmp($a['name'], $b['name']);
    });

    return $endpoints;
}

$moduleImportEndpoints = itmDocCollectModuleImportEndpoints($itmRootPath);
$modulesWithoutImportEndpoint = itmDocCollectModulesWithoutImportEndpoint($itmRootPath, $moduleImportEndpoints);
$idfApiEndpoints = itmDocCollectIdfApiEndpoints($itmRootPath);

$projectJsonEndpoints = [
    ['method' => 'POST', 'path' => 'includes/get_ports.php', 'purpose' => 'Fetch/seed switch ports and lookup metadata.'],
    ['method' => 'POST', 'path' => 'includes/update_port.php', 'purpose' => 'Update switch port fields and sync matching idf_ports rows; To IDF saves now auto-create/update/delete auto-synced idf_ports records.'],
    ['method' => 'GET',  'path' => 'modules/idfs/index.php?refresh_select_options=rack|location', 'purpose' => 'Refresh select options for IDF forms.'],
    ['method' => 'POST', 'path' => 'modules/employee_assignment_history/index.php', 'purpose' => 'Employee Assignment History save-to-database import endpoint (JSON rows from table-tools).'],
    ['method' => 'POST', 'path' => 'modules/tickets/archive.php', 'purpose' => 'Archive or un-archive a ticket (requires id, archive_action, and csrf_token).'],
    ['method' => 'POST', 'path' => 'scripts/test_sql_injection.php', 'purpose' => 'Security test helper endpoint used during audits.'],
    ['method' => 'GET', 'path' => 'scripts/DBdesign.php?format=html|mermaid|json', 'purpose' => 'Generate database.sql ER diagram output (drawdb-style viewer, Mermaid, or JSON).'],
    ['method' => 'GET', 'path' => 'modules/calendar/index.php?date=YYYY-MM-DD', 'purpose' => 'Fetch calendar view for a specific month/year and selected day.'],
    ['method' => 'POST', 'path' => 'modules/tickets/archive.php', 'purpose' => 'Archive or un-archive a support ticket. Requires id and archive_action (archive/unarchive).'],
    ['method' => 'POST', 'path' => 'modules/org_chart/index.php', 'purpose' => 'Update employee reporting hierarchy via drag-and-drop. Requires employee_id, reports_to, and action=update_hierarchy.'],
    ['method' => 'POST', 'path' => 'modules/visitors_access_log/index.php', 'purpose' => 'Visitors Access Log inline editing and timestamp updates. Supports ajax_inline_edit=1 or action_timestamp=1.'],
    ['method' => 'POST', 'path' => 'modules/floor_designer/index.php', 'purpose' => 'Floor Designer AJAX actions: save_point, update_point_pos, update_comment_pos, delete_point, get_switch_ports.'],
    ['method' => 'POST', 'path' => 'modules/floor_designer_points/index.php', 'purpose' => 'Floor Designer Points save-to-database import endpoint (JSON rows from table-tools).'],
    ["method" => "POST", "path" => "modules/alerts/index.php", "purpose" => "Alerts save-to-database import endpoint (JSON rows) or ICS file import support."],
    ["method" => "POST", "path" => "modules/explorer/api.php", "purpose" => "Explorer file management actions (create, rename, delete, move, upload)."],
    ["method" => "POST", "path" => "modules/patches_updates/list_all.php", "purpose" => "Patches & Updates save-to-database import endpoint (JSON rows)."],
    ["method" => "POST", "path" => "scripts/module_browser_qa_runner.php", "purpose" => "QA runner for automated browser testing (requires action and credentials)."],
    ["method" => "GET",  "path" => "scripts/compare_database_sql_modules.php", "purpose" => "Compare database schema with module definitions (JSON output)."],
    ["method" => "GET",  "path" => "scripts/list_modules_not_on_sidebar.php", "purpose" => "List modules missing from the sidebar (JSON output)."],
    ["method" => "POST", "path" => "scripts/check_audit_logs_coverage.php", "purpose" => "Static analysis of audit log coverage (supports ajax=1)."],
    ["method" => "POST", "path" => "modules/contacts/api/inline_edit.php", "purpose" => "Contacts inline editing handler (JSON)."],
    ["method" => "POST", "path" => "modules/ip_subnets/index.php", "purpose" => "IP Subnets AJAX actions and JSON import (via includes/handlers_ajax.php and includes/handlers_post.php)."],
    ["method" => "POST", "path" => "modules/ip_addresses/index.php", "purpose" => "IP Addresses AJAX actions and JSON import (via includes/handlers_ajax.php and includes/handlers_post.php)."],
    ["method" => "POST", "path" => "modules/rack_planner/index.php", "purpose" => "Rack Planner AJAX actions: save_item, delete_item, get_available_equipment (via includes/handlers.php)."],
];
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
    </style>
</head>
<body>
<?php require_once __DIR__ . '/lib/script_browser_nav.php'; ?>
<div class="container">
    <?php itm_script_browser_nav_echo('../'); ?>
    <div class="card">
        <h1>IT Management API Documentation</h1>
        <p>Comprehensive API documentation for the <strong>actual JSON endpoints available in this project</strong>, including authentication model, validation, error handling, and examples.</p>
        <p class="muted">Generated: <?= itmDocEscape($itmDocGeneratedAt); ?></p>
    </div>

    <div class="card">
        <h2>Recent Updates</h2>
        <ul>
            <li><strong>2026-06-09:</strong> Added sortable column headers and state-preserving pagination to the Bookmarks List view.</li>
            <li><strong>2026-06-07:</strong> Refactored Passwords UI to match standardized layout, moved it to the Employee sidebar section, and added unit tests for vault data management.</li>
            <li><strong>2026-03-29:</strong> Added <code>api-examples/</code> folder with PHP implementation examples for Equipment, Employees, Tickets, Catalogs, and Events, and authentication helpers (sessionCookie, csrfToken, authenticate), and CRUD operations (archive, delete, single view, edit, and list filtering (Open tickets, Active catalogs).</li>
        </ul>
    </div>

    <div class="card">
        <h2>Overview</h2>
        <ul>
            <li>This project uses <strong>session-based authentication + CSRF</strong> (not JWT bearer tokens).</li>
            <li>Most JSON APIs are internal AJAX endpoints used by module pages.</li>
            <li>Multi-tenancy is enforced via session <code>company_id</code> checks in endpoints.</li>
            <li><strong>Do not send <code>company_id</code> in API payloads</strong>; tenant context is resolved server-side from the authenticated session.</li>
            <li>Many modules expose a JSON import endpoint at their own <code>modules/&lt;module&gt;/index.php</code>.</li>
        </ul>
    </div>

    <div class="card">
        <h2>API Examples</h2>
        <p>Standalone PHP examples demonstrating session-based authentication and <code>import_excel_rows</code> JSON payloads:</p>
        <ul>
            <li><code>api-examples/equipment.php</code> - Equipment multi-row import.</li>
            <li><code>api-examples/employees.php</code> - Employee directory import (with auto-lookup resolution).</li>
            <li><code>api-examples/tickets.php</code> - Bulk ticket creation.</li>
            <li><code>api-examples/catalogs.php</code> - Catalog product listing import.</li>
            <li><code>api-examples/events.php</code> - Calendar event batch import.</li>
            <li><code>api-examples/sessionCookie.php</code> - How to capture session ID from headers.</li>
            <li><code>api-examples/csrfToken.php</code> - How to extract CSRF token from page content.</li>
            <li><code>api-examples/authenticate.php</code> - Full login and token acquisition flow.</li>
            <li><code>api-examples/ticket_archive.php</code> - Archiving a closed ticket.</li>
            <li><code>api-examples/catalog_delete.php</code> - Deleting a single catalog record.</li>
            <li><code>api-examples/employees_singleview.php</code> - Fetching and parsing a single record view.</li>
            <li><code>api-examples/equipment_edit.php</code> - Updating an existing equipment item.</li>
            <li><code>api-examples/tickets_listall_open.php</code> - Filtering tickets by "Open" status.</li>
            <li><code>api-examples/catalogs_listall_active.php</code> - Filtering catalogs by "Active" status.</li>
        </ul>
    </div>

    <div class="card">
        <h2>Authentication</h2>
        <p>All protected endpoints require:</p>
        <ol>
            <li>An authenticated PHP session cookie.</li>
            <li>A valid CSRF token in <code>csrf_token</code> (body) or <code>X-CSRF-Token</code> header (where supported).</li>
            <li>An active <code>company_id</code> context in session for tenant-scoped operations.</li>
            <li>Tenant scope is automatic: requests are filtered by server-side session company; payload-level <code>company_id</code> is intentionally hidden from UI/API contracts.</li>
        </ol>
<pre><code># 1) Login and store session cookies (example)
curl -c cookies.txt -X POST "http://localhost/it-management/login.php"   -H "Content-Type: application/x-www-form-urlencoded"   --data "email=Admin&amp;password=Admin&amp;csrf_token=&lt;token_from_login_form&gt;"

# 2) Call a protected JSON endpoint with same cookie + CSRF token
curl -b cookies.txt -X POST "http://localhost/it-management/includes/get_ports.php"   -H "Content-Type: application/json"   -d '{"switch_id": 1, "csrf_token": "&lt;csrf_token&gt;"}'</code></pre>
    </div>

    <div class="card">
        <h2>Available APIs (non-module specific)</h2>
        <table>
            <thead><tr><th>Method</th><th>Endpoint</th><th>Purpose</th></tr></thead>
            <tbody>
            <?php foreach ($projectJsonEndpoints as $endpoint): ?>
                <tr>
                    <td><?= itmDocEscape($endpoint['method']); ?></td>
                    <td><code><?= itmDocEscape($endpoint['path']); ?></code></td>
                    <td><?= itmDocEscape($endpoint['purpose']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h2>IDF API Endpoints</h2>
        <p>All endpoints below are under <code>modules/idfs/api/</code> and are POST-only JSON handlers.</p>
        <table>
            <thead><tr><th>Method</th><th>Endpoint</th></tr></thead>
            <tbody>
            <?php foreach ($idfApiEndpoints as $endpoint): ?>
                <tr>
                    <td><?= itmDocEscape($endpoint['method']); ?></td>
                    <td><code><?= itmDocEscape($endpoint['path']); ?></code></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
<pre><code># Example: save an IDF position
curl -b cookies.txt -X POST "http://localhost/it-management/modules/idfs/api/position_save.php"   -H "Content-Type: application/json"   -d '{"csrf_token":"&lt;csrf_token&gt;","id":12,"name":"U12","notes":"Reserved"}'</code></pre>
    </div>

    <div class="card">
        <h2>Module Import APIs (all detected)</h2>
        <p>Detected <strong><?= (int)count($moduleImportEndpoints); ?></strong> module import JSON endpoints. These are used by the 📥 Import Excel flow in <code>js/table-tools.js</code>.</p>
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
<pre><code># Example: table-tools.js JSON import payload
curl -b cookies.txt -X POST "http://localhost/it-management/modules/departments/index.php"   -H "Content-Type: application/json"   -d '{
    "csrf_token":"&lt;csrf_token&gt;",
    "import_excel_rows":[
      ["Name","Active"],
      ["IT", "1"],
      ["HR", "1"]
    ]
  }'

# Typical success response
{"ok": true, "inserted": 2}</code></pre>
    </div>

    <div class="card">
        <h2>Modules without detected JSON Import API</h2>
        <p>The modules below currently do not expose an <code>import_excel_rows</code> JSON endpoint in their <code>index.php</code> file.</p>
        <?php if (!empty($modulesWithoutImportEndpoint)): ?>
            <ul>
                <?php foreach ($modulesWithoutImportEndpoint as $moduleName): ?>
                    <li><code>modules/<?= itmDocEscape($moduleName); ?>/index.php</code></li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p>All modules currently expose a detected JSON import endpoint.</p>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>Error Handling</h2>
        <table>
            <thead><tr><th>Status</th><th>Common message</th><th>When it happens</th></tr></thead>
            <tbody>
            <tr><td>400</td><td><code>Invalid CSRF token.</code>, <code>Missing switch id</code></td><td>Bad payload or missing required fields.</td></tr>
            <tr><td>401</td><td><code>Unauthorized</code></td><td>No valid session or company context.</td></tr>
            <tr><td>403</td><td><code>Invalid CSRF token</code></td><td>CSRF check failed.</td></tr>
            <tr><td>404</td><td><code>Switch not found</code></td><td>Resource not found in tenant scope.</td></tr>
            <tr><td>405</td><td><code>Method not allowed</code></td><td>Wrong HTTP verb.</td></tr>
            <tr><td>500</td><td><code>DB error</code>, schema messages</td><td>Server-side SQL/schema issues.</td></tr>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h2>Rate Limiting</h2>
        <p>No centralized API rate limiting middleware is defined for these JSON endpoints. Login and reset flows include attempt/rate controls; implement reverse-proxy or app-level throttling if public API exposure is planned.</p>
    </div>

<div class="card">
        <h2>Versioning</h2>
        <p>Current endpoints are file-path based and are not under a versioned prefix like <code>/api/v1</code>. If external integrations are planned, introduce a versioned gateway path before publishing public contracts.</p>
    </div>

    <div class="card">
        <h2>Validation Rules (practical summary)</h2>
        <ul>
            <li><code>csrf_token</code> is mandatory on protected POST endpoints.</li>
            <li><code>switch_id</code> is required for <code>includes/get_ports.php</code> and <code>includes/update_port.php</code>.</li>
            <li><code>id</code> is required for <code>includes/update_port.php</code>.</li>
            <li><code>import_excel_rows</code> must include header row + at least one data row for module import APIs.</li>
            <li>All DB writes are tenant-scoped by active session company.</li>
            <li><code>company_id</code> is not a client-editable API field for these endpoints; if missing from session, endpoints return unauthorized/company-context errors.</li>
        </ul>
    </div>
</div>
</body>
</html>
