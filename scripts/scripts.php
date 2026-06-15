<?php
define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
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
            <strong>Data mutation quick reference:</strong> these scripts add sample/test rows in the DB: <code>module_browser_qa_runner.php</code>, <code>employees_delete_clear_table_test.php</code>, <code>equipment_delete_clear_table_test.php</code>, <code>explorer_human_test.php</code>, <code>floor_plans_folder_move_test.php</code>, <code>idfs_sync_human_test.php</code>, <code>tickets_related_asset_equipment_delete_test.php</code>. Dump-only helper: <code>export_floor_plan_folders_seed.php</code> (prints <code>INSERT</code> SQL to stdout).
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
                    <td><a href="api.php">api.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span></span></td>
                    <td>HTML reference for JSON API endpoints (auth, CSRF, module import routes, request/response examples).</td>
                    <td>Open in the browser after deploy. Use as the source of truth when calling <code>/modules/*/index.php</code> JSON handlers or shared APIs documented in the file.</td>
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
                    <td><a href="DBdesign.php">DBdesign.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>ER-style diagram generated from <code>database.sql</code> (Mermaid render, zoom, SVG/PNG export).</td>
                    <td>
                        Open <a href="DBdesign.php">DBdesign.php</a>. Optional:
                        <a href="DBdesign.php?format=mermaid">?format=mermaid</a>,
                        <a href="DBdesign.php?format=json">?format=json</a>.
                        CLI: <code>php scripts/DBdesign.php --mermaid</code> or <code>--json</code>.
                    </td>
                </tr>
                <tr>
                    <td><a href="crud_tables.php">crud_tables.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Lists every module folder and the first <code>$crud_table =</code> line found in <code>index.php</code>, with links to each module.</td>
                    <td>Browser: HTML report. CLI: <code>php scripts/crud_tables.php</code> (HTML to stdout) or <code>&gt; crud_tables.html</code>.</td>
                </tr>
                <tr>
                    <td><a href="update_all_created_at.php">update_all_created_at.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>After importing <code>database.sql</code>, sets every live row’s <code>created_at</code> to one timestamp (default <code>2026-01-01 00:00:01</code>). Dry-run previews counts first.</td>
                    <td><strong>Log in first.</strong> Open <a href="update_all_created_at.php">update_all_created_at.php</a> (HTML + <strong>← Scripts index</strong>). CLI: <code>php scripts/update_all_created_at.php --dry-run</code></td>
                </tr>
                <tr>
                    <td><a href="detect_fk_dropdown_ui_risk_ui.php">detect_fk_dropdown_ui_risk_ui.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span></span></td>
                    <td>Scans cross-tenant FK rows and module code that can cause <strong>duplicate dropdown options</strong>. Results are plain-language summaries with links to <code>modules/…/</code> and edit screens (new tab) when a module folder exists.</td>
                    <td>
                        <strong>Log in first.</strong> Open the UI → <strong>← Scripts index</strong> at top → choose scan mode, company, risk filter, output (table/JSON) → <strong>Run scan</strong>.
                        CLI: <code>php scripts/detect_fk_dropdown_ui_risk.php</code> · <code>--company=N</code> · <code>--json</code> · <code>--data-only</code> · <code>--code-only</code> · <code>--repair-catalogs</code> (one-time legacy DB cleanup).
                    </td>
                </tr>
                <tr>
                    <td><a href="detect_fk_dropdown_ui_risk.php">detect_fk_dropdown_ui_risk.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Same scanner as the UI. Browser requests load <code>detect_fk_dropdown_ui_risk_ui.php</code>; CLI prints human-readable lines (or JSON with <code>--json</code>).</td>
                    <td>
                        Browser: opens the UI. CLI:
                        <code>php scripts/detect_fk_dropdown_ui_risk.php [--company=N] [--json] [--data-only] [--code-only] [--repair-catalogs] [--help]</code>.
                    </td>
                </tr>
                <tr>
                    <td><a href="debug.php">debug.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span></span></td>
                    <td>System overview (DB, tables, PHP version, extensions, and file permissions).</td>
                    <td>Open in browser for quick troubleshooting.</td>
                </tr>
                <tr>
                    <td><a href="health.php">health.php</a></td>
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
                    <td><a href="test_form_failed_save_display.php">test_form_failed_save_display.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Scans all module create forms for SQL-quoted re-display after failed saves (e.g. <code>value="'USA'"</code>); optional runtime POST tests per module.</td>
                    <td>
                        <strong>Log in first.</strong> Open <a href="test_form_failed_save_display.php">test_form_failed_save_display.php</a>, run static scan, optionally enable <em>Runtime HTTP tests</em>.
                        CLI: <code>php scripts/test_form_failed_save_display.php</code> · runtime: <code>--runtime</code> with <code>ITM_TEST_BASE_URL</code> and <code>ITM_TEST_COOKIE</code>.
                    </td>
                </tr>
                <tr>
                    <td><a href="test_sql_injection.php">test_sql_injection.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span></span></td>
                    <td>JSON sandbox to test SQL-injection signature detection and safe prepared-statement execution (GET/POST).</td>
                    <td>
                        <strong>Requires login</strong> and active <code>company_id</code>.
                        GET/POST JSON with a test payload; POST must include a valid CSRF token.
                        Use only in dev/staging — not a public endpoint.
                    </td>
                </tr>
                <tr>
                    <td>apply_form_failed_save_display_fix.php</td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Bulk-applies <code>cr_form_display_value</code> / POST normalization fixes across CRUD entry files (companion to the form test above).</td>
                    <td>
                        <strong>Do not open in browser</strong> — would write PHP without <code>--dry-run</code>. CLI:
                        <code>php scripts/apply_form_failed_save_display_fix.php --dry-run</code><br>
                        <code>php scripts/apply_form_failed_save_display_fix.php --module=manufacturers</code>
                    </td>
                </tr>
                <tr>
                    <td>test_db_error_messages.php</td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Asserts human-friendly output from <code>itm_format_db_constraint_error()</code>, <code>itm_render_alert_errors()</code>, and <code>itm_humanize_api_error_message()</code> (e.g. NOT NULL → “Please select a value for Employee”; strips <code>Database error:</code> / <code>DB error:</code> prefixes).</td>
                    <td>
                        <code>php scripts/test_db_error_messages.php</code> — exit <code>1</code> on any failed assertion. Run after changing <code>config/config.php</code>, <code>includes/ui_alert_helpers.php</code>, or IDF/header flash paths.
                    </td>
                </tr>
                <tr>
                    <td>apply_human_friendly_error_display.php</td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Replaces duplicated <code>alert alert-error</code> blocks with <code>itm_render_alert_errors()</code> across module PHP files (all modules, including protection-zone folders).</td>
                    <td>
                        <strong>Do not open in browser</strong> — writes PHP without <code>--dry-run</code>. CLI:
                        <code>php scripts/apply_human_friendly_error_display.php --dry-run</code><br>
                        <code>php scripts/apply_human_friendly_error_display.php --module=approvers</code>
                    </td>
                </tr>
                <tr>
                    <td><a href="test_email_forgot.php">test_email_forgot.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Test script for Password Reset email delivery verification.</td>
                    <td>
                        <strong>Log in first.</strong> Open <a href="test_email_forgot.php">test_email_forgot.php</a>.
                        CLI: <code>php scripts/test_email_forgot.php email=test@example.com</code>
                    </td>
                </tr>
                <tr>
                    <td><a href="test_register_mail.php">test_register_mail.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Test script for Registration Welcome email delivery verification.</td>
                    <td>
                        <strong>Log in first.</strong> Open <a href="test_register_mail.php">test_register_mail.php</a>.
                        CLI: <code>php scripts/test_register_mail.php email=test@example.com</code>
                    </td>
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
                    <td><a href="run_tests.php">run_tests.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Executes the PHPUnit test suite located in <code>tests/Unit/</code> using <code>phpunit.phar</code>. Cross-platform support for database-less execution via <code>ITM_SKIP_DB_TESTS=1</code>.</td>
                    <td>
                        Open in browser (requires <code>?token=...</code> if not local) to see styled output.<br>
                        CLI: <code>php scripts/run_tests.php</code>
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
                    <td>Automatically generates PHPUnit integration tests for all standard CRUD modules identified in the metadata. Creates test files in <code>tests/Unit/Modules/</code>.</td>
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
                    <td><a href="analyze_database_health.php">analyze_database_health.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Runs <code>ANALYZE TABLE</code> on every base table and lists per-table success/failure (avoids phpMyAdmin stopping on first error).</td>
                    <td>Open <a href="analyze_database_health.php">analyze_database_health.php</a> while logged in. Optional CLI: <code>php scripts/analyze_database_health.php</code></td>
                </tr>
                <tr>
                    <td><a href="force_delete_company.php">force_delete_company.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Bypasses triggers and FK checks to completely remove a company and its data across all <code>company_id</code> tables (including audit logs).</td>
                    <td>Open <a href="force_delete_company.php">force_delete_company.php</a> while logged in as Admin. CLI: <code>php scripts/force_delete_company.php --id=N</code>. <strong>DANGER: Destructive.</strong></td>
                </tr>
                <tr>
                    <td><a href="check_database_sql_company_name_uniques.php">check_database_sql_company_name_uniques.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Audits <code>database.sql</code>: each <code>company_id</code> table needs 2 uniques (PRIMARY + scope UNIQUE). Floor plans: <code>IFNULL(parent_folder_id,0)+name</code> / <code>IFNULL(folder_id,0)+display_name</code> (not <code>company_id+folder_id</code> alone).</td>
                    <td>Open <a href="check_database_sql_company_name_uniques.php">check_database_sql_company_name_uniques.php</a> or <code>php scripts/check_database_sql_company_name_uniques.php</code> (exit 1 if any fail).</td>
                </tr>
                <tr>
                    <td>repair_table_from_schema.php</td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Rebuilds one InnoDB table from <code>database.sql</code> when metadata drift causes "doesn't exist in engine" errors. Refuses web requests (<code>PHP_SAPI !== 'cli'</code>).</td>
                    <td><code>php scripts/repair_table_from_schema.php --table=table_name</code> — <strong>destructive</strong>; backup first.</td>
                </tr>
                <tr>
                    <td>verify_database_schema.php</td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Compares <code>CREATE TABLE</code> names in <code>database.sql</code> with <code>information_schema</code> for <code>itmanagement</code>. Use after PowerShell/MySQL imports that report success but stop early (e.g. 73 tables instead of 88). Lists missing/extra tables; exit <code>1</code> on mismatch.</td>
                    <td><code>php scripts/verify_database_schema.php</code> — run from repository root after <code>database.sql</code> import; check <code>mysql-import.err</code> for the first <code>ERROR</code> line if this fails.</td>
                </tr>
                <tr>
                    <td>normalize_database_sql_created_at.php</td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Sets every seed <code>created_at</code> literal in <code>database.sql</code> INSERT rows to one timestamp (default <code>2026-01-01 00:00:01</code>); leaves <code>updated_at</code> and other date columns unchanged. <strong>Writes</strong> <code>database.sql</code>.</td>
                    <td><strong>CLI only</strong> (browser shows instructions + <strong>← Scripts index</strong>): <code>php scripts/normalize_database_sql_created_at.php</code></td>
                </tr>
                <tr>
                    <td>apply_module_sample_data_seed.php</td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Automates PR #1993-style seed expansion per module/table: adds missing sample rows for every company listed in <code>companies</code>. Default <code>idf_device_type</code> samples are <code>other</code> 📦, <code>server</code> 🖥️, <code>ups</code> 🔋, <code>patch_panel</code> ➿, and <code>switch</code> 🔀; custom <code>--sample</code> values are supported for other modules.</td>
                    <td>
                        <code>php scripts/apply_module_sample_data_seed.php --module=idf_device_type</code><br>
                        <code>php scripts/apply_module_sample_data_seed.php --module=idf_device_type --dry-run</code><br>
                        <code>php scripts/apply_module_sample_data_seed.php --module=equipment_poe --value-column=name --sample=LabPoE --dry-run</code>
                    </td>
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
                    <td><a href="check_delimiters.php">check_delimiters.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Audit database.sql for correct DELIMITER usage in trigger blocks.</td>
                    <td>Open <a href="check_delimiters.php">check_delimiters.php</a> while logged in. CLI: <code>php scripts/check_delimiters.php</code></td>
                </tr>
                <tr>
                    <td><a href="check_duplicates.php">check_duplicates.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Audit database.sql for duplicate column definitions in CREATE TABLE blocks.</td>
                    <td>Open <a href="check_duplicates.php">check_duplicates.php</a> while logged in. CLI: <code>php scripts/check_duplicates.php</code></td>
                </tr>
                <tr>
                    <td><a href="check_phones.php">check_phones.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Identify tables in database.sql that contain phone-related columns for PII auditing.</td>
                    <td>Open <a href="check_phones.php">check_phones.php</a> while logged in. CLI: <code>php scripts/check_phones.php</code></td>
                </tr>
                <tr>
                    <td><a href="check_sql_errors.php">check_sql_errors.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Audit database.sql for column mismatches in triggers and INSERT statements.</td>
                    <td>Open <a href="check_sql_errors.php">check_sql_errors.php</a> while logged in. CLI: <code>php scripts/check_sql_errors.php</code></td>
                </tr>
                <tr>
                    <td><a href="count_args.php">count_args.php</a></td>
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
                    <td><a href="list_phone_columns.php">list_phone_columns.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>List columns for each table defined in database.sql, filtering for phone columns.</td>
                    <td>Open <a href="list_phone_columns.php">list_phone_columns.php</a> while logged in. CLI: <code>php scripts/list_phone_columns.php</code></td>
                </tr>
                <tr>
                    <td><a href="verify_sql.php">verify_sql.php</a></td>
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
                    <td><a href="idfs_sync_human_test.php">idfs_sync_human_test.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>End-to-end HTTP regression for IDF rack/device flows; asserts sync across <code>idf_ports</code>, <code>switch_ports</code>, <code>equipment</code>, <code>idf_links</code>. <strong>Mutates DB:</strong> creates temporary equipment/port/position/link rows and removes temporary artifacts at the end.</td>
                    <td>
                        CLI (recommended): <code>php scripts/idfs_sync_human_test.php</code><br>
                        Optional env: <code>ITM_BASE_URL</code>, <code>ITM_USER</code>, <code>ITM_PASS</code>, <code>ITM_COMPANY_ID</code>, <code>ITM_IDF_ID</code>.<br>
                        Browser: HTML log with <strong>← Scripts index</strong> and module/table links (debugging). Required before IDF-related PRs per AGENTS.md.
                    </td>
                </tr>
                <tr>
                    <td><a href="idfs_api_payload_dry_run.php">idfs_api_payload_dry_run.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Validates IDF device API JSON payloads offline (no MySQL). Requires CLI flags for payload validation.</td>
                    <td>
                        <code>php scripts/idfs_api_payload_dry_run.php --samples</code>,
                        <code>--endpoint=port_update --file=payload.json</code>,
                        <code>--endpoint=link_create --json='{"port_id_a":1,"port_id_b":2}'</code>
                    </td>
                </tr>
                <tr>
                    <td><a href="idf_device_port_sort_test.php">idf_device_port_sort_test.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Proves RJ45 ports sort before fiber (SFP) in IDF device SQL; optional live MySQL checks.</td>
                    <td>
                        Browser: plain-text <code>[PASS]</code>/<code>[FAIL]</code> log. CLI:
                        <code>php scripts/idf_device_port_sort_test.php</code> ·
                        <code>--offline-only</code>
                    </td>
                </tr>
                <tr>
                    <td><a href="ensure_equipment_type_modules.php">ensure_equipment_type_modules.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Verifies or recreates canonical equipment-type façade modules under <code>modules/is_*</code> (<code>is_switch</code>, <code>is_server</code>, <code>is_workstation</code>, …). Does not delete anything.</td>
                    <td><code>php scripts/ensure_equipment_type_modules.php</code> — CLI-only (browser shows <strong>← Scripts index</strong> + CLI command). Exit <code>1</code> if any canonical <code>index.php</code> is missing.</td>
                </tr>
                <tr>
                    <td><a href="cleanup_equipment_test_module_artifacts.php">cleanup_equipment_test_module_artifacts.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>
                        <strong>Destructive (local dev DB):</strong> removes regression-test <code>equipment_types</code> rows (including <code>MBQA-equipment_types-…</code> runner tags), ITM test companies, junk <code>modules/is_*_itm_eqdct_*</code> / <code>*_itm_edct_*</code> / orphan <code>modules/is_mbqa_equipment_types_*</code> folders, and matching sidebar prefs — then re-ensures canonical <code>is_*</code> modules. Never removes <code>is_switch</code>, <code>is_server</code>, etc. Browser <strong>Run QA</strong> now executes this cleanup silently before and after <code>module_browser_qa_runner.php</code>.
                    </td>
                    <td><code>php scripts/cleanup_equipment_test_module_artifacts.php</code> — CLI-only (browser shows <strong>← Scripts index</strong> + CLI command). Same logic as post-QA runner cleanup; run manually after other equipment DB tests if needed.</td>
                </tr>
                <tr>
                    <td><a href="equipment_delete_clear_table_test.php">equipment_delete_clear_table_test.php</a></td>
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
                    <td><a href="tickets_related_asset_equipment_delete_test.php">tickets_related_asset_equipment_delete_test.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>
                        MySQL regression for tickets sample data: seeds lookup parents (including <code>equipment</code>), inserts <code>TCK-0001</code> with <code>asset_id</code> on Primary File Server, and asserts <code>equipment_delete_record()</code> is blocked with a Related Asset / in-use message. <strong>Mutates DB:</strong> seeds/updates sample ticket rows during the test.
                    </td>
                    <td>
                        CLI: <code>php scripts/tickets_related_asset_equipment_delete_test.php</code> · <code>ITM_SKIP_DB_TESTS=1</code> · <code>ITM_TEST_COMPANY_ID</code> (default <code>1</code>).<br>
                        Browser: static checks only (no DB). MySQL regression requires CLI.
                    </td>
                </tr>
                <tr>
                    <td><a href="employees_delete_clear_table_test.php">employees_delete_clear_table_test.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>MySQL regression for employees <code>clear_table</code> transactional delete (access rows roll back when employee delete is blocked by FK). <strong>Mutates DB:</strong> creates temporary tenant/reference/employee rows, then cleans them up.</td>
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
                    <td><a href="list_modules_not_on_sidebar.php">list_modules_not_on_sidebar.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Lists <code>modules/*/index.php</code> folders that are <strong>not</strong> on the sidebar (including policy-hidden internal modules such as <code>floor_plan_folders</code>, <code>floor_plan_tags</code>, <code>floor_plan_item_tags</code>).</td>
                    <td>
                        <strong>Log in first.</strong> Open <a href="list_modules_not_on_sidebar.php">list_modules_not_on_sidebar.php</a> or
                        <a href="list_modules_not_on_sidebar.php?format=json">?format=json</a>.<br>
                        CLI: <code>php scripts/list_modules_not_on_sidebar.php</code> · JSON: <code>--json</code>
                    </td>
                </tr>
                <tr>
                    <td><a href="compare_database_sql_modules.php">compare_database_sql_modules.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Compares every <code>CREATE TABLE</code> in <code>database.sql</code> with <code>modules/</code> folders and each module’s <code>$crud_table</code> mapping (matched, missing module, missing table, mismatch).</td>
                    <td>
                        <strong>Log in first.</strong> Open <a href="compare_database_sql_modules.php">compare_database_sql_modules.php</a> or
                        <a href="compare_database_sql_modules.php?format=json">?format=json</a>.<br>
                        CLI: <code>php scripts/compare_database_sql_modules.php</code> · JSON: <code>--json</code> · exit code <code>1</code> when gaps exist.
                    </td>
                </tr>
                <tr>
                    <td><a href="floor_plans_folder_move_test.php">floor_plans_folder_move_test.php</a></td>
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
                    <td><a href="floor_designer_test.php">floor_designer_test.php</a></td>
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
                    <td><a href="check_csrf_coverage.php">check_csrf_coverage.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Static scan: POST handlers that mutate data without a known CSRF guard.</td>
                    <td>Browser: plain-text report. CLI: <code>php scripts/check_csrf_coverage.php</code> — smoke step 2 / AGENTS.md after CRUD changes.</td>
                </tr>
                <tr>
                    <td><a href="apply_display_field_columns_search_alias.php">apply_display_field_columns_search_alias.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI-only</span></span></td>
                    <td>One-time/maintenance: add <code>$displayFieldColumns = $uiColumns</code> (or <code>$visibleFieldColumns</code>) before module paths so list search does not reference an undefined variable.</td>
                    <td><code>php scripts/apply_display_field_columns_search_alias.php</code> from repository root. Re-run only when new flattened CRUD modules omit the alias.</td>
                </tr>
                <tr>
                    <td><a href="apply_itm_actions_cell_markers.php">apply_itm_actions_cell_markers.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI-only</span></span></td>
                    <td>One-time/maintenance: add <code>class="itm-actions-cell"</code> and <code>data-itm-actions-origin="1"</code> on Actions column header and body cells in module list tables (module browser QA <code>ui_check</code>).</td>
                    <td><code>php scripts/apply_itm_actions_cell_markers.php</code> from repository root (Windows Laragon: full PHP 7.4 path). Updates <code>modules/*/index.php</code> and <code>modules/*/includes/partials/render.php</code>.</td>
                </tr>
                <tr>
                    <td><a href="check_display_field_columns_search.php">check_display_field_columns_search.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Static audit: every <code>modules/*/index.php</code> that uses <code>foreach ($displayFieldColumns …)</code> must assign <code>$displayFieldColumns</code> first.</td>
                    <td>Browser: plain-text report. CLI: <code>php scripts/check_display_field_columns_search.php</code> — run after bulk CRUD/search changes; exit <code>1</code> on failure.</td>
                </tr>
                <tr>
                    <td><a href="check_sql_injection_coverage.php">check_sql_injection_coverage.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Static scan: direct queries near user input without obvious binding/sanitization.</td>
                    <td>Browser: plain-text report. CLI: <code>php scripts/check_sql_injection_coverage.php</code> — smoke step 3 / AGENTS.md after PHP/SQL changes.</td>
                </tr>
                <tr>
                    <td><a href="check_multi_tenant_leaks.php">check_multi_tenant_leaks.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Static scan: SQL queries and INSERTs on scoped tables missing <code>company_id</code> filters, and improper UI exposure of company identifiers.</td>
                    <td>Browser: HTML report with detailed leak locations. CLI: <code>php scripts/check_multi_tenant_leaks.php</code> — run after CRUD changes to ensure data isolation.</td>
                </tr>
                <tr>
                    <td><a href="check_index_table_compliance.php">check_index_table_compliance.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>
                        Index list tables: <code>data-itm-db-import-endpoint</code>, <code>data-itm-actions-origin</code>, POST CSRF, form <code>csrf_token</code>.
                        Baseline: <code>scripts/data/index_table_compliance_baseline.txt</code>. Skips protection-zone modules and <code>rack_planner</code>.
                    </td>
                    <td>
                        Browser: HTML report in <code>&lt;pre&gt;</code>. CLI: <code>php scripts/check_index_table_compliance.php</code> — run manually when index-table contract changes; exit <code>1</code> on new violations.<br>
                        <code>--strict</code> · <code>--write-baseline</code>
                    </td>
                </tr>
                <tr>
                    <td><a href="apply_bulk_delete_cancel_ux.php">apply_bulk_delete_cancel_ux.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>One-time maintenance: strip duplicated inline bulk-delete <code>selectionMode</code> scripts from module PHP files after <code>js/bulk-delete-selection.js</code> (shared Cancel button) ships via <code>includes/header.php</code>.</td>
                    <td><code>php scripts/apply_bulk_delete_cancel_ux.php</code> · <code>php scripts/apply_bulk_delete_cancel_ux.php --dry-run</code></td>
                </tr>
                <tr>
                    <td>apply_bulk_actions_records_per_page_gate.php</td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Add records_per_page visibility gate for bulk delete / clear table on module index.php files.</td>
                    <td><code>php scripts/apply_bulk_actions_records_per_page_gate.php [--dry-run]</code> — CLI only (browser shows instructions).</td>
                </tr>
                <tr>
                    <td><a href="module_clean_tests_qa_runner.php">module_clean_tests_qa_runner.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Runs the same equipment QA artifact cleanup used by <code>module_browser_qa_runner.php</code> (scaffold folders, QA/test rows, sidebar leftovers), then re-ensures canonical <code>modules/is_*</code> facades. Browser <strong>Run QA</strong> already triggers this cleanup silently at start and end; use this page for manual cleanup runs. Includes quick links: <strong>Clean Tests · Open markdown file · Download XLSX · Rebuild report · Re-Run Test · Run QA runner</strong>.</td>
                    <td>
                        Browser: <a href="module_clean_tests_qa_runner.php">module_clean_tests_qa_runner.php</a> (open page, click <strong>Run Clean Tests</strong>; POST + CSRF required).<br>
                        CLI: <code>php scripts/module_clean_tests_qa_runner.php</code><br>
                        Help: <code>php scripts/module_clean_tests_qa_runner.php --help</code>
                    </td>
                </tr>
                <tr>
                    <td><a href="module_browser_qa_runner.php">module_browser_qa_runner.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>
                        Full-module QA across all <code>modules/*/index.php</code> entries for companies 1–5: login, then per module <strong>mysql</strong> (<code>database.sql</code> INSERT row count), <strong>rotate error_log.txt</strong>, list/<strong>clear</strong>/sample_data, <strong>add</strong>, <strong>bulk_delete</strong>, CRUD/export, <strong>clear_table</strong>, second <strong>clear</strong>, import/<strong>single_delete</strong>, end sample restore + <strong>error_log</strong>. <strong>Mutates DB:</strong> seeds sample data and inserts/imports test rows as part of the flow. Tier lists: <code>$bespokeSmoke</code> / <code>$skipClear</code> in <code>scripts/lib/mbqa_runner_tiers.php</code>. Browser <strong>Run QA</strong> silently runs <code>module_clean_tests_qa_runner.php</code> at start and end. Preflight validation, auto-detected Base URL on Laragon, structured <strong>import_db</strong> JSON parsing, stale AJAX cleanup. Optional browser-only <strong>UI click smoke</strong> (one module + one company) appends <code>bulk_cancel_click</code>, <code>pagination_click</code>, <code>export_xlsx_click</code>, <code>import_excel_click</code>. Writes timestamped <code>qa-reports/module-browser-qa-YYYY-MM-DD-HH-MM-SS.json</code> and matching <code>.xlsx</code> each run.
                    </td>
                    <td>
                        Browser: <a href="module_browser_qa_runner.php">module_browser_qa_runner.php</a> — form: <strong>Run QA</strong> + <strong>Stop</strong> (AJAX poll every 400ms). Optional <strong>UI click smoke</strong> (one module + one company).<br>
                        <code>php scripts/module_browser_qa_runner.php</code><br>
                        <code>php scripts/module_browser_qa_runner.php --pilot-only</code> (expenses only)<br>
                        <code>php scripts/module_browser_qa_runner.php --module=expenses --company=1</code><br>
                        <code>php scripts/module_browser_qa_runner.php --ui-click-smoke --module=expenses --company=1</code> (CLI guard only — real clicks need the browser form)<br>
                        <code>php scripts/module_browser_qa_runner.php --help</code>
                    </td>
                </tr>
                <tr>
                    <td><a href="module_browser_qa_build_report.php">module_browser_qa_build_report.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Builds markdown summary from a timestamped runner JSON: tier reference (<code>$bespokeSmoke</code>, <code>$skipClear</code>), configured step exceptions, per-module results, failure/skip indexes. Re-Run links preserve UI click smoke when set. Writes <code>qa-reports/module-browser-qa.md</code> (overwritten each build).</td>
                    <td>
                        Browser: <a href="module_browser_qa_build_report.php">module_browser_qa_build_report.php</a> (form; <code>?run=1&amp;date=YYYY-MM-DD</code>)<br>
                        <code>php scripts/module_browser_qa_build_report.php</code><br>
                        <code>php scripts/module_browser_qa_build_report.php --date=2026-05-20</code>
                    </td>
                </tr>
                <tr>
                    <td><a href="check_employees_clear_table_transaction.php">check_employees_clear_table_transaction.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Static guard: employees <code>clear_table</code> uses a MySQL transaction (access delete + employees delete with rollback).</td>
                    <td><code>php scripts/check_employees_clear_table_transaction.php</code> — run manually after employees <code>clear_table</code> changes (AGENTS.md).</td>
                </tr>
                <tr>
                    <td><a href="check_equipment_clear_table_delete.php">check_equipment_clear_table_delete.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Static guard: equipment <code>clear_table</code> / <code>equipment_delete_record()</code> helpers in <code>delete_functions.php</code>.</td>
                    <td><code>php scripts/check_equipment_clear_table_delete.php</code> — run manually after equipment delete/clear-table changes (AGENTS.md).</td>
                </tr>
                <tr>
                    <td><a href="check_ui_configuration_coverage.php">check_ui_configuration_coverage.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>UI configuration hooks: table actions, new button, export toolbar, back/save on forms.</td>
                    <td>Browser: plain-text module list. CLI: <code>php scripts/check_ui_configuration_coverage.php</code> — run manually when module UI/layout changes; exits <code>2</code> on failure. Audits table actions, export card, search, Settings <code>records_per_page</code> pagination, bulk Select to Delete / Clear Table, and CRUD entry files. Skips modules in <code>scripts/data/ui_configuration_excluded_modules.txt</code> (e.g. <code>audit_logs</code>, <code>ip_subnets</code>, <code>rack_planner</code>) and prefixes in <code>ui_configuration_excluded_prefixes.txt</code> (e.g. <code>is_*</code>).</td>
                </tr>
                <tr>
                    <td><a href="check_audit_logs_coverage.php">check_audit_logs_coverage.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Audit trail for mutations: <code>itm_run_query</code>, <code>itm_log_audit</code>, bulk helpers, or <code>trg_{table}_audit_*</code> in <code>database.sql</code>.</td>
                    <td>
                        Browser: HTML/plain report (PHP 7.4+); query <code>?module=NAME</code> or <code>?json=1</code>. CLI: <code>php scripts/check_audit_logs_coverage.php</code> — exit <code>2</code> on failures.<br>
                        <code>--module=NAME</code> · <code>--json</code>
                    </td>
                </tr>
                <tr>
                    <td><a href="sql_injection_matrix_test.php">sql_injection_matrix_test.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Fixed payload matrix against <code>lib/sql_injection_detector.php</code>.</td>
                    <td>Browser: plain-text results. CLI: <code>php scripts/sql_injection_matrix_test.php</code> — non-zero exit if any case fails.</td>
                </tr>
                <tr>
                    <td><a href="db_field_active.php">db_field_active.php</a></td>
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
                    <td><code>bypass_login.php</code></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI-only</span></span></td>
                    <td>Bypasses the login screen by manually establishing an authenticated Admin session in the database and returning the session ID. Sets up Admin user, TechCorp Global company, and Vault master key.</td>
                    <td><code>php scripts/bypass_login.php</code> — Follow CLI instructions to hijack the session in your browser using Developer Tools.</td>
                </tr>
                <tr>
                    <td><a href="sql_insert.php">sql_insert.php</a></td>
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
                    <td>Automated UI screenshot utility. Authenticates as Admin and captures key states of modules. Requires Playwright.</td>
                    <td><code>python3 scripts/take_screenshots_modules.py</code></td>
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
                    <td><code>repro_bug.php</code></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Reproduction and verification script for Todo module visibility and security bugs (multi-assignment and IDOR).</td>
                    <td><code>php scripts/repro_bug.php</code></td>
                </tr>
                <tr>
                    <td><a href="repro_vulnerabilities.php">repro_vulnerabilities.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Reproduction and verification script for Explorer RCE, User Privilege Escalation, and Unauthorized Access to Role Module Permissions.</td>
                    <td>Open in browser or run via CLI: <code>php scripts/repro_vulnerabilities.php</code></td>
                </tr>
                <tr>
                    <td><a href="repro_auth_bypass_v3.php">repro_auth_bypass_v3.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td>Reproduction and verification script for Authorization Bypass in Companies and Users modules.</td>
                    <td>Open in browser or run via CLI: <code>php scripts/repro_auth_bypass_v3.php</code></td>
                </tr>
            </tbody>
        </table></div>
    </div>

    <div class="scripts-card" >
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
                    <td><a href="http://myhome.dynip.sapo.pt/delete.php" target="_blank" rel="nofollow noreferrer">delete.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span></span></td>
                    <td>Clone Github + Import Database</td>
                    <td>Open in browser to trigger remote deployment</td>
                </tr>
                <tr>
                    <td><a href="http://myhome.dynip.sapo.pt/it-management/reset_git_history.php" target="_blank" rel="nofollow noreferrer">reset_git_history.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span></span></td>
                    <td>Completely reset Git history and force push a clean master branch.</td>
                    <td><strong>Log in first.</strong> Open <a href="http://myhome.dynip.sapo.pt/it-management/reset_git_history.php" target="_blank" rel="nofollow noreferrer">reset_git_history.php</a>. <strong>DANGER: Destructive.</strong></td>
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
