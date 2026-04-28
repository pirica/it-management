<?php
/**
 * IT Management System - API Documentation (project-aligned)
 *
 * Why: Document only the JSON endpoints that actually exist in this codebase,
 * including request format, auth model, and real endpoint paths.
 * Schema note: employee access is stored via employee_system_access mapped to system_access,
 * and active system_access entries resolve per-company with legacy code/name fallback
 * when mapping matrix permissions to employee_system_access columns, with runtime
 * column backfill for standard legacy matrix flags.
 * Settings SQL backup exports now include database trigger definitions (DROP/CREATE).
 * The module also auto-seeds missing system_access catalog rows per company at read time.
 * Custom tenant system_access codes are also backfilled into employee_system_access
 * columns at runtime so new catalog entries can be displayed/edited in the matrix UI.
 * Employee system-access matrix/list screens resolve catalog labels from mapped legacy fields
 * so documented exports/imports align with visible column headers.
 * Added scripts/DBdesign.php to generate a drawdb-style ER diagram, Mermaid source,
 * and JSON metadata by parsing database.sql directly.
 * DBdesign rendered view includes client-side zoom controls (up to 1000%) and SVG/PNG export actions that use the current zoom level.
 * Settings module SQL maintenance cards are rendered only for Admin-role users, while
 * backup actions are role-gated for Admin/IT Manager/IT Assistant and backup exports
 * include only rows scoped to the active session company_id.
 * Equipment switch Fiber Count field was removed; switch port layout now relies on
 * switch_fiber_ports_number instead of legacy fiber-count lookups.
 * includes/get_ports.php now adapts to both schemas by only joining equipment_fiber_count
 * when equipment.switch_fiber_count_id exists, preventing "Switch not found" on migrated databases.
 * user_sidebar_preferences seed data now bootstraps a cleaned default sidebar layout
 * for companies 1-5 using schema defaults for created_at/updated_at timestamps.
 * Sidebar seed ordering now places switch_ports under reference_data before switch_port_numbering_layout for all base companies.
 * Sidebar preference loading now reconciles legacy user_sidebar_preferences rows in DB so switch_ports is stored under reference_data above switch_status.
 * database.sql equipment seed rows were corrected to align VALUES counts with the 44-column equipment insert signature.
 * Equipment create/edit now renders Fiber Ports Number as a select with a quick-add (➕) option.
 * Equipment switch manager summary now includes Rack/IDF/Location and uses the selected Fiber Ports label for SFP counts, while Fiber Patch/Fiber Rack/Fiber Port Label details are shown on SFP/SFP+ port tooltips.
 * Switch port DOM metadata now includes Fiber Ports/Fiber Patch/Fiber Rack fields so selected SFP/SFP+ ports carry the expected data-* attributes and tooltip details.
 * Switch Port Manager save flow now refreshes selected port fiber metadata in the DOM immediately after save, keeping tooltip Fiber fields in sync without requiring a page reload.
 * Switch manager header summary now shows Hostname (instead of the prior Layout label) before RJ45/Fiber counts for clearer device identification.
 * Switch manager header summary now hardcodes fiber count label as SFP whenever SFP ports exist, preventing fiber catalog names like "SFP 1 Gbps" from replacing the header label.
 * Switch manager SFP section heading now stays fixed as "SFP Ports" so fiber catalog names do not rename the fiber row header.
 * Equipment switch manager now orders SFP/SFP+ ports using the selected Port Numbering Layout.
 * Vertical switch layouts now render fiber ports in two rows (odd top / even bottom) to match RJ45 numbering semantics.
 * Switch manager now hides inactive/zero-count fiber sections so SFP+ is not shown when only SFP ports are configured.
 * Database seed data now includes QSFP switch port type entries for companies 1 through 5 in `switch_port_types`.
 * `switch_port_types` table definition now sets AUTO_INCREMENT to 21 so inserts continue correctly after seeded IDs 1-20.
 * includes/get_ports.php now avoids PHP 8-only string helpers so the switch-port loader returns JSON correctly on PHP 7.4 environments.
 * includes/get_ports.php now guards equipment_fiber table-existence checks when shared helpers are unavailable, preventing fatal errors that returned empty AJAX bodies.
 * Equipment create/edit switch form now hides Fiber Ports/Fiber Patch/Fiber Rack/Fiber Port Label inputs while keeping Fiber Ports Number visible for switch sizing.
 * Switch port seeding now resolves Fiber Ports Number-only fallback types from equipment_fiber.name (tenant-first, global fallback) when no fiber type is saved, so fiber ports are still auto-created without hardcoded labels.
 * Switch Port Manager edit controls now stay hidden until a port is clicked; fiber-port clicks show Fiber Ports/Fiber Patch/Fiber Rack dropdowns (with quick-add), while RJ45 keeps VLAN controls.
 * Switch Port Manager edit controls now keep VLAN visible for fiber ports so SFP/SFP+ updates can be assigned VLANs without switching port type.
 * Switch Port Manager no longer renders the color legend block under port controls, keeping the panel focused on editable fields.
 * Inventory module UI now includes storage_date and read-only created/updated timestamps in create/edit flows,
 * Inventory create/edit now includes a Last User employee selector (display_name) plus a manual fallback text input (varchar 100), stored in inventory_items.last_user_id and inventory_items.last_user_manual after Price (€).
 * Inventory view now resolves Last User labels from employees (first_name + last_name, username fallback) with tenant-safe id fallback to avoid raw numeric user IDs in detail screens.
 * inventory_items seed/replication INSERT statements now include last_user_id and last_user_manual columns to match current schema.
 * and index list output hides item code while exposing storage_date and updated_at columns.
 * Patches Updates import endpoint now accepts multiple scanner-style header aliases and maps them to
 * expanded patches_updates fields for mixed Excel/CSV import layouts.
 * Patches Updates scanner import stores vendor text from "Host MAC" columns in
 * host_mac_manufacturer to avoid mislabeling manufacturer data as a physical MAC address.
 * Scanner import aliases also map external source "id" headers into id_external to keep internal IDs untouched.
 * Employees create/edit/view now use a workstation_mode_id relation to workstation_modes (mode_name select + quick-add in forms, label rendering in detail view).
 * Employees create/edit/view/index now also use assignment_type_id relation to assignment_types (name select + quick-add in forms, label rendering in list/detail views).
 * Database schema now enforces unique patches/updates status names per company via patches_updates_status(company_id, name).
 * Patches Updates Level schema now stores only level (dropped legacy name), enforces patches_updates_level(company_id, level) uniqueness, and keeps JSON import compatibility for legacy Name headers.
 * Patches Updates CRUD FK label resolution now recognizes `level` columns so Level dropdown/list/detail views remain human-readable after patches_updates_level schema cleanup.
 * Role Module Permissions now includes can_import/can_export flags so per-module access can explicitly control import/export actions.
 * Role Module Permissions create/edit now render module_name as a predefined select list (no quick-add option) to keep permission targets consistent.
 * Added employee_assignment_history module to track per-employee handover history for laptops, phones, printers, key cards, and SIM IMEI records.
 * Employee Assignment History now accepts JSON imports even when clients omit JSON content-type headers,
 * and resolves FK display labels/user full names with tenant-safe fallback-by-id behavior in list/detail/edit flows.
 * Employee Assignment History employee/user FK dropdowns now render full names (first_name + last_name, username fallback) so edit forms do not show raw SQL-like label text.
 * Employee Assignment History now keeps Assigned/Received user labels non-empty by falling back to "User #<id>" when names are missing.
 * Employee Assignment History user pickers now include Admin-role users in Assigned By/Received By dropdowns even when they are outside the active tenant scope.
 * Added missing modules/equipment_environment CRUD entry files so the sidebar reference-data route now resolves
 * correctly and supports the standard JSON import handler used by js/table-tools.js.
 * Equipment Environment wrapper routes (create/edit/view/delete/list_all) now preserve $crud_action set by wrapper
 * files so entry screens no longer fall back to the index list view.
 * Added missing modules/equipment_fiber_count CRUD entry files so the reference-data route now supports
 * create/edit/view/delete/list_all and the standard JSON import flow in js/table-tools.js.
 * Added missing modules/equipment_poe CRUD entry files so PoE reference-data routes resolve create/edit/view/delete/list_all
 * correctly and expose the standard JSON import handler consumed by js/table-tools.js.
 * Employees, Employee System Access, and Employee Onboarding Requests edit/create screens now include a Select All / Deselect All toggle helper for System Access checkboxes.
 * IDF rack view device icon now derives SFP/SFP+ dot count/tooltip from equipment fiber metadata (switch_fiber_ports_number + fiber label/name), with port-type fallback, so IDF cards stay aligned with Switch Port Manager context without CSS/style changes.
 * includes/get_ports.php now detects numeric switch_ports.port_type schemas, maps values via switch_port_types (company-scoped), and uses fiber label+name hints so SFP/SFP+ ports are seeded/returned with the correct type instead of collapsing to RJ45.
 * IDF equipment-port API/link creation now resolves switch_ports.port_type through switch_port_types for numeric FK deployments, while preserving raw type value for uniqueness checks during switch port-number updates.

 * IDF link/port switch sync updates now match both port_number and port_type when mirroring to switch_ports, preventing cross-type writes on overlapping port numbers (for example RJ45 1 vs SFP 1).
 * IDF rack device icon now derives SFP/SFP+ dots using resilient type-label matching and paints each dot using matching SFP/SFP+ cable/status colors from rendered port metadata.
 * IDF rack device icon now includes RJ45+SFP/SFP+ dot previews (capped) and paints each dot from matching port cable/status colors so active RJ45 colors are visible in the compact card icon.
 * IDF rack card icon now honors configured RJ45/SFP/SFP+ counts from rack metadata (with rendered-port fallback) so compact previews do not over-render stale legacy ports.
 * IDF rack icon dot grid now sets columns inline per rendered dot count (up to 10) so global icon CSS remains compact while switch previews can expand without style regressions.
 * IDF SFP dot count now defaults directly from equipment.switch_fiber_ports_number when fiber labels are blank, ensuring configured fiber totals are still represented.
 * IDF rack port visualizer now assigns deterministic visual slots per port type so RJ45/SFP/SFP+ entries with overlapping port numbers render on the correct dots instead of overwriting each other.
* IDFs index now includes an empty-state "Add sample data" action that seeds a tenant-scoped starter IDF row only when the company has no IDF records.

 * Inventory Items index now uses the same ✏️ glyph for non-empty comments tooltips used by other module edit affordances.
 */

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
    ['method' => 'POST', 'path' => 'includes/update_port.php', 'purpose' => 'Update a switch port label/status/color/vlan/comments.'],
    ['method' => 'GET',  'path' => 'modules/idfs/index.php?refresh_select_options=rack|location', 'purpose' => 'Refresh select options for IDF forms.'],
    ['method' => 'POST', 'path' => 'modules/employee_assignment_history/index.php', 'purpose' => 'Employee Assignment History save-to-database import endpoint (JSON rows from table-tools).'],
    ['method' => 'POST', 'path' => 'scripts/test_sql_injection.php', 'purpose' => 'Security test helper endpoint used during audits.'],
    ['method' => 'GET', 'path' => 'scripts/DBdesign.php?format=html|mermaid|json', 'purpose' => 'Generate database.sql ER diagram output (drawdb-style viewer, Mermaid, or JSON).'],
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
<div class="container">
    <div class="card">
        <h1>IT Management API Documentation</h1>
        <p>Comprehensive API documentation for the <strong>actual JSON endpoints available in this project</strong>, including authentication model, validation, error handling, and examples.</p>
        <p class="muted">Generated: <?= itmDocEscape($itmDocGeneratedAt); ?></p>
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
        <h2>Authentication</h2>
        <p>All protected endpoints require:</p>
        <ol>
            <li>An authenticated PHP session cookie.</li>
            <li>A valid CSRF token in <code>csrf_token</code> (body) or <code>X-CSRF-Token</code> header (where supported).</li>
            <li>An active <code>company_id</code> context in session for tenant-scoped operations.</li>
            <li>Tenant scope is automatic: requests are filtered by server-side session company; payload-level <code>company_id</code> is intentionally hidden from UI/API contracts.</li>
        </ol>
<pre><code># 1) Login and store session cookies (example)
curl -c cookies.txt -X POST "http://localhost/it-management/login.php" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  --data "email=Admin&amp;password=Admin&amp;csrf_token=&lt;token_from_login_form&gt;"

# 2) Call a protected JSON endpoint with same cookie + CSRF token
curl -b cookies.txt -X POST "http://localhost/it-management/includes/get_ports.php" \
  -H "Content-Type: application/json" \
  -d '{"switch_id": 1, "csrf_token": "&lt;csrf_token&gt;"}'</code></pre>
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
curl -b cookies.txt -X POST "http://localhost/it-management/modules/idfs/api/position_save.php" \
  -H "Content-Type: application/json" \
  -d '{"csrf_token":"&lt;csrf_token&gt;","id":12,"name":"U12","notes":"Reserved"}'</code></pre>
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
curl -b cookies.txt -X POST "http://localhost/it-management/modules/departments/index.php" \
  -H "Content-Type: application/json" \
  -d '{
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
