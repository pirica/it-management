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
 * user_sidebar_preferences seed data now bootstraps a cleaned default sidebar layout
 * for companies 1-5 using schema defaults for created_at/updated_at timestamps.
 * Sidebar seed ordering now places switch_ports under reference_data before switch_port_numbering_layout for all base companies.
 * Sidebar preference loading now reconciles legacy user_sidebar_preferences rows in DB so switch_ports is stored under reference_data above switch_status.
 * database.sql equipment seed rows were corrected to align VALUES counts with the 44-column equipment insert signature.
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
