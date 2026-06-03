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
        <h2>Recent Updates</h2>
        <p style="margin:0 0 12px;color:var(--text-muted,#57606a);">Major product and platform changes, newest first. Each entry maps to merged work on <code>master</code>; see <code>scripts/index.html</code> for runnable audit tools.</p>
        <ul>
            <li><strong>2026-05-21:</strong> <strong>Companies Module:</strong> Added <code>Unit No.</code> field to the <code>companies</code> table and updated the Create, Edit, and View screens. The field is displayed on the same row as Company and InCode in forms, and appears in the detail view only when not empty. Audit triggers were updated to include the new field.</li>
            <li><strong>2026-05-20:</strong> <strong>Org Chart Security:</strong> Added cross-tenant validation check to the <code>update_hierarchy</code> AJAX handler in <code>modules/org_chart/index.php</code>; the subject employee ID is now verified against the company-scoped employee map to prevent unauthorized hierarchy manipulation across tenants.</li>
            <li><strong>2026-05-18:</strong> IDF <strong>Create Cable Link</strong> no longer returns HTTP 500 when saving with a VLAN selected: corrected <code>mysqli_stmt_bind_param</code> type string on the mirrored <code>switch_ports</code> sync UPDATE in <code>modules/idfs/api/link_create.php</code>, and added <code>spt_any</code> join fallbacks for <code>SFP</code> type matching parity with <code>device.php</code>.</li>
            <li><strong>2026-05-17:</strong> Added <strong>audit logs coverage</strong> static analysis (<code>scripts/check_audit_logs_coverage.php</code>, CLI and browser) to verify every CRUD module has INSERT/UPDATE/DELETE traceability via <code>itm_run_query</code>, <code>itm_log_audit</code>, bulk helpers, or <code>trg_{table}_audit_*</code> triggers in <code>database.sql</code>; <code>rack_planner</code> now has <code>trg_rack_planner_audit_insert/update/delete</code> after its table definition so fresh imports succeed.</li>
            <li><strong>2026-05-17:</strong> <strong>Floor Plans</strong> gallery mutations now write <code>audit_logs</code> on confirmed DELETE and UPDATE for plans, nested folders, and tags (<code>modules/floor_plans/index.php</code>, <code>gallery_helpers.php</code>) using <code>itm_log_audit</code> when audit logging is enabled for the tenant.</li>
            <li><strong>2026-05-17:</strong> <strong>Smoke tests and scripts catalog:</strong> <code>bash scripts/smoke_test.sh</code> runs CSRF, SQLi, index-table compliance (step 4, baseline <code>scripts/data/index_table_compliance_baseline.txt</code>), and UI configuration coverage (step 5) in CI; <code>scripts/index.html</code> documents Browser vs CLI access. UI config step skips <code>is_*</code> equipment façades (prefix <code>scripts/data/ui_configuration_excluded_prefixes.txt</code>) and bespoke modules listed in <code>ui_configuration_excluded_modules.txt</code>.</li>
            <li><strong>2026-05-17:</strong> <code>wiki/Modules.md</code> documents non-CRUD entry points (Settings, Budget report, IDFs, Floor Plans) and which smoke audits they are exempt from; <code>wiki/Security.md</code> documents <code>ITM_APP_URL</code> and <code>IP2WHOIS_*</code> environment variables.</li>
            <li><strong>2026-05-17:</strong> Repository maintenance: removed obsolete <code>scripts/fixes/2026-05-07_restore_sidebar_emojis.sql</code> (sidebar emojis ship in <code>database.sql</code> seeds); stripped UTF-8 BOM from affected module includes; audit scanner recognizes dynamic CRUD using <code>cr_escape_identifier($crud_table)</code>.</li>
            <li><strong>2026-05-16:</strong> IDF <strong>Edit Port</strong> (<code>modules/idfs/device.php</code>) no longer shows <strong>Destination port</strong>; new links use <strong>Create Cable Link</strong> only (port save no longer chains <code>link_create</code> from that form).</li>
            <li><strong>2026-05-16:</strong> IDF <code>device.php</code> <strong>Edit Port</strong> / <strong>Create Cable Link</strong> modals no longer show <code>To Rack</code>, <code>To IDF</code>, or <code>Location</code> routing selects; existing <code>to_*</code> values are preserved on save/link from port metadata and <code>switch_ports</code>, and unused IDF/rack/location option lookups were removed from this page load.</li>
            <li><strong>2026-05-16:</strong> Agent workflow rules now mandate <strong>a new PR for every deliverable</strong> (documented in <code>AGENTS.md</code>). IDF <code>Create Cable Link</code> no longer renders a duplicate <strong>VLAN</strong> select (<code>modules/idfs/device.php</code>).</li>
            <li><strong>2026-05-15:</strong> Create Cable Link now includes a tenant-scoped <strong>VLAN</strong> selector (prefilled from the source port), passes <code>vlan_id</code> through <code>link_create.php</code> for validation, updates <code>idf_ports</code>/<code>switch_ports</code>, and syncs <code>switch_ports.vlan_id</code> when linking (<code>modules/idfs/device.php</code>, <code>modules/idfs/api/link_create.php</code>).</li>
            <li><strong>2026-05-15:</strong> IDF Edit Port routing fields (<code>To Rack</code> / <code>To IDF</code> / <code>Location</code>) now fall back to <code>switch_ports.rack_id</code>, <code>location_id</code>, and <code>idf_id</code> when legacy data only populated those columns (equipment modal uses the same pattern for IDF). Updated: <code>modules/idfs/device.php</code> (effective_* SQL + JS merge), <code>modules/idfs/idf_ports_sync.php</code> (attach merge), <code>modules/idfs/api/switch_ports_by_equipment.php</code>.</li>
            <li><strong>2026-05-15:</strong> IDF <code>Port Editor</code> and <code>Create Cable Link</code> flows now expose destination-port selection plus type-aware link metadata fields (<code>RJ45 Cable</code>/<code>Fiber Ports</code>, fiber patch/rack, destination IDF/rack/location, patch-port/comments), and the save endpoints persist the added metadata into <code>idf_links</code> with matching <code>switch_ports</code> sync support (<code>modules/idfs/device.php</code>, <code>modules/idfs/api/link_create.php</code>, <code>modules/idfs/api/port_update.php</code>, <code>modules/idfs/api/switch_ports_by_equipment.php</code>).</li>
            <li><strong>2026-05-15:</strong> IDF rack <code>position_save.php</code> sync now clears stale <code>SFP</code>/<code>SFP+</code> rows that sit inside an expanded RJ45 footprint (possible because <code>unique_switch_port</code> treats <code>(port_type, port_number)</code> as compound), RJ45 UPSERT collisions now overwrite <code>switch_ports.port_type</code>, synthetic fiber numbering shares the RJ45-tail baseline helpers, rack split renders use dense per-fragment ordinals plus non-empty-capacity overrides, and Fiber/SFP pruning on <code>idf_positions</code> preserves RJ45-offset fiber slots (<code>modules/idfs/api/position_save.php</code>, <code>modules/idfs/port_visualizer_helper.php</code>, <code>modules/idfs/idf_ports_sync.php</code>, <code>modules/idfs/api/ports_regen.php</code>).</li>
            <li><strong>2026-05-15:</strong> Equipment save / switch regen (<code>modules/equipment/create.php</code>) now writes <code>idf_positions.sfp_count</code> from <code>equipment.switch_fiber_ports_number</code> alongside <code>rj45_count</code>, keeping IDF rack slot summary badges aligned with linked switch metadata without altering port visual rendering.</li>
            <li><strong>2026-05-15:</strong> <strong>Regenerate Ports</strong> (<code>modules/idfs/api/ports_regen.php</code>) now deletes <code>idf_links</code> referencing ports that will be dropped and resets surviving peer <code>idf_ports</code>/<code>switch_ports</code> like unlink (avoiding orphaned “connected” display with no unlink control); helpers are shared via <code>modules/idfs/api/_bootstrap.php</code> (<code>modules/idfs/api/link_delete.php</code> reuses the same resets).</li>
            <li><strong>2026-05-14:</strong> IDF rack Edit Device now preselects <code>Numbering Layout</code> from linked <code>equipment.switch_port_numbering_layout_id</code>, mapping legacy/global layout IDs to the active company's matching layout option by name so linked equipment no longer opens with a blank layout select.</li>
            <li><strong>2026-05-14:</strong> Equipment create/edit now saves <code>Workstation Office</code> through the missing <code>equipment.workstation_office_id</code> FK, renames the shared switch section to <code>Network Details</code> for all equipment types, adds an equipment-level <code>RJ45 Cable</code> selector from <code>rj45_speed.cable_type</code>, and Switch Port Manager now loads/saves per-port RJ45 cable selections through <code>switch_ports.rj45_speed_id</code> with mirrored <code>idf_ports.rj45_speed_id</code> sync.</li>
            <li><strong>2026-05-14:</strong> IDF rack Edit Device now re-renders the rack from saved <code>idf_positions.switch_port_numbering_layout_id</code> first (with linked-equipment fallback), joins the selected RJ45 profile for rendered port totals, prevents legacy position-number port bleed-through, and cache-busts the same-page refresh after modal saves so Numbering Layout changes are visible immediately.</li>
            <li><strong>2026-05-14:</strong> IDF rack Edit and Copy now preserve effective Numbering Layout and Port Count by loading layout/RJ45 metadata from linked equipment and existing IDF port rows, then syncing saved layouts back to <code>idf_positions</code>, <code>equipment</code>, and <code>idf_ports</code> as applicable.</li>
            <li><strong>2026-05-14:</strong> IDF rack port clicks in <code>modules/idfs/view.php</code> navigate in the same tab to <code>modules/idfs/device.php</code> with <code>open_link_*</code> when no cable/link is represented yet (<code>data-has-explicit-connection</code> matches device.php “Connected To” semantics), otherwise <code>open_edit_*</code>. Successful link/port saves redirect back through <code>return_to</code> (for example <code>view.php?id=…</code>) via <code>finishInlineMutationOrReload()</code>.</li>
            <li><strong>2026-05-13:</strong> IDF device view parity hardening now mirrors rack-view precedence for <code>status</code>, <code>status_color</code>, <code>connected_to</code>, and cable color resolution (plus company-scoped link lookup), so matching port IDs render consistently between <code>modules/idfs/view.php</code> and <code>modules/idfs/device.php</code>.</li>
            <li><strong>2026-05-13:</strong> IDF rack <code>Edit device</code> now keeps submitted <code>Device Name</code> as the source of truth for linked assets and synchronizes it back to <code>equipment.name</code> (with company-scoped duplicate-name validation) so rack modal name edits immediately appear in equipment views without DB constraint failures (<code>modules/idfs/api/position_save.php</code>).</li>
            <li><strong>2026-05-13:</strong> IDF rack <code>Edit device</code> save flow now applies selected <code>RJ45 Ports</code> capacity to linked switch records by shrinking/growing RJ45 rows in <code>switch_ports</code> and pruning/regenerating corresponding RJ45 rows in <code>idf_ports</code>, so modal RJ45 changes immediately persist in both tables after save/reload (<code>modules/idfs/api/position_save.php</code>).</li>
            <li><strong>2026-05-13:</strong> Equipment edit fiber-port sync now resolves <code>idf_ports.fiber_ports_number</code> through tenant <code>equipment_fiber_count</code> IDs (instead of raw equipment numeric labels), hard-fails on sync SQL errors inside the save transaction, and IDF rack/device compact icons no longer render synthetic <code>Port 0</code> SFP placeholder dots when fiber ports are unconfigured (<code>modules/equipment/create.php</code>, <code>modules/idfs/port_visualizer_helper.php</code>).</li>
            <li><strong>2026-05-13:</strong> FK/relationship display audit for <code>modules/switch_ports/</code>, <code>modules/idf_links/</code>, <code>modules/idf_ports/</code>, and <code>modules/idf_positions/</code> now renders human-readable related values in list/detail views (including tenant-safe fallback lookups for legacy/shared rows), adds richer endpoint labels for <code>idf_links.port_id_a/port_id_b</code>, and normalizes wrapper action routing in <code>modules/idf_positions/index.php</code> so create/edit/view/list_all wrappers are not overwritten.</li>
            <li><strong>2026-05-12:</strong> IDF synchronization hardening now routes all <code>modules/switch_ports/*</code> CRUD entry points through a shared <code>index.php</code> sync path that mirrors create/edit/import/delete changes into <code>idf_ports</code>/<code>idf_links</code>, while equipment save/delete flows now use stricter sync cleanup + transactions to avoid partial writes across <code>equipment</code>, <code>idf_positions</code>, <code>idf_ports</code>, and <code>switch_ports</code> (<code>modules/switch_ports/index.php</code>, <code>modules/switch_ports/create.php</code>, <code>modules/switch_ports/edit.php</code>, <code>modules/switch_ports/view.php</code>, <code>modules/switch_ports/delete.php</code>, <code>modules/switch_ports/list_all.php</code>, <code>modules/equipment/create.php</code>, <code>modules/equipment/delete.php</code>, <code>modules/idfs/api/position_delete.php</code>, <code>modules/idfs/api/position_copy.php</code>, <code>modules/idfs/api/link_delete.php</code>).</li>
            <li><strong>2026-05-09:</strong> IDF rack-position delete confirmation in <code>modules/idfs/view.php</code> now uses clearer English and explicit irreversible-impact warning (ports, links, and sync records are permanently removed).</li>
            <li><strong>2026-05-09:</strong> IDF rack <code>➖</code> now performs a true DB position delete for empty slots through <code>modules/idfs/api/position_remove_slot.php</code>, collapsing higher <code>idf_positions.position_no</code> values by 1 so rack ordering is persisted server-side.</li>
            <li><strong>2026-05-09:</strong> IDF <code>Copy to…</code> now auto-renames duplicate device names (for example <code>Name</code> → <code>Name (2)</code>) and, for linked equipment rows, clones a new <code>equipment</code> record (new Asset ID) plus matching <code>switch_ports</code> rows so copied positions no longer reuse the original linked equipment id (<code>modules/idfs/api/position_copy.php</code>).</li>
            <li><strong>2026-05-09:</strong> IDF <code>Copy to…</code> now always assigns a new Asset ID for non-linked copies and preserves <code>switch_port_numbering_layout_id</code> on copied <code>idf_positions</code>; linked copies still clone a new <code>equipment</code> row + <code>switch_ports</code> and use the new equipment id (<code>modules/idfs/api/position_copy.php</code>).</li>
            <li><strong>2026-05-10:</strong> IDF port save status resolution now enforces tenant-valid <code>switch_status.id</code> values (numeric IDs are validated against company scope) and auto-creates tenant <code>Unknown</code> status when missing, preventing 500 errors from invalid status FKs and ensuring status updates persist correctly (<code>modules/idfs/api/_bootstrap.php</code>).</li>
            <li><strong>2026-05-10:</strong> IDF Create Cable Link destination options now include explicit status and color context per port in the label (<code>Status (UP)</code> and <code>Color Name (#HEX)</code>) using tenant-scoped status/color joins with fallback defaults (<code>Unknown</code>/<code>Gray</code>/<code>#808080</code>) and normalized placeholder cleanup for legacy <code>0</code> values (<code>modules/idfs/device.php</code>).</li>
            <li><strong>2026-05-10:</strong> IDF Create Cable Link now persists selected <code>Status</code> and <code>Cable color</code> consistently across <code>idf_ports</code>, <code>idf_links</code>, and matching <code>switch_ports</code> rows by preventing status override from destination metadata and using robust type-aware switch port matching during sync (<code>modules/idfs/api/link_create.php</code>).</li>
            <li><strong>2026-05-10:</strong> IDF Edit Port now propagates selected <code>Status</code> and <code>Cable color</code> to linked peer <code>idf_ports</code> rows and peer <code>switch_ports</code> mirrors, while also hardening switch sync joins for legacy <code>position_id</code> and mixed <code>port_type</code> storage (<code>modules/idfs/api/port_update.php</code>).</li>
            <li><strong>2026-05-10:</strong> IDF Unlink now resets matching <code>switch_ports</code> rows to <code>Status: Unknown</code> and Gray (<code>#808080</code>) with robust type-aware sync matching; linked <code>idf_ports</code> rows are reset to the same defaults and disconnected state (<code>modules/idfs/api/link_delete.php</code>).</li>
            <li><strong>2026-05-10:</strong> IDF rack view port rendering now prefers linked metadata/status-color fallbacks consistently across both ends of a cable link (including <code>equipment_status_id</code>/<code>cable_color_id</code> paths) so mirrored ports no longer show mismatched Unknown/Gray vs Free/Green states in <code>/modules/idfs/view.php</code>.</li>
            <li><strong>2026-05-10:</strong> IDF switch sync root-fix removed invalid multi-table <code>UPDATE ... JOIN ... LIMIT 1</code> usage and hardened sync execution with explicit DB error responses, preventing silent switch-port desynchronization in link/edit/unlink flows (<code>modules/idfs/api/link_create.php</code>, <code>modules/idfs/api/port_update.php</code>, <code>modules/idfs/api/link_delete.php</code>).</li>
            <li><strong>2026-05-10:</strong> IDF edit/unlink switch sync now includes a direct legacy <code>port_type</code> fallback match (string-safe <code>pr.port_type</code> comparison) so status/color propagation still works when <code>switch_port_types</code> rows are missing or out-of-scope for existing legacy data (<code>modules/idfs/api/port_update.php</code>, <code>modules/idfs/api/link_delete.php</code>).</li>
            <li><strong>2026-05-10:</strong> IDF position lifecycle now keeps <code>equipment.idf_id</code> and <code>switch_ports.idf_id</code> aligned on create/update/delete from rack workflows, including detach cleanup when a linked equipment is removed or moved (<code>modules/idfs/api/position_save.php</code>, <code>modules/idfs/api/position_delete.php</code>).</li>
            <li><strong>2026-05-10:</strong> IDF <code>Copy to…</code> overwrite flow now detaches the overwritten target linked equipment from IDF sync when no remaining rack position references it (<code>equipment.idf_id</code> and matching <code>switch_ports.idf_id</code> are set to <code>NULL</code>), and the rack UI now shows a confirmation dialog before overwrite is executed (<code>modules/idfs/api/position_copy.php</code>, <code>modules/idfs/view.php</code>).</li>
            <li><strong>2026-05-10:</strong> IDF modals no longer close when clicking outside the dialog (backdrop-click close disabled); explicit in-modal <code>Cancel</code> actions are now provided for Device/Copy/Edit Port/Create Link flows (<code>modules/idfs/view.php</code>, <code>modules/idfs/device.php</code>).</li>
            <li><strong>2026-05-10:</strong> Added human-style E2E regression script <code>scripts/idfs_sync_human_test.php</code> to validate synchronized create/edit/update/delete behavior across <code>idf_ports</code>, <code>switch_ports</code>, <code>equipment</code>, and <code>idf_links</code>.</li>
            <li><strong>2026-05-15:</strong> IDF device port table sorting helpers live in <code>includes/idf_device_port_sort_sql.php</code> so copper rows always sort before fiber for any chosen column; <code>scripts/idf_device_port_sort_test.php</code> asserts <code>device.php</code> wiring, runs a mandatory synthetic MySQL ORDER BY proof (fails if DB is unreachable unless <code>--offline-only</code>), and runs an optional live duplex monotonic check when RJ45+SFP share the same <code>position_id/port_no</code>.</li>
            <li><strong>2026-05-09:</strong> Equipment-to-IDF sync now bootstraps the first <code>idf_positions</code> slot when the destination IDF has no rows, mirrors linked <code>switch_ports</code> (RJ45/SFP) into <code>idf_ports</code>, and keeps <code>switch_ports.idf_id</code> aligned during create/update/delete flows (<code>modules/equipment/create.php</code>).</li>
            <li><strong>2026-05-09:</strong> Equipment create/edit now synchronizes IDF mapping lifecycle from the selected <code>idf_id</code>: it creates/updates/deletes linked <code>idf_positions</code> and seeds <code>idf_ports</code> for RJ45 capacity; when destination IDF has no empty slot, save is blocked with <code>There is none Empty positions, add more positions on IDF.</code> (<code>modules/equipment/create.php</code>).</li>
            <li><strong>2026-05-09:</strong> Equipment now enforces company-scoped optional uniqueness for <code>serial_number</code>, <code>hostname</code>, and <code>ip_address</code> (nullable allowed) via schema unique keys and create/edit validation checks (<code>database.sql</code>, <code>modules/equipment/create.php</code>).</li>
            <li><strong>2026-05-09:</strong> Switch Port Manager To IDF auto-add now validates destination capacity: when no empty position exists in the selected IDF, save is blocked with an explicit alert to add more IDF positions before creating synced <code>idf_ports</code> rows (<code>includes/update_port.php</code>).</li>
            <li><strong>2026-05-09:</strong> Switch Port Manager <code>To IDF</code> saves now synchronize both tables by creating/updating matching <code>idf_ports</code> rows from <code>switch_ports</code> metadata (label/status/vlan/speed/management/notes + connected destination), and removing only auto-synced IDF rows when destination is cleared (<code>includes/update_port.php</code>).</li>
            <li><strong>2026-05-09:</strong> Switch Port Manager add/edit now enforces <code>switch_ports.management_id</code> from <code>equipment.switch_environment_id</code> on save, and equipment create/edit also backfills existing switch port rows for the same equipment to keep management values synchronized (<code>includes/update_port.php</code>, <code>modules/equipment/create.php</code>).</li>
            <li><strong>2026-05-09:</strong> Switch/IDF port schemas now include <code>management_id</code> (FK to <code>equipment_environment</code>) and runtime migrations backfill missing values to tenant <code>Unmanaged</code>; switch/IDF port seeding and regeneration now persist this default when no explicit management value is provided (<code>database.sql</code>, <code>modules/idfs/api/_bootstrap.php</code>, <code>includes/get_ports.php</code>, <code>modules/idfs/api/ports_regen.php</code>, <code>modules/idfs/api/position_save.php</code>, <code>modules/idfs/api/position_copy.php</code>).</li>
            <li><strong>2026-05-09:</strong> IDF ports schema/runtime now includes <code>fiber_ports_number</code> (FK to <code>equipment_fiber_count</code>), <code>switch_port_numbering_layout_id</code> (FK to <code>switch_port_numbering_layout</code>), and <code>management_id</code> (FK to <code>equipment_environment</code>), and IDF port create/copy/regenerate flows now persist these values (<code>database.sql</code>, <code>modules/idfs/api/_bootstrap.php</code>, <code>modules/idfs/api/position_save.php</code>, <code>modules/idfs/api/position_copy.php</code>, <code>modules/idfs/api/ports_regen.php</code>).</li>
            <li><strong>2026-05-09:</strong> IDF position APIs now enforce unique <code>idf_positions.device_name</code> per company across all IDFs; <code>position_save.php</code> and <code>position_copy.php</code> return a validation error on duplicates, and schema docs include <code>uq_idf_positions_company_device_name (company_id, device_name)</code>.</li>
            <li><strong>2026-05-09:</strong> IDF Create Cable Link destination list is now deterministically ordered by IDF name, position number, device name, then port number to prevent mixed destination-port ordering (<code>modules/idfs/device.php</code>).</li>
            <li><strong>2026-05-09:</strong> IDF Create Cable Link linked-equipment flow now uses the same swatch + cable color dropdown UI as Edit (instead of free-text/picker), including quick-add support and prefill from selected equipment port color metadata (<code>modules/idfs/device.php</code>).</li>
            <li><strong>2026-05-08:</strong> IDF Create Cable Link now persists cable color name/hex for standard and linked-equipment flows, resolving or creating tenant cable color rows when needed and syncing color values to <code>switch_ports</code> (<code>color_id</code>), <code>idf_links</code> (<code>cable_color_id</code>/<code>cable_color_hex</code>), and <code>idf_ports</code> (<code>cable_color</code>/<code>hex_color</code>) (<code>modules/idfs/device.php</code>, <code>modules/idfs/api/link_create.php</code>).</li>
            <li><strong>2026-05-08:</strong> IDF port regeneration now rebuilds both <code>idf_ports</code> and linked-equipment <code>switch_ports</code> in one transaction (RJ45 + SFP/SFP+), resetting stale switch rows during Regenerate Ports (<code>modules/idfs/api/ports_regen.php</code>).</li>
            <li><strong>2026-05-08:</strong> IDF device ports page now keeps the <code>Label</code> column tenant-local to <code>idf_ports</code>/<code>idf_links</code> metadata and no longer backfills it from live <code>switch_ports</code>, so <code>Regenerate Ports</code> clears stale labels as expected (<code>modules/idfs/device.php</code>).</li>
            <li><strong>2026-05-08:</strong> IDF cable link creation now persists linked-mode custom cable color name/hex from the modal inputs (<code>linked_cable_color</code> and color picker) into IDF port/link metadata instead of silently reverting to default cable color selection (<code>modules/idfs/device.php</code>, <code>modules/idfs/api/link_create.php</code>).</li>
            <li><strong>2026-05-08:</strong> IDF device modal now hides <code>Numbering Layout</code> when <code>Device Type</code> is <code>UPS</code>, and clears saved layout selection for UPS flows (<code>modules/idfs/view.php</code>).</li>
            <li><strong>2026-05-08:</strong> IDF position save endpoint now enforces <code>UPS</code> entries with <code>port_count=0</code> and only auto-derives port counts from linked RJ45 profiles for <code>Switch</code> device type (<code>modules/idfs/api/position_save.php</code>).</li>
            <li><strong>2026-05-08:</strong> IDF device modal now hides <code>Port Count</code> when the selected device type is <code>UPS</code> in add/edit flows (<code>modules/idfs/view.php</code>).</li>
            <li><strong>2026-05-08:</strong> IDF position save endpoint (<code>modules/idfs/api/position_save.php</code>) now enforces duplicate linked-equipment blocking only for <code>Switch</code> device type, allowing non-switch rack entries (for example UPS/server placeholders) to reuse the same linked equipment across positions.</li>
            <li><strong>2026-05-08:</strong> IDF position save endpoint (<code>modules/idfs/api/position_save.php</code>) now allows linked equipment entries with the same <code>device_name</code>; duplicate prevention is enforced by <code>equipment_id</code> for linked assets.</li>
            <li><strong>2026-05-07:</strong> IDF port regeneration endpoint (<code>modules/idfs/api/ports_regen.php</code>) now recreates both RJ45 and SFP/SFP+ rows from tenant-scoped equipment metadata instead of RJ45-only regeneration.</li>
            <li><strong>2026-05-01:</strong> IDF detail view joins now enforce tenant-scoped lookups across <code>equipment</code>, <code>equipment_fiber</code>, and <code>equipment_rj45</code> so rack/device rendering stays synchronized with <code>idf_ports</code> and live <code>switch_ports</code> rows for the active company.</li>
            <li><strong>2026-04-30:</strong> IDF device ports page (<code>modules/idfs/device.php</code>) now defaults to sorting by <code>idf_ports.port_type ASC</code> and supports ASC/DESC sorting toggles for #, Type, Label, Status, Connected To, VLAN, Speed, PoE, Notes, and Link columns.</li>
            <li><strong>2026-04-30:</strong> IDF detail view (<code>modules/idfs/view.php</code>) now falls back to tenant-scoped live <code>switch_ports</code> data when <code>idf_ports</code> rows are not yet present.</li>
            <li>Fallback port numbering normalizes non-numeric labels into numeric slots so the visualizer grid still renders.</li>
        </ul>
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
