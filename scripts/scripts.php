<?php
define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';

// Why: Audited and synchronized; lists all functional scripts with secure relative paths.
// All functional, reproduction, and verification scripts have been audited for absolute correctness.
// References to obsolete directories (like fixed_files/) have been removed or updated.
// Why: Script catalog lists destructive CLI repro tools; browser view is admin-only (no web runner links).
if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg' && !itm_is_admin($conn, (int)($_SESSION['employee_id'] ?? 0))) {
    http_response_code(403);
    die('Access denied. Administrator privileges required.');
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>IT Management — Scripts</title>
    <link rel="stylesheet" href="../css/styles.css">
    <style>
        body { padding: 0; margin: 0; background-color: var(--bg-secondary, #f6f8fa); }
        .scripts-wrap { max-width: 1400px; width: 95%; margin: 0 auto; padding: 24px 20px 48px; min-height: calc(100vh - 60px); }
        .scripts-card { background: var(--bg-primary, #fff); border: 1px solid var(--border, #d0d7de); border-radius: 8px; margin-bottom: 20px; padding: 18px 20px; }
        .scripts-muted { color: var(--text-secondary, #57606a); margin: 0 0 12px; line-height: 1.5; }
        .scripts-table-wrap { overflow-x: auto; margin-bottom: 20px; border: 1px solid var(--border, #d0d7de); border-radius: 8px; -webkit-overflow-scrolling: touch; background: var(--bg-primary, #fff); }
        .scripts-table-wrap::-webkit-scrollbar { height: 8px; }
        .scripts-table-wrap::-webkit-scrollbar-track { background: var(--bg-secondary); border-radius: 4px; }
        .scripts-table-wrap::-webkit-scrollbar-thumb { background: var(--border); border-radius: 4px; }
        .scripts-table-wrap::-webkit-scrollbar-thumb:hover { background: var(--text-tertiary); }
        .scripts-table { width: 100%; border-collapse: collapse; font-size: 0.9rem; border: none; min-width: max-content; }
        .scripts-table th, .scripts-table td { border: 1px solid var(--border, #d0d7de); padding: 12px 16px; text-align: left; vertical-align: top; }
        .scripts-table th, .scripts-table td { white-space: nowrap; }
        .scripts-table th { background: var(--bg-secondary, #f6f8fa); }
        .scripts-table th:first-child,
        .scripts-table td:first-child,
        .scripts-table th.scripts-access-col,
        .scripts-table td.scripts-access-cell { white-space: nowrap; width: 1%; }
        .scripts-table code { font-size: 0.88rem; word-break: break-word; }
        .scripts-table td:last-child { white-space: normal; min-width: 300px; }
        .scripts-access-badges { display: inline-flex; flex-wrap: nowrap; gap: 4px; align-items: center; }
        .scripts-badge { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 0.75rem; font-weight: 600; white-space: nowrap; }
        .scripts-badge-web { background: #ddf4ff; color: #0969da; border: 1px solid #c0e6ff; }
        .scripts-badge-cli { background: #f6f8fa; color: #24292f; border: 1px solid #d0d7de; }
        .scripts-toc { display: flex; flex-wrap: wrap; gap: 8px 14px; margin: 0 0 8px; padding: 0; list-style: none; }
        .scripts-toc a { color: #0969da; text-decoration: none; }
        .scripts-toc a:hover { text-decoration: underline; }
        .scripts-toc a.scripts-toc-external::after { content: " ↗"; font-size: 0.85em; }
        h1 { margin: 0 0 8px; }
        h2 { margin: 0 0 12px; font-size: 1.15rem; }
        .scripts-cli-hint { margin-top: 16px; padding: 12px; border-radius: 6px; background: var(--bg-secondary, #f6f8fa); border: 1px solid var(--border, #d0d7de); font-size: 0.9rem; }
        .scripts-top-nav { position: sticky; top: 0; z-index: 100; margin: 0 0 16px; padding: 10px 20px; background: var(--bg-primary, #fff); border-bottom: 1px solid var(--border, #d0d7de); box-shadow: 0 1px 3px rgba(27, 31, 36, 0.08); }
        .scripts-top-nav-inner { max-width: 1400px; width: 95%; margin: 0 auto; display: flex; flex-wrap: wrap; align-items: center; gap: 10px 16px; }
        .scripts-top-nav-brand { font-weight: 700; color: var(--text-primary, #24292f); text-decoration: none; white-space: nowrap; }
        .scripts-top-nav-brand:hover { text-decoration: underline; }
        .scripts-top-nav-links { display: flex; flex-wrap: wrap; gap: 6px 12px; margin: 0; padding: 0; list-style: none; flex: 1 1 auto; }
        .scripts-top-nav-links a { color: #0969da; text-decoration: none; font-size: 0.9rem; white-space: nowrap; }
        .scripts-top-nav-links a:hover { text-decoration: underline; }
        .scripts-top-nav-links a.scripts-toc-external::after { content: " ↗"; font-size: 0.85em; }
        .scripts-top-nav-home { color: #0969da; text-decoration: none; font-size: 0.9rem; white-space: nowrap; margin-left: auto; }
        .scripts-top-nav-home:hover { text-decoration: underline; }
    </style>
</head>
<body>
<nav class="scripts-top-nav" aria-label="Scripts directory sections">
    <div class="scripts-top-nav-inner">
        <a class="scripts-top-nav-brand" href="scripts.php">Scripts</a>
        <ul class="scripts-top-nav-links">
            <li><a href="#docs">Documentation</a></li>
            <li><a href="#browser">Browser tools</a></li>
            <li><a href="#security">Security</a></li>
            <li><a href="#ci">CI &amp; static analysis</a></li>
            <li><a href="#database">Database</a></li>
            <li><a href="#idf">IDF &amp; equipment</a></li>
            <li><a href="#ui-modules">UI &amp; modules</a></li>
            <li><a href="#admin-tools">Administrative Tools</a></li>
            <li><a href="#system-status">System Status</a></li>
            <li><a href="http://myhome.dynip.sapo.pt/phpmyadmin/" class="scripts-toc-external" target="_blank" rel="noopener noreferrer">phpMyAdmin</a></li>
            <li><a href="https://github.com/pirica/it-management" class="scripts-toc-external" target="_blank" rel="noopener noreferrer">Github</a></li>
        </ul>
        <a class="scripts-top-nav-home" href="../index.php">← Home</a>
    </div>
</nav>
<div class="scripts-wrap">
    <div class="scripts-card">
        <h1>Scripts directory</h1>
        <p class="scripts-muted">
            Maintenance, audits, and developer tools for the IT Management System.
            <strong>Browser</strong> = open the script URL (HTML UI when built; otherwise a plain-text report). Access requires login OR authorized source (IP <code>127.0.0.1</code> / <code>::1</code>, or <code>ITM_MAINTENANCE_TOKEN</code> via <code>?token=</code> or <code>X-ITM-Maintenance-Token</code> header). Every browser script must show <strong>← Scripts index</strong> back to this page (use <code>scripts/lib/script_browser_nav.php</code>, <code>lib/utf8_file.php</code> (UTF-8 BOM file I/O), <code>lib/mbqa_import_helpers.php</code> (QA import resolution), <code>lib/mbqa_report_paths.php</code> (QA report naming), <code>lib/mbqa_step_display.php</code> (QA result display)).
            <strong>CLI</strong> = terminal or CI from the project root with PHP 7.4+ (preferred for exit codes and long scans).
            <strong>CLI-only</strong> = <code>smoke_test.sh</code> (bash), explicit <code>PHP_SAPI !== 'cli'</code> guards, or unsafe unattended file writes.
        </p>
        <p class="scripts-muted">
            <strong>New script checklist (see <code>AGENTS.md</code> § Scripts directory):</strong> catalog row (what + how + Browser/CLI), <strong>← Scripts index</strong> on every HTML report, human-readable results, <code>target="_blank"</code> relative links to <code>../modules/…</code> when a module folder exists, and table names linked only when <code>modules/&lt;table&gt;/</code> exists. <strong>phpMyAdmin</strong> (<code>http://localhost/phpmyadmin/</code>) is linked from this catalog page only—not inside other scripts.
        </p>
        <p class="scripts-muted">
            <strong>Data mutation quick reference:</strong> these scripts add sample/test rows in the DB: <code>module_browser_qa_runner.php</code>, <code>employees_delete_clear_table_test.php</code>, <code>equipment_delete_clear_table_test.php</code>, <code>explorer_human_test.php</code>, <code>floor_plans_folder_move_test.php</code>, <code>idfs_sync_human_test.php</code>, <code>auth_register_reset_human_test.php</code>, <code>tickets_related_equipment_delete_test.php</code>. Dump-only helper: <code>export_floor_plan_folders_seed.php</code> (prints <code>INSERT</code> SQL to stdout).
        </p>
        <div class="scripts-cli-hint">
            <strong>CLI example:</strong>
            <code>C:\&lt;folder&gt;\bin\php\php-7.4.33-nts-Win32-vc15-x64\php.exe scripts\&lt;script&gt;.php [options]</code><br>
            <code>C:\Users\NelsonSalvador\Downloads\laragon-portable\bin\php\php-7.4.33-nts-Win32-vc15-x64\php.exe scripts\apply_module_sample_data_seed.php</code><br>
            <strong>From project root:</strong> <code>php scripts/&lt;script&gt;.php [options]</code>
        </div>
    </div>

    <div class="scripts-card" id="docs">
        <h2>Documentation</h2>
        <div class="scripts-table-wrap"><table class="scripts-table">
            <thead>
                <tr>
                    <th>Script</th>
                    <th class="scripts-access-col">Access</th>
                    <th>What it does</th>
                    <th>How to use</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><a href="api.php" target="_blank" rel="nofollow noreferrer">api.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span></span></td>
                    <td>HTML reference for JSON/AJAX endpoints: Explorer file manager, Switch Port Manager (<code>includes/get_ports.php</code>, <code>includes/update_port.php</code>), IDF APIs, module imports (auto-detected), passwords, notes, todo, System Status API, and shared includes. Switch-port handlers document <code>itm_api_json_response()</code> contracts and mysqlnd-safe fetch helpers.</td>
                    <td>Open <code>scripts/api.php</code> in the browser after API changes. Run <code>php scripts/run_tests.php --filter ApiFunctionsTest</code> when editing collector helpers. After switch-port changes also run <code>php scripts/idfs_sync_human_test.php</code>.</td>
                </tr>
                <tr>
                    <td><a href="pitfalls.php" target="_blank" rel="nofollow noreferrer">pitfalls.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Aggregated pitfalls and developer traps from every <code>AGENT_NOTES.md</code> in the repository (not only <code>modules/</code>). Backfills missing note files under <code>modules/</code> only. Reviewed empty §10 sections may use <code>[Confirmed] No pitfalls documented</code>. Skips top-level upload roots but still includes <code>modules/floor_plans/</code>.</td>
                    <td>Open <code>scripts/pitfalls.php</code> in the browser (Admin only). CLI: <code>php scripts/pitfalls.php [-module=&lt;slug&#124;path&gt;] [--json]</code></td>                </tr>
                <tr>
                    <td><a href="SCRIPTS.md" target="_blank" rel="nofollow noreferrer">SCRIPTS.md</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">Markdown</span></span></td>
                    <td>Development standards for the scripts directory (catalog, newlines, security, retention).</td>
                    <td>Read in repository root or online. Follow rules when creating new utilities.</td>
                </tr>
                <tr>
                    <td><a href="SCRIPTS_TEST_MATRIX.md" target="_blank" rel="nofollow noreferrer">SCRIPTS_TEST_MATRIX.md</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">Markdown</span></span></td>
                    <td>Full catalog verification matrix: tiers 0–5, runner coverage map, Tier 5 exclusion list, destroy→document→fresh <code>database.sql</code> clone protocol. Companion logs: <code>data/scripts-matrix-destroy-log.md</code>, <code>data/scripts_errors.txt</code> (latest safe-matrix run).</td>
                    <td>Read before blanket <code>scripts/*</code> verification. Update in the same PR when adding catalog rows. Do not use <code>perform_audit.php</code> as a quality gate.</td>
                </tr>
            </tbody>
        </table></div>
    </div>

    <div class="scripts-card" id="browser">
        <h2>Browser tools</h2>
        <div class="scripts-table-wrap"><table class="scripts-table">
            <thead>
                <tr>
                    <th>Script</th>
                    <th class="scripts-access-col">Access</th>
                    <th>What it does</th>
                    <th>How to use</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><a href="DBdesign.php" target="_blank" rel="nofollow noreferrer">DBdesign.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>ER-style diagram generated from <code>database.sql</code> (Mermaid render, zoom, SVG/PNG export).</td>
                    <td>
                        Open <a href="DBdesign.php" target="_blank" rel="nofollow noreferrer">DBdesign.php</a>. Optional:
                        <a href="DBdesign.php?format=mermaid">?format=mermaid</a>,
                        <a href="DBdesign.php?format=json">?format=json</a>.
                        CLI: <code>php scripts/DBdesign.php --mermaid</code> or <code>--json</code>.
                    </td>
                </tr>
                <tr>
                    <td><a href="crud_tables.php" target="_blank" rel="nofollow noreferrer">crud_tables.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Lists every module folder and the first <code>$crud_table =</code> line found in <code>index.php</code>, with links to each module.</td>
                    <td>Browser: HTML report. CLI: <code>php scripts/crud_tables.php</code> (HTML to stdout) or <code>&gt; crud_tables.html</code>.</td>
                </tr>
                <tr>
                    <td><a href="crud_titles.php" target="_blank" rel="nofollow noreferrer">crud_titles.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Lists every module folder and the first <code>$crud_title =</code> line found in <code>index.php</code>, with links to each module.</td>
                    <td>Browser: HTML report. CLI: <code>php scripts/crud_titles.php</code> (HTML to stdout) or <code>&gt; crud_titles.html</code>.</td>
                </tr>
                <tr>
                    <td><a href="crud_actions.php" target="_blank" rel="nofollow noreferrer">crud_actions.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Lists every module folder and the first <code>$crud_action =</code> line found in <code>index.php</code>, with links to each module.</td>
                    <td>Browser: HTML report. CLI: <code>php scripts/crud_actions.php</code> (HTML to stdout) or <code>&gt; crud_actions.html</code>.</td>
                </tr>
                <tr>
                    <td><a href="update_all_created_at.php">update_all_created_at.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>After importing <code>database.sql</code>, sets every live row’s <code>created_at</code> to one timestamp (default <code>2026-01-01 00:00:01</code>). Dry-run previews counts first.</td>
                    <td><strong>Log in first.</strong> Open <a href="update_all_created_at.php">update_all_created_at.php</a> (HTML + <strong>← Scripts index</strong>). CLI: <code>php scripts/update_all_created_at.php --dry-run</code></td>
                </tr>
                <tr>
                    <td><a href="detect_fk_dropdown_ui_risk_ui.php" target="_blank" rel="nofollow noreferrer">detect_fk_dropdown_ui_risk_ui.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span></span></td>
                    <td>Scans cross-tenant FK rows and module code that can cause <strong>duplicate dropdown options</strong>. Results are plain-language summaries with links to <code>modules/…/</code> and edit screens (new tab) when a module folder exists.</td>
                    <td>
                        <strong>Log in first.</strong> Open the UI → <strong>← Scripts index</strong> at top → choose scan mode, company, risk filter, output (table/JSON) → <strong>Run scan</strong>.
                        CLI: <code>php scripts/detect_fk_dropdown_ui_risk.php</code> · <code>--company=N</code> · <code>--json</code> · <code>--data-only</code> · <code>--code-only</code> · <code>--repair-catalogs</code> (one-time legacy DB cleanup).
                    </td>
                </tr>
                <tr>
                    <td><a href="detect_fk_dropdown_ui_risk.php" target="_blank" rel="nofollow noreferrer">detect_fk_dropdown_ui_risk.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Same scanner as the UI. Browser requests load <code>detect_fk_dropdown_ui_risk_ui.php</code>; CLI prints human-readable lines (or JSON with <code>--json</code>).</td>
                    <td>
                        Browser: opens the UI. CLI:
                        <code>php scripts/detect_fk_dropdown_ui_risk.php [--company=N] [--json] [--data-only] [--code-only] [--repair-catalogs] [--help]</code>.
                    </td>
                </tr>
                <tr>
                    <td><a href="list_boolean_integer_fields.php" target="_blank" rel="nofollow noreferrer">list_boolean_integer_fields.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Lists all database fields that can be Boolean, int, tinyint, and others, matching tables to modules and formatting output precisely.</td>
                    <td>
                        <strong>Log in first.</strong> Open <a href="list_boolean_integer_fields.php">list_boolean_integer_fields.php</a>.
                        CLI: <code>php scripts/list_boolean_integer_fields.php [--source=sql|db|both]</code>
                    </td>
                </tr>
                <tr>
                    <td><a href="list_enum_fields.php" target="_blank" rel="nofollow noreferrer">list_enum_fields.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Lists all database fields of ENUM type, matching tables to modules and formatting output precisely.</td>
                    <td>
                        <strong>Log in first.</strong> Open <a href="list_enum_fields.php">list_enum_fields.php</a>.
                        CLI: <code>php scripts/list_enum_fields.php [--source=sql|db|both]</code>
                    </td>
                </tr>
                <tr>
                    <td><a href="extract_by_fields.php" target="_blank" rel="nofollow noreferrer">extract_by_fields.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Scans database.sql and lists table fields matching keywords (by, to, employee_id, employee).</td>
                    <td>
                        <strong>Log in first.</strong> Open <a href="extract_by_fields.php">extract_by_fields.php</a>.
                        CLI: <code>php scripts/extract_by_fields.php</code>
                    </td>
                </tr>
                <tr>
                    <td><a href="debug.php" target="_blank" rel="nofollow noreferrer">debug.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span></span></td>
                    <td>System overview (DB, tables, PHP version, extensions, and file permissions).</td>
                    <td>Open in browser for quick troubleshooting.</td>
                </tr>
                <tr>
                    <td><a href="health.php" target="_blank" rel="nofollow noreferrer">health.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span></span></td>
                    <td>Lightweight health check endpoint for monitoring.</td>
                    <td>Open in browser or use with monitoring tools.</td>
                </tr>
            </tbody>
        </table></div>
    </div>

    <div class="scripts-card" id="security">
        <h2>Security (interactive)</h2>
        <p class="scripts-muted">Browser-first sandboxes and form tests. Repo-wide static scanners are under <a href="#ci">CI &amp; static analysis</a>.</p>
        <div class="scripts-table-wrap"><table class="scripts-table">
            <thead>
                <tr>
                    <th>Script</th>
                    <th class="scripts-access-col">Access</th>
                    <th>What it does</th>
                    <th>How to use</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><a href="test_form_failed_save_display.php" target="_blank" rel="nofollow noreferrer">test_form_failed_save_display.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Scans all module create forms for SQL-quoted re-display after failed saves (e.g. <code>value="'USA'"</code>); optional runtime POST tests per module.</td>
                    <td>
                        <strong>Log in first.</strong> Open <a href="test_form_failed_save_display.php">test_form_failed_save_display.php</a>, run static scan, optionally enable <em>Runtime HTTP tests</em>.
                        CLI: <code>php scripts/test_form_failed_save_display.php</code> · runtime: <code>--runtime</code> with <code>ITM_TEST_BASE_URL</code> and <code>ITM_TEST_COOKIE</code>.
                    </td>
                </tr>
                <tr>
                    <td><a href="test_sql_injection.php" target="_blank" rel="nofollow noreferrer">test_sql_injection.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span></span></td>
                    <td>JSON sandbox to test SQL-injection signature detection and safe prepared-statement execution (GET/POST).</td>
                    <td>
                        <strong>Requires login</strong> and active <code>company_id</code>.
                        GET/POST JSON with a test payload; POST must include a valid CSRF token.
                        Use only in dev/staging — not a public endpoint.
                    </td>
                </tr>
                <tr>
                    <td><a href="apply_form_failed_save_display_fix.php" target="_blank" rel="nofollow noreferrer">apply_form_failed_save_display_fix.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Bulk-applies <code>cr_form_display_value</code> / POST normalization fixes across CRUD entry files (companion to the form test above). <strong>Default = dry-run</strong>; writes with CLI <code>--apply</code> or browser <code>?apply=1</code> (Admin). Lists scanned / changed / skipped targets. Optional <code>--module</code> / <code>?module=</code> filter.</td>
                    <td>Browser: <a href="apply_form_failed_save_display_fix.php">dry-run</a> / <a href="apply_form_failed_save_display_fix.php?apply=1">apply=1</a> · <a href="apply_form_failed_save_display_fix.php?module=manufacturers">?module=manufacturers</a>. CLI: <code>php scripts/apply_form_failed_save_display_fix.php</code> then <code>php scripts/apply_form_failed_save_display_fix.php --apply</code> · <code>--module=manufacturers</code></td>
                </tr>
                <tr>
                    <td><a href="test_db_error_messages.php" target="_blank" rel="nofollow noreferrer">test_db_error_messages.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Read-only assertion harness for <code>itm_format_db_constraint_error()</code>, <code>itm_render_alert_errors()</code>, and <code>itm_humanize_api_error_message()</code> (e.g. NOT NULL → “Please select a value for Employee”; strips <code>Database error:</code> / <code>DB error:</code> prefixes). Lists passed/failed assertion labels.</td>
                    <td>Browser: <a href="test_db_error_messages.php">run</a>. CLI: <code>php scripts/test_db_error_messages.php</code> — exit <code>1</code> on any failed assertion. Run after changing <code>config/config.php</code>, <code>includes/ui_alert_helpers.php</code>, or IDF/header flash paths.</td>
                </tr>
                <tr>
                    <td><a href="apply_human_friendly_error_display.php" target="_blank" rel="nofollow noreferrer">apply_human_friendly_error_display.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Replaces duplicated <code>alert alert-error</code> blocks with <code>itm_render_alert_errors()</code> across module PHP files (all modules, including bespoke folders). <strong>Default = dry-run</strong>; writes with CLI <code>--apply</code> or browser <code>?apply=1</code> (Admin). Lists scanned / changed / skipped targets. Optional <code>--module</code> / <code>?module=</code> filter.</td>
                    <td>Browser: <a href="apply_human_friendly_error_display.php">dry-run</a> / <a href="apply_human_friendly_error_display.php?apply=1">apply=1</a> · <a href="apply_human_friendly_error_display.php?module=approvers">?module=approvers</a>. CLI: <code>php scripts/apply_human_friendly_error_display.php</code> then <code>php scripts/apply_human_friendly_error_display.php --apply</code> · <code>--module=approvers</code></td>
                </tr>
                <tr>
                    <td><a href="test_email_forgot.php" target="_blank" rel="nofollow noreferrer">test_email_forgot.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Test script for Password Reset email delivery verification.</td>
                    <td>
                        <strong>Log in first.</strong> Open <a href="test_email_forgot.php">test_email_forgot.php</a>.
                        CLI: <code>php scripts/test_email_forgot.php email=test@example.com</code> or <code>php scripts/test_email_forgot.php email=test@example.com --company=1</code>
                    </td>
                </tr>
                <tr>
                    <td><a href="test_register_mail.php" target="_blank" rel="nofollow noreferrer">test_register_mail.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Test script for Registration Welcome email delivery verification.</td>
                    <td>
                        <strong>Log in first.</strong> Open <a href="test_register_mail.php">test_register_mail.php</a>.
                        CLI: <code>php scripts/test_register_mail.php email=test@example.com</code> or <code>php scripts/test_register_mail.php email=test@example.com --company=1</code>
                    </td>
                </tr>
                <tr>
                    <td><a href="verify_user_config_profile.php" target="_blank" rel="nofollow noreferrer">verify_user_config_profile.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span><span class="scripts-badge scripts-badge-web">Browser</span></span></td>
                    <td>Regression for <code>user-config.php</code> profile fields: home-company UPDATE (vs tenant switcher), birthday/theme/emergency round-trip, and profile photo URL/serve contract (app-absolute Explorer proxy, not <code>../../modules/…</code>).</td>
                    <td><code>php scripts/verify_user_config_profile.php</code> — exit <code>1</code> on failure. Run when changing <code>user-config.php</code>, <code>includes/employee_profile_photo.php</code>, or Explorer <code>file.php</code> profile photo serving.</td>
                </tr>
                <tr>
                    <td><a href="verify_emails_module.php" target="_blank" rel="nofollow noreferrer">verify_emails_module.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span><span class="scripts-badge scripts-badge-web">Browser</span></span></td>
                    <td>Regression checks for Email Management tables, registry row, SMTP seed, alert rules, <code>itm_send_email()</code>, and company 1 30-day warranty/license alert window (hard fail; disposable sample insert when empty).</td>
                    <td><code>php scripts/verify_emails_module.php</code> — exit <code>1</code> on failure. Run when changing <code>modules/emails/</code>, <code>includes/itm_email.php</code>, or <code>email*</code> / alert-window seed rows in <code>database.sql</code>.</td>
                </tr>
                <tr>
                    <td><a href="run_email_alert_rules.php" target="_blank" rel="nofollow noreferrer">run_email_alert_rules.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span><span class="scripts-badge scripts-badge-web">Browser</span></span></td>
                    <td>Dispatches enabled <code>email_alert_rules</code> (warranty, license, certificate, alerts, notes, to-do, events) using tenant default SMTP.</td>
                    <td><code>php scripts/run_email_alert_rules.php</code>, <code>php scripts/run_email_alert_rules.php --company=1</code>, or <code>php scripts/run_email_alert_rules.php --verbose</code>. Schedule via cron; admin browser access. Use <code>--verbose</code> when dispatched count is 0 to see per-rule match notes.</td>
                </tr>
                <tr>
                    <td><a href="../js/itm-user-errors.js">itm-user-errors.js</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span></span></td>
                    <td>Global JS helpers <code>itmNotifyError()</code>, <code>itmNotifyAjaxError()</code> (modal-aware), and <code>itmNotifySuccess()</code> for themed in-page alerts after AJAX/modal failures (loaded from <code>includes/header.php</code>).</td>
                    <td>Included on all standard pages via header. IDF modals use <code>itmNotifyAjaxError()</code> so errors render inside the open modal instead of behind the backdrop.</td>
                </tr>
            </tbody>
        </table></div>
    </div>
<div class="scripts-card" id="tests">
        <h2>PHPUnit</h2>
        <div class="scripts-table-wrap"><table class="scripts-table">
            <thead>
                <tr>
                    <th>Script</th>
                    <th class="scripts-access-col">Access</th>
                    <th>What it does</th>
                    <th>How to use</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><a href="run_tests.php" target="_blank" rel="nofollow noreferrer">run_tests.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Runs the PHPUnit suite in <code>phpunit/tests/Unit/</code> via <code>phpunit/phpunit.phar</code> and <code>phpunit/phpunit.xml</code>. Browser menu: <strong>Standard</strong> (verbose) or <strong>HTML coverage</strong> (Xdebug/PCOV). Report: <code>phpunit/coverage/html/coverage.html</code>. Skips coverage driver when Xdebug/PCOV missing. <code>processUncoveredFiles="false"</code> in phpunit.xml for reliable report generation. Entry guards: <code>includes/itm_script_entry_guard.php</code>. See <code>scripts/SCRIPTS.md</code> (PHPUnit test runner).</td>
                    <td>
                        Browser menu: <a href="run_tests.php">run_tests.php</a><br>
                        HTML coverage: <code>run_tests.php?run=1&amp;mode=coverage</code><br>
                        CLI: <code>php scripts/run_tests.php</code><br>
                        CLI coverage: <code>php scripts/run_tests.php --coverage</code><br>
                        Skip DB: <code>ITM_SKIP_DB_TESTS=1</code> or browser checkbox
                    </td>
                </tr>
                <tr>
                    <td>identify_modules.php</td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI-only</span></span></td>
                    <td>Scans the <code>modules/</code> directory to identify and categorize all modules into standard CRUD and bespoke types, saving metadata to <code>scripts/modules_metadata.json</code>.</td>
                    <td>CLI: <code>php scripts/identify_modules.php > scripts/modules_metadata.json</code></td>
                </tr>
                <tr>
                    <td>generate_tests.php</td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI-only</span></span></td>
                    <td>Automatically generates PHPUnit integration tests for all standard CRUD modules identified in the metadata. Creates test files in <code>phpunit/tests/Unit/Modules/</code>.</td>
                    <td>CLI: <code>php scripts/generate_tests.php</code></td>
                </tr>
                <tr>
                    <td>test_import_user_samples.php</td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Verifies the JSON table import logic against specific user-provided sample data for the employees module.</td>
                    <td>CLI: <code>php scripts/test_import_user_samples.php</code></td>
                </tr>
                <tr>
                    <td>test_ajax.php</td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Test utility for AJAX endpoints.</td>
                    <td>CLI: <code>php scripts/test_ajax.php</code></td>
                </tr>
                <tr>
                    <td>test_edit.php</td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Test utility for record editing logic.</td>
                    <td>CLI: <code>php scripts/test_edit.php</code></td>
                </tr>
                <tr>
                    <td>test_session.php</td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Verifies session handling and persistence.</td>
                    <td>CLI: <code>php scripts/test_session.php</code></td>
                </tr>
                <tr>
                    <td>verify_api_coverage.php</td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Audits API endpoint coverage and consistency.</td>
                    <td>CLI: <code>php scripts/verify_api_coverage.php</code></td>
                </tr>
            </tbody>
        </table></div>
    </div>
    <div class="scripts-card" id="database">
        <h2>Database</h2>
        <div class="scripts-table-wrap"><table class="scripts-table">
            <thead>
                <tr>
                    <th>Script</th>
                    <th class="scripts-access-col">Access</th>
                    <th>What it does</th>
                    <th>How to use</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><a href="http://localhost/phpmyadmin/" target="_blank" rel="noopener noreferrer">phpMyAdmin</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span></span></td>
                    <td>Local MySQL admin UI for schema inspection, ad-hoc SQL, and imports (database <code>itmanagement</code> on typical Laragon installs).</td>
                    <td>Open <a href="http://localhost/phpmyadmin/" target="_blank" rel="noopener noreferrer">http://localhost/phpmyadmin/</a> in a new tab. Default dev: user <code>root</code>, password per your Laragon/MySQL setup (often blank locally).</td>
                </tr>
                <tr>
                    <td><a href="analyze_database_health.php" target="_blank" rel="nofollow noreferrer">analyze_database_health.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Runs <code>ANALYZE TABLE</code> on every base table and lists per-table success/failure (avoids phpMyAdmin stopping on first error).</td>
                    <td>Open <a href="analyze_database_health.php">analyze_database_health.php</a> while logged in. Optional CLI: <code>php scripts/analyze_database_health.php</code></td>
                </tr>
                <tr>
                    <td><a href="force_delete_company.php" target="_blank" rel="nofollow noreferrer">force_delete_company.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Bypasses triggers and FK checks to completely remove a company and its data across all <code>company_id</code> tables (including audit logs).</td>
                    <td>Open <a href="force_delete_company.php">force_delete_company.php</a> while logged in as Admin. CLI: <code>php scripts/force_delete_company.php --id=N</code>. <strong>DANGER: Destructive.</strong></td>
                </tr>
                <tr>
                    <td><a href="check_database_sql_company_name_uniques.php">check_database_sql_company_name_uniques.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Audits <code>database.sql</code>: each <code>company_id</code> table needs 2 uniques (PRIMARY + scope UNIQUE). Floor plans: <code>IFNULL(parent_folder_id,0)+name</code> / <code>IFNULL(folder_id,0)+display_name</code> (not <code>company_id+folder_id</code> alone). Skips <code>bookmark_folders</code> (duplicate names OK) and <code>floor_plan_item_tags</code> (junction PK only).</td>
                    <td>Open <a href="check_database_sql_company_name_uniques.php">check_database_sql_company_name_uniques.php</a> or <code>php scripts/check_database_sql_company_name_uniques.php</code> (exit 1 if any fail).</td>
                </tr>
                <tr>
                    <td>repair_table_from_schema.php</td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Rebuilds one InnoDB table from <code>database.sql</code> when metadata drift causes "doesn't exist in engine" errors. Refuses web requests (<code>PHP_SAPI !== 'cli'</code>).</td>
                    <td><code>php scripts/repair_table_from_schema.php --table=table_name</code> — <strong>destructive</strong>; backup first.</td>
                </tr>
                <tr>
                    <td><a href="count_db_tables.php" target="_blank" rel="nofollow noreferrer">count_db_tables.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Counts live tables in <code>information_schema</code> for <code>itmanagement</code>, echoes the total as plain text, and overwrites <code>scripts/number_db_tables.txt</code> with the same number (for external monitors). <strong>No login required.</strong></td>
                    <td>Open <a href="count_db_tables.php">count_db_tables.php</a> (plain number response) or run <code>php scripts/count_db_tables.php</code> from the repository root. Output file: <code>scripts/number_db_tables.txt</code>.</td>
                </tr>
                <tr>
                    <td>verify_database_schema.php</td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Compares <code>CREATE TABLE</code> names in <code>database.sql</code> with <code>information_schema</code> for <code>itmanagement</code>. Use after PowerShell/MySQL imports that report success but stop early (e.g. 73 tables instead of 117). Lists missing/extra tables; exit <code>1</code> on mismatch.</td>
                    <td><code>php scripts/verify_database_schema.php</code> — run from repository root after <code>database.sql</code> import; check <code>mysql-import.err</code> for the first <code>ERROR</code> line if this fails.</td>
                </tr>
                <tr>
                    <td>verify_database_sql_import.sh</td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Imports the full <code>database.sql</code> against a live MySQL 8.0 server and asserts the live <code>itmanagement</code> table count matches <code>CREATE TABLE</code> entries in <code>database.sql</code> (currently <strong>117</strong>). Catches INSERT/SELECT column-count mismatches (for example cross-company <code>equipment</code> seed at <code>department_id</code>). Used by CI job <strong>database-import</strong> in <code>.github/workflows/smoke.yml</code>.</td>
                    <td><code>bash scripts/verify_database_sql_import.sh</code> — requires MySQL on <code>127.0.0.1</code>, user <code>root</code>, password <code>itmanagement</code>. Env: <code>MYSQL_HOST</code>, <code>MYSQL_USER</code>, <code>MYSQL_PASSWORD</code>, optional <code>EXPECTED_TABLE_COUNT</code> override.</td>
                </tr>
                <tr>
                    <td><a href="employee_fields_missing.php" target="_blank" rel="nofollow noreferrer">employee_fields_missing.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Compares <code>employees</code> columns in <code>database.sql</code> and the live schema with create/edit/view/index coverage in <code>modules/employees/</code>. Fails when critical columns (including <code>termination_date</code>) are missing from the DB or module UI; lists optional gaps as <code>[INFO]</code>.</td>
                    <td><code>php scripts/employee_fields_missing.php</code> — run after changing <code>database.sql</code>, <code>modules/employees/</code> profile fields, or employee list/import columns.</td>
                </tr>
                <tr>
                    <td><a href="debug_resignations_termination_date.php" target="_blank" rel="nofollow noreferrer">debug_resignations_termination_date.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Diagnose why a <code>termination_date</code> (default <code>18/06/2026</code>, ISO week 25) does or does not match <code>modules/resignations/index.php</code> — PHP vs MySQL week metadata, ISO bounds, legacy <code>YEAR/MONTH/WEEK</code>, simulated module SQL (<code>itm_sql_valid_date_predicate()</code>; not <code>&lt;&gt; '0000-00-00'</code>), employee row, verify-probe bounds. Use when the report is empty or prepare fails with <code>Incorrect DATE value: '0000-00-00'</code>.</td>
                    <td><code>php scripts/debug_resignations_termination_date.php --date=18/06/2026 --company_id=4 --employee_id=432 --week=25 --month=6 --year=2026</code></td>
                </tr>
                <tr>
                    <td><a href="verify_employee_type_resignations.php" target="_blank" rel="nofollow noreferrer">verify_employee_type_resignations.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Regression for <code>employee_type</code> seed data, <code>employees.start_date</code> / <code>employee_type_id</code>, <code>modules_registry</code> slugs, and the weekly resignations SQL filter (<code>itm_iso_week_bounds()</code>, <code>MONTH(termination_date)</code>, <code>itm_sql_valid_date_predicate()</code>) aligned with <code>modules/resignations/index.php</code>.</td>
                    <td><code>php scripts/verify_employee_type_resignations.php</code> — after changes to <code>modules/employee_type/</code>, <code>modules/resignations/</code>, <code>modules/employees/</code> termination/type fields, or related <code>database.sql</code> tables.</td>
                </tr>
                <tr>
                    <td>normalize_database_sql_created_at.php</td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Sets every seed <code>created_at</code> literal in <code>database.sql</code> INSERT rows to one timestamp (default <code>2026-01-01 00:00:01</code>); leaves <code>updated_at</code> and other date columns unchanged. <strong>Writes</strong> <code>database.sql</code>.</td>
                    <td><strong>CLI only</strong> (browser shows instructions + <strong>← Scripts index</strong>): <code>php scripts/normalize_database_sql_created_at.php</code></td>
                </tr>
                <tr>
                    <td><a href="apply_module_sample_data_seed.php" target="_blank" rel="nofollow noreferrer">apply_module_sample_data_seed.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Automates per-module/table seed expansion: adds missing sample rows for every company listed in <code>companies</code> into <code>database.sql</code>. Default <code>idf_device_type</code> samples are <code>other</code> 📦, <code>server</code> 🖥️, <code>ups</code> 🔋, <code>patch_panel</code> ➿, and <code>switch</code> 🔀; custom <code>--sample</code> values supported. <strong>Default = dry-run</strong>; writes with CLI <code>--apply</code> or browser <code>?apply=1</code> (Admin). Lists new INSERT statements and skipped targets before apply. Requires <code>--module</code> / <code>?module=</code>.</td>
                    <td>Browser: <a href="apply_module_sample_data_seed.php?module=idf_device_type">dry-run</a> / <a href="apply_module_sample_data_seed.php?module=idf_device_type&amp;apply=1">apply=1</a>. CLI: <code>php scripts/apply_module_sample_data_seed.php --module=idf_device_type</code> then <code>php scripts/apply_module_sample_data_seed.php --module=idf_device_type --apply</code> · <code>--value-column=name --sample=LabPoE</code></td>
                </tr>
                <tr>
                    <td>export_floor_plan_folders_seed.php</td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Exports <code>floor_plan_folders</code> rows from the live DB as <code>database.sql</code>-style <code>INSERT</code> statements for pasting into seed data.</td>
                    <td>
                        <code>php scripts/export_floor_plan_folders_seed.php</code><br>
                        <code>php scripts/export_floor_plan_folders_seed.php --company=1</code> — stdout INSERTs; exit <code>1</code> when no rows.
                    </td>
                </tr>
                <tr>
                    <td><a href="check_delimiters.php" target="_blank" rel="nofollow noreferrer">check_delimiters.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Audit database.sql for correct DELIMITER usage in trigger blocks.</td>
                    <td>Open <a href="check_delimiters.php">check_delimiters.php</a> while logged in. CLI: <code>php scripts/check_delimiters.php</code></td>
                </tr>
                <tr>
                    <td><a href="check_duplicates.php" target="_blank" rel="nofollow noreferrer">check_duplicates.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Audit database.sql for duplicate column definitions in CREATE TABLE blocks.</td>
                    <td>Open <a href="check_duplicates.php">check_duplicates.php</a> while logged in. CLI: <code>php scripts/check_duplicates.php</code></td>
                </tr>
                <tr>
                    <td><a href="check_phones.php" target="_blank" rel="nofollow noreferrer">check_phones.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Identify tables in database.sql that contain phone-related columns for PII auditing.</td>
                    <td>Open <a href="check_phones.php">check_phones.php</a> while logged in. CLI: <code>php scripts/check_phones.php</code></td>
                </tr>
                <tr>
                    <td><a href="check_sql_errors.php" target="_blank" rel="nofollow noreferrer">check_sql_errors.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Audit database.sql for column mismatches in triggers and INSERT statements.</td>
                    <td>Open <a href="check_sql_errors.php">check_sql_errors.php</a> while logged in. CLI: <code>php scripts/check_sql_errors.php</code></td>
                </tr>
                <tr>
                    <td><a href="count_args.php" target="_blank" rel="nofollow noreferrer">count_args.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Count arguments in the <code>trg_employees_audit_insert</code> trigger in database.sql.</td>
                    <td>Open <a href="count_args.php">count_args.php</a> while logged in. CLI: <code>php scripts/count_args.php</code></td>
                </tr>
                <tr>
                    <td>fix_sql_departments.php</td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Fix column count mismatch in departments INSERT statements in database.sql.</td>
                    <td><code>php scripts/fix_sql_departments.php</code> — CLI only (browser shows instructions).</td>
                </tr>
                <tr>
                    <td><a href="list_phone_columns.php" target="_blank" rel="nofollow noreferrer">list_phone_columns.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>List columns for each table defined in database.sql, filtering for phone columns.</td>
                    <td>Open <a href="list_phone_columns.php">list_phone_columns.php</a> while logged in. CLI: <code>php scripts/list_phone_columns.php</code></td>
                </tr>
                <tr>
                    <td><a href="verify_sql.php" target="_blank" rel="nofollow noreferrer">verify_sql.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Comprehensive SQL audit script for database.sql (delimiters, duplicates, references).</td>
                    <td>Open <a href="verify_sql.php">verify_sql.php</a> while logged in. CLI: <code>php scripts/verify_sql.php</code></td>
                </tr>
                <tr>
                    <td>fix_sql.php</td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Utility to fix common SQL errors in database.sql.</td>
                    <td>CLI: <code>php scripts/fix_sql.php</code></td>
                </tr>
                <tr>
                    <td>fix_sql_broad.php</td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Broad-spectrum SQL cleanup utility.</td>
                    <td>CLI: <code>php scripts/fix_sql_broad.php</code></td>
                </tr>
                <tr>
                    <td><a href="schema_report.php" target="_blank" rel="nofollow noreferrer">schema_report.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span></span></td>
                    <td>Visual report for database schema validation (errors and warnings).</td>
                    <td>Open <a href="schema_report.php">schema_report.php</a> in the browser.</td>
                </tr>
                <tr>
                    <td><a href="validate_DB_schema.php" target="_blank" rel="nofollow noreferrer">validate_DB_schema.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span></span></td>
                    <td>Validates database schema consistency (FKs, duplicate indexes, orphaned indexes).</td>
                    <td>Open <a href="validate_DB_schema.php">validate_DB_schema.php</a> in the browser.</td>
                </tr>
                <tr>
                    <td><a href="test_employee_id-foreign_keys.php" target="_blank" rel="nofollow noreferrer">test_employee_id-foreign_keys.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span></span></td>
                    <td>Validates <code>employee_id</code> foreign keys across all tables.</td>
                    <td>Open <a href="test_employee_id-foreign_keys.php">test_employee_id-foreign_keys.php</a> in the browser.</td>
                </tr>
                <tr>
                    <td><a href="validate_delete_employee.php" target="_blank" rel="nofollow noreferrer">validate_delete_employee.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span></span></td>
                    <td>Validates if employees can be safely deleted by checking FKs and triggers.</td>
                    <td>Open <a href="validate_delete_employee.php">validate_delete_employee.php</a> in the browser.</td>
                </tr>
                <tr>
                    <td><a href="generate_FK_employee_id.php" target="_blank" rel="nofollow noreferrer">generate_FK_employee_id.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span></span></td>
                    <td>Generates SQL for missing <code>employee_id</code> foreign keys.</td>
                    <td>Open <a href="generate_FK_employee_id.php">generate_FK_employee_id.php</a> in the browser.</td>
                </tr>
                <tr>
                    <td><a href="generate_reassignment.php" target="_blank" rel="nofollow noreferrer">generate_reassignment.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span></span></td>
                    <td>Generates reassignment SQL for <code>employee_id</code> before deletion.</td>
                    <td>Open <a href="generate_reassignment.php">generate_reassignment.php</a> in the browser.</td>
                </tr>
                <tr>
                    <td><a href="transfer_data_from_employee.php" target="_blank" rel="nofollow noreferrer">transfer_data_from_employee.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span></span></td>
                    <td>Clones an employee and transfers/copies their related data to the new record.</td>
                    <td>Open <a href="transfer_data_from_employee.php">transfer_data_from_employee.php</a> in the browser. <strong>DANGER: Mutates DB.</strong></td>
                </tr>
                <tr>
                    <td><a href="delete_clone_employee.php" target="_blank" rel="nofollow noreferrer">delete_clone_employee.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span></span></td>
                    <td>Reverses an employee clone by deleting the employee and their related data.</td>
                    <td>Open <a href="delete_clone_employee.php">delete_clone_employee.php</a> in the browser. <strong>DANGER: Destructive.</strong></td>
                </tr>
            </tbody>
        </table></div>
    </div>

    <div class="scripts-card" id="idf">
        <h2>IDF &amp; equipment</h2>
        <div class="scripts-table-wrap"><table class="scripts-table">
            <thead>
                <tr>
                    <th>Script</th>
                    <th class="scripts-access-col">Access</th>
                    <th>What it does</th>
                    <th>How to use</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><a href="idfs_sync_human_test.php" target="_blank" rel="nofollow noreferrer">idfs_sync_human_test.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>End-to-end HTTP regression for IDF rack/device flows; asserts sync across <code>idf_ports</code>, <code>switch_ports</code>, <code>equipment</code>, <code>idf_links</code>. <strong>Mutates DB:</strong> creates temporary equipment/port/position/link rows and removes temporary artifacts at the end. After login, POSTs to <code>index.php</code> so session <code>company_id</code> matches <code>ITM_COMPANY_ID</code>; company-selection GET resolves redirects manually (open_basedir-safe).</td>
                    <td>
                        CLI (recommended): <code>php scripts/idfs_sync_human_test.php</code><br>
                        Optional env: <code>ITM_BASE_URL</code>, <code>ITM_USER</code>, <code>ITM_PASS</code>, <code>ITM_COMPANY_ID</code>, <code>ITM_IDF_ID</code> (auto-resolved when the pair is missing).<br>
                        Browser: HTML log with <strong>← Scripts index</strong> and module/table links (debugging). Required before IDF-related PRs per AGENTS.md.
                    </td>
                </tr>
                <tr>
                    <td><a href="idfs_api_payload_dry_run.php" target="_blank" rel="nofollow noreferrer">idfs_api_payload_dry_run.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Validates IDF device API JSON payloads offline (no MySQL). Requires CLI flags for payload validation.</td>
                    <td>
                        <code>php scripts/idfs_api_payload_dry_run.php --samples</code>,
                        <code>--endpoint=port_update --file=payload.json</code>,
                        <code>--endpoint=link_create --json='{"port_id_a":1,"port_id_b":2}'</code>
                    </td>
                </tr>
                <tr>
                    <td><a href="idf_device_port_sort_test.php" target="_blank" rel="nofollow noreferrer">idf_device_port_sort_test.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Proves RJ45 ports sort before fiber (SFP) in IDF device SQL; optional live MySQL checks.</td>
                    <td>
                        Browser: plain-text <code>[PASS]</code>/<code>[FAIL]</code> log. CLI:
                        <code>php scripts/idf_device_port_sort_test.php</code> ·
                        <code>--offline-only</code>
                    </td>
                </tr>
                <tr>
                    <td><a href="ensure_equipment_type_modules.php" target="_blank" rel="nofollow noreferrer">ensure_equipment_type_modules.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Verifies or recreates canonical equipment-type façade modules under <code>modules/is_*</code> (<code>is_switch</code>, <code>is_server</code>, <code>is_workstation</code>, …). Does not delete anything.</td>
                    <td><code>php scripts/ensure_equipment_type_modules.php</code> — CLI-only (browser shows <strong>← Scripts index</strong> + CLI command). Exit <code>1</code> if any canonical <code>index.php</code> is missing.</td>
                </tr>
                <tr>
                    <td><a href="cleanup_equipment_test_module_artifacts.php" target="_blank" rel="nofollow noreferrer">cleanup_equipment_test_module_artifacts.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>
                        <strong>Destructive (local dev DB):</strong> removes regression-test <code>equipment_types</code> rows (including <code>MBQA-equipment_types-…</code> runner tags), ITM test companies, junk <code>modules/is_*_itm_eqdct_*</code> / <code>*_itm_edct_*</code> / orphan <code>modules/is_mbqa_equipment_types_*</code> folders, and matching sidebar prefs — then re-ensures canonical <code>is_*</code> modules. Never removes <code>is_switch</code>, <code>is_server</code>, etc. Browser <strong>Run QA</strong> now executes this cleanup silently before and after <code>module_browser_qa_runner.php</code>.
                    </td>
                    <td><code>php scripts/cleanup_equipment_test_module_artifacts.php</code> — CLI-only (browser shows <strong>← Scripts index</strong> + CLI command). Same logic as post-QA runner cleanup; run manually after other equipment DB tests if needed.</td>
                </tr>
                <tr>
                    <td><a href="equipment_delete_clear_table_test.php" target="_blank" rel="nofollow noreferrer">equipment_delete_clear_table_test.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>
                        MySQL regression for equipment <code>clear_table</code> and transactional <code>equipment_delete_record()</code>. Uses equipment type names <code>Switch</code> / <code>Server</code> only (not suffixed names) so canonical <code>modules/is_*</code> façades are reused and the sidebar stays clean. <strong>Mutates DB:</strong> creates temporary tenant/reference/equipment rows, then cleans them up.
                    </td>
                    <td>
                        CLI: <code>php scripts/equipment_delete_clear_table_test.php</code> · <code>ITM_SKIP_DB_TESTS=1</code> (static only) · <code>ITM_TEST_COMPANY_ID</code>.<br>
                        Browser: static checks only (no DB or filesystem cleanup). MySQL regression requires CLI: <code>php scripts/equipment_delete_clear_table_test.php</code>.
                    </td>
                </tr>
                <tr>
                    <td><a href="tickets_related_equipment_delete_test.php" target="_blank" rel="nofollow noreferrer">tickets_related_equipment_delete_test.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>
                        MySQL regression for tickets sample data: seeds lookup parents (including <code>equipment</code>), inserts <code>TCK-0001</code> with <code>asset_id</code> on Primary File Server, and asserts <code>equipment_delete_record()</code> is blocked with a Related Asset / in-use message. <strong>Mutates DB:</strong> seeds/updates sample ticket rows during the test.
                    </td>
                    <td>
                        CLI: <code>php scripts/tickets_related_equipment_delete_test.php</code> · <code>ITM_SKIP_DB_TESTS=1</code> · <code>ITM_TEST_COMPANY_ID</code> (default <code>1</code>).<br>
                        Browser: static checks only (no DB). MySQL regression requires CLI.
                    </td>
                </tr>
                <tr>
                    <td><a href="verify_equipment_triggers.php" target="_blank" rel="nofollow noreferrer">verify_equipment_triggers.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>
                        MySQL regression for <code>equipment</code> audit triggers: verifies that <code>INSERT</code>, <code>UPDATE</code>, and <code>DELETE</code> operations on the equipment table are correctly logged to <code>audit_logs</code>. <strong>Mutates DB:</strong> creates and deletes temporary equipment rows.
                    </td>
                    <td>
                        CLI: <code>php scripts/verify_equipment_triggers.php</code> · <code>ITM_TEST_COMPANY_ID</code> (default <code>1</code>).<br>
                        Browser: runs the verification and displays results (HTML).
                    </td>
                </tr>
                <tr>
                    <td><a href="employees_delete_clear_table_test.php" target="_blank" rel="nofollow noreferrer">employees_delete_clear_table_test.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>MySQL regression for employees <code>clear_table</code> soft-delete (detach + <code>active=0</code>/<code>deleted_at</code>; live rows cleared, audit rows remain). <strong>Mutates DB:</strong> creates temporary tenant/reference/employee rows, then cleans them up.</td>
                    <td>
                        CLI: <code>php scripts/employees_delete_clear_table_test.php</code> · <code>ITM_SKIP_DB_TESTS=1</code> · <code>ITM_TEST_COMPANY_ID</code>.<br>
                        Browser: static checks only (no DB mutations). MySQL regression requires CLI: <code>php scripts/employees_delete_clear_table_test.php</code>.
                    </td>
                </tr>
                <tr>
                    <td>check_points.php</td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Audits network points and connections.</td>
                    <td>CLI: <code>php scripts/check_points.php</code></td>
                </tr>
                <tr>
                    <td><a href="test_visualizer_v2.php" target="_blank" rel="nofollow noreferrer">test_visualizer_v2.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span></span></td>
                    <td>Visual test for Equipment Port Visualizer (IDF rack/device dots).</td>
                    <td>Open <code>scripts/test_visualizer_v2.php</code> to verify port rendering style and cable colors.</td>
                </tr>
            </tbody>
        </table></div>
    </div>

    <div class="scripts-card" id="ui-modules">
        <h2>UI &amp; modules</h2>
        <div class="scripts-table-wrap"><table class="scripts-table">
            <thead>
                <tr>
                    <th>Script</th>
                    <th class="scripts-access-col">Access</th>
                    <th>What it does</th>
                    <th>How to use</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><a href="titles_list.php" target="_blank" rel="nofollow noreferrer">titles_list.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Scans all PHP files under the <code>modules/</code> directory to extract their <code>&lt;title&gt;</code> tags. Outputs the list matching <code>module/{module_name}/{file}.php</code>.</td>
                    <td>Browser: HTML report. CLI: <code>php scripts/titles_list.php</code></td>
                </tr>
                <tr>
                    <td><a href="titles_list_show.php" target="_blank" rel="nofollow noreferrer">titles_list_show.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Scans all PHP files under the <code>modules/</code> directory to extract their <code>&lt;title&gt;</code> tags showing only the inner title text. Outputs the list matching <code>module/{module_name}/{file}.php</code>.</td>
                    <td>Browser: HTML report. CLI: <code>php scripts/titles_list_show.php</code></td>
                </tr>
                <tr>
                    <td><a href="list_modules_not_on_sidebar.php" target="_blank" rel="nofollow noreferrer">list_modules_not_on_sidebar.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Lists <code>modules/*/index.php</code> folders that are <strong>not</strong> on the sidebar (including policy-hidden internal modules such as <code>floor_plan_folders</code>, <code>floor_plan_tags</code>, <code>floor_plan_item_tags</code>).</td>
                    <td>
                        <strong>Log in first.</strong> Open <a href="list_modules_not_on_sidebar.php" target="_blank" rel="nofollow noreferrer">list_modules_not_on_sidebar.php</a> or
                        <a href="list_modules_not_on_sidebar.php?format=json">?format=json</a>.<br>
                        CLI: <code>php scripts/list_modules_not_on_sidebar.php</code> · JSON: <code>--json</code>
                    </td>
                </tr>
                <tr>
                    <td><a href="compare_database_sql_modules.php" target="_blank" rel="nofollow noreferrer">compare_database_sql_modules.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Compares every <code>CREATE TABLE</code> in <code>database.sql</code> with <code>modules/</code> folders and each module’s <code>$crud_table</code> mapping (matched, missing module, missing table, mismatch).</td>
                    <td>
                        <strong>Log in first.</strong> Open <a href="compare_database_sql_modules.php" target="_blank" rel="nofollow noreferrer">compare_database_sql_modules.php</a> or
                        <a href="compare_database_sql_modules.php?format=json">?format=json</a>.<br>
                        CLI: <code>php scripts/compare_database_sql_modules.php</code> · JSON: <code>--json</code> · exit code <code>1</code> when gaps exist.
                    </td>
                </tr>
                <tr>
                    <td><a href="floor_plans_folder_move_test.php" target="_blank" rel="nofollow noreferrer">floor_plans_folder_move_test.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>MySQL regression for Floor Plans folder reparenting (<code>fp_move_folder_to_parent</code> in <code>gallery_helpers.php</code>). <strong>Mutates DB:</strong> creates temporary folder hierarchy rows, then removes them.</td>
                    <td>Browser: HTML log with module/table links (needs DB). CLI: <code>php scripts/floor_plans_folder_move_test.php</code> — optional <code>ITM_TEST_COMPANY_ID</code>.</td>
                </tr>
                <tr>
                    <td>explorer_human_test.php</td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Human-flow regression for Explorer storage, access control, copy/move/delete, database synchronisation, and audit logging. <strong>Mutates DB and filesystem:</strong> creates a temporary company plus isolated <code>files/{company_id}</code> content, then removes them at shutdown.</td>
                    <td><code>php scripts/explorer_human_test.php</code> — CLI-only; run from the repository root after Explorer module changes.</td>
                </tr>
                <tr>
                    <td><a href="floor_designer_test.php" target="_blank" rel="nofollow noreferrer">floor_designer_test.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Validates Floor Designer module logic, AJAX endpoints, and schema mapping.</td>
                    <td>Open in browser or run via CLI: <code>php scripts/floor_designer_test.php</code>.</td>
                </tr>
                <tr>
                    <td>list_active_and_checkboxes.php</td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Audit utility for active flags and checkbox inputs in module forms.</td>
                    <td>CLI: <code>php scripts/list_active_and_checkboxes.php</code></td>
                </tr>
            </tbody>
        </table></div>
    </div>

    <div class="scripts-card" id="ci">
        <h2>CI &amp; static analysis</h2>
        <p class="scripts-muted">PHP scanners support <strong>Browser</strong> (plain-text) and <strong>CLI</strong> (recommended for CI). Only <code>smoke_test.sh</code> is CLI-only (bash).</p>
        <div class="scripts-table-wrap"><table class="scripts-table">
            <thead>
                <tr>
                    <th>Script</th>
                    <th class="scripts-access-col">Access</th>
                    <th>What it does</th>
                    <th>How to use</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>smoke_test.sh</td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>CI/local smoke runner (<code>.github/workflows/smoke.yml</code>): (1) <code>php -l</code> all PHP, (2) CSRF coverage, (3) SQLi coverage. Other check scripts are run manually when needed — not part of smoke.</td>
                    <td><code>bash scripts/smoke_test.sh</code> from repository root. Optional: <code>PHP_BIN=/path/to/php</code> on Windows Laragon.</td>
                </tr>
                <tr>
                    <td><a href="perform_audit.php" target="_blank" rel="nofollow noreferrer">perform_audit.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Runs all scripts in the system and gathers any PHP warnings, notices, errors, or fatal exceptions. Saves results in a structured JSON file.</td>
                    <td><code>php scripts/perform_audit.php</code></td>
                </tr>
                <tr>
                    <td><a href="check_standard_crud_delegate_requires.php" target="_blank" rel="nofollow noreferrer">check_standard_crud_delegate_requires.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Static scan: <code>modules/*/</code> PHP files must not <code>require __DIR__ . '/../manufacturers/…'</code> (only <code>modules/manufacturers/</code> may host that CRUD tree).</td>
                    <td>Browser: plain-text report. CLI: <code>php scripts/check_standard_crud_delegate_requires.php</code> — run after standard CRUD template or module scaffold changes.</td>
                </tr>
                <tr>
                    <td><a href="check_csrf_coverage.php" target="_blank" rel="nofollow noreferrer">check_csrf_coverage.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Static scan: POST handlers that mutate data without a known CSRF guard.</td>
                    <td>Browser: plain-text report. CLI: <code>php scripts/check_csrf_coverage.php</code> — smoke step 2 / AGENTS.md after CRUD changes.</td>
                </tr>
                <tr>
                    <td><a href="apply_date_display_format.php" target="_blank" rel="nofollow noreferrer">apply_date_display_format.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>One-time/maintenance: patch duplicated <code>cr_render_cell_value()</code> helpers to call <code>itm_format_cell_scalar_display()</code> (dd/mm/yyyy list/view display). <strong>Default = dry-run</strong>; writes with CLI <code>--apply</code> or browser <code>?apply=1</code> (Admin). Lists scanned / changed / skipped module files. Idempotent; re-run when new flattened CRUD modules ship without the date display hook.</td>
                    <td>Browser: <a href="apply_date_display_format.php">dry-run</a> / <a href="apply_date_display_format.php?apply=1">apply=1</a>. CLI: <code>php scripts/apply_date_display_format.php</code> then <code>php scripts/apply_date_display_format.php --apply</code>.</td>
                </tr>
                <tr>
                    <td><a href="apply_crud_hidden_employee_id_alias.php" target="_blank" rel="nofollow noreferrer">apply_crud_hidden_employee_id_alias.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>One-time/maintenance: replace dead <code>'user_id'</code> entries in flattened CRUD <code>$hidden</code> column arrays with <code>'employee_id'</code> under <code>modules/</code>. <strong>Default = dry-run</strong>; writes with CLI <code>--apply</code> or browser <code>?apply=1</code> (Admin). Lists scanned / changed / skipped module files. Idempotent; re-run when new scaffolds copy the old hide list.</td>
                    <td>Browser: <a href="apply_crud_hidden_employee_id_alias.php">dry-run</a> / <a href="apply_crud_hidden_employee_id_alias.php?apply=1">apply=1</a>. CLI: <code>php scripts/apply_crud_hidden_employee_id_alias.php</code> then <code>php scripts/apply_crud_hidden_employee_id_alias.php --apply</code>.</td>
                </tr>
                <tr>
                    <td><a href="apply_crud_fk_label_search.php" target="_blank" rel="nofollow noreferrer">apply_crud_fk_label_search.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>One-time/maintenance: extend flattened CRUD <code>index.php</code> search blocks with <code>itm_crud_fk_label_search_conditions()</code> so Search (all fields) matches FK label tables, not only raw IDs. <strong>Default = dry-run</strong>; writes with CLI <code>--apply</code> or browser <code>?apply=1</code> (Admin). Lists scanned / changed / skipped modules (Employees uses <code>includes/itm_employees_search.php</code> instead). Idempotent.</td>
                    <td>Browser: <a href="apply_crud_fk_label_search.php">dry-run</a> / <a href="apply_crud_fk_label_search.php?apply=1">apply=1</a>. CLI: <code>php scripts/apply_crud_fk_label_search.php</code> then <code>php scripts/apply_crud_fk_label_search.php --apply</code>.</td>
                </tr>
                <tr>
                    <td><a href="apply_display_field_columns_search_alias.php" target="_blank" rel="nofollow noreferrer">apply_display_field_columns_search_alias.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>One-time/maintenance: add <code>$displayFieldColumns = $uiColumns</code> (or <code>$visibleFieldColumns</code>) before module paths so list search does not reference an undefined variable. <strong>Default = dry-run</strong>; writes with CLI <code>--apply</code> or browser <code>?apply=1</code> (Admin). Lists scanned / changed / skipped <code>index.php</code> files. Re-run when new flattened CRUD modules omit the alias.</td>
                    <td>Browser: <a href="apply_display_field_columns_search_alias.php">dry-run</a> / <a href="apply_display_field_columns_search_alias.php?apply=1">apply=1</a>. CLI: <code>php scripts/apply_display_field_columns_search_alias.php</code> then <code>php scripts/apply_display_field_columns_search_alias.php --apply</code>.</td>
                </tr>
                <tr>
                    <td><a href="apply_itm_actions_cell_markers.php" target="_blank" rel="nofollow noreferrer">apply_itm_actions_cell_markers.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>One-time/maintenance: add <code>class="itm-actions-cell"</code> and <code>data-itm-actions-origin="1"</code> on Actions column header and body cells in module list tables (module browser QA <code>ui_check</code>). <strong>Default = dry-run</strong>; writes with CLI <code>--apply</code> or browser <code>?apply=1</code> (Admin). Lists scanned / changed / skipped files under <code>modules/*/index.php</code> and <code>modules/*/includes/partials/render.php</code>.</td>
                    <td>Browser: <a href="apply_itm_actions_cell_markers.php">dry-run</a> / <a href="apply_itm_actions_cell_markers.php?apply=1">apply=1</a>. CLI: <code>php scripts/apply_itm_actions_cell_markers.php</code> then <code>php scripts/apply_itm_actions_cell_markers.php --apply</code>.</td>
                </tr>
                <tr>
                    <td><a href="apply_ui_action_emoji.php" target="_blank" rel="nofollow noreferrer">apply_ui_action_emoji.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>One-time/maintenance: bulk replace simple NO MIXED markup (emoji + action word) → emoji-only + <code>title</code> on modules and shared UI files. <strong>Default = dry-run</strong>; writes with CLI <code>--apply</code> or browser <code>?apply=1</code> (Admin). Lists scanned / changed / skipped files. Skip PHP ternaries / JS templates — fix manually.</td>
                    <td>Browser: <a href="apply_ui_action_emoji.php">dry-run</a> / <a href="apply_ui_action_emoji.php?apply=1">apply=1</a>. CLI: <code>php scripts/apply_ui_action_emoji.php</code> then <code>php scripts/apply_ui_action_emoji.php --apply</code>.</td>
                </tr>
                <tr>
                    <td><a href="check_ui_action_emoji.php">check_ui_action_emoji.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Static audit: NO MIXED gate on <code>&lt;a&gt;</code>, <code>&lt;button&gt;</code>, <code>&lt;input&gt;</code>, <code>&lt;h1&gt;</code>–<code>&lt;h3&gt;</code> — seven emoji+word regex families, known compound literals, plain-text action outliers, header <code>intentRules</code> drift.</td>
                    <td>Browser: plain-text report. CLI: <code>php scripts/check_ui_action_emoji.php</code> — exit <code>0</code> when <strong>0 violations incl. mixed emoji+word</strong>; exit <code>1</code> on any match. Run after UI label changes.</td>
                </tr>
                <tr>
                    <td><a href="check_fk_label_search_coverage.php" target="_blank" rel="nofollow noreferrer">check_fk_label_search_coverage.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI-only</span></span></td>
                    <td>Static audit: every module with server-side list search must match visible FK/label columns (shared CRUD helper, EXISTS/JOIN label LIKE, employee JOIN/CONCAT, or scalar-only fields). No per-module allowlist.</td>
                    <td><code>php scripts/check_fk_label_search_coverage.php</code> — smoke step 4 / AGENTS.md after list search changes; exit <code>0</code> on full coverage.</td>
                </tr>
                <tr>
                    <td><a href="check_display_field_columns_search.php" target="_blank" rel="nofollow noreferrer">check_display_field_columns_search.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Static audit: every <code>modules/*/index.php</code> that uses <code>foreach ($displayFieldColumns …)</code> must assign <code>$displayFieldColumns</code> first.</td>
                    <td>Browser: plain-text report. CLI: <code>php scripts/check_display_field_columns_search.php</code> — run after bulk CRUD/search changes; exit <code>1</code> on failure.</td>
                </tr>
                <tr>
                    <td><a href="check_script_disposable_employees.php" target="_blank" rel="nofollow noreferrer">check_script_disposable_employees.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Static audit: repro/verify scripts must not hardcode seed user id <code>1</code> for <code>employees</code> / <code>reset_token</code> / notes mutations — use <code>scripts/lib/itm_script_test_employee.php</code>.</td>
                    <td>Browser: plain-text report. CLI: <code>php scripts/check_script_disposable_employees.php</code> — run after changing audit repro scripts; exit <code>1</code> on failure.</td>
                </tr>
                <tr>
                    <td><a href="check_sql_injection_coverage.php" target="_blank" rel="nofollow noreferrer">check_sql_injection_coverage.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Static scan: direct queries near user input without obvious binding/sanitization.</td>
                    <td>Browser: plain-text report. CLI: <code>php scripts/check_sql_injection_coverage.php</code> — smoke step 3 / AGENTS.md after PHP/SQL changes.</td>
                </tr>
                <tr>
                    <td><a href="check_stale_user_id_sql.php" target="_blank" rel="nofollow noreferrer">check_stale_user_id_sql.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Static audit: fail on stale <code>user_id</code> column SQL or legacy <code>users</code> table references in <code>modules/</code>, <code>includes/</code>, and <code>config/</code> after the employees merge.</td>
                    <td>Browser: plain-text report. CLI: <code>php scripts/check_stale_user_id_sql.php</code> — run after auth/session or schema merge changes; exit <code>1</code> on failure.</td>
                </tr>
                <tr>
                    <td><a href="check_stale_user_terminology.php" target="_blank" rel="nofollow noreferrer">check_stale_user_terminology.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Static audit: fail on stale <code>Users module</code> / <code>Users Management</code> prose, <code>employee_companies</code> + <code>user_id</code> helper references, session <code>role_name</code> admin checks in <code>modules/</code>, <code>cr_username_for_user_id</code>, and <code>'user_id'</code> inside CRUD <code>$hidden</code> arrays.</td>
                    <td>Browser: plain-text report. CLI: <code>php scripts/check_stale_user_terminology.php</code> — run after docs/script copy changes; exit <code>1</code> on failure.</td>
                </tr>
                <tr>
                    <td><a href="check_multi_tenant_leaks.php" target="_blank" rel="nofollow noreferrer">check_multi_tenant_leaks.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Static scan: SQL queries and INSERTs on scoped tables missing <code>company_id</code> filters, and improper UI exposure of company identifiers.</td>
                    <td>Browser: HTML report with detailed leak locations. CLI: <code>php scripts/check_multi_tenant_leaks.php</code> — run after CRUD changes to ensure data isolation.</td>
                </tr>
                <tr>
                    <td><a href="check_index_table_compliance.php" target="_blank" rel="nofollow noreferrer">check_index_table_compliance.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>
                        Index list tables: <code>data-itm-db-import-endpoint</code>, <code>data-itm-actions-origin</code>, POST CSRF, form <code>csrf_token</code>.
                        Skips import when <code>data-itm-no-import-excel="1"</code>; skips Actions markers when the index has no Actions column.
                        Baseline: <code>scripts/data/index_table_compliance_baseline.txt</code>. Skips bespoke modules and <code>rack_planner</code>.
                    </td>
                    <td>
                        Browser: HTML-escaped report in <code>&lt;pre&gt;</code> (vertical list). CLI: <code>php scripts/check_index_table_compliance.php</code> — run manually when index-table contract changes; exit <code>1</code> on new violations.<br>
                        <code>--strict</code> · <code>--write-baseline</code>
                    </td>
                </tr>
                <tr>
                    <td><a href="apply_bulk_delete_cancel_ux.php" target="_blank" rel="nofollow noreferrer">apply_bulk_delete_cancel_ux.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>One-time maintenance: strip duplicated inline bulk-delete <code>selectionMode</code> scripts from module PHP files after <code>js/bulk-delete-selection.js</code> (shared Cancel button) ships via <code>includes/header.php</code>. <strong>Default = dry-run</strong>; writes with CLI <code>--apply</code> or browser <code>?apply=1</code> (Admin). Lists scanned / changed / skipped module files.</td>
                    <td>Browser: <a href="apply_bulk_delete_cancel_ux.php">dry-run</a> / <a href="apply_bulk_delete_cancel_ux.php?apply=1">apply=1</a>. CLI: <code>php scripts/apply_bulk_delete_cancel_ux.php</code> then <code>php scripts/apply_bulk_delete_cancel_ux.php --apply</code>.</td>
                </tr>
                <tr>
                    <td><a href="apply_bulk_actions_records_per_page_gate.php" target="_blank" rel="nofollow noreferrer">apply_bulk_actions_records_per_page_gate.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Add <code>records_per_page</code> visibility gate for bulk delete / clear table on module <code>index.php</code> files (<code>$showBulkActions = ($totalRows &gt;= $perPage)</code>). <strong>Default = dry-run</strong>; writes with CLI <code>--apply</code> or browser <code>?apply=1</code> (Admin). Lists scanned / changed / skipped module files.</td>
                    <td>Browser: <a href="apply_bulk_actions_records_per_page_gate.php">dry-run</a> / <a href="apply_bulk_actions_records_per_page_gate.php?apply=1">apply=1</a>. CLI: <code>php scripts/apply_bulk_actions_records_per_page_gate.php</code> then <code>php scripts/apply_bulk_actions_records_per_page_gate.php --apply</code>.</td>
                </tr>
                <tr>
                    <td><a href="module_clean_tests_qa_runner.php" target="_blank" rel="nofollow noreferrer">module_clean_tests_qa_runner.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Runs the same QA artifact cleanup used by <code>module_browser_qa_runner.php</code>: equipment scaffold folders, legacy template stub modules, MBQA/QA-IMPORT DB rows, sidebar leftovers, then re-ensures canonical <code>modules/is_*</code> facades. Browser <strong>Run QA</strong> already triggers this cleanup silently at start and end; use this page for manual cleanup runs. Includes quick links: <strong>Clean Tests · Open markdown file · Download XLSX · Rebuild report · Re-Run Test · Run QA runner</strong>.</td>
                    <td>
                        Browser: <a href="module_clean_tests_qa_runner.php" target="_blank" rel="nofollow noreferrer">module_clean_tests_qa_runner.php</a> (open page, click <strong>Run Clean Tests</strong>; POST + CSRF required).<br>
                        CLI: <code>php scripts/module_clean_tests_qa_runner.php</code><br>
                        Help: <code>php scripts/module_clean_tests_qa_runner.php --help</code>
                    </td>
                </tr>
                <tr>
                    <td><a href="module_browser_qa_runner.php" target="_blank" rel="nofollow noreferrer">module_browser_qa_runner.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>
                        Full-module QA across all <code>modules/*/index.php</code> entries for companies 1–5: login, then per module <strong>mysql</strong> (<code>database.sql</code> INSERT row count), <strong>rotate error_log.txt</strong>, list/<strong>clear</strong>/sample_data, <strong>add</strong>, <strong>bulk_delete</strong>, CRUD/export, <strong>clear_table</strong>, second <strong>clear</strong>, import/<strong>single_delete</strong>, end sample restore + <strong>error_log</strong>. <strong>Mutates DB:</strong> seeds sample data and inserts/imports test rows as part of the flow. Tier lists: <code>$bespokeSmoke</code> / <code>$skipClear</code> in <code>scripts/lib/mbqa_runner_tiers.php</code>. Browser <strong>Run QA</strong> silently runs <code>module_clean_tests_qa_runner.php</code> at start and end. Preflight validation, auto-detected Base URL on Laragon, structured <strong>import_db</strong> JSON parsing, stale AJAX cleanup. Optional browser-only <strong>UI click smoke</strong> (one module + one company) appends <code>bulk_cancel_click</code>, <code>pagination_click</code>, <code>export_xlsx_click</code>, <code>import_excel_click</code>. Writes timestamped <code>qa-reports/module-browser-qa-YYYY-MM-DD-HH-MM-SS.json</code> and matching <code>.xlsx</code> each run.
                    </td>
                    <td>
                        Browser: <a href="module_browser_qa_runner.php" target="_blank" rel="nofollow noreferrer">module_browser_qa_runner.php</a> — form: <strong>Run QA</strong> + <strong>Stop</strong> (AJAX poll every 400ms). Optional <strong>UI click smoke</strong> (one module + one company).<br>
                        <code>php scripts/module_browser_qa_runner.php</code><br>
                        <code>php scripts/module_browser_qa_runner.php --pilot-only</code> (expenses only)<br>
                        <code>php scripts/module_browser_qa_runner.php --module=expenses --company=1</code><br>
                        <code>php scripts/module_browser_qa_runner.php --module=license_management --company=1</code><br>
                        <code>php scripts/module_browser_qa_runner.php --ui-click-smoke --module=expenses --company=1</code> (CLI guard only — real clicks need the browser form)<br>
                        <code>php scripts/module_browser_qa_runner.php --help</code>
                    </td>
                </tr>
                <tr>
                    <td><a href="module_browser_qa_build_report.php" target="_blank" rel="nofollow noreferrer">module_browser_qa_build_report.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Builds markdown summary from a timestamped runner JSON: tier reference (<code>$bespokeSmoke</code>, <code>$skipClear</code>), configured step exceptions, per-module results, failure/skip indexes. Re-Run links preserve UI click smoke when set. Writes <code>qa-reports/module-browser-qa.md</code> (overwritten each build).</td>
                    <td>
                        Browser: <a href="module_browser_qa_build_report.php" target="_blank" rel="nofollow noreferrer">module_browser_qa_build_report.php</a> (form; <code>?run=1&amp;date=YYYY-MM-DD</code>)<br>
                        <code>php scripts/module_browser_qa_build_report.php</code><br>
                        <code>php scripts/module_browser_qa_build_report.php --date=2026-05-20</code>
                    </td>
                </tr>
                <tr>
                    <td><a href="check_employees_clear_table_transaction.php" target="_blank" rel="nofollow noreferrer">check_employees_clear_table_transaction.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Static guard: employees <code>clear_table</code> uses soft-delete via <code>employees_delete_record()</code> (detach + transaction + <code>itm_crud_build_soft_delete_sql</code>).</td>
                    <td><code>php scripts/check_employees_clear_table_transaction.php</code> — run manually after employees <code>clear_table</code> changes (AGENTS.md).</td>
                </tr>
                <tr>
                    <td><a href="check_equipment_clear_table_delete.php" target="_blank" rel="nofollow noreferrer">check_equipment_clear_table_delete.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Static guard: equipment <code>clear_table</code> / <code>equipment_delete_record()</code> helpers in <code>delete_functions.php</code>.</td>
                    <td><code>php scripts/check_equipment_clear_table_delete.php</code> — run manually after equipment delete/clear-table changes (AGENTS.md).</td>
                </tr>
                <tr>
                    <td><a href="check_ui_configuration_coverage.php" target="_blank" rel="nofollow noreferrer">check_ui_configuration_coverage.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>UI configuration hooks: table actions, new button, export toolbar, back/save on forms.</td>
                    <td>Browser: plain-text module list. CLI: <code>php scripts/check_ui_configuration_coverage.php</code> — run manually when module UI/layout changes; exits <code>2</code> on failure. Audits table actions, export card, search, Settings <code>records_per_page</code> pagination, bulk Select to Delete / Clear Table, and CRUD entry files. Skips modules in <code>scripts/data/ui_configuration_excluded_modules.txt</code> (e.g. <code>audit_logs</code>, <code>ip_subnets</code>, <code>rack_planner</code>) and prefixes in <code>ui_configuration_excluded_prefixes.txt</code> (e.g. <code>is_*</code>).</td>
                </tr>
                <tr>
                    <td><a href="check_audit_logs_coverage.php" target="_blank" rel="nofollow noreferrer">check_audit_logs_coverage.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Audit trail for mutations: <code>itm_run_query</code>, <code>itm_log_audit</code>, bulk helpers, or <code>trg_{table}_audit_*</code> in <code>database.sql</code>.</td>
                    <td>
                        Browser: HTML/plain report (PHP 7.4+); query <code>?module=NAME</code> or <code>?json=1</code>. CLI: <code>php scripts/check_audit_logs_coverage.php</code> — exit <code>2</code> on failures.<br>
                        <code>--module=NAME</code> · <code>--json</code>
                    </td>
                </tr>
                <tr>
                    <td><a href="sql_injection_matrix_test.php" target="_blank" rel="nofollow noreferrer">sql_injection_matrix_test.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Fixed payload matrix against <code>lib/sql_injection_detector.php</code>.</td>
                    <td>Browser: plain-text results. CLI: <code>php scripts/sql_injection_matrix_test.php</code> — non-zero exit if any case fails.</td>
                </tr>
                <tr>
                    <td><a href="db_field_active.php" target="_blank" rel="nofollow noreferrer">db_field_active.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Identifies tables missing the mandatory <code>active</code> column and detects code mismatches where queries expect this field on tables that lack it.</td>
                    <td>Browser: HTML report with detailed mismatch locations. CLI: <code>php scripts/db_field_active.php</code> — run after CRUD changes to ensure schema compliance.</td>
                </tr>
            </tbody>
        </table></div>
        <p class="scripts-muted" style="margin-top:12px;">
            Library (not run directly): <code>lib/sql_injection_detector.php</code>, <code>lib/equipment_type_modules.php</code> (canonical <code>modules/is_*</code> allowlist; safe removal of <code>*_itm_eqdct_*</code> / <code>*_itm_edct_*</code> test scaffolds only), <code>lib/script_cli_output.php</code> (wraps browser output in <code>&lt;pre&gt;</code> + shared nav), <code>lib/script_browser_nav.php</code>, <code>lib/utf8_file.php</code> (UTF-8 BOM file I/O), <code>lib/mbqa_import_helpers.php</code> (QA import resolution), <code>lib/mbqa_report_paths.php</code> (QA report naming), <code>lib/mbqa_step_display.php</code> (QA result display) (<strong>← Scripts index</strong>, relative module / table→module links).
        </p>
    </div>


    <div class="scripts-card" id="admin-tools">
        <h2>Administrative Tools</h2>
        <div class="scripts-table-wrap"><table class="scripts-table">
            <thead>
                <tr>
                    <th>Script</th>
                    <th class="scripts-access-col">Access</th>
                    <th>What it does</th>
                    <th>How to use</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><a href="sync_modules_registry.php" target="_blank" rel="nofollow noreferrer">sync_modules_registry.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Upserts <code>modules_registry</code> rows from filesystem module folders and sidebar-excluded slugs; seeds <code>company_module_access</code> for new registry rows. Sidebar discovery also auto-registers new tables/folders on page load — use this script for bulk backfill after deploy or when icons/labels need catalog sync.</td>
                    <td>Browser: <a href="sync_modules_registry.php">sync_modules_registry.php</a>. CLI: <code>php scripts/sync_modules_registry.php</code>. Run after adding module folders; optional when only a new MySQL table was created (sidebar auto-scaffold + register).</td>
                </tr>
                <tr>
                    <td><a href="verify_ops_report.php" target="_blank" rel="nofollow noreferrer">verify_ops_report.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Regression for <code>modules/ops_report/</code>: D-2 edit lock (today/yesterday editable; D-2+ locked unless admin), daily <code>ops_report</code> CRUD, child-row cascade delete, audit triggers on all <code>ops_report*</code> tables, and <code>modules_registry</code> slug <code>ops_report</code>.</td>
                    <td><code>php scripts/verify_ops_report.php</code>. PHPUnit: <code>php scripts/run_tests.php --filter OpsReport</code>. Run when changing <code>modules/ops_report/</code> or <code>ops_report*</code> tables in <code>database.sql</code>.</td>
                </tr>
                <tr>
                    <td><a href="verify_reports_hub.php" target="_blank" rel="nofollow noreferrer">verify_reports_hub.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Regression for <code>modules/reports/</code> Reports Hub: exercises every <code>api/helpers.php</code> chart payload, Hotel Operations MTD metrics (<code>ops_report</code>, <code>ops_report_fb_outlet</code>), budget vs actual / YoY totals, <code>modules_registry</code> slug <code>reports</code>, and core Chart.js canvas ids in <code>index.php</code>. Expects <code>database.sql</code> Reports Hub sample seeds.</td>
                    <td>Browser: <a href="verify_reports_hub.php">verify_reports_hub.php</a>. CLI: <code>php scripts/verify_reports_hub.php</code>. Optional <code>ITM_TEST_COMPANY_ID</code> (default 1). Run when changing <code>modules/reports/</code>, helpers, or Reports Hub seeds in <code>database.sql</code>.</td>
                </tr>
                <tr>
                    <td><a href="benchmark_sidebar_module_access.php" target="_blank" rel="nofollow noreferrer">benchmark_sidebar_module_access.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Read-only benchmark for sidebar generation: measures MySQL <code>Questions</code> delta for the live path (<code>itm_sidebar_structure()</code> + <code>has_module_access()</code> filter) vs an uncached legacy N+1 simulation (per-slug registry + admin + CMA queries and per-slug registry ensure). Reports median query count, timing, and reduction percentage. Requires prefetch cache in <code>includes/itm_company_module_access.php</code>.</td>
                    <td>CLI: <code>php scripts/benchmark_sidebar_module_access.php</code> · optional <code>--company=1 --employee=1 --iterations=3 --checks=100</code>. Browser: <a href="benchmark_sidebar_module_access.php">benchmark_sidebar_module_access.php</a> · query params <code>company</code>, <code>employee</code>, <code>iterations</code>, <code>checks</code>. Env thresholds: <code>ITM_BSMA_MAX_FULL_QUERIES</code> (default 45), <code>ITM_BSMA_MIN_REDUCTION_PCT</code> (default 50), plus BOLT journal component vars documented in <code>scripts/SCRIPTS.md</code>. Verifies claims in <code>docs/bolt.md</code>.</td>
                </tr>
                <tr>
                    <td><a href="verify_company_module_access.php" target="_blank" rel="nofollow noreferrer">verify_company_module_access.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Regression for registry coverage, opt-out deny, sidebar-excluded slugs in the admin matrix, company icon overrides, and disposable sidebar discovery probes: registry-only (no <code>modules/{slug}/</code>), new MySQL table (auto-scaffold), folder-only (<code>index.php</code>), registry + folder (single entry), neither (absent + denied). Uses <code>itm_sidebar_discovery_probe_cleanup()</code> for probe teardown.</td>
                    <td>Browser: <a href="verify_company_module_access.php">verify_company_module_access.php</a>. CLI: <code>php scripts/verify_company_module_access.php</code>. PHPUnit: <code>php scripts/run_tests.php --filter CompanyModuleAccessVerifyTest</code> (subprocess wrapper). Run when changing <code>includes/itm_company_module_access.php</code>, <code>includes/ui_config.php</code> sidebar discovery, or CMA enforcement.</td>
                </tr>
                <tr>
                    <td><a href="verify_roles_permissions.php" target="_blank" rel="nofollow noreferrer">verify_roles_permissions.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Regression for <code>modules/roles_permissions/</code>: registry row, module entry + matrix JS, RBAC-exempt slug, Admin <code>ALL</code> wildcard with six flags, seeded roles and <code>role_hierarchy</code> for company 1, <code>can_import</code>/<code>can_export</code> columns, role sidebar <code>active_count</code> (role_id + HR Active).</td>
                    <td>Browser: <a href="verify_roles_permissions.php">verify_roles_permissions.php</a>. CLI: <code>php scripts/verify_roles_permissions.php</code>. Run when changing <code>modules/roles_permissions/</code>, <code>js/roles-permissions-matrix.js</code>, or <code>role_module_permissions</code> / <code>employee_roles</code> schema.</td>
                </tr>
                <tr>
                    <td><a href="verify_dashboard_active_employees.php" target="_blank" rel="nofollow noreferrer">verify_dashboard_active_employees.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Regression for <code>dashboard.php</code> row-2 <strong>Active</strong> and <strong>On Leave</strong> stats: helper call-sites, no leftover join-predicate SQL, soft-delete-aware counts (optional <code>ITM_TEST_COMPANY_ID</code>).</td>
                    <td>Browser: <a href="verify_dashboard_active_employees.php">verify_dashboard_active_employees.php</a>. CLI: <code>php scripts/verify_dashboard_active_employees.php</code>. Run when changing <code>dashboard.php</code> or Active/On Leave employee count logic.</td>
                </tr>
                <tr>
                    <td><a href="verify_dashboard_online_employees.php" target="_blank" rel="nofollow noreferrer">verify_dashboard_online_employees.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Regression for <code>dashboard.php</code> row-2 <strong>Online now</strong> stat: session presence helper, <code>config/config.php</code> touch hook, count after touch.</td>
                    <td>Browser: <a href="verify_dashboard_online_employees.php">verify_dashboard_online_employees.php</a>. CLI: <code>php scripts/verify_dashboard_online_employees.php</code>. Run when changing online session presence or dashboard Online now markup.</td>
                </tr>
                <tr>
                    <td><a href="seed_company_module_access.php" target="_blank" rel="nofollow noreferrer">seed_company_module_access.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Backfills <code>company_module_access</code> rows as <code>enabled=1</code> for active companies (all modules or one <code>company_id</code>). Calls <code>sync_modules_registry.php</code> first when seeding a single company.</td>
                    <td>CLI: <code>php scripts/seed_company_module_access.php</code> (all companies) or <code>php scripts/seed_company_module_access.php 3</code> (one company). Browser: <a href="seed_company_module_access.php">seed_company_module_access.php</a>.</td>
                </tr>
                <tr>
                    <td><code>bypass_login.php</code></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI-only</span></span></td>
                    <td>Bypasses the login screen by manually establishing an authenticated Admin session in the database and returning the session ID. Sets up Admin user, TechCorp Global company, and Vault master key.</td>
                    <td><code>php scripts/bypass_login.php</code> — CLI-only Admin session hijack for dev/Playwright (non-admin users rejected via <code>itm_is_admin()</code>). Follow CLI instructions to set <code>PHPSESSID</code> in the browser.</td>
                </tr>
                <tr>
                    <td><code>bypass_v2.php</code></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI-only</span></span></td>
                    <td>CLI-only Admin session hijack for dev/Playwright (non-admin users rejected via <code>itm_is_admin()</code>). Sets up Admin user, TechCorp Global company, and Vault master key.</td>
                    <td><code>php scripts/bypass_v2.php</code> — CLI-only Admin session hijack for dev/Playwright (non-admin users rejected via <code>itm_is_admin()</code>). Follow CLI instructions to set <code>PHPSESSID</code> in the browser.</td>
                </tr>
                <tr>
                    <td><a href="sql_insert.php" target="_blank" rel="nofollow noreferrer">sql_insert.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Allows administrators to paste and execute raw SQL <code>INSERT</code> commands with optional Foreign Key check toggling. Maintains audit logging.</td>
                    <td>Browser: <a href="sql_insert.php">sql_insert.php</a> (form).<br>CLI: <code>php scripts/sql_insert.php --file=path/to/file.sql [--disable-fk]</code></td>
                </tr>
                <tr>
                    <td><code>take_screenshots_passwords.py</code></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI-only</span></span></td>
                    <td>Automated UI screenshot utility. Authenticates as Admin and captures key states of Bookmarks and Passwords modules. Requires Playwright.</td>
                    <td><code>python3 scripts/take_screenshots_passwords.py</code></td>
                </tr>
                <tr>
                    <td><code>take_screenshots_modules.py</code></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI-only</span></span></td>
                    <td>Playwright screenshots for README module images. Default slugs: <code>todo</code>, <code>notes</code>, <code>roles_permissions</code>, <code>system_status</code>. Uses <code>bypass_login.php</code> + <code>sudo chown www-data</code> on the sess file; cookie domain follows the base URL hostname; waits for <code>#rp-permission-matrix</code> (Roles &amp; Permissions) and <code>#system-info-content</code> (System Status) before saving.</td>
                    <td><code>ITM_SCREENSHOT_ONLY=roles_permissions python3 scripts/take_screenshots_modules.py</code> · <code>ITM_SCREENSHOT_ONLY=system_status python3 scripts/take_screenshots_modules.py</code> · optional <code>ITM_SCREENSHOT_BASE_URL</code>, <code>ITM_SCREENSHOT_MODULES</code> (see <code>scripts/SCRIPTS.md</code>).</td>
                </tr>
                <tr>
                    <td><code>take_screenshots_modules_all.py</code></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI-only</span></span></td>
                    <td>Bulk UI screenshot utility for all modules. Requires Playwright.</td>
                    <td><code>python3 scripts/take_screenshots_modules_all.py</code></td>
                </tr>
                <tr>
                    <td><code>test_notes_human.py</code></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI-only</span></span></td>
                    <td>Playwright-based human-flow regression for Notes module.</td>
                    <td><code>python3 scripts/test_notes_human.py</code></td>
                </tr>
                <tr>
                    <td><code>update_display.py</code></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI-only</span></span></td>
                    <td>Display update utility.</td>
                    <td><code>python3 scripts/update_display.py</code></td>
                </tr>
                <tr>
                    <td><code>verify_dnd.py</code></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI-only</span></span></td>
                    <td>Verifies Drag and Drop functionality in UI.</td>
                    <td><code>python3 scripts/verify_dnd.py</code></td>
                </tr>
                <tr>
                    <td><a href="verify_todo.py">verify_todo.py</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI-only</span></span></td>
                    <td>Verifies Todo module functionality via Playwright.</td>
                    <td><code>python3 scripts/verify_todo.py</code></td>
                </tr>
                <tr>
                    <td><a href="verify_todo_categories.py">verify_todo_categories.py</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI-only</span></span></td>
                    <td>Verifies Todo categories functionality via Playwright.</td>
                    <td><code>python3 scripts/verify_todo_categories.py</code></td>
                </tr>
                <tr>
                    <td><code>verify_notes_ui.py</code></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI-only</span></span></td>
                    <td>Verifies Notes module functionality via Playwright.</td>
                    <td><code>python3 scripts/verify_notes_ui.py</code></td>
                </tr>
                <tr>
                    <td><a href="repro_bug.php" target="_blank" rel="nofollow noreferrer">repro_bug.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Reproduction and verification script for Todo module visibility and security bugs (multi-assignment and IDOR).</td>
                    <td><code>php scripts/repro_bug.php</code></td>
                </tr>
                <tr>
                    <td><a href="repro_vulnerabilities.php" target="_blank" rel="nofollow noreferrer">repro_vulnerabilities.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Reproduction and verification script for Explorer RCE, User Privilege Escalation, and Unauthorized Access to Role Module Permissions. Subprocess spawn uses <code>escapeshellarg()</code>.</td>
                    <td>Open in browser or run via CLI: <code>php scripts/repro_vulnerabilities.php</code></td>
                </tr>
                <tr>
                    <td><a href="repro_bac.php" target="_blank" rel="nofollow noreferrer">repro_bac.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>PoC for Broken Access Control in IDFs API.</td>
                    <td><code>php scripts/repro_bac.php</code></td>
                </tr>
                <tr>
                    <td><a href="repro_bac_updated.php" target="_blank" rel="nofollow noreferrer">repro_bac_updated.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Validation for IDFs API BAC fix.</td>
                    <td><code>php scripts/repro_bac_updated.php</code></td>
                </tr>
                <tr>
                    <td><a href="repro_rce.php" target="_blank" rel="nofollow noreferrer">repro_rce.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>PoC for RCE in Floor Designer via 'save_as_floor_plan' action.</td>
                    <td><code>php scripts/repro_rce.php</code></td>
                </tr>
                <tr>
                    <td><a href="repro_rce_updated.php" target="_blank" rel="nofollow noreferrer">repro_rce_updated.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Validation for Floor Designer RCE fix.</td>
                    <td><code>php scripts/repro_rce_updated.php</code></td>
                </tr>
                <tr>
                    <td><a href="repro_sqli.php" target="_blank" rel="nofollow noreferrer">repro_sqli.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>PoC for SQL Injection in Floor Designer via 'dir' parameter.</td>
                    <td><code>php scripts/repro_sqli.php</code></td>
                </tr>
                <tr>
                    <td><a href="repro_sqli_updated.php" target="_blank" rel="nofollow noreferrer">repro_sqli_updated.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Validation for Floor Designer SQLi fix.</td>
                    <td><code>php scripts/repro_sqli_updated.php</code></td>
                </tr>
                <tr>
                    <td><a href="repro_request_password_bypass.php" target="_blank" rel="nofollow noreferrer">repro_request_password_bypass.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Reproduction script for request_password module bypass.</td>
                    <td><code>php scripts/repro_request_password_bypass.php</code></td>
                </tr>
                <tr>
                    <td><a href="repro_select_options_rbac.php" target="_blank" rel="nofollow noreferrer">repro_select_options_rbac.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Reproduction script for Select Options RBAC bypass.</td>
                    <td><code>php scripts/repro_select_options_rbac.php</code></td>
                </tr>
                <tr>
                    <td><a href="repro_equip_issues.php" target="_blank" rel="nofollow noreferrer">repro_equip_issues.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Reproduction script for Equipment edit issues. Mocks a POST request to equipment/edit.php.</td>
                    <td><code>php scripts/repro_equip_issues.php</code></td>
                </tr>
                <tr>
                    <td><a href="repro_esa_vulnerability.php" target="_blank" rel="nofollow noreferrer">repro_esa_vulnerability.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>PoC for Employee System Access Broken Access Control (non-admin access to edit page).</td>
                    <td><code>php scripts/repro_esa_vulnerability.php</code></td>
                </tr>
                <tr>
                    <td><a href="benchmark_stats_optimized.php" target="_blank" rel="nofollow noreferrer">benchmark_stats_optimized.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Benchmark for user-config.php stats gathering optimization. Compares performance of individual queries vs one consolidated query.</td>
                    <td><code>php scripts/benchmark_stats_optimized.php</code></td>
                </tr>
                <tr>
                    <td><a href="benchmark_user_config.php" target="_blank" rel="nofollow noreferrer">benchmark_user_config.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span><span class="scripts-badge scripts-badge-web">Browser</span></span></td>
                    <td>Benchmark for user-config.php redundant query removal. Compares performance of individual queries vs consolidated query results.</td>
                    <td><code>php scripts/benchmark_user_config.php</code></td>
                </tr>
            </tbody>
        </table></div>
    </div>
    <div class="scripts-card" id="system-status">
        <h2>System Status</h2>
        <div class="scripts-table-wrap"><table class="scripts-table">
            <thead>
                <tr>
                    <th>Script</th>
                    <th class="scripts-access-col">Access</th>
                    <th>What it does</th>
                    <th>How to use</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><a href="verify_system_status.php" target="_blank" rel="nofollow noreferrer">verify_system_status.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Regression for <code>modules/system_status/</code>: file layout, registry row, native API payloads, storage tree + active DB table reports, <code>information_schema</code> query; Windows also checks <code>is_readable()</code> on each <code>includes/*.ps1</code> and runs <code>test_*.php</code> PowerShell wrappers.</td>
                    <td><code>php scripts/verify_system_status.php</code></td>
                </tr>
                <tr>
					<td><a href="system_status_api.php" target="_blank" rel="nofollow noreferrer">system_status_api.php</a></td>
					<td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span></span></td>
					<td>Admin JSON API dispatcher (<code>?action=system_info</code>, etc.). PHP/MySQL actions always native; Windows hardware uses <code>includes/*.ps1</code> via allowlisted <code>itm_system_status_run_powershell_action()</code>. Invalid <code>action</code> → HTTP 400. Documented in <a href="api.php">api.php</a>.</td>
					<td>GET <code>scripts/system_status_api.php?action=cpu_usage</code> (Admin session)</td>
				</tr>
                <tr>
					<td><a href="system_status_phpinfo.php" target="_blank" rel="nofollow noreferrer">system_status_phpinfo.php</a></td>
					<td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span></span></td>
					<td>Admin-only <code>phpinfo()</code> for the active Apache PHP runtime (linked from System Status → PHP Settings).</td><td>GET <code>scripts/system_status_phpinfo.php</code> (Admin session)</td>
				</tr>
                <tr>
					<td><a href="test_system_info.php" target="_blank" rel="nofollow noreferrer">test_system_info.php</a></td>
					<td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
					<td>Tests <code>includes/system_info.ps1</code> JSON output (Windows Laragon).</td>
					<td><code>php scripts/test_system_info.php</code></td>
				</tr>
                <tr>
					<td><a href="test_cpu_usage.php" target="_blank" rel="nofollow noreferrer">test_cpu_usage.php</a></td>
					<td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
					<td>Tests cpu_usage.ps1 output.</td>
					<td><code>php scripts/test_cpu_usage.php</code></td>
				</tr>
                <tr>
					<td><a href="test_ram_usage.php" target="_blank" rel="nofollow noreferrer">test_ram_usage.php</a></td>
					<td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
					<td>Tests ram_usage.ps1 output.</td>
					<td><code>php scripts/test_ram_usage.php</code></td>
				</tr>
                <tr>
					<td><a href="test_disk_usage.php" target="_blank" rel="nofollow noreferrer">test_disk_usage.php</a></td>
					<td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
					<td>Tests disk_usage.ps1 output.</td>
					<td><code>php scripts/test_disk_usage.php</code></td>
				</tr>
                <tr>
					<td><a href="test_uptime.php" target="_blank" rel="nofollow noreferrer">test_uptime.php</a></td>
					<td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
					<td>Tests uptime.ps1 output.</td>
					<td><code>php scripts/test_uptime.php</code></td>
				</tr>
                <tr>
					<td><a href="test_php_version.php" target="_blank" rel="nofollow noreferrer">test_php_version.php</a></td>
					<td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
					<td>Tests php_version.ps1 output.</td>
					<td><code>php scripts/test_php_version.php</code></td>
				</tr>
                <tr>
					<td><a href="test_php_extensions.php" target="_blank" rel="nofollow noreferrer">test_php_extensions.php</a></td>
					<td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
					<td>Tests php_extensions.ps1 output.</td>
					<td><code>php scripts/test_php_extensions.php</code></td>
				</tr>
                <tr>
					<td><a href="test_php_ini_values.php" target="_blank" rel="nofollow noreferrer">test_php_ini_values.php</a></td>
					<td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
					<td>Tests php_ini_values.ps1 output.</td>
					<td><code>php scripts/test_php_ini_values.php</code></td>
				</tr>
                <tr>
					<td><a href="test_mysql_status.php" target="_blank" rel="nofollow noreferrer">test_mysql_status.php</a></td>
					<td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
					<td>Tests mysql_status.ps1 output.</td>
					<td><code>php scripts/test_mysql_status.php</code></td>
				</tr>
                <tr>
					<td><a href="test_mysql_version.php" target="_blank" rel="nofollow noreferrer">test_mysql_version.php</a></td>
					<td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
					<td>Tests mysql_version.ps1 output.</td>
					<td><code>php scripts/test_mysql_version.php</code></td>
				</tr>
                <tr>
					<td><a href="test_mysql_databases.php" target="_blank" rel="nofollow noreferrer">test_mysql_databases.php</a></td>
					<td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
					<td>Tests mysql_databases.ps1 output.</td>
					<td><code>php scripts/test_mysql_databases.php</code></td>
				</tr>
                <tr>
					<td><a href="test_mysql_size.php" target="_blank" rel="nofollow noreferrer">test_mysql_size.php</a></td>
					<td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
					<td>Tests mysql_size.ps1 output.</td>
					<td><code>php scripts/test_mysql_size.php</code></td>
				</tr>
            </tbody>
        </table></div>
    </div>
    <div class="scripts-card" id="verification">
        <h2>Verification</h2>
        <div class="scripts-table-wrap"><table class="scripts-table">
            <thead>
                <tr>
                    <th>Script</th>
                    <th class="scripts-access-col">Access</th>
                    <th>What it does</th>
                    <th>How to use</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><a href="apitest_tier_free.php" target="_blank" rel="nofollow noreferrer">apitest_tier_free.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Regression: disposable <code>ui_configuration</code> row on <strong>Free</strong> tier (no <code>api_key</code>) stays unlimited; session resolve and HTTP probe via <code>itm_apitest_publish_http_session()</code>. Prints keyless probe URL <code>scripts/api.php?rate_limit=1</code>.</td>
                    <td><code>php scripts/apitest_tier_free.php</code></td>
                </tr>
                <tr>
                    <td><a href="apitest_tier_basic.php" target="_blank" rel="nofollow noreferrer">apitest_tier_basic.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Regression: disposable <code>ui_configuration</code> row on <strong>Basic</strong> tier allows the final hourly request then blocks the next. Auto-generates API key and prints browser URL <code>scripts/api.php?rate_limit=1&amp;api_key=…</code>.</td>
                    <td><code>php scripts/apitest_tier_basic.php</code></td>
                </tr>
                <tr>
                    <td><a href="verify_select_options_escalation.php" target="_blank" rel="nofollow noreferrer">verify_select_options_escalation.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Regression check: Select Options API blocks admin employee quick-add via <code>employees</code> table (expects PASS).</td>
                    <td><code>php scripts/verify_select_options_escalation.php</code></td>
                </tr>
                <tr>
                    <td><a href="verify_user_idor.php" target="_blank" rel="nofollow noreferrer">verify_user_idor.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>PoC for unauthorized user account deletion via IDOR.</td>
                    <td><code>php scripts/verify_user_idor.php</code></td>
                </tr>
                <tr>
                    <td><a href="verify_crud_fk_label_search.php" target="_blank" rel="nofollow noreferrer">verify_crud_fk_label_search.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Regression: Employees <code>?search=Active</code> matches <code>employee_statuses.name</code>; <code>?search=FNB</code> matches <code>departments.code</code>; equipment <code>?search=FNB</code> matches linked <code>departments.code</code>; license_management search matches <code>license_types.name</code>; shared FK EXISTS helper; bespoke modules (switch_ports, todo, notes, private_contacts, ip_subnets, bookmarks, passwords) label search.</td>
                    <td><code>php scripts/verify_crud_fk_label_search.php</code> — run after changing list search or FK label helpers.</td>
                </tr>
                <tr>
                    <td><a href="verify_employees_equipment_search_coverage.php" target="_blank" rel="nofollow noreferrer">verify_employees_equipment_search_coverage.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Regression: dedicated employees + equipment index search probes — <code>first_name</code>, <code>last_name</code>, <code>username</code>, full name, FK codes (<code>FNB</code>, <code>LOC-NY-01</code>, <code>SUP-001</code>, <code>RACK-A</code>), position description, manager username, assignee identity on equipment. Disposable employee + equipment rows; optional <code>ITM_TEST_COMPANY_ID</code> (default <code>1</code>).</td>
                    <td><code>php scripts/verify_employees_equipment_search_coverage.php</code> — run after changing <code>itm_employees_search.php</code>, <code>itm_equipment_search.php</code>, or employees/equipment index search. Covers 34 runtime probes (scalar identity, FK labels, equipment scalars).</td>
                </tr>
                <tr>
                    <td><a href="verify_employees_sensitive_view.php" target="_blank" rel="nofollow noreferrer">verify_employees_sensitive_view.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Regression check: Employees list/view HTML omits password and reset-token columns (<code>itm_employees_auth_filter_ui_columns()</code>).</td>
                    <td><code>php scripts/verify_employees_sensitive_view.php</code></td>
                </tr>
                <tr>
                    <td><a href="verify_reset_git_history_access.php" target="_blank" rel="nofollow noreferrer">verify_reset_git_history_access.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Confirms lack of access control on destructive Git history reset utility.</td>
                    <td><code>php scripts/verify_reset_git_history_access.php</code></td>
                </tr>
                <tr>
                    <td><a href="verify_explorer_zip_leak.php" target="_blank" rel="nofollow noreferrer">verify_explorer_zip_leak.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Regression for Explorer <code>downloadZip</code>: Step 1 blocks roots. Step 2 allows only exact <code>Private/{username}_{employee_id}</code>. Step 3 blocks all other paths (own subfolders, <code>Common</code>/<code>Departments</code>, other users). Subprocess uses Laragon CLI <code>php.exe</code> and session before <code>config.php</code>.</td>
                    <td><code>php scripts/verify_explorer_zip_leak.php</code></td>
                </tr>
                <tr>
                    <td><a href="verify_company_deletion.php" target="_blank" rel="nofollow noreferrer">verify_company_deletion.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>PoC for unauthorized company deletion by regular employees.</td>
                    <td><code>php scripts/verify_company_deletion.php</code></td>
                </tr>
                <tr>
                    <td><a href="repro_auth_bypass_v3.php" target="_blank" rel="nofollow noreferrer">repro_auth_bypass_v3.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Reproduction script for Auth Bypass v3 vulnerability. Subprocess spawn uses <code>escapeshellarg()</code>.</td>
                    <td><code>php scripts/repro_auth_bypass_v3.php</code></td>
                </tr>
                <tr>
                    <td><a href="empty_folders.php" target="_blank" rel="nofollow noreferrer">empty_folders.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Backfill empty <code>index.html</code> on <strong>every</strong> folder under the project root (skips <code>.git</code>, <code>.github</code>, and other dot dirs). Lists only <strong>new or changed</strong> repo-relative <code>path/index.html</code> paths before the summary. Upload paths (<code>images/</code>, <code>tickets_photos/</code>, <code>floor_plans/</code>, <code>backups/</code>, <code>files/</code>) also receive managed <code>.htaccess</code> (idempotent).</td>
                    <td><code>php scripts/empty_folders.php</code></td>
                </tr>
                <tr>
                    <td><a href="ensure_files_htaccess_chain.php" target="_blank" rel="nofollow noreferrer">ensure_files_htaccess_chain.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Backfill <code>deny_http</code> managed <code>.htaccess</code> and empty <code>index.html</code> on every directory segment under <code>files/</code> only. Lists only <strong>new or changed</strong> segments before the summary (idempotent).</td>
                    <td><code>php scripts/ensure_files_htaccess_chain.php</code></td>
                </tr>
                <tr>
                    <td><a href="verify_explorer_rce_htaccess.php" target="_blank" rel="nofollow noreferrer">verify_explorer_rce_htaccess.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>PoC for .htaccess-based RCE in the Explorer module.</td>
                    <td><code>php scripts/verify_explorer_rce_htaccess.php</code></td>
                </tr>
                <tr>
                    <td><a href="verify_explorer_rce_marker.php" target="_blank" rel="nofollow noreferrer">verify_explorer_rce_marker.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>PoC for .htaccess-based RCE in the Explorer module using a specific marker to bypass filters.</td>
                    <td><code>php scripts/verify_explorer_rce_marker.php</code></td>
                </tr>
                <tr>
                    <td><a href="test_explorer_paths.php" target="_blank" rel="nofollow noreferrer">test_explorer_paths.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Regression tests for Explorer path validation logic including sensitive root folder blocking.</td>
                    <td><code>php scripts/test_explorer_paths.php</code></td>
                </tr>
                <tr>
                    <td><a href="test_explorer_preview.php" target="_blank" rel="nofollow noreferrer">test_explorer_preview.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Regression tests for Explorer preview routing so JPG/PNG/PDF use <code>file.php</code> and text types use the open API.</td>
                    <td><code>php scripts/test_explorer_preview.php</code></td>
                </tr>
                <tr>
                    <td><a href="verify_audit_logs_disclosure.php" target="_blank" rel="nofollow noreferrer">verify_audit_logs_disclosure.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Three-step regression for employees audit disclosure: (1) static <code>database.sql</code> trigger scan — <code>trg_employees_audit_*</code> must omit <code>password</code>, <code>vault_key_hash</code>, <code>reset_token*</code>; (2) live disposable employee UPDATE probe via <code>itm_script_test_employee.php</code>; (3) retro scan of last 25 <code>employees</code> audit rows. Prints each step with <code>[PASS]</code>/<code>[FAIL]</code>. Optional env: <code>ITM_TEST_COMPANY_ID</code> (default <code>1</code>).</td>
                    <td><code>php scripts/verify_audit_logs_disclosure.php</code></td>
                </tr>
                <tr>
                    <td><a href="auth_register_reset_human_test.php" target="_blank" rel="nofollow noreferrer">auth_register_reset_human_test.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Human-style auth regression: invitation create, register INSERT with tenant Active employment status, login lookup + <code>password_verify</code>, reset-token password change, and <code>mysqli_stmt_bind_param</code> contracts on public auth pages. <strong>Mutates DB:</strong> disposable invitations and <code>script-*</code> employees (teardown on exit).</td>
                    <td><code>php scripts/auth_register_reset_human_test.php</code> — default companies 1–2; <code>php scripts/auth_register_reset_human_test.php --company=2</code>. Run after <code>register.php</code>, <code>login.php</code>, <code>forgot-password.php</code>, or <code>reset-password.php</code> changes.</td>
                </tr>
                <tr>
                    <td><a href="verify_password_reset_flow.php" target="_blank" rel="nofollow noreferrer">verify_password_reset_flow.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Password reset token regression: store with MySQL <code>DATE_ADD</code> expiry (24 hours), hash lookup, legacy plain <code>reset_token</code> fallback, and completion UPDATE. <strong>Mutates DB:</strong> disposable script-test employee (teardown on exit).</td>
                    <td><code>php scripts/verify_password_reset_flow.php</code> — run after <code>includes/itm_password_reset.php</code>, <code>forgot-password.php</code>, or <code>reset-password.php</code> changes.</td>
                </tr>
                <tr>
                    <td><a href="verify_invitations_escalation.php" target="_blank" rel="nofollow noreferrer">verify_invitations_escalation.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Verification script for authorization bypass and privilege escalation in registration invitations.</td>
                    <td><code>php scripts/verify_invitations_escalation.php</code></td>
                </tr>
                <tr>
                    <td><a href="repro_zip_slip.php" target="_blank" rel="nofollow noreferrer">repro_zip_slip.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Reproduction script for Zip Slip (path traversal during extraction) vulnerability.</td>
                    <td><code>php scripts/repro_zip_slip.php</code></td>
                </tr>
                <tr>
                    <td><a href="repro_notes_traversal.php" target="_blank" rel="nofollow noreferrer">repro_notes_traversal.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>PoC for Path Traversal and arbitrary file read via Notes ZIP download.</td>
                    <td><code>php scripts/repro_notes_traversal.php</code></td>
                </tr>
                <tr>
                    <td><a href="repro_notes_idor.php" target="_blank" rel="nofollow noreferrer">repro_notes_idor.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>PoC for Cross-user IDOR (unauthorized view/delete) in the Notes module.</td>
                    <td><code>php scripts/repro_notes_idor.php</code></td>
                </tr>
                <tr>
                    <td><a href="verify_metadata_column_cache.php" target="_blank" rel="nofollow noreferrer">verify_metadata_column_cache.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Regression for table-level <code>information_schema</code> caching in <code>itm_table_has_column()</code> and <code>itm_table_column_is_nullable()</code> (<code>includes/bootstrap_helpers.php</code>). Cold batch expects schema <code>Questions</code> delta 1–2; warm repeat expects schema delta 0 (trailing <code>SHOW STATUS</code> excluded from delta). Optional env: <code>ITM_META_CACHE_TABLE</code> (default <code>switch_ports</code>).</td>
                    <td><code>php scripts/verify_metadata_column_cache.php</code></td>
                </tr>
                <tr>
                    <td><a href="verify_update_port_zero_row.php" target="_blank" rel="nofollow noreferrer">verify_update_port_zero_row.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Regression for <code>includes/update_port.php</code>: zero-row tenant-scoped UPDATE returns HTTP 404 before any IDF auto-sync (idf_ports row count unchanged). Creates disposable probe equipment in a transaction when the tenant has no switch_ports rows. Subprocess seeds session tenant before <code>config.php</code>, uses <code>ITM_HTTP_ENDPOINT_CONTRACT_TEST</code>, and stubs <code>itm_api_json_response()</code> to capture HTTP status. Optional env: <code>ITM_TEST_COMPANY_ID</code> (default <code>1</code>).</td>
                    <td><code>php scripts/verify_update_port_zero_row.php</code></td>
                </tr>
                <tr>
                    <td><a href="verify_notes_ajax_contract.php" target="_blank" rel="nofollow noreferrer">verify_notes_ajax_contract.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Regression for Notes AJAX mutations: blocked single_delete returns HTTP 404 with ok:false when affected_rows is zero.</td>
                    <td><code>php scripts/verify_notes_ajax_contract.php</code></td>
                </tr>
                <tr>
                    <td><a href="verify_json_import_validation.php" target="_blank" rel="nofollow noreferrer">verify_json_import_validation.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Regression for JSON import: invalid numeric, date/datetime, and enum column values are rejected instead of silent NULL inserts.</td>
                    <td><code>php scripts/verify_json_import_validation.php</code></td>
                </tr>
                <tr>
                    <td><a href="verify_maintenance_scripts_rbac.php" target="_blank" rel="nofollow noreferrer">verify_maintenance_scripts_rbac.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Regression for Admin browser gate on maintenance scripts (MBQA runner, compare_database_sql_modules, test_sql_injection).</td>
                    <td><code>php scripts/verify_maintenance_scripts_rbac.php</code></td>
                </tr>
                <tr>
                    <td><a href="repro_audit_disclosure.php" target="_blank" rel="nofollow noreferrer">repro_audit_disclosure.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>PoC for Sensitive Information Disclosure (reset tokens) in Audit Logs. Uses a disposable script test user (not seed Admin id 1).</td>
                    <td><code>php scripts/repro_audit_disclosure.php</code></td>
                </tr>
                <tr>
                    <td><a href="check_crud_rbac_coverage.php" target="_blank" rel="nofollow noreferrer">check_crud_rbac_coverage.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Static audit for server-side CRUD RBAC on flattened <code>modules/*/index.php</code> delete/create/edit handlers. Exit <code>1</code> when guards are missing.</td>
                    <td><code>php scripts/check_crud_rbac_coverage.php</code></td>
                </tr>
                <tr>
                    <td><a href="apply_crud_rbac_guards.php" target="_blank" rel="nofollow noreferrer">apply_crud_rbac_guards.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Bulk repair — insert <code>itm_require_crud_role_module_permission()</code> on flattened CRUD index handlers. <strong>Default = dry-run</strong>; writes with CLI <code>--apply</code> or browser <code>?apply=1</code> (Admin). Lists scanned / changed / skipped modules (exempt slugs skipped). Idempotent.</td>
                    <td>Browser: <a href="apply_crud_rbac_guards.php">dry-run</a> / <a href="apply_crud_rbac_guards.php?apply=1">apply=1</a>. CLI: <code>php scripts/apply_crud_rbac_guards.php</code> then <code>php scripts/apply_crud_rbac_guards.php --apply</code>.</td>
                </tr>
                <tr>
                    <td><a href="repro_rbac_bypass.php" target="_blank" rel="nofollow noreferrer">repro_rbac_bypass.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>PoC for RBAC bypass on Expenses <code>delete.php</code>: read-only role must get HTTP 403 and retain the row. Uses a free <code>cost_centers</code> slot (<code>uq_expenses_company_scope</code>). Subprocess spawn uses <code>escapeshellarg()</code>; do not stub <code>cr_require_valid_csrf_token()</code>.</td>
                    <td><code>php scripts/repro_rbac_bypass.php</code></td>
                </tr>
                <tr>
                    <td><a href="repro_employee_companies_leak.php" target="_blank" rel="nofollow noreferrer">repro_employee_companies_leak.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>PoC for Multi-Tenant Data Leak in Employees module. Subprocess spawn uses <code>escapeshellarg()</code>.</td>
                    <td><code>php scripts/repro_employee_companies_leak.php</code></td>
                </tr>
                <tr>
                    <td><a href="repro_employee_companies_bac.php" target="_blank" rel="nofollow noreferrer">repro_employee_companies_bac.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>PoC for Broken Access Control in Employees module. Subprocess spawn uses <code>escapeshellarg()</code>.</td>
                    <td><code>php scripts/repro_employee_companies_bac.php</code></td>
                </tr>
                <tr>
                    <td><a href="repro_audit_token_leak.php" target="_blank" rel="nofollow noreferrer">repro_audit_token_leak.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Verification for Audit Log Sensitive Data Exposure. Disposable test user; prepared <code>UPDATE employees</code> for <code>reset_token</code> fields.</td>
                    <td><code>php scripts/repro_audit_token_leak.php</code></td>
                </tr>
                <tr>
                    <td><a href="repro_destructive_import.php" target="_blank" rel="nofollow noreferrer">repro_destructive_import.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Reproduction — destructive employee import scenario for company 1. <strong>Browser + CLI dry-run default</strong>; <code>--apply</code> / <code>?apply=1</code> (Admin) seeds disposable Keep/Delete Me rows, imports only Keep Me, asserts Delete Me survives, tears down disposable rows.</td>
                    <td>Browser: <a href="repro_destructive_import.php">dry-run</a>, <a href="repro_destructive_import.php?apply=1">apply</a>. CLI: <code>php scripts/repro_destructive_import.php</code>, <code>php scripts/repro_destructive_import.php --apply</code></td>
                </tr>
                <tr>
                    <td><a href="repro_todo_user_leak.php" target="_blank" rel="nofollow noreferrer">repro_todo_user_leak.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Reproduction script for multi-tenant username leak in Todo module.</td>
                    <td><code>php scripts/repro_todo_user_leak.php</code></td>
                </tr>
                <tr>
                    <td><a href="repro_cross_tenant_admin.php" target="_blank" rel="nofollow noreferrer">repro_cross_tenant_admin.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Reproduction script for cross-tenant admin access in Employees module.</td>
                    <td class='scripts-table-cell'><code>php scripts/repro_cross_tenant_admin.php</code></td>
                </tr>
                <tr>
                    <td><a href="repro_explorer_path_bypass_v4.php" target="_blank" rel="nofollow noreferrer">repro_explorer_path_bypass_v4.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Regression — Explorer <code>./Private</code> path bypass blocked after normalization.</td>
                    <td><code>php scripts/repro_explorer_path_bypass_v4.php</code></td>
                </tr>
                <tr>
                    <td><a href="repro_explorer_traversal.php" target="_blank" rel="nofollow noreferrer">repro_explorer_traversal.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span><span class="scripts-badge scripts-badge-web">Browser</span></span></td>
                    <td>Reproduction script for Explorer Path Traversal vulnerability via 'item' parameter.</td>
                    <td><code>php scripts/repro_explorer_traversal.php</code></td>
                </tr>
                <tr>
                    <td><a href="verify_explorer_fix.php" target="_blank" rel="nofollow noreferrer">verify_explorer_fix.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span><span class="scripts-badge scripts-badge-web">Browser</span></span></td>
                    <td>Verification script for Explorer Path Traversal fix.</td>
                    <td><code>php scripts/verify_explorer_fix.php</code></td>
                </tr>
                <tr>
                    <td><a href="verify_explorer_fix_updated.php" target="_blank" rel="nofollow noreferrer">verify_explorer_fix_updated.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span><span class="scripts-badge scripts-badge-web">Browser</span></span></td>
                    <td>Updated verification script for Explorer Path Traversal fix.</td>
                    <td><code>php scripts/verify_explorer_fix_updated.php</code></td>
                </tr>
                <tr>
                    <td><a href="verify_explorer_fix_web.php" target="_blank" rel="nofollow noreferrer">verify_explorer_fix_web.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span></span></td>
                    <td>Web-friendly verification for Explorer Path Traversal fix.</td>
                    <td>Open <code>verify_explorer_fix_web.php</code> in browser.</td>
                </tr>
                <tr>
                    <td><a href="verify_explorer_fix_standalone.php" target="_blank" rel="nofollow noreferrer">verify_explorer_fix_standalone.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span></span></td>
                    <td>Standalone verification for Explorer Path Traversal fix (HTML UI).</td>
                    <td>Open <code>verify_explorer_fix_standalone.php</code> in browser.</td>
                </tr>
                <tr>
                    <td><a href="repro_attempts_data_leak_v2.php" target="_blank" rel="nofollow noreferrer">repro_attempts_data_leak_v2.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Regression — password-like login identifiers redacted in <code>attempts.email</code> (disposable secret per run; checks only the row inserted by this request).</td>
                    <td>Browser: <a href="repro_attempts_data_leak_v2.php">run</a>. CLI: <code>php scripts/repro_attempts_data_leak_v2.php</code></td>
                </tr>
                <tr>
                    <td><a href="repro_explorer_zip_slip_v2.php" target="_blank" rel="nofollow noreferrer">repro_explorer_zip_slip_v2.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Regression — Zip Slip traversal entries blocked during Explorer unzip.</td>
                    <td><code>php scripts/repro_explorer_zip_slip_v2.php</code></td>
                </tr>
                <tr>
                    <td><a href="repro_select_options_unauthorized_v2.php" target="_blank" rel="nofollow noreferrer">repro_select_options_unauthorized_v2.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Regression — <code>companies</code> quick-add blocked for regular employees. Scenario matrix + live API subprocess (browser uses Laragon CLI <code>php.exe</code>, not <code>php-cgi</code>); policy fallback if harness still fails.</td>
                    <td>Browser: <a href="repro_select_options_unauthorized_v2.php">run</a>. CLI: <code>php scripts/repro_select_options_unauthorized_v2.php</code></td>
                </tr>
                <tr>
                    <td><a href="repro_vault_corruption.php" target="_blank" rel="nofollow noreferrer">repro_vault_corruption.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Regression — vault master key re-encryption rollback (atomicity).</td>
                    <td><code>php scripts/repro_vault_corruption.php</code></td>
                </tr>
				<tr>
                    <td><a href="repro_db_integrity.php" target="_blank" rel="nofollow noreferrer">repro_db_integrity.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Reproduction — incorrect UNIQUE constraints and trigger errors in database.sql.</td>
                    <td><code>php scripts/repro_db_integrity.php</code></td>
                </tr>
				<tr>
				    <td><a href="test_multi_tenant.php" target="_blank" rel="nofollow noreferrer">test_multi_tenant.php</td>
				    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
				    <td>Verifies multi-tenant integrity by checking company_id distribution and foreign keys across all tables.</td>
				    <td><code>php scripts/test_multi_tenant.php</code></td>
				</tr>
				<tr>
				    <td><a href="verify_audit_columns.php" target="_blank" rel="nofollow noreferrer">verify_audit_columns.php</td>
				    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
				    <td>Verifies that every table in the database has the mandatory 8 audit columns with correct types and defaults.</td>
				    <td><code>php scripts/verify_audit_columns.php</code></td>
				</tr>
				<tr>
				    <td><a href="apply_crud_audit_soft_delete.php" target="_blank" rel="nofollow noreferrer">apply_crud_audit_soft_delete.php</a></td>
				    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
				    <td>Apply soft-delete + audit meta UI patches to scaffold modules in <code>docs/list_soft-delete.txt</code>. <strong>Default = dry-run</strong>; writes with CLI <code>--apply</code> or browser <code>?apply=1</code> (Admin). After counts, lists inventory / skip / missing / patch / compliant modules (real newlines for browser <code>&lt;pre&gt;</code>). Idempotent; skips status-driven modules.</td>
				    <td>Browser: <a href="apply_crud_audit_soft_delete.php">dry-run</a> / <a href="apply_crud_audit_soft_delete.php?apply=1">apply=1</a>. CLI: <code>php scripts/apply_crud_audit_soft_delete.php</code> then <code>php scripts/apply_crud_audit_soft_delete.php --apply</code>.</td>
				</tr>
				<tr>
				    <td><a href="check_crud_audit_soft_delete.php" target="_blank" rel="nofollow noreferrer">check_crud_audit_soft_delete.php</a></td>
				    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
				    <td>Static gate for scaffold soft-delete / audit UI contracts (list hide, view columns, not-deleted filter, soft-delete helper).</td>
				    <td><code>php scripts/check_crud_audit_soft_delete.php</code></td>
				</tr>
				<tr>
                    <td><a href="repro_employee_dataloss.php" target="_blank" rel="nofollow noreferrer">repro_employee_dataloss.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Regression test for employee Excel import. Verifies that columns missing from the import payload are not wiped in the database during update. <strong>CLI-only</strong> — catalog listing only; no browser runner link.</td>
                   <td>Run from repo root: <code>php scripts/repro_employee_dataloss.php</code> (exit <code>0</code> on pass, non-zero on failure).</td>
                </tr>
                <tr>
                    <td><a href="verify_import_fix_updated.php" target="_blank" rel="nofollow noreferrer">verify_import_fix_updated.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span><span class="scripts-badge scripts-badge-web">Browser</span></span></td>
                    <td>Verification script for Employee Import Department Data Loss Fix.</td>
                    <td><code>php scripts/verify_import_fix_updated.php</code></td>
                </tr>
                <tr>
                    <td><a href="repro_generic_dataloss.php" target="_blank" rel="nofollow noreferrer">repro_generic_dataloss.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Regression test for generic table Excel import. Verifies that columns missing from the import payload are not wiped in the database during update. <strong>CLI-only</strong> — catalog listing only; no browser runner link.</td>
                    <td>Run from repo root: <code>php scripts/repro_generic_dataloss.php</code> (exit <code>0</code> on pass, non-zero on failure).</td>
                </tr>
                <tr>
                    <td><a href="repro_contacts_idor.php" target="_blank" rel="nofollow noreferrer">repro_contacts_idor.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Reproduction script for IDOR in contacts API inline edit; disposable employees via shared helper (clears stale audit actor before INSERT).</td>
                    <td><code>php scripts/repro_contacts_idor.php</code> — expects PASS when IDOR is blocked. Clears <code>@app_employee_id</code> before create to avoid <code>audit_logs</code> FK failures.</td>
                </tr>
                <tr>
                    <td><a href="repro_select_options.php" target="_blank" rel="nofollow noreferrer">repro_select_options.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Reproduction script for RBAC bypass in select options API.</td>
                    <td><code>php scripts/repro_select_options.php</code></td>
                </tr>
                <tr>
                    <td><a href="repro_status_leak.php" target="_blank" rel="nofollow noreferrer">repro_status_leak.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Reproduction script for employee status cross-tenant leak.</td>
                    <td><code>php scripts/repro_status_leak.php</code></td>
                </tr>
                <tr>
                    <td><a href="repro_visitors_bac.php" target="_blank" rel="nofollow noreferrer">repro_visitors_bac.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Reproduction script for Broken Access Control in visitors access log.</td>
                    <td><code>php scripts/repro_visitors_bac.php</code></td>
                </tr>
                <tr>
                    <td><a href="repro_visitors_sqli.php" target="_blank" rel="nofollow noreferrer">repro_visitors_sqli.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Reproduction script for SQL Injection in visitors access log inline edit.</td>
                    <td><code>php scripts/repro_visitors_sqli.php</code></td>
                </tr>
                <tr>
                    <td><a href="verify_audit_updated.php" target="_blank" rel="nofollow noreferrer">verify_audit_updated.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Verification script for audit log redaction of sensitive fields.</td>
                    <td><code>php scripts/verify_audit_updated.php</code></td>
                </tr>
                <tr>
                    <td><a href="verify_clear_table_fix.php" target="_blank" rel="nofollow noreferrer">verify_clear_table_fix.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Verification script for employees clear-table soft-delete (employee audit row remains; bookmarks detached).</td>
                    <td><code>php scripts/verify_clear_table_fix.php</code></td>
                </tr>
                <tr>
                    <td><a href="verify_explorer_updated.php" target="_blank" rel="nofollow noreferrer">verify_explorer_updated.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Verification script for Explorer file extension whitelisting.</td>
                    <td><code>php scripts/verify_explorer_updated.php</code></td>
                </tr>
                <tr>
                    <td><a href="verify_rbac_updated.php" target="_blank" rel="nofollow noreferrer">verify_rbac_updated.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Verification script for RBAC protection guards in module handlers.</td>
                    <td><code>php scripts/verify_rbac_updated.php</code></td>
                </tr>
                <tr>
                    <td><a href="verify_sqli_updated.php" target="_blank" rel="nofollow noreferrer">verify_sqli_updated.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Verification script for SQL Injection fix in visitors access log.</td>
                    <td><code>php scripts/verify_sqli_updated.php</code></td>
                </tr>
                <tr>
                    <td><a href="verify_status_leak_fixed.php" target="_blank" rel="nofollow noreferrer">verify_status_leak_fixed.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Verification script for employee status cross-tenant leak fix.</td>
                    <td><code>php scripts/verify_status_leak_fixed.php</code></td>
                </tr>
                <tr>
                    <td><a href="verify_visitors_bac_fix.php" target="_blank" rel="nofollow noreferrer">verify_visitors_bac_fix.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Verification script for Broken Access Control fix in visitors access log.</td>
                    <td><code>php scripts/verify_visitors_bac_fix.php</code></td>
                </tr>
                <tr>
                    <td><a href="verify_visitors_sqli_fix.php" target="_blank" rel="nofollow noreferrer">verify_visitors_sqli_fix.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Verification script for SQL Injection fix in visitors access log against fixed files.</td>
                    <td><code>php scripts/verify_visitors_sqli_fix.php</code></td>
                </tr>
                <tr>
                    <td><a href="repro_birthdays_resignations_rbac.php" target="_blank" rel="nofollow noreferrer">repro_birthdays_resignations_rbac.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Reproduction & verification script for Birthdays & Resignations RBAC View Bypass vulnerability.</td>
                    <td><code>php scripts/repro_birthdays_resignations_rbac.php</code></td>
                </tr>
                <tr>
                    <td><a href="verify_auto_scaffolding.php" target="_blank" rel="nofollow noreferrer">verify_auto_scaffolding.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Verification script for Dynamic Auto-Scaffolding toggled via <code>enable_auto_scaffolding</code>. Checks both disabled and enabled scaffolding on dummy tables.</td>
                    <td><code>php scripts/verify_auto_scaffolding.php</code> or open in browser.</td>
                </tr>
            </tbody>
        </table></div>
    </div>
    <div class="scripts-card" id="deployment">
        <h2>Deployment &amp; Git</h2>
        <div class="scripts-table-wrap"><table class="scripts-table">
            <thead>
                <tr>
                    <th>Script</th>
                    <th class="scripts-access-col">Access</th>
                    <th>What it does</th>
                    <th>How to use</th>
                </tr>
            </thead>
            <tbody>
             <tr>
                <td><a href="http://myhome.dynip.sapo.pt/deletev2.php" target="_blank" rel="nofollow noreferrer">deletev2.php</a></td>
                <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span></span></td>
                <td>Clone Github + Import Database</td>
                <td>Open in browser to trigger remote deployment</td>
            </tr>
            <tr>
                <td><a href="http://myhome.dynip.sapo.pt/reset_git_history.php" target="_blank" rel="nofollow noreferrer">reset_git_history.php</a></td>
                <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span></span></td>
                <td>Completely reset Git history and force push a clean master branch. <strong>BETA only</strong> — destructive; rewrites Git history and force-pushes. Used in development to purge history or reset a branch to a clean state.</td>
                <td><strong>Log in first.</strong> Open <code>reset_git_history.php</code>. <strong>DANGER: Destructive.</strong> <strong>BETA / pre-production only</strong> — no implementation changes while the project remains in BETA and not yet in production.</td>
            </tr>
        </tbody>
        </table></div>
    </div>

    <div class="scripts-card">
        <p class="scripts-muted" style="margin:0;">
            Back to app: <a href="../index.php">Home</a> ·
            Catalog: <a href="scripts.php">scripts.php</a>
        </p>
    </div>
</div>
</body>
</html>

<!-- Standard: scripts/SCRIPTS.md -->
<!-- Newline: (php_sapi_name() === 'cli' ? "\n" : "<br><br>") -->
