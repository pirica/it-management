<?php
// Why: CLI runners include config without a web session; browser catalog must not set ITM_CLI_SCRIPT (avoids auth edge cases).
if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
    define('ITM_CLI_SCRIPT', true);
}
require_once __DIR__ . '/../config/config.php';

// Why: Script catalog lists destructive CLI repro tools; browser view is admin-only (no web runner links).
if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg') {
    $itmScriptsEmployeeId = (int)($_SESSION['employee_id'] ?? 0);
    if (!itm_is_admin($conn, $itmScriptsEmployeeId)) {
        http_response_code(403);
        header('Content-Type: text/html; charset=utf-8');
        $itmScriptsHomeUrl = htmlspecialchars((string)BASE_URL, ENT_QUOTES, 'UTF-8');
        $itmScriptsDashboardUrl = htmlspecialchars((string)BASE_URL . 'dashboard.php', ENT_QUOTES, 'UTF-8');
        echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Access denied</title></head><body>';
        echo '<p>Administrator privileges required to open the scripts catalog.</p>';
        echo '<p>Your session is still active.</p>';
        echo '<p>If your browser session was replaced by a script test user (<code>apitest-user-*</code> / <code>script-*</code>), ';
        echo '<a href="' . htmlspecialchars((string)BASE_URL . 'logout.php', ENT_QUOTES, 'UTF-8') . '">sign out</a> ';
        echo 'and log in again as Admin.</p>';
        echo '<p><a href="' . $itmScriptsDashboardUrl . '">Return to dashboard</a> · ';
        echo '<a href="' . $itmScriptsHomeUrl . 'index.php">Company selector</a></p>';
        echo '</body></html>';
        exit;
    }
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
        body { padding: 0; margin: 0; background-color: var(--bg-secondary, #f6f8fa); color: var(--text-primary, #24292f); }
        .scripts-wrap { --scripts-edge-pad: 10px; max-width: none; width: 100%; margin: 0; padding: 10px var(--scripts-edge-pad) 32px; min-height: calc(100vh - 60px); box-sizing: border-box; }
        .scripts-intro { margin-bottom: 4px; padding-bottom: 8px; border-bottom: 1px solid var(--border, #d0d7de); max-width: 56rem; }
        .scripts-section { margin-bottom: 20px; scroll-margin-top: 88px; width: 100%; }
        .scripts-section.scripts-catalog-section-empty { display: none; }
        .scripts-footer { margin-top: 20px; padding-top: 12px; border-top: 1px solid var(--border, #d0d7de); font-size: 0.88rem; }
        .scripts-muted { color: var(--text-secondary, #57606a); margin: 0 0 10px; line-height: 1.5; font-size: 0.86rem; }
        .scripts-muted strong { color: var(--text-primary, #24292f); }
        .scripts-lede { margin: 0 0 10px; font-size: 0.9rem; line-height: 1.45; color: var(--text-secondary, #57606a); }
        .scripts-intro-details { margin: 0 0 8px; font-size: 0.86rem; line-height: 1.5; color: var(--text-secondary, #57606a); }
        .scripts-intro-details summary { cursor: pointer; font-weight: 600; color: var(--text-primary, #24292f); margin-bottom: 6px; }
        .scripts-intro-details[open] summary { margin-bottom: 8px; }
        h1 { margin: 0 0 6px; font-size: 1.45rem; letter-spacing: -0.02em; }
        .scripts-section > h2 { margin: 0 0 6px; font-size: 0.95rem; font-weight: 700; letter-spacing: 0.02em; text-transform: uppercase; color: var(--text-secondary, #57606a); padding: 0; border: none; }
        .scripts-toolbar { display: flex; flex-direction: column; gap: 8px; margin: 10px 0 8px; padding: 10px 0; background: var(--bg-secondary, #f6f8fa); }
        .scripts-toolbar-sticky { position: sticky; top: 46px; z-index: 90; border-bottom: 1px solid var(--border, #d0d7de); }
        .scripts-search-row { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 12px; width: 100%; }
        .scripts-search-form { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; flex: 1 1 240px; }
        .scripts-filter { flex: 1 1 200px; min-width: 160px; padding: 7px 10px; border: 1px solid var(--border, #d0d7de); border-radius: 6px; background: var(--bg-primary, #fff); color: var(--text-primary, #24292f); font-size: 0.88rem; }
        .scripts-filter:focus { outline: 2px solid #0969da; outline-offset: 1px; border-color: #0969da; }
        .scripts-filter-hint { font-size: 0.8rem; color: var(--text-secondary, #57606a); white-space: nowrap; }
        .scripts-filter-row-2 { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 12px; }
        .scripts-table-tag-select { min-width: 200px; max-width: 100%; flex: 1 1 220px; padding: 6px 8px; border: 1px solid var(--border, #d0d7de); border-radius: 6px; background: var(--bg-primary, #fff); font-size: 0.82rem; }
        .scripts-catalog-empty { margin: 8px 0 0; padding: 10px 12px; border-radius: 6px; background: var(--bg-primary, #fff); border: 1px dashed var(--border, #d0d7de); color: var(--text-secondary, #57606a); font-size: 0.86rem; }
        .scripts-catalog-grid { overflow-x: auto; -webkit-overflow-scrolling: touch; width: 100%; }
        .scripts-catalog { width: 100%; min-width: 52rem; border-collapse: collapse; table-layout: fixed; font-size: 0.82rem; line-height: 1.4; background: var(--bg-primary, #fff); border: 1px solid var(--border, #d0d7de); }
        .scripts-catalog col.scripts-col-pick { width: 1.75rem; }
        .scripts-catalog col.scripts-col-script { width: 11rem; }
        .scripts-catalog col.scripts-col-access { width: 4.75rem; }
        .scripts-catalog col.scripts-col-tags { width: 7rem; }
        .scripts-catalog col.scripts-col-what { width: auto; }
        .scripts-catalog col.scripts-col-how { width: 0; }
        .scripts-catalog thead th { text-align: left; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text-secondary, #57606a); padding: 6px 8px; background: var(--bg-secondary, #f6f8fa); border-bottom: 2px solid var(--border, #d0d7de); white-space: nowrap; }
        .scripts-catalog tbody tr { border-bottom: 1px solid var(--border, #d0d7de); }
        .scripts-catalog tbody tr:nth-child(even) { background: rgba(246, 248, 250, 0.65); }
        .scripts-catalog tbody tr:hover { background: #eef6ff; }
        .scripts-catalog tr.scripts-catalog-hidden { display: none; }
        .scripts-catalog td { padding: 5px 8px; vertical-align: top; text-align: left; }
        .scripts-catalog thead th:nth-child(6),
        .scripts-catalog tbody td:nth-child(6) { display: none; }
        .scripts-catalog th.scripts-pick-col,
        .scripts-catalog td.scripts-cell-pick {
            width: 1.75rem;
            max-width: 1.75rem;
            padding: 5px 0 5px 6px;
            text-align: left;
            vertical-align: top;
            white-space: nowrap;
        }
        .scripts-catalog th.scripts-pick-col { padding-top: 6px; padding-bottom: 6px; }
        .scripts-catalog td.scripts-cell-script { white-space: nowrap; vertical-align: top; text-align: left; padding: 5px 6px 5px 2px; }
        .scripts-catalog td.scripts-cell-pick .scripts-catalog-row-cb {
            display: inline-block;
            vertical-align: middle;
            margin: 0;
            cursor: pointer;
        }
        .scripts-catalog td.scripts-tags-cell { color: var(--text-primary, #24292f); overflow: hidden; }
        .scripts-catalog td.scripts-access-cell { overflow: hidden; vertical-align: top; }
        .scripts-tag-badges { display: flex; flex-wrap: wrap; gap: 3px; }
        .scripts-badge-tag { background: #eaeef2; color: #24292f; border: 1px solid #d0d7de; font-size: 0.68rem; padding: 1px 5px; }
        .scripts-badge-tag[data-tag-kind="mixed"] { background: #fff1e5; color: #9a6700; border-color: #f0d8b8; }
        .scripts-badge-tag[data-tag-kind="codebase"] { background: #f0f6fc; color: #0550ae; border-color: #c0d8f0; }
        .scripts-badge-tag[data-tag-kind="python"] { background: #fbefff; color: #5a32a3; border-color: #d8b4fe; }
        .scripts-badge-tag[data-tag-kind="server"] { background: #fff0f0; color: #9b1c1c; border-color: #f0c4c4; }
        .scripts-badge-tag[data-tag-kind="info"] { background: #ecfdf5; color: #047857; border-color: #a7f3d0; }
        .scripts-badge-tag[data-tag-kind="markdown"] { background: #fff8c5; color: #6a5f00; border-color: #f0e6a8; }
        .scripts-tag-filter-bar { display: flex; flex-wrap: wrap; gap: 4px; align-items: center; }
        .scripts-tag-chip { padding: 3px 8px; border-radius: 999px; border: 1px solid var(--border, #d0d7de); background: var(--bg-primary, #fff); color: var(--text-primary, #24292f); font-size: 0.74rem; cursor: pointer; line-height: 1.35; }
        .scripts-tag-chip:hover { border-color: #0969da; }
        .scripts-tag-chip.is-active { background: #0969da; color: #fff; border-color: #0969da; }
        .scripts-tag-chip-alias { border-style: dashed; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 0.7rem; }
        .scripts-catalog td.scripts-cell-what { color: var(--text-primary, #24292f); overflow: hidden; min-width: 0; height: 1px; }
        .scripts-catalog td.scripts-cell-how { color: var(--text-secondary, #57606a); font-size: 0.78rem; }
        .scripts-cell-clamp { word-break: break-word; min-width: 0; }
        .scripts-cell-clamp.scripts-cell-what-inner {
            display: block;
            width: 100%;
            max-height: 6.5rem;
            overflow-x: hidden;
            overflow-y: auto;
            overflow-wrap: anywhere;
            word-break: break-word;
            white-space: normal;
            box-sizing: border-box;
            scrollbar-gutter: stable;
            scrollbar-width: thin;
            scrollbar-color: #8c959f #f6f8fa;
            padding-right: 4px;
        }
        .scripts-cell-clamp.scripts-cell-what-inner::-webkit-scrollbar { width: 6px; }
        .scripts-cell-clamp.scripts-cell-what-inner::-webkit-scrollbar-thumb { background: #8c959f; border-radius: 4px; }
        .scripts-cell-clamp.scripts-cell-what-inner::-webkit-scrollbar-track { background: #f6f8fa; }
        .scripts-cell-clamp.scripts-cell-how-inner {
            display: block;
            max-height: 3.25rem;
            overflow-y: auto;
            overflow-x: hidden;
            scrollbar-width: thin;
        }
        .scripts-catalog-how-inline { margin-top: 6px; padding-top: 6px; border-top: 1px dashed var(--border, #d0d7de); font-size: 0.78rem; color: var(--text-secondary, #57606a); }
        .scripts-catalog-how-inline::before { content: "How: "; font-weight: 600; color: var(--text-primary, #24292f); }
        .scripts-catalog td.scripts-cell-script a { font-weight: 600; font-size: 0.82rem; color: #0969da; text-decoration: none; }
        .scripts-catalog td.scripts-cell-script a:hover { text-decoration: underline; }
        .scripts-catalog code { font-size: 0.78rem; word-break: break-word; background: var(--bg-secondary, #f6f8fa); border: 1px solid var(--border, #d0d7de); border-radius: 3px; padding: 0 4px; }
        .scripts-access-badges { display: flex; flex-direction: column; align-items: flex-start; flex-wrap: nowrap; gap: 3px; }
        .scripts-badge { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 0.72rem; font-weight: 600; white-space: nowrap; line-height: 1.4; }
        .scripts-badge-web { background: #ddf4ff; color: #0969da; border: 1px solid #c0e6ff; }
        .scripts-badge-cli { background: #f6f8fa; color: #24292f; border: 1px solid #d0d7de; }
        .scripts-badge-cli-only { background: #f6f8fa; color: #57606a; border: 1px dashed #8c959f; }
        .scripts-badge-md { background: #fff8c5; color: #6a5f00; border: 1px solid #f0e6a8; }
        .scripts-badge-info { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
        .scripts-toc { display: flex; flex-wrap: wrap; gap: 8px 14px; margin: 0 0 8px; padding: 0; list-style: none; }
        .scripts-toc a { color: #0969da; text-decoration: none; }
        .scripts-toc a:hover { text-decoration: underline; }
        .scripts-toc a.scripts-toc-external::after { content: " ↗"; font-size: 0.85em; }
        .scripts-cli-hint { margin: 0; padding: 8px 0 0; font-size: 0.8rem; line-height: 1.45; }
        .scripts-cli-hint code { font-size: 0.78rem; }
        .scripts-top-nav { position: sticky; top: 0; z-index: 100; margin: 0 0 12px; padding: 8px 10px; background: var(--bg-primary, #fff); border-bottom: 1px solid var(--border, #d0d7de); box-shadow: 0 1px 3px rgba(27, 31, 36, 0.08); box-sizing: border-box; }
        .scripts-top-nav-inner { max-width: none; width: 100%; margin: 0; padding: 0; display: flex; flex-wrap: wrap; align-items: center; gap: 10px 16px; box-sizing: border-box; }
        .scripts-top-nav-brand { font-weight: 700; color: var(--text-primary, #24292f); text-decoration: none; white-space: nowrap; }
        .scripts-top-nav-brand:hover { text-decoration: underline; }
        .scripts-top-nav-links { display: flex; flex-wrap: wrap; gap: 6px 12px; margin: 0; padding: 0; list-style: none; flex: 1 1 auto; }
        .scripts-top-nav-links a { color: #0969da; text-decoration: none; font-size: 0.88rem; white-space: nowrap; }
        .scripts-top-nav-links a:hover { text-decoration: underline; }
        .scripts-top-nav-links a.scripts-toc-external::after { content: " ↗"; font-size: 0.85em; }
        .scripts-top-nav-home { color: #0969da; text-decoration: none; font-size: 0.9rem; white-space: nowrap; margin-left: auto; }
        .scripts-top-nav-home:hover { text-decoration: underline; }
        @media (min-width: 1200px) {
            .scripts-wrap { --scripts-edge-pad: 16px; }
        }
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
        <a class="scripts-top-nav-home" href="../admin.php" title="Admin overview">← Admin</a>
        <a class="scripts-top-nav-home" href="../dashboard.php" title="Dashboard">← Dashboard</a>
        <a class="scripts-top-nav-home" href="../index.php">← Home</a>
    </div>
</nav>
<div class="scripts-wrap">
    <div class="scripts-intro">
        <h1>Scripts directory</h1>
        <p class="scripts-lede">
            <strong>Canonical catalog</strong> — every script’s <em>What it does</em> and Browser/CLI access.
            For <code>scripts/*.php</code>, <em>How to use</em> is on the script’s browser landing page before <code>run=1</code>.
            Standards live in <code>scripts/SCRIPTS.md</code>.
        </p>
        <details class="scripts-intro-details">
            <summary>Access, badges, and new-script checklist</summary>
            <p class="scripts-muted">
                <strong>Browser</strong> = open the script URL (HTML UI or plain-text report). Login or authorized source (IP <code>127.0.0.1</code> / <code>::1</code>, or <code>ITM_MAINTENANCE_TOKEN</code>).
                Every browser script shows <strong>← Scripts index</strong> (<code>scripts/lib/script_browser_nav.php</code>).
                <strong>CLI</strong> = project root, PHP 7.4+.
                <strong>CLI-only</strong> = bash/Python/session helpers — badge <span class="scripts-badge scripts-badge-cli-only">CLI-only</span>.
                Most PHP rows: <span class="scripts-badge scripts-badge-web">Browser</span> + <span class="scripts-badge scripts-badge-cli">CLI</span>; maintenance defaults to dry-run — <code>--apply</code> / <code>?apply=1</code> (Admin) to write.
            </p>
            <p class="scripts-muted">
                <strong>New script checklist (<code>AGENTS.md</code>):</strong> catalog row, ← Scripts index, human-readable results, module links when <code>modules/&lt;slug&gt;/</code> exists.
                <strong>phpMyAdmin</strong> is linked from this page only.
            </p>
        </details>
        <details class="scripts-intro-details">
            <summary>Data mutation quick reference &amp; CLI paths</summary>
            <p class="scripts-muted">
                <strong>DB sample/test scripts:</strong>
                <code>module_browser_qa_runner.php</code>, <code>employees_delete_clear_table_test.php</code>, <code>equipment_delete_clear_table_test.php</code>, <code>explorer_human_test.php</code>, <code>floor_plans_folder_move_test.php</code>, <code>idfs_sync_human_test.php</code>, <code>auth_register_reset_human_test.php</code>, <code>tickets_related_equipment_delete_test.php</code>.
                Dump-only: <code>export_floor_plan_folders_seed.php</code>.
            </p>
            <div class="scripts-cli-hint">
                <strong>CLI:</strong>
                <code>D:\dunebox-v1.0.6\system\apps\php\php-7.4.33-nts-Win32-vc15-x64\php.exe scripts\&lt;script&gt;.php</code>
                · <code>php scripts/&lt;script&gt;.php [options]</code> from project root
            </div>
        </details>
        <div class="scripts-toolbar scripts-toolbar-sticky">
            <div class="scripts-search-row">
                <form id="scripts-catalog-search-form" class="scripts-search-form" role="search" action="scripts.php" method="get">
                    <input type="search" id="scripts-catalog-filter" name="q" class="scripts-filter" placeholder="Search name, description, tag… (%wildcards, *.json, *.md)" autocomplete="off" aria-label="Filter scripts catalog" value="<?php echo htmlspecialchars(trim((string)($_GET['q'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>">
                    <button type="submit" class="btn btn-sm">Search</button>
                    <button type="button" class="btn btn-sm" id="scripts-catalog-filter-clear" title="Clear">🔙</button>
                </form>
                <span class="scripts-filter-hint" id="scripts-catalog-filter-count" aria-live="polite"></span>
            </div>
            <div class="scripts-filter-row-2">
                <div id="scripts-tag-filter-bar" class="scripts-tag-filter-bar" role="group" aria-label="Filter by tag kind"></div>
                <select id="scripts-table-tag-select" class="scripts-table-tag-select" aria-label="Filter by table or scope tag">
                    <option value="">All table / scope tags</option>
                </select>
            </div>
        </div>
        <p class="scripts-catalog-empty" id="scripts-catalog-empty" hidden>No scripts match the current filter. Use 🔙 to clear search and tags.</p>
    </div>

    <div class="scripts-section scripts-catalog-section" id="docs">
        <h2>Documentation</h2>
        <div class="scripts-catalog-grid"><table class="scripts-catalog">
            <colgroup>
                <col class="scripts-col-script">
                <col class="scripts-col-access">
                <col class="scripts-col-tags">
                <col class="scripts-col-what">
                <col class="scripts-col-how">
            </colgroup>
            <thead>
                <tr>
                    <th>Script</th>
                    <th class="scripts-access-col">Access</th>
                    <th>Tags</th>
                    <th>What it does</th>
                    <th>How to use</th>
                </tr>
            </thead>
            <tbody>
                <tr data-tags="Mixed">
                    <td><a href="api.php" target="_blank" rel="nofollow noreferrer">api.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>HTML reference for JSON/AJAX endpoints: Explorer file manager, Switch Port Manager (<code>includes/get_ports.php</code>, <code>includes/update_port.php</code>), IDF APIs, module imports (auto-detected), passwords, notes, todo, System Status API, and shared includes. Switch-port handlers document <code>itm_api_json_response()</code> contracts and mysqlnd-safe fetch helpers.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="pitfalls.php" target="_blank" rel="nofollow noreferrer">pitfalls.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Aggregated pitfalls and developer traps from every <code>AGENT_NOTES.md</code> in the repository (not only <code>modules/</code>). Backfills missing note files under <code>modules/</code> only. Reviewed empty §10 sections may use <code>[Confirmed] No pitfalls documented</code>. Skips top-level upload roots but still includes <code>modules/floor_plans/</code>.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>                </tr>
                <tr data-tags="Markdown">
                    <td><a href="SCRIPTS.md" target="_blank" rel="nofollow noreferrer">SCRIPTS.md</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-md">Markdown</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="markdown">Markdown</span></span></td>
                    <td>Development standards for the scripts directory (catalog, newlines, security, retention).</td>
                    <td>Read in repository root or online. Follow rules when creating new utilities.</td>
                </tr>
                <tr data-tags="Markdown">
                    <td><a href="SCRIPTS_TEST_MATRIX.md" target="_blank" rel="nofollow noreferrer">SCRIPTS_TEST_MATRIX.md</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-md">Markdown</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="markdown">Markdown</span></span></td>
                    <td>Full catalog verification matrix: tiers 0–5, runner coverage map, Tier 5 exclusion list, destroy→document→fresh <code>db/</code> split bundle or <code>db/</code> split clone protocol. Companion logs: <code>data/scripts-matrix-destroy-log.md</code>, <code>data/scripts_errors.txt</code> (latest safe-matrix run).</td>
                    <td>Read before blanket <code>scripts/*</code> verification. Update in the same PR when adding catalog rows. Do not use <code>perform_audit.php</code> as a quality gate.</td>
                </tr>
            
<!-- ITM_CATALOG_DATA_DOCS_BEGIN -->
                <tr data-tags="Markdown" data-catalog-doc-file="1">
                    <td><a href="AGENT_NOTES.md" target="_blank" rel="nofollow noreferrer">AGENT_NOTES.md</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-md">Markdown</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="markdown">Markdown</span></span></td>
                    <td>Scripts folder agent notes: catalog layout, pitfalls, and maintenance contracts.</td>
                    <td>Open <code>scripts/AGENT_NOTES.md</code> in the repository or IDE.</td>
                </tr>
                <tr data-tags="Markdown" data-catalog-doc-file="1">
                    <td><a href="data/AGENT_NOTES.md" target="_blank" rel="nofollow noreferrer">data/AGENT_NOTES.md</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-md">Markdown</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="markdown">Markdown</span></span></td>
                    <td>scripts/data static files: allowlists, baselines, reviewed exception manifests, matrix logs.</td>
                    <td>Open <code>scripts/data/AGENT_NOTES.md</code> in the repository or IDE.</td>
                </tr>
                <tr data-tags="Info" data-catalog-doc-file="1">
                    <td><a href="data/crud_tables_skip_modules.txt" target="_blank" rel="nofollow noreferrer">data/crud_tables_skip_modules.txt</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-info">Info</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="info">Info</span></span></td>
                    <td>Static scripts data file: <code>data/crud_tables_skip_modules.txt</code>.</td>
                    <td>Open <code>scripts/data/crud_tables_skip_modules.txt</code> in the repository or IDE.</td>
                </tr>
                <tr data-tags="Info" data-catalog-doc-file="1">
                    <td><a href="data/fields_missing_reviewed.json" target="_blank" rel="nofollow noreferrer">data/fields_missing_reviewed.json</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-info">Info</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="info">Info</span></span></td>
                    <td>Static scripts data file: <code>data/fields_missing_reviewed.json</code>.</td>
                    <td>Open <code>scripts/data/fields_missing_reviewed.json</code> in the repository or IDE.</td>
                </tr>
                <tr data-tags="Info" data-catalog-doc-file="1">
                    <td><a href="data/index_table_compliance_baseline.txt" target="_blank" rel="nofollow noreferrer">data/index_table_compliance_baseline.txt</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-info">Info</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="info">Info</span></span></td>
                    <td>Static scripts data file: <code>data/index_table_compliance_baseline.txt</code>.</td>
                    <td>Open <code>scripts/data/index_table_compliance_baseline.txt</code> in the repository or IDE.</td>
                </tr>
                <tr data-tags="Info" data-catalog-doc-file="1">
                    <td><a href="data/multi_tenant_leak_allowlist.json" target="_blank" rel="nofollow noreferrer">data/multi_tenant_leak_allowlist.json</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-info">Info</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="info">Info</span></span></td>
                    <td>Static scripts data file: <code>data/multi_tenant_leak_allowlist.json</code>.</td>
                    <td>Open <code>scripts/data/multi_tenant_leak_allowlist.json</code> in the repository or IDE.</td>
                </tr>
                <tr data-tags="Info" data-catalog-doc-file="1">
                    <td><a href="data/perform_audit_allowlist.json" target="_blank" rel="nofollow noreferrer">data/perform_audit_allowlist.json</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-info">Info</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="info">Info</span></span></td>
                    <td>Static scripts data file: <code>data/perform_audit_allowlist.json</code>.</td>
                    <td>Open <code>scripts/data/perform_audit_allowlist.json</code> in the repository or IDE.</td>
                </tr>
                <tr data-tags="Markdown" data-catalog-doc-file="1">
                    <td><a href="data/scripts-matrix-destroy-log.md" target="_blank" rel="nofollow noreferrer">data/scripts-matrix-destroy-log.md</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-md">Markdown</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="markdown">Markdown</span></span></td>
                    <td>Append-only destroy→fresh-clone log for blanket scripts/* verification.</td>
                    <td>Open <code>scripts/data/scripts-matrix-destroy-log.md</code> in the repository or IDE.</td>
                </tr>
                <tr data-tags="Info" data-catalog-doc-file="1">
                    <td><a href="data/scripts_errors.txt" target="_blank" rel="nofollow noreferrer">data/scripts_errors.txt</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-info">Info</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="info">Info</span></span></td>
                    <td>Latest safe scripts matrix run report (Passed / Skipped / Failures lists).</td>
                    <td>Open <code>scripts/data/scripts_errors.txt</code> in the repository or IDE.</td>
                </tr>
                <tr data-tags="Info" data-catalog-doc-file="1">
                    <td><a href="data/script_catalog_tags.json" target="_blank" rel="nofollow noreferrer">data/script_catalog_tags.json</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-info">Info</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="info">Info</span></span></td>
                    <td>Computed catalog tag manifest for scripts/scripts.php rows (apply/check drift gate).</td>
                    <td>Open <code>scripts/data/script_catalog_tags.json</code> in the repository or IDE.</td>
                </tr>
                <tr data-tags="Info" data-catalog-doc-file="1">
                    <td><a href="data/ui_configuration_excluded_modules.txt" target="_blank" rel="nofollow noreferrer">data/ui_configuration_excluded_modules.txt</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-info">Info</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="info">Info</span></span></td>
                    <td>Static scripts data file: <code>data/ui_configuration_excluded_modules.txt</code>.</td>
                    <td>Open <code>scripts/data/ui_configuration_excluded_modules.txt</code> in the repository or IDE.</td>
                </tr>
                <tr data-tags="Info" data-catalog-doc-file="1">
                    <td><a href="data/ui_configuration_excluded_prefixes.txt" target="_blank" rel="nofollow noreferrer">data/ui_configuration_excluded_prefixes.txt</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-info">Info</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="info">Info</span></span></td>
                    <td>Static scripts data file: <code>data/ui_configuration_excluded_prefixes.txt</code>.</td>
                    <td>Open <code>scripts/data/ui_configuration_excluded_prefixes.txt</code> in the repository or IDE.</td>
                </tr>
                <tr data-tags="Info" data-catalog-doc-file="1">
                    <td><a href="data/ui_configuration_reviewed.json" target="_blank" rel="nofollow noreferrer">data/ui_configuration_reviewed.json</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-info">Info</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="info">Info</span></span></td>
                    <td>Static scripts data file: <code>data/ui_configuration_reviewed.json</code>.</td>
                    <td>Open <code>scripts/data/ui_configuration_reviewed.json</code> in the repository or IDE.</td>
                </tr>
                <!-- ITM_CATALOG_DATA_DOCS_END -->
            </tbody>
        </table></div>
    </div>

    <div class="scripts-section scripts-catalog-section" id="browser">
        <h2>Browser tools</h2>
        <div class="scripts-catalog-grid"><table class="scripts-catalog">
            <colgroup>
                <col class="scripts-col-script">
                <col class="scripts-col-access">
                <col class="scripts-col-tags">
                <col class="scripts-col-what">
                <col class="scripts-col-how">
            </colgroup>
            <thead>
                <tr>
                    <th>Script</th>
                    <th class="scripts-access-col">Access</th>
                    <th>Tags</th>
                    <th>What it does</th>
                    <th>How to use</th>
                </tr>
            </thead>
            <tbody>
                <tr data-tags="Codebase">
                    <td><a href="DBdesign.php" target="_blank" rel="nofollow noreferrer">DBdesign.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>ER-style diagram generated from <code>db/</code> split bundle (Mermaid render, zoom, SVG/PNG export).</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="crud_tables.php" target="_blank" rel="nofollow noreferrer">crud_tables.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Lists each module’s first <code>$crud_table</code> in <code>index.php</code>. Bespoke/exception modules without it show <strong>Skip</strong> (<code>docs/list_bespoke_UI.txt</code> + <code>scripts/data/crud_tables_skip_modules.txt</code>).</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="crud_titles.php" target="_blank" rel="nofollow noreferrer">crud_titles.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Lists each module’s first <code>$crud_title</code> in <code>index.php</code>. <code>is_*</code> modules and bespoke slugs without it show <strong>Skip</strong>.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="crud_actions.php" target="_blank" rel="nofollow noreferrer">crud_actions.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Lists every module entry file (<code>index.php</code> and CRUD wrappers) and each <code>$crud_action =</code> assignment. Non-standard CRUD modules with no assignment show <strong>Skip</strong>.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="audit_logs">
                    <td><a href="update_all_created_at.php" target="_blank" rel="nofollow noreferrer">update_all_created_at.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">audit_logs</span></span></td>
                    <td>After importing <code>db/</code> split bundle, sets every live row’s <code>created_at</code> to one timestamp (default <code>2026-01-01 00:00:01</code>). Dry-run previews counts first.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="companies">
                    <td><a href="detect_fk_dropdown_ui_risk_ui.php" target="_blank" rel="nofollow noreferrer">detect_fk_dropdown_ui_risk_ui.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">companies</span></span></td>
                    <td>Scans cross-tenant FK rows and module code that can cause <strong>duplicate dropdown options</strong>. Browser access requires an Admin session; results show filter-matched summaries plus links to <code>modules/…/</code> and edit screens (new tab) when a module folder exists.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="companies">
                    <td><a href="detect_fk_dropdown_ui_risk.php" target="_blank" rel="nofollow noreferrer">detect_fk_dropdown_ui_risk.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">companies</span></span></td>
                    <td>Same scanner as the UI. Browser requests load <code>detect_fk_dropdown_ui_risk_ui.php</code>; CLI prints human-readable lines (or JSON with <code>--json</code>).</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="list_boolean_integer_fields.php" target="_blank" rel="nofollow noreferrer">list_boolean_integer_fields.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Lists all database fields that can be Boolean, int, tinyint, and others, matching tables to modules and formatting output precisely.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="list_enum_fields.php" target="_blank" rel="nofollow noreferrer">list_enum_fields.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Lists all database fields of ENUM type, matching tables to modules and formatting output precisely.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="extract_by_fields.php" target="_blank" rel="nofollow noreferrer">extract_by_fields.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Scans db/ and lists table fields matching keywords (by, to, employee_id, employee).</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="debug.php" target="_blank" rel="nofollow noreferrer">debug.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>System overview (DB, tables, PHP version, extensions, and file permissions).</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="health.php" target="_blank" rel="nofollow noreferrer">health.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli-only">CLI-only</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Shell bootstrap that provisions a JSON health-check endpoint for monitoring (not a PHP entry script).</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
            </tbody>
        </table></div>
    </div>

    <div class="scripts-section scripts-catalog-section" id="security">
        <h2>Security (interactive)</h2>
        <p class="scripts-muted">Browser-first sandboxes and form tests. Repo-wide static scanners are under <a href="#ci">CI &amp; static analysis</a>.</p>
        <div class="scripts-catalog-grid"><table class="scripts-catalog">
            <colgroup>
                <col class="scripts-col-script">
                <col class="scripts-col-access">
                <col class="scripts-col-tags">
                <col class="scripts-col-what">
                <col class="scripts-col-how">
            </colgroup>
            <thead>
                <tr>
                    <th>Script</th>
                    <th class="scripts-access-col">Access</th>
                    <th>Tags</th>
                    <th>What it does</th>
                    <th>How to use</th>
                </tr>
            </thead>
            <tbody>
                <tr data-tags="Codebase">
                    <td><a href="test_form_failed_save_display.php" target="_blank" rel="nofollow noreferrer">test_form_failed_save_display.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Scans all module create forms for SQL-quoted re-display after failed saves (e.g. <code>value="'USA'"</code>); optional runtime POST tests per module.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="equipment">
                    <td><a href="test_sql_injection.php" target="_blank" rel="nofollow noreferrer">test_sql_injection.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">equipment</span></span></td>
                    <td>JSON sandbox to test SQL-injection signature detection and safe prepared-statement execution (GET/POST).</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="apply_form_failed_save_display_fix.php" target="_blank" rel="nofollow noreferrer">apply_form_failed_save_display_fix.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Bulk-applies <code>cr_form_display_value</code> / POST normalization fixes across CRUD entry files (companion to the form test above). <strong>Default = dry-run</strong>; writes with CLI <code>--apply</code> or browser <code>?apply=1</code> (Admin). Lists scanned / changed / skipped targets. Optional <code>--module</code> / <code>?module=</code> filter.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="test_db_error_messages.php" target="_blank" rel="nofollow noreferrer">test_db_error_messages.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Read-only assertion harness for <code>itm_format_db_constraint_error()</code>, <code>itm_render_alert_errors()</code>, and <code>itm_humanize_api_error_message()</code> (e.g. NOT NULL → “Please select a value for Employee”; strips <code>Database error:</code> / <code>DB error:</code> prefixes). Lists passed/failed assertion labels.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="apply_human_friendly_error_display.php" target="_blank" rel="nofollow noreferrer">apply_human_friendly_error_display.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Replaces duplicated <code>alert alert-error</code> blocks with <code>itm_render_alert_errors()</code> across module PHP files (all modules, including bespoke folders). <strong>Default = dry-run</strong>; writes with CLI <code>--apply</code> or browser <code>?apply=1</code> (Admin). Lists scanned / changed / skipped targets. Optional <code>--module</code> / <code>?module=</code> filter.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="emails employees">
                    <td><a href="test_email_forgot.php" target="_blank" rel="nofollow noreferrer">test_email_forgot.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">emails</span><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">employees</span></span></td>
                    <td>Test script for Password Reset email delivery verification.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="test_register_mail.php" target="_blank" rel="nofollow noreferrer">test_register_mail.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Test script for Registration Welcome email delivery verification.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="verify_user_config_profile.php" target="_blank" rel="nofollow noreferrer">verify_user_config_profile.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>Regression for <code>user-config.php</code> profile fields: home-company UPDATE (vs tenant switcher), birthday/theme/emergency round-trip, and profile photo URL/serve contract (app-absolute Explorer proxy, not <code>../../modules/…</code>).</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="verify_sidebar_preferences.php" target="_blank" rel="nofollow noreferrer">verify_sidebar_preferences.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>Regression for <code>employee_sidebar_preferences</code> save/load: Personalized Sidebar + Settings SideMenu access contracts, DB round-trip via <code>itm_user_config_save_personalized_sidebar_items()</code>.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="verify_webmail_module.php" target="_blank" rel="nofollow noreferrer">verify_webmail_module.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>Regression checks for <code>modules/webmail/</code>: <code>modules_registry</code> row, inbox/starred/sent/archived/trash folder scope, star and archive toggles, soft delete, restore, and hard delete on shared <code>emails</code> table.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="verify_emails_module.php" target="_blank" rel="nofollow noreferrer">verify_emails_module.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>Regression checks for Email Management tables, registry row, SMTP seed, alert rules, <code>itm_send_email()</code>, <code>user-config.php</code> vault-key notification contract (no plaintext secrets), and company 1 30-day warranty/license alert window (hard fail; disposable sample insert when empty).</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="run_email_alert_rules.php" target="_blank" rel="nofollow noreferrer">run_email_alert_rules.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>Dispatches enabled <code>email_alert_rules</code> (warranty, license, certificate, alerts, notes, to-do, events) using tenant default SMTP; warranty matches also enqueue in-app notifications for equipment assignees.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="run_notification_digest.php" target="_blank" rel="nofollow noreferrer">run_notification_digest.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>Sends digest emails to employees with unread <code>employee_notifications</code> rows (metadata only; links to inbox).</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="verify_employee_notifications.php" target="_blank" rel="nofollow noreferrer">verify_employee_notifications.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>Regression for in-app notification center: <code>itm_notify_employee()</code>, unread count, mark read, API/JS assets.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="../js/itm-user-errors.js" target="_blank" rel="nofollow noreferrer">itm-user-errors.js</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Global JS helpers <code>itmNotifyError()</code>, <code>itmNotifyAjaxError()</code> (modal-aware), and <code>itmNotifySuccess()</code> for themed in-page alerts after AJAX/modal failures (loaded from <code>includes/header.php</code>).</td>
                    <td>Included on all standard pages via header. IDF modals use <code>itmNotifyAjaxError()</code> so errors render inside the open modal instead of behind the backdrop.</td>
                </tr>
            </tbody>
        </table></div>
    </div>
<div class="scripts-section scripts-catalog-section" id="tests">
        <h2>PHPUnit</h2>
        <div class="scripts-catalog-grid"><table class="scripts-catalog">
            <colgroup>
                <col class="scripts-col-script">
                <col class="scripts-col-access">
                <col class="scripts-col-tags">
                <col class="scripts-col-what">
                <col class="scripts-col-how">
            </colgroup>
            <thead>
                <tr>
                    <th>Script</th>
                    <th class="scripts-access-col">Access</th>
                    <th>Tags</th>
                    <th>What it does</th>
                    <th>How to use</th>
                </tr>
            </thead>
            <tbody>
                <tr data-tags="Codebase">
                    <td><a href="run_tests.php" target="_blank" rel="nofollow noreferrer">run_tests.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Runs the PHPUnit suite in <code>phpunit/tests/Unit/</code> via <code>phpunit/phpunit.phar</code> and <code>phpunit/phpunit.xml</code>. Browser menu: <strong>Standard</strong> (verbose) or <strong>HTML coverage</strong> (Xdebug/PCOV). Report: <code>phpunit/coverage/html/coverage.html</code>. Skips coverage driver when Xdebug/PCOV missing. <code>processUncoveredFiles="false"</code> in phpunit.xml for reliable report generation. Entry guards: <code>includes/itm_script_entry_guard.php</code>. See <code>scripts/SCRIPTS.md</code> (PHPUnit test runner).</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="identify_modules.php" target="_blank" rel="nofollow noreferrer">identify_modules.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Scans the <code>modules/</code> directory to identify and categorize all modules into standard CRUD and bespoke types, saving metadata to <code>scripts/modules_metadata.json</code>.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="departments">
                    <td><a href="scaffold_departments_child_table_modules.php" target="_blank" rel="nofollow noreferrer">scaffold_departments_child_table_modules.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">departments</span></span></td>
                    <td>Copies flattened CRUD from <code>modules/departments/</code> into the 24 child/support table module allowlist. <strong>Default = dry-run</strong> (<code>?run=1</code> compact summary; <code>?verbose=1</code> lists slugs); writes with CLI <code>--apply</code> or browser <code>?run=1&amp;apply=1</code> (Admin). Verify: <code>verify_scaffold_departments_child_table_modules.php</code>.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="departments">
                    <td><a href="verify_scaffold_departments_child_table_modules.php" target="_blank" rel="nofollow noreferrer">verify_scaffold_departments_child_table_modules.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">departments</span></span></td>
                    <td>Three-step gate for departments child-table modules: folder <code>index.php</code> + <code>$crud_table</code>, schema tables-without-module count, sidebar catalog ids. Exit <code>1</code> on failure.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="generate_tests.php" target="_blank" rel="nofollow noreferrer">generate_tests.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Automatically generates PHPUnit integration tests for all standard CRUD modules identified in the metadata. Creates test files in <code>phpunit/tests/Unit/Modules/</code>.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="test_import_user_samples.php" target="_blank" rel="nofollow noreferrer">test_import_user_samples.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>Verifies the JSON table import logic against specific user-provided sample data for the employees module.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="test_ajax.php" target="_blank" rel="nofollow noreferrer">test_ajax.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>CLI harness that mocks session/POST for Notes <code>quick_add</code> AJAX.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="test_edit.php" target="_blank" rel="nofollow noreferrer">test_edit.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>CLI harness that mocks session/POST for Notes edit.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="test_session.php" target="_blank" rel="nofollow noreferrer">test_session.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Verifies session handling and persistence.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="license_types">
                    <td><a href="verify_api_coverage.php" target="_blank" rel="nofollow noreferrer">verify_api_coverage.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">license_types</span></span></td>
                    <td>Audits import endpoints, bespoke paths, and non-index module JSON handlers (PHP <code>Content-Type: application/json</code> headers only; matches <code>scripts/api.php</code> project + Explorer + IDF api catalogs).</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
            </tbody>
        </table></div>
    </div>
    <div class="scripts-section scripts-catalog-section" id="database">
        <h2>Database</h2>
        <div class="scripts-catalog-grid"><table class="scripts-catalog">
            <colgroup>
                <col class="scripts-col-script">
                <col class="scripts-col-access">
                <col class="scripts-col-tags">
                <col class="scripts-col-what">
                <col class="scripts-col-how">
            </colgroup>
            <thead>
                <tr>
                    <th>Script</th>
                    <th class="scripts-access-col">Access</th>
                    <th>Tags</th>
                    <th>What it does</th>
                    <th>How to use</th>
                </tr>
            </thead>
            <tbody>
                <tr data-tags="Codebase">
                    <td><a href="http://localhost/phpmyadmin/" target="_blank" rel="noopener noreferrer">phpMyAdmin</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Local MySQL admin UI for schema inspection, ad-hoc SQL, and imports (database <code>itmanagement</code> on typical Laragon installs).</td>
                    <td>Open <a href="http://localhost/phpmyadmin/" target="_blank" rel="noopener noreferrer">http://localhost/phpmyadmin/</a> in a new tab. Default dev: user <code>root</code>, password per your Laragon/MySQL setup (often blank locally).</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="analyze_database_health.php" target="_blank" rel="nofollow noreferrer">analyze_database_health.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli-only">CLI-only</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Runs <code>ANALYZE TABLE</code> on every base table and lists per-table success/failure (avoids phpMyAdmin stopping on first error).</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="companies">
                    <td><a href="force_delete_company.php" target="_blank" rel="nofollow noreferrer">force_delete_company.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">companies</span></span></td>
                    <td>Bypasses triggers and FK checks to completely remove a company and its data across all <code>company_id</code> tables (including audit logs).</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="check_database_sql_company_name_uniques.php">check_database_sql_company_name_uniques.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Audits <code>db/</code> split bundle: each <code>company_id</code> table needs 2 uniques (PRIMARY + scope UNIQUE). Floor plans: <code>IFNULL(parent_folder_id,0)+name</code> / <code>IFNULL(folder_id,0)+display_name</code> (not <code>company_id+folder_id</code> alone). Skips <code>bookmark_folders</code> (duplicate names OK) and <code>floor_plan_item_tags</code> (junction PK only).</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="repair_table_from_schema.php" target="_blank" rel="nofollow noreferrer">repair_table_from_schema.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Rebuilds one InnoDB table from <code>db/01_schema.sql</code> when metadata drift causes "doesn't exist in engine" errors. <strong>Destructive</strong> on apply — dry-run validates <code>--table</code> / <code>?table=</code> and shows CREATE excerpt only.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="count_db_tables.php" target="_blank" rel="nofollow noreferrer">count_db_tables.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Counts live tables in <code>information_schema</code> for <code>itmanagement</code>, echoes the total as plain text, and overwrites <code>scripts/number_db_tables.txt</code> with the same number (for external monitors). <strong>No login required.</strong></td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="audit_logs">
                    <td><a href="verify_bigint_table_review.php" target="_blank" rel="nofollow noreferrer">verify_bigint_table_review.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">audit_logs</span></span></td>
                    <td>Live BIGINT migration review: row counts, max IDs, column types, and AUTO_INCREMENT for six review tables. Browser + CLI (Administrator); <code>?run=1</code> landing and coloured report shell (same pattern as <code>verify_db_migrations.php</code>). Includes static <strong>300 staff × 5 companies</strong> scale projection. Pair with <code>db/migrations/audit_logs_bigint.sql</code>.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="schema_migrations">
                    <td><a href="migrate.php" target="_blank" rel="nofollow noreferrer">migrate.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">schema_migrations</span></span></td>
                    <td>Migration runner for <code>db/migrations/*.sql</code>: <code>--status</code> probes the <strong>live database</strong> for every file (Applied vs Pending); <code>schema_migrations</code> is audit/history only. <code>--apply</code> runs SQL only when the live probe failed; records satisfied migrations without re-executing destructive files (Admin browser <code>?apply=1</code>). Browser status table: per-row <code>?run=1&amp;sql={filename}</code> opens migration SQL in a new tab; Admin 🗑️ delete removes the file from disk (JS confirm). Helpers: <code>includes/itm_database_migrations.php</code>.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="verify_db_migrations.php" target="_blank" rel="nofollow noreferrer">verify_db_migrations.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>Compares live <code>itmanagement</code> schema/data against every <code>db/migrations/*.sql</code> file discovered on disk (natural sort; Applied / Superseded / Not applied / DML only). Parses <code>CREATE TABLE</code> / <code>CREATE TRIGGER</code> per file. Schema probe only — applied history: <code>migrate.php</code> + <code>schema_migrations</code>. Shared lib: <code>lib/itm_verify_db_migrations_report.php</code>.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="verify_database_schema.php" target="_blank" rel="nofollow noreferrer">verify_database_schema.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Compares <code>CREATE TABLE</code> names in <code>db/</code> split bundle with <code>information_schema</code> for <code>itmanagement</code>. Use after PowerShell/MySQL imports that report success but stop early (e.g. 73 tables instead of 126). Lists missing/extra tables; exit <code>1</code> on mismatch.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Server">
                    <td>verify_database_sql_import.sh</td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli-only">CLI-only</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="server">Server</span></span></td>
                    <td>Imports the full <code>db/</code> split bundle against a live MySQL 8.0 server and asserts the live <code>itmanagement</code> table count matches <code>CREATE TABLE</code> entries in <code>db/01_schema.sql</code> (currently <strong>126</strong>). Catches INSERT/SELECT column-count mismatches (for example cross-company <code>equipment</code> seed at <code>department_id</code>). Used by CI job <strong>database-import</strong> in <code>.github/workflows/smoke.yml</code>. Split alternative: <code>import_database_split.sh</code>.</td>
                    <td><code>bash scripts/verify_database_sql_import.sh</code> — requires MySQL on <code>127.0.0.1</code>, user <code>root</code>, password <code>itmanagement</code>. Env: <code>MYSQL_HOST</code>, <code>MYSQL_USER</code>, <code>MYSQL_PASSWORD</code>, optional <code>EXPECTED_TABLE_COUNT</code> override.</td>
                </tr>
                <tr data-tags="Server">
                    <td>import_database_split.sh</td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli-only">CLI-only</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="server">Server</span></span></td>
                    <td>Imports <code>db/</code> SQL in one MySQL session: <code>01_schema.sql</code> → <code>02_data.sql</code> → <code>03_triggers.sql</code> (preserves <code>@replicate_source_company_id</code>; loads triggers after seed data). Runs <code>verify_database_schema.php</code> on success.</td>
                    <td><code>bash scripts/import_database_split.sh</code> — same MySQL env vars as <code>verify_database_sql_import.sh</code>. See <code>db/AGENT_NOTES.md</code> for import order.</td>
                </tr>
                <tr data-tags="employees ui_configuration">
                    <td><a href="employee_fields_missing.php" target="_blank" rel="nofollow noreferrer">employee_fields_missing.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">employees</span><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">ui_configuration</span></span></td>
                    <td>Compares <code>employees</code> columns in <code>db/</code> split bundle and the live schema with create/edit/view/index coverage in <code>modules/employees/</code>. Fails when critical columns (including <code>termination_date</code>) are missing from the DB or module UI; lists optional gaps as <code>[INFO]</code>. Uses shared lib <code>itm_fields_missing_report.php</code>.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="ui_configuration">
                    <td><a href="fields_missing.php" target="_blank" rel="nofollow noreferrer">fields_missing.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">ui_configuration</span></span></td>
                    <td>All-module schema/UI audit: <code>db/</code> split bundle columns vs live MySQL vs module screens for every discoverable <code>$crud_table</code> module. Flattened scaffold: <code>[PASS]</code>/<code>[FAIL]</code>; bespoke/status-driven slugs: gated <code>[SKIP][pass]</code>/<code>[SKIP][fail]</code>/<code>[SKIP][fail][reviewed]</code> (page + list UI contract via <code>scripts/lib/itm_ui_list_contract_checks.php</code>). Reviewed exceptions: <a href="fields_missing_reviewed.php" target="_blank" rel="nofollow noreferrer">fields_missing_reviewed.php</a> · <code>scripts/data/fields_missing_reviewed.json</code>. Shared lib: <code>itm_fields_missing_report.php</code>.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="ui_configuration">
                    <td><a href="fields_missing_reviewed.php" target="_blank" rel="nofollow noreferrer">fields_missing_reviewed.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">ui_configuration</span></span></td>
                    <td>Read-only manifest for reviewed bespoke <code>[SKIP][fail]</code> gate lines consumed by <code>fields_missing.php</code>. Data: <code>scripts/data/fields_missing_reviewed.json</code> (module slug + check label/code + reason).</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="ui_configuration">
                    <td><a href="ui_configuration_reviewed.php" target="_blank" rel="nofollow noreferrer">ui_configuration_reviewed.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">ui_configuration</span></span></td>
                    <td>Read-only manifest for reviewed gate-excluded <code>[n/a][pass|fail|n/a]</code> lines consumed by <code>check_ui_configuration_coverage.php</code>. Data: <code>scripts/data/ui_configuration_reviewed.json</code> (module slug + UI config check label/code + reason).</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="equipment">
                    <td><a href="debug_equipment_create_rollback_errno.php" target="_blank" rel="nofollow noreferrer">debug_equipment_create_rollback_errno.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">equipment</span></span></td>
                    <td>Diagnose generic equipment create save errors: runs a deliberate failing <code>equipment</code> INSERT (NULL <code>status_id</code>) inside a transaction and compares <code>mysqli_errno()</code> / <code>mysqli_error()</code> plus <code>itm_format_db_constraint_error()</code> output before vs after <code>mysqli_rollback()</code>. Exit <code>1</code> when rollback clears the error state (explains the vague “Review the required fields” UI message).</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="debug_resignations_termination_date.php" target="_blank" rel="nofollow noreferrer">debug_resignations_termination_date.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>Diagnose why a <code>termination_date</code> (default <code>18/06/2026</code>, ISO week 25) does or does not match <code>modules/resignations/index.php</code> — PHP vs MySQL week metadata, ISO bounds, legacy <code>YEAR/MONTH/WEEK</code>, simulated module SQL (<code>itm_sql_valid_date_predicate()</code>; not <code>&lt;&gt; '0000-00-00'</code>), employee row, verify-probe bounds. Use when the report is empty or prepare fails with <code>Incorrect DATE value: '0000-00-00'</code>.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="verify_employee_type_resignations.php" target="_blank" rel="nofollow noreferrer">verify_employee_type_resignations.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>Regression for <code>employee_type</code> seed data, <code>employees.start_date</code> / <code>employee_type_id</code>, <code>modules_registry</code> slugs, and the weekly resignations SQL filter (<code>itm_iso_week_bounds()</code>, <code>MONTH(termination_date)</code>, <code>itm_sql_valid_date_predicate()</code>) aligned with <code>modules/resignations/index.php</code>.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="normalize_database_sql_created_at.php" target="_blank" rel="nofollow noreferrer">normalize_database_sql_created_at.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Sets every seed <code>created_at</code> literal in <code>db/01_schema.sql</code> INSERT rows to one timestamp (default <code>2026-01-01 00:00:01</code>); leaves <code>updated_at</code> and other date columns unchanged. <strong>Default = dry-run</strong>; writes <code>db/01_schema.sql</code> with <code>--apply</code> / <code>?apply=1</code> (Admin).</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="companies">
                    <td><a href="apply_module_sample_data_seed.php" target="_blank" rel="nofollow noreferrer">apply_module_sample_data_seed.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">companies</span></span></td>
                    <td>Automates per-module/table seed expansion: adds missing sample rows for every company listed in <code>companies</code> into <code>db/</code> split bundle. Default <code>idf_device_type</code> samples are <code>other</code> 📦, <code>server</code> 🖥️, <code>ups</code> 🔋, <code>patch_panel</code> ➿, and <code>switch</code> 🔀; custom <code>--sample</code> values supported. <strong>Default = dry-run</strong>; writes with CLI <code>--apply</code> or browser <code>?apply=1</code> (Admin). Lists new INSERT statements and skipped targets before apply. Requires <code>--module</code> / <code>?module=</code>.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="extract_02_data_sample.php" target="_blank" rel="nofollow noreferrer">extract_02_data_sample.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Builds <code>db/02_data_sample.sql</code> (runtime Add sample data templates) from company <code>1</code> rows in <code>db/02_data.sql</code>, live MySQL backfill, and minimal synthesis so every tenant-scoped table has at least one template. Requires MySQL. <strong>Default = dry-run</strong>; writes with <code>--apply</code> / <code>?apply=1</code> (Admin).</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="check_02_data_sample_coverage.php" target="_blank" rel="nofollow noreferrer">check_02_data_sample_coverage.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Static gate: every tenant-scoped table has at least one company <code>1</code> marker row in <code>db/02_data_sample.sql</code> (exempt: audit logs, RBAC, share sessions, <code>ui_configuration</code>, etc.).</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="dedupe_02_data_per_company_inserts.php" target="_blank" rel="nofollow noreferrer">dedupe_02_data_per_company_inserts.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Removes redundant companies <code>2–5</code> single-row INSERTs from <code>db/02_data.sql</code> when <code>@replicate_source_company_id</code> replication already copies from company <code>1</code>. <strong>Default = dry-run</strong>.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="verify_sample_data_seed.php" target="_blank" rel="nofollow noreferrer">verify_sample_data_seed.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>Regression: Add sample data seeds arbitrary disposable companies from <code>db/02_data_sample.sql</code> with tenant scoping, FK parent chain, and duplicate skip.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="floor_plans floor_plan_folders">
                    <td><a href="export_floor_plan_folders_seed.php" target="_blank" rel="nofollow noreferrer">export_floor_plan_folders_seed.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">floor_plans</span><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">floor_plan_folders</span></span></td>
                    <td>Exports <code>floor_plan_folders</code> rows from the live DB as <code>db/</code> split bundle-style <code>INSERT</code> statements for pasting into seed data. Read-only dump (no dry-run apply gate).</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="check_delimiters.php" target="_blank" rel="nofollow noreferrer">check_delimiters.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Audit db/ for correct DELIMITER usage in trigger blocks.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="check_duplicates.php" target="_blank" rel="nofollow noreferrer">check_duplicates.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Audit db/ for duplicate column definitions in CREATE TABLE blocks.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="check_phones.php" target="_blank" rel="nofollow noreferrer">check_phones.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Identify tables in db/ that contain phone-related columns for PII auditing.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="check_sql_errors.php" target="_blank" rel="nofollow noreferrer">check_sql_errors.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Audit db/ for column mismatches in triggers and INSERT statements.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="count_args.php" target="_blank" rel="nofollow noreferrer">count_args.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Count arguments in the <code>trg_employees_audit_insert</code> trigger in db/.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="departments">
                    <td><a href="fix_sql_departments.php" target="_blank" rel="nofollow noreferrer">fix_sql_departments.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">departments</span></span></td>
                    <td>Fix column count mismatch in departments INSERT statements in <code>db/01_schema.sql</code>. <strong>Default = dry-run</strong>.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="list_phone_columns.php" target="_blank" rel="nofollow noreferrer">list_phone_columns.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>List columns for each table defined in db/, filtering for phone columns.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="verify_sql.php" target="_blank" rel="nofollow noreferrer">verify_sql.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Comprehensive SQL audit script for db/ (delimiters, duplicates, references).</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="cable_colors switch_port_types">
                    <td><a href="fix_sql.php" target="_blank" rel="nofollow noreferrer">fix_sql.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">cable_colors</span><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">switch_port_types</span></span></td>
                    <td>Utility to fix common SQL errors in <code>db/01_schema.sql</code> (<code>cable_colors</code>, <code>switch_port_types</code>). <strong>Default = dry-run</strong>.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="fix_sql_broad.php" target="_blank" rel="nofollow noreferrer">fix_sql_broad.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Broad-spectrum SQL cleanup utility for <code>db/01_schema.sql</code>. <strong>Default = dry-run</strong>.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="schema_report.php" target="_blank" rel="nofollow noreferrer">schema_report.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Visual report for database schema validation (errors, warnings, and SKIP DELETE CASCADE skips).</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="validate_DB_schema.php" target="_blank" rel="nofollow noreferrer">validate_DB_schema.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Validates database schema consistency (FKs, duplicate indexes, orphaned indexes); intentional CASCADE prints as <code>[SKIP]</code>.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="employees">
                    <td><a href="test_employee_id-foreign_keys.php" target="_blank" rel="nofollow noreferrer">test_employee_id-foreign_keys.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">employees</span></span></td>
                    <td>Validates <code>employee_id</code> foreign keys across all tables.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="employees">
                    <td><a href="validate_delete_employee.php" target="_blank" rel="nofollow noreferrer">validate_delete_employee.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">employees</span></span></td>
                    <td>Validates if employees can be safely deleted by checking FKs and triggers.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="employees">
                    <td><a href="generate_FK_employee_id.php" target="_blank" rel="nofollow noreferrer">generate_FK_employee_id.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">employees</span></span></td>
                    <td>Generates SQL for missing <code>employee_id</code> foreign keys.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="generate_ms_support_feed_products.php" target="_blank" rel="nofollow noreferrer">generate_ms_support_feed_products.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Regenerates <code>includes/itm_news_feed_ms_support_products.php</code> from Microsoft Support RSS feed picker (205 Atom product feeds).</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="employees">
                    <td><a href="generate_reassignment.php" target="_blank" rel="nofollow noreferrer">generate_reassignment.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">employees</span></span></td>
                    <td>Reassignment plan before delete: filter by <strong>employee id</strong>, row counts, skip reasons, FK debug. Default dry-run + rows-only.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="employees">
                    <td><a href="transfer_data_from_employee.php" target="_blank" rel="nofollow noreferrer">transfer_data_from_employee.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">employees</span></span></td>
                    <td>Clones an employee and copies related data. <strong>Default dry-run</strong>; <code>apply=1</code> mutates DB (Admin).</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="employees">
                    <td><a href="delete_clone_employee.php" target="_blank" rel="nofollow noreferrer">delete_clone_employee.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">employees</span></span></td>
                    <td>Deletes a cloned employee and related data. <strong>Default dry-run</strong>; <code>apply=1</code> is destructive (Admin).</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
            </tbody>
        </table></div>
    </div>

    <div class="scripts-section scripts-catalog-section" id="idf">
        <h2>IDF &amp; equipment</h2>
        <div class="scripts-catalog-grid"><table class="scripts-catalog">
            <colgroup>
                <col class="scripts-col-script">
                <col class="scripts-col-access">
                <col class="scripts-col-tags">
                <col class="scripts-col-what">
                <col class="scripts-col-how">
            </colgroup>
            <thead>
                <tr>
                    <th>Script</th>
                    <th class="scripts-access-col">Access</th>
                    <th>Tags</th>
                    <th>What it does</th>
                    <th>How to use</th>
                </tr>
            </thead>
            <tbody>
                <tr data-tags="Mixed">
                    <td><a href="idfs_sync_human_test.php" target="_blank" rel="nofollow noreferrer">idfs_sync_human_test.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>End-to-end HTTP regression for IDF rack/device flows; asserts sync across <code>idf_ports</code>, <code>switch_ports</code>, <code>equipment</code>, <code>idf_links</code>. <strong>Mutates DB:</strong> creates temporary equipment/port/position/link rows and removes temporary artifacts at the end. After login, POSTs to <code>index.php</code> so session <code>company_id</code> matches <code>ITM_COMPANY_ID</code>; company-selection GET resolves redirects manually (open_basedir-safe).</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="idfs">
                    <td><a href="idfs_api_payload_dry_run.php" target="_blank" rel="nofollow noreferrer">idfs_api_payload_dry_run.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">idfs</span></span></td>
                    <td>Validates IDF device API JSON payloads offline (no MySQL). Read-only structure checks — always dry-run.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="idf_device_port_sort_test.php" target="_blank" rel="nofollow noreferrer">idf_device_port_sort_test.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>Proves RJ45 ports sort before fiber (SFP) in IDF device SQL; optional live MySQL checks.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="ensure_equipment_type_modules.php" target="_blank" rel="nofollow noreferrer">ensure_equipment_type_modules.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>Verifies or recreates canonical equipment-type façade modules under <code>modules/is_*</code> (<code>is_switch</code>, <code>is_server</code>, <code>is_workstation</code>, …). Does not delete anything. <strong>Default = dry-run</strong> (lists missing); <code>--apply</code> / <code>?apply=1</code> runs scaffold.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="cleanup_equipment_test_module_artifacts.php" target="_blank" rel="nofollow noreferrer">cleanup_equipment_test_module_artifacts.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>
                        <strong>Destructive (local dev DB) on apply:</strong> removes regression-test <code>equipment_types</code> rows (including <code>MBQA-equipment_types-…</code> runner tags), ITM test companies, junk <code>modules/is_*_itm_eqdct_*</code> / <code>*_itm_edct_*</code> / orphan <code>modules/is_mbqa_equipment_types_*</code> folders, and matching sidebar prefs — then re-ensures canonical <code>is_*</code> modules. Never removes <code>is_switch</code>, <code>is_server</code>, etc. <strong>Default = dry-run</strong> preview counts. Browser <strong>Run QA</strong> runs apply mode silently before/after <code>module_browser_qa_runner.php</code>.
                    </td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="equipment_delete_clear_table_test.php" target="_blank" rel="nofollow noreferrer">equipment_delete_clear_table_test.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>
                        MySQL regression for equipment <code>clear_table</code> and transactional <code>equipment_delete_record()</code>. Uses equipment type names <code>Switch</code> / <code>Server</code> only (not suffixed names) so canonical <code>modules/is_*</code> façades are reused and the sidebar stays clean. <strong>Mutates DB:</strong> creates temporary tenant/reference/equipment rows, then cleans them up.
                    </td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="equipment tickets">
                    <td><a href="tickets_related_equipment_delete_test.php" target="_blank" rel="nofollow noreferrer">tickets_related_equipment_delete_test.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">equipment</span><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">tickets</span></span></td>
                    <td>
                        MySQL regression for tickets sample data: seeds lookup parents (including <code>equipment</code>), inserts <code>TCK-0001</code> with <code>asset_id</code> on Primary File Server, and asserts <code>equipment_delete_record()</code> is blocked with a Related Asset / in-use message. <strong>Mutates DB:</strong> seeds/updates sample ticket rows during the test.
                    </td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="verify_tickets_sample_data.php" target="_blank" rel="nofollow noreferrer">verify_tickets_sample_data.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>
                        MySQL regression for tickets <strong>Add sample data</strong> on empty tenants: seeds <code>TCK-0001</code> with <code>is_archived = 0</code> when the company has no local employees (session Admin cross-company stamp). <strong>Mutates DB:</strong> deletes and re-seeds company <code>4</code> ticket lookup rows during the test.
                    </td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="verify_equipment_triggers.php" target="_blank" rel="nofollow noreferrer">verify_equipment_triggers.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>
                        MySQL regression for <code>equipment</code> audit triggers: verifies that <code>INSERT</code>, <code>UPDATE</code>, and <code>DELETE</code> operations on the equipment table are correctly logged to <code>audit_logs</code>. <strong>Mutates DB:</strong> creates and deletes temporary equipment rows.
                    </td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="employees_delete_clear_table_test.php" target="_blank" rel="nofollow noreferrer">employees_delete_clear_table_test.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>MySQL regression for employees <code>clear_table</code> soft-delete (detach + <code>active=0</code>/<code>deleted_at</code>; live rows cleared, audit rows remain). <strong>Mutates DB:</strong> creates temporary tenant/reference/employee rows, then cleans them up.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="floor_designer_points switch_port_types">
                    <td><a href="check_points.php" target="_blank" rel="nofollow noreferrer">check_points.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">floor_designer_points</span><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">switch_port_types</span></span></td>
                    <td>Audits network points and connections.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="test_visualizer_v2.php" target="_blank" rel="nofollow noreferrer">test_visualizer_v2.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Mock Vertical vs Horizontal IDF port visualizer (48 ports).</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="verify_port_visualizer_layout.php" target="_blank" rel="nofollow noreferrer">verify_port_visualizer_layout.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Regression: Vertical odd/even grid placement vs Horizontal L→R (<code>grid-row</code> / <code>grid-column</code>).</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="verify_rack_planner.php" target="_blank" rel="nofollow noreferrer">verify_rack_planner.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>Regression for <code>modules/rack_planner/</code>: <code>rack_planner</code> table + registry row, handler price-source sync wiring, audit triggers, and disposable <code>catalog:</code> / <code>equipment:</code> / <code>idf_unlinked:</code> price propagation to source tables.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
            </tbody>
        </table></div>
    </div>

    <div class="scripts-section scripts-catalog-section" id="ui-modules">
        <h2>UI &amp; modules</h2>
        <div class="scripts-catalog-grid"><table class="scripts-catalog">
            <colgroup>
                <col class="scripts-col-script">
                <col class="scripts-col-access">
                <col class="scripts-col-tags">
                <col class="scripts-col-what">
                <col class="scripts-col-how">
            </colgroup>
            <thead>
                <tr>
                    <th>Script</th>
                    <th class="scripts-access-col">Access</th>
                    <th>Tags</th>
                    <th>What it does</th>
                    <th>How to use</th>
                </tr>
            </thead>
            <tbody>
                <tr data-tags="Codebase">
                    <td><a href="titles_list.php" target="_blank" rel="nofollow noreferrer">titles_list.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Scans all PHP files under <code>modules/</code> for <code>&lt;title&gt;</code> tags. Summary: match vs not match for canonical <code>&lt;title&gt;&lt;?= sanitize($crud_title) ?&gt; - &lt;?php echo sanitize($app_name ?? itm_ui_config_app_name($currentUiConfig)); ?&gt;&lt;/title&gt;</code>; non-matching rows prefixed <code>[NOT MATCH]</code>.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="titles_list_show.php" target="_blank" rel="nofollow noreferrer">titles_list_show.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Same scan/summary as <code>titles_list.php</code> with rendered inner title text; non-matching rows prefixed <code>[NOT MATCH]</code>.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="ui_configuration">
                    <td><a href="verify_module_page_chrome.php" target="_blank" rel="nofollow noreferrer">verify_module_page_chrome.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">ui_configuration</span></span></td>
                    <td>Cross-check: canonical browser <code>&lt;title&gt;</code> (<code>titles_list.php</code>) and Settings favicon wiring (<code>fields_missing.php</code> bespoke gate) on every <code>modules/**/*.php</code> file with a standalone <code>&lt;head&gt;</code>. Exit <code>1</code> on any title or favicon failure.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="employees ui_configuration">
                    <td><a href="verify_favicon_root_cause.php" target="_blank" rel="nofollow noreferrer">verify_favicon_root_cause.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">employees</span><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">ui_configuration</span></span></td>
                    <td>Admin diagnostic: <code>ui_configuration</code> favicon path + on-disk <code>.ico</code> (Settings data layer) and module <code>&lt;head&gt;</code> favicon gate counts. Explains why <code>apply_head_favicon_link.php</code> alone does not fix empty <code>$favicon_url</code>. Optional <code>?module=</code> / <code>--module=</code> sample.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="list_modules_without_share.php" target="_blank" rel="nofollow noreferrer">list_modules_without_share.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Lists <code>modules_registry</code> rows <strong>not</strong> in <code>itm_qr_share_capable_module_slugs()</code> (Share Modules <span class="badge">No share UI</span>). Browser table links each module <strong>name</strong> to <code>modules/{slug}/index.php</code> when the folder exists.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="list_modules_not_on_sidebar.php" target="_blank" rel="nofollow noreferrer">list_modules_not_on_sidebar.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Audits <code>modules/*/index.php</code> vs live sidebar <code>match_dir</code> entries from <code>itm_sidebar_structure()</code> (base catalog, filesystem discovery, registry merge). Also lists active <code>modules_registry</code> rows without module folders (policy-hidden vs unexpected).</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="companies">
                    <td><a href="list_empty_tables.php" target="_blank" rel="nofollow noreferrer">list_empty_tables.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">companies</span></span></td>
                    <td>Lists every <code>company_id</code> table with zero live rows for the signed-in session company or a selected company (<code>?company=N</code> dropdown / <code>--company=N</code> on CLI). Links to <code>modules/{table}/index.php</code> in a new tab when the module folder exists.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="verify_company_empty_sample_data.php" target="_blank" rel="nofollow noreferrer">verify_company_empty_sample_data.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>Seeds empty tenant tables that have an <strong>Add sample data</strong> button (16-module allowlist). Uses shared <code>itm_seed_table_from_database_sql()</code>; exits <code>1</code> when any seed leaves the table empty.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="list_db_tables_without_modules.php" target="_blank" rel="nofollow noreferrer">list_db_tables_without_modules.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Lists live database tables with no <code>modules/</code> folder and no <code>$crud_table</code> mapping (policy-hidden internal tables excluded). Exit <code>1</code> when gaps exist. JSON: <code>--json</code> / <code>?format=json</code>.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="compare_database_sql_modules.php" target="_blank" rel="nofollow noreferrer">compare_database_sql_modules.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Compares every <code>CREATE TABLE</code> in <code>db/</code> split bundle with <code>modules/</code> folders and each module’s <code>$crud_table</code> mapping (matched, missing module, missing table, mismatch).</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="floor_plans floor_plan_folders">
                    <td><a href="floor_plans_folder_move_test.php" target="_blank" rel="nofollow noreferrer">floor_plans_folder_move_test.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">floor_plans</span><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">floor_plan_folders</span></span></td>
                    <td>MySQL regression for Floor Plans folder reparenting (<code>fp_move_folder_to_parent</code> in <code>gallery_helpers.php</code>). <strong>Mutates DB:</strong> creates temporary folder hierarchy rows, then removes them.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="explorer_human_test.php" target="_blank" rel="nofollow noreferrer">explorer_human_test.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>Human-flow regression for Explorer storage, access control, copy/move/delete, database synchronisation, and audit logging. <strong>Mutates DB and filesystem:</strong> creates a temporary company plus isolated <code>files/{company_id}</code> content, then removes them at shutdown.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="floor_designer">
                    <td><a href="floor_designer_test.php" target="_blank" rel="nofollow noreferrer">floor_designer_test.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">floor_designer</span></span></td>
                    <td>Validates Floor Designer module logic, AJAX endpoints, and schema mapping.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="list_active_and_checkboxes.php" target="_blank" rel="nofollow noreferrer">list_active_and_checkboxes.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Audits <code>active</code> field UI for modules with an <code>active</code> DB column (resolved via <code>$crud_table</code>). Flags forbidden text inputs, non-compliant scaffold checkboxes, and status-driven modules with visible active checkboxes.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="fix_scaffold_active_checkbox.php" target="_blank" rel="nofollow noreferrer">fix_scaffold_active_checkbox.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Repairs <code>scaffold_active_checkbox_not_compliant</code> findings (wrap <code>name="active"</code> checkboxes with <code>itm-checkbox-control</code> + <code>itm-check-indicator</code>). Browser module select; CLI <code>--module=</code> / <code>--all</code>. <strong>Default = dry-run</strong>; writes with <code>--apply</code> or browser <code>?apply=1</code> (Admin).</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
            </tbody>
        </table></div>
    </div>

    <div class="scripts-section scripts-catalog-section" id="ci">
        <h2>CI &amp; static analysis</h2>
        <p class="scripts-muted">PHP scanners support <strong>Browser</strong> (plain-text) and <strong>CLI</strong> (recommended for CI). Bash wrappers (<code>smoke_test.sh</code>, <code>import_database_split.sh</code>, …) and a few session helpers remain <strong>CLI-only</strong>.</p>
        <div class="scripts-catalog-grid"><table class="scripts-catalog">
            <colgroup>
                <col class="scripts-col-script">
                <col class="scripts-col-access">
                <col class="scripts-col-tags">
                <col class="scripts-col-what">
                <col class="scripts-col-how">
            </colgroup>
            <thead>
                <tr>
                    <th>Script</th>
                    <th class="scripts-access-col">Access</th>
                    <th>Tags</th>
                    <th>What it does</th>
                    <th>How to use</th>
                </tr>
            </thead>
            <tbody>
                <tr data-tags="Server">
                    <td>smoke_test.sh</td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli-only">CLI-only</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="server">Server</span></span></td>
                    <td>CI/local smoke runner (<code>.github/workflows/smoke.yml</code>): (1) <code>php -l</code> all PHP, (2) CSRF coverage, (3) SQLi coverage, (4) FK label search static audit. Other Tier 2 <code>check_*</code> scripts: use <code>run_tier2_checks.php</code>.</td>
                    <td><code>bash scripts/smoke_test.sh</code> from repository root. Optional: <code>PHP_BIN=/path/to/php</code> on Windows Laragon.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="run_tier2_checks.php" target="_blank" rel="nofollow noreferrer">run_tier2_checks.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>Batch runner for all Tier 2 static <code>check_*</code> scripts in <code>SCRIPTS_TEST_MATRIX.md</code> (pre-merge static cluster; no DB mutation). Stops on first failure by default; <code>--continue</code> collects all failures.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="perform_audit.php" target="_blank" rel="nofollow noreferrer">perform_audit.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Exploratory Tier 1–3 script subprocess audit (excludes Tier 4/5, <code>repro_*</code>, <code>verify_*</code>, <code>_tmp_*</code>). Per-script <code>exit_code</code> + isolated <code>error_log.txt</code> deltas → <code>scripts/php_error_audit_results.json</code>. Allowlist: <code>scripts/data/perform_audit_allowlist.json</code>. Not a CI gate.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="check_standard_crud_delegate_requires.php" target="_blank" rel="nofollow noreferrer">check_standard_crud_delegate_requires.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Static scan: <code>modules/*/</code> PHP files must not <code>require __DIR__ . '/../manufacturers/…'</code> (only <code>modules/manufacturers/</code> may host that CRUD tree).</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="check_csrf_coverage.php" target="_blank" rel="nofollow noreferrer">check_csrf_coverage.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>Static scan: POST handlers that mutate data without a known CSRF guard.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="apply_date_display_format.php" target="_blank" rel="nofollow noreferrer">apply_date_display_format.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>One-time/maintenance: patch duplicated <code>cr_render_cell_value()</code> helpers to call <code>itm_format_cell_scalar_display()</code> (dd/mm/yyyy list/view display). <strong>Default = dry-run</strong>; writes with CLI <code>--apply</code> or browser <code>?apply=1</code> (Admin). Lists scanned / changed / skipped module files. Idempotent; re-run when new flattened CRUD modules ship without the date display hook.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="employees">
                    <td><a href="apply_crud_hidden_employee_id_alias.php" target="_blank" rel="nofollow noreferrer">apply_crud_hidden_employee_id_alias.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">employees</span></span></td>
                    <td>One-time/maintenance: replace dead <code>'user_id'</code> entries in flattened CRUD <code>$hidden</code> column arrays with <code>'employee_id'</code> under <code>modules/</code>. <strong>Default = dry-run</strong>; writes with CLI <code>--apply</code> or browser <code>?apply=1</code> (Admin). Lists scanned / changed / skipped module files. Idempotent; re-run when new scaffolds copy the old hide list.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="apply_crud_fk_label_search.php" target="_blank" rel="nofollow noreferrer">apply_crud_fk_label_search.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>One-time/maintenance: extend flattened CRUD <code>index.php</code> search blocks with <code>itm_crud_fk_label_search_conditions()</code> so Search (all fields) matches FK label tables, not only raw IDs. <strong>Default = dry-run</strong>; writes with CLI <code>--apply</code> or browser <code>?apply=1</code> (Admin). Lists scanned / changed / skipped modules (Employees uses <code>includes/itm_employees_search.php</code> instead). Idempotent.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="apply_display_field_columns_search_alias.php" target="_blank" rel="nofollow noreferrer">apply_display_field_columns_search_alias.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>One-time/maintenance: add <code>$displayFieldColumns = $uiColumns</code> (or <code>$visibleFieldColumns</code>) before module paths so list search does not reference an undefined variable. <strong>Default = dry-run</strong>; writes with CLI <code>--apply</code> or browser <code>?apply=1</code> (Admin). Lists scanned / changed / skipped <code>index.php</code> files. Re-run when new flattened CRUD modules omit the alias.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="catalogs">
                    <td><a href="apply_script_catalog_tags.php" target="_blank" rel="nofollow noreferrer">apply_script_catalog_tags.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">catalogs</span></span></td>
                    <td>Computes table tags for every <code>scripts/scripts.php</code> catalog row (entry script + transitive <code>scripts/</code> requires + one-level spawn targets). Writes <code>scripts/data/script_catalog_tags.json</code> and patches <code>data-tags</code> + tag badge markup on each card.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="catalogs">
                    <td><a href="apply_script_catalog_usage_to_php.php" target="_blank" rel="nofollow noreferrer">apply_script_catalog_usage_to_php.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">catalogs</span></span></td>
                    <td>Moves catalog <em>How to use</em> HTML from <code>scripts/scripts.php</code> into each <code>*.php</code> row as <code>itm_script_browser_how_to_use()</code> and replaces catalog column 5 with <code>scripts-catalog-how-stub</code>. Default dry-run.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="check_script_browser_usage.php" target="_blank" rel="nofollow noreferrer">check_script_browser_usage.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Static gate: every browser-capable catalog <code>*.php</code> defines <code>itm_script_browser_how_to_use()</code>, a usage-gate hook, and a stub how-cell in <code>scripts.php</code>. Honors <code>itm_script_browser_usage_exempt_basenames()</code>.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="check_script_stdio_fwrite.php" target="_blank" rel="nofollow noreferrer">check_script_stdio_fwrite.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Static gate: no raw <code>fwrite(STDOUT|STDERR)</code> under <code>scripts/</code> — use <code>itm_script_write_stdout()</code> / <code>itm_script_write_stderr()</code> (<code>scripts/lib/itm_script_stdio.php</code>) so browser SAPI does not warn.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="check_script_php_utf8_no_bom.php" target="_blank" rel="nofollow noreferrer">check_script_php_utf8_no_bom.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Static gate: no UTF-8 BOM at file start under <code>scripts/**/*.php</code> (tracked source must be UTF-8 without BOM per <code>AGENTS.md</code>).</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="ui_configuration">
                    <td><a href="apply_head_favicon_link.php" target="_blank" rel="nofollow noreferrer">apply_head_favicon_link.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">ui_configuration</span></span></td>
                    <td>Maintenance: add <code>itm_render_head_favicon_link($favicon_url ?? null)</code> in module <code>index.php</code>, <code>create.php</code>, <code>edit.php</code>, and <code>view.php</code> <code>&lt;head&gt;</code> so the tab icon matches Settings on first paint. <strong>Default = dry-run</strong>; writes with CLI <code>--apply</code> or browser <code>?apply=1</code> (Admin).</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="apply_crud_browser_title_module_icon.php" target="_blank" rel="nofollow noreferrer">apply_crud_browser_title_module_icon.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Maintenance: inject <code>itm_crud_apply_module_icon_to_browser_title()</code> before canonical <code>&lt;title&gt;</code> in <code>modules/**/*.php</code> (sidebar icon in browser tab). <strong>Default = dry-run</strong>; writes with CLI <code>--apply</code> or browser <code>?apply=1</code> (Admin).</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="check_crud_browser_title_module_icon.php" target="_blank" rel="nofollow noreferrer">check_crud_browser_title_module_icon.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Static gate: every canonical module <code>&lt;title&gt;</code> must call <code>itm_crud_apply_module_icon_to_browser_title()</code>. Exit <code>1</code> on missing helper.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="ui_configuration">
                    <td><a href="apply_list_new_button_style.php" target="_blank" rel="nofollow noreferrer">apply_list_new_button_style.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">ui_configuration</span></span></td>
                    <td>Maintenance: normalize list-header <code>create.php</code> ➕ controls to <code>btn btn-primary itm-list-new-button</code> with <code>title="Create"</code> (40×40 CSS footprint). <strong>Default = dry-run</strong>; writes with CLI <code>--apply</code> or browser <code>?apply=1</code> (Admin).</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="apply_new_button_position_helper.php" target="_blank" rel="nofollow noreferrer">apply_new_button_position_helper.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Maintenance: replace duplicated <code>new_button_position</code> fallbacks with <code>itm_resolve_new_button_position($ui_config)</code> (default <code>left</code>). <strong>Default = dry-run</strong>; writes with CLI <code>--apply</code> or browser <code>?apply=1</code> (Admin).</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="apply_itm_actions_cell_markers.php" target="_blank" rel="nofollow noreferrer">apply_itm_actions_cell_markers.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>One-time/maintenance: add <code>class="itm-actions-cell"</code> and <code>data-itm-actions-origin="1"</code> on Actions column header and body cells in module list tables (module browser QA <code>ui_check</code>). <strong>Default = dry-run</strong>; writes with CLI <code>--apply</code> or browser <code>?apply=1</code> (Admin). Lists scanned / changed / skipped files under <code>modules/*/index.php</code> and <code>modules/*/includes/partials/render.php</code>.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="apply_ui_action_emoji.php" target="_blank" rel="nofollow noreferrer">apply_ui_action_emoji.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>One-time/maintenance: bulk replace simple NO MIXED markup (emoji + action word) → emoji-only + <code>title</code> on modules and shared UI files. <strong>Default = dry-run</strong>; writes with CLI <code>--apply</code> or browser <code>?apply=1</code> (Admin). Lists scanned / changed / skipped files. Skip PHP ternaries / JS templates — fix manually.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="apply_pagination_emoji_labels.php" target="_blank" rel="nofollow noreferrer">apply_pagination_emoji_labels.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>One-time/maintenance: bulk replace legacy list pagination plain <code>Previous</code>/<code>Next</code> visible labels (and old mixed title attributes) with emoji-only visible text (<code>◀️</code>/<code>▶️</code>) and word-only <code>title</code> (<code>Previous page</code>/<code>Next page</code>). <strong>Default = dry-run</strong>; writes with CLI <code>--apply</code> or browser <code>?apply=1</code> (Admin). Lists changed files.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="apply_pagination_first_last.php" target="_blank" rel="nofollow noreferrer">apply_pagination_first_last.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Add **⏮️** first-page and **⏭️** last-page <code>btn-sm</code> anchors beside existing **◀️** / **▶️** list pagination (word-only <code>title</code> attributes). Covers standard <code>?search=&amp;sort=&amp;dir=&amp;page=</code> links plus bespoke builders (<code>pwd_build_list_url</code>, <code>sa_build_query</code>, <code>itm_audit_logs_build_query</code>, tickets <code>show_archived</code>, catalogs <code>$catalogNewProductsQuery</code>, IPAM focused list suffix, ops_report search hits, emails send logs). <strong>Default = dry-run</strong>; writes with CLI <code>--apply</code> or browser <code>?apply=1</code> (Admin). Skips files that already include both first and last controls.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="ui_configuration">
                    <td><a href="check_pagination_emoji.php" target="_blank" rel="nofollow noreferrer">check_pagination_emoji.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">ui_configuration</span></span></td>
                    <td>Static audit: list pagination emoji-only visible labels (<code>⏮️</code>/<code>◀️</code>/<code>▶️</code>/<code>⏭️</code>) and word-only <code>title</code> attributes (<code>First page</code> … <code>Last page</code>). Scans module <code>index.php</code> (thin-router merge), <code>list_all.php</code>/<code>view.php</code>/<code>delete.php</code>, IPAM/rack <code>includes/partials/render.php</code>, and <code>tabs/*.php</code>. Uses <code>itm_check_pagination_nav_titles()</code>.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="verify_source_utf8_mojibake.php" target="_blank" rel="nofollow noreferrer">verify_source_utf8_mojibake.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Static audit: tracked <code>modules/</code>, <code>includes/</code>, <code>scripts/</code>, <code>js/</code>, <code>css/</code>, <code>config/</code> source must be valid UTF-8 without mojibake literals (corrupted emoji, accents, or punctuation). Optional scope: <code>?path=modules/patches_updates</code> / <code>--path=…</code>.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="fix_source_utf8_mojibake.php" target="_blank" rel="nofollow noreferrer">fix_source_utf8_mojibake.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Repair known mojibake literals in tracked source. Browser <strong>selection mode</strong> (Select to Fix → check files → Preview / Fix Selected; Admin for apply). CLI: <code>--path=</code>, <code>--files=</code>, <code>--apply</code>. Default dry-run.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="check_codacy_xss_echo.php" target="_blank" rel="nofollow noreferrer">check_codacy_xss_echo.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Static audit: Codacy-risky user-input echo patterns in <code>modules/**/*.php</code> — short-echo <code>&lt;?= sanitize($search…)</code> in <code>value</code>/<code>href</code>/<code>&lt;strong&gt;</code>, and <code>echo sanitize(http_build_query(…))</code> inside <code>href</code>.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="check_manual_sql_string.php" target="_blank" rel="nofollow noreferrer">check_manual_sql_string.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Static audit: manually-constructed SQL strings in <code>modules/**/*.php</code> — SQL keyword literals concatenated/interpolated with variables, or <code>mysqli_query</code>/<code>itm_run_query</code> with user input in the SQL string. Excludes URL <code>http_build_query()</code> / <code>.php?</code> href patterns.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="check_not_operator.php" target="_blank" rel="nofollow noreferrer">check_not_operator.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Static audit <strong>[Warning]</strong>: Codacy-style unary <code>!</code> on <code>$variables</code> in <code>modules/</code>, <code>includes/</code>, and <code>config/</code> — flags <code>if (!$ok)</code> patterns for manual review; excludes <code>!is_*()</code>, <code>!function_exists()</code>, and <code>!==</code>. Always informational (exit <code>0</code>) — do not bulk-replace with <code>=== false</code>.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="check_ui_action_emoji.php" target="_blank" rel="nofollow noreferrer">check_ui_action_emoji.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Static audit: NO MIXED gate on <code>&lt;a&gt;</code>, <code>&lt;button&gt;</code>, <code>&lt;input&gt;</code>, <code>&lt;h1&gt;</code>–<code>&lt;h3&gt;</code> — seven emoji+word regex families, known compound literals, plain-text action outliers, header <code>intentRules</code> drift.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="check_script_browser_nav_duplicate.php" target="_blank" rel="nofollow noreferrer">check_script_browser_nav_duplicate.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Static audit: <code>scripts/*.php</code> must not stack two <strong>← Scripts index</strong> links in one browser response — <code>itm_script_output_begin()</code> already calls <code>itm_script_browser_nav_echo()</code>; custom HTML must not call nav again after <code>close_pre</code> or in the same <code>if (!$isCli)</code> block.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="employees">
                    <td><a href="check_fk_label_search_coverage.php" target="_blank" rel="nofollow noreferrer">check_fk_label_search_coverage.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">employees</span></span></td>
                    <td>Static audit: every module with server-side list search must match visible FK/label columns (shared CRUD helper, scalar column helper, EXISTS/JOIN label LIKE, employee JOIN/CONCAT, or scalar-only fields). No per-module allowlist.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="check_display_field_columns_search.php" target="_blank" rel="nofollow noreferrer">check_display_field_columns_search.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Static audit: every <code>modules/*/index.php</code> that uses <code>foreach ($displayFieldColumns …)</code> must assign <code>$displayFieldColumns</code> first.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="check_env_vars_in_use.php" target="_blank" rel="nofollow noreferrer">check_env_vars_in_use.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Static audit: scans PHP, Python, and shell sources for <code>getenv()</code>, <code>$_ENV</code>, <code>os.environ</code>, and <code>${VAR}</code> reads; compares against <code>.env.example</code>. Reports matched keys, example-only placeholders, and undocumented app/tooling/OS vars.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="catalogs">
                    <td><a href="check_script_catalog_tags.php" target="_blank" rel="nofollow noreferrer">check_script_catalog_tags.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">catalogs</span></span></td>
                    <td>Static gate: every <code>scripts/scripts.php</code> catalog row has correct <code>data-tags</code> and tag badge markup matching the computed scan in <code>scripts/lib/itm_script_catalog_tags.php</code>.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="catalogs">
                    <td><a href="verify_scripts_catalog_filter.php" target="_blank" rel="nofollow noreferrer">verify_scripts_catalog_filter.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">catalogs</span></span></td>
                    <td>Scrape <code>scripts/scripts.php</code> and verify catalog filter contract: all rows have <code>data-tags</code>, five-column markup, CSS column mapping, and simulated <code>*.json</code> / <code>*.txt</code> / <code>*.md</code> search plus Info / <code>*.md</code> chip filters.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Python">
                    <td>verify_scripts_catalog_filter_screenshot.py</td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli-only">CLI-only</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="python">Python</span></span></td>
                    <td>Runs <code>verify_scripts_catalog_filter.php</code>, then Playwright captures PNGs under <code>qa-reports/scripts_catalog_filter/</code> and asserts visible row counts for Info / <code>*.json</code> / <code>*.txt</code> / <code>*.md</code> filters on <code>scripts/scripts.php</code>.</td>
                    <td><code>python scripts/verify_scripts_catalog_filter_screenshot.py</code>. Env: <code>ITM_SCREENSHOT_BASE_URL</code>, <code>ITM_PHP_BIN</code>, <code>ITM_PYTHON_BIN</code> (Playwright + local Apache).</td>
                </tr>
                <tr data-tags="employees notes">
                    <td><a href="check_script_disposable_employees.php" target="_blank" rel="nofollow noreferrer">check_script_disposable_employees.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">employees</span><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">notes</span></span></td>
                    <td>Static audit: repro/verify scripts must not hardcode seed user id <code>1</code> for <code>employees</code> / <code>reset_token</code> / notes mutations — use <code>scripts/lib/itm_script_test_employee.php</code>.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="check_sql_injection_coverage.php" target="_blank" rel="nofollow noreferrer">check_sql_injection_coverage.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Static scan: direct queries near user input without obvious binding/sanitization.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="check_stale_user_id_sql.php" target="_blank" rel="nofollow noreferrer">check_stale_user_id_sql.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Static audit: fail on stale <code>user_id</code> column SQL or legacy <code>users</code> table references in <code>modules/</code>, <code>includes/</code>, and <code>config/</code> after the employees merge.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="check_stale_user_terminology.php" target="_blank" rel="nofollow noreferrer">check_stale_user_terminology.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Static audit: fail on stale <code>Users module</code> / <code>Users Management</code> prose, <code>employee_companies</code> + <code>user_id</code> helper references, session <code>role_name</code> admin checks in <code>modules/</code>, <code>cr_username_for_user_id</code>, and <code>'user_id'</code> inside CRUD <code>$hidden</code> arrays.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="idf_ports">
                    <td><a href="check_multi_tenant_leaks.php" target="_blank" rel="nofollow noreferrer">check_multi_tenant_leaks.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">idf_ports</span></span></td>
                    <td>Static scan: SQL queries and INSERTs on scoped tables missing <code>company_id</code> filters, and improper UI exposure of company identifiers.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="check_index_table_compliance.php" target="_blank" rel="nofollow noreferrer">check_index_table_compliance.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>
                        Index list tables: <code>data-itm-db-import-endpoint</code>, <code>data-itm-actions-origin</code>, POST CSRF, form <code>csrf_token</code>.
                        Skips import when <code>data-itm-no-import-excel="1"</code>; skips Actions markers when the index has no Actions column.
                        Baseline: <code>scripts/data/index_table_compliance_baseline.txt</code>. Skips bespoke modules and <code>rack_planner</code>.
                    </td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="apply_bulk_delete_cancel_ux.php" target="_blank" rel="nofollow noreferrer">apply_bulk_delete_cancel_ux.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>One-time maintenance: strip duplicated inline bulk-delete <code>selectionMode</code> scripts from module PHP files after <code>js/bulk-delete-selection.js</code> (shared Cancel button) ships via <code>includes/header.php</code>. <strong>Default = dry-run</strong>; writes with CLI <code>--apply</code> or browser <code>?apply=1</code> (Admin). Lists scanned / changed / skipped module files.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="apply_bulk_actions_records_per_page_gate.php" target="_blank" rel="nofollow noreferrer">apply_bulk_actions_records_per_page_gate.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Add <code>records_per_page</code> visibility gate for bulk delete / clear table on module <code>index.php</code> files (<code>$showBulkActions = ($totalRows &gt;= $perPage)</code>). <strong>Default = dry-run</strong>; writes with CLI <code>--apply</code> or browser <code>?apply=1</code> (Admin). Lists scanned / changed / skipped module files.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="module_clean_tests_qa_runner.php" target="_blank" rel="nofollow noreferrer">module_clean_tests_qa_runner.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>Runs the same QA artifact cleanup used by <code>module_browser_qa_runner.php</code>: equipment scaffold folders, legacy template stub modules, MBQA/QA-IMPORT DB rows, sidebar leftovers, then re-ensures canonical <code>modules/is_*</code> facades. Browser <strong>Run QA</strong> already triggers this cleanup silently at start and end; use this page for manual cleanup runs. Includes quick links: <strong>Clean Tests · Open markdown file · Download XLSX · Rebuild report · Re-Run Test · Run QA runner</strong>.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="module_browser_qa_runner.php" target="_blank" rel="nofollow noreferrer">module_browser_qa_runner.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>
                        Full-module QA across all <code>modules/*/index.php</code> entries for companies 1–5: login, then per module <strong>mysql</strong> (<code>db/</code> split bundle INSERT row count), <strong>rotate error_log.txt</strong>, list/<strong>clear</strong>/sample_data, <strong>add</strong>, <strong>bulk_delete</strong>, CRUD/export, <strong>clear_table</strong>, second <strong>clear</strong>, import/<strong>single_delete</strong>, end sample restore + <strong>error_log</strong>. <strong>Mutates DB:</strong> seeds sample data and inserts/imports test rows as part of the flow. Tier lists: <code>$bespokeSmoke</code> / <code>$skipClear</code> in <code>scripts/lib/mbqa_runner_tiers.php</code>. Browser <strong>Run QA</strong> silently runs <code>module_clean_tests_qa_runner.php</code> at start and end. Preflight validation, auto-detected Base URL on Laragon, structured <strong>import_db</strong> JSON parsing, stale AJAX cleanup. Optional browser-only <strong>UI click smoke</strong> (one module + one company) appends <code>bulk_cancel_click</code>, <code>pagination_click</code>, <code>export_xlsx_click</code>, <code>import_excel_click</code>. Writes timestamped <code>qa-reports/module-browser-qa-YYYY-MM-DD-HH-MM-SS.json</code> and matching <code>.xlsx</code> each run.
                    </td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="module_browser_qa_build_report.php" target="_blank" rel="nofollow noreferrer">module_browser_qa_build_report.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>Builds markdown summary from a timestamped runner JSON: tier reference (<code>$bespokeSmoke</code>, <code>$skipClear</code>), configured step exceptions, per-module results, failure/skip indexes. Re-Run links preserve UI click smoke when set. Writes <code>qa-reports/module-browser-qa.md</code> (overwritten each build).</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="employees">
                    <td><a href="check_employees_clear_table_transaction.php" target="_blank" rel="nofollow noreferrer">check_employees_clear_table_transaction.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">employees</span></span></td>
                    <td>Static guard: employees <code>clear_table</code> uses soft-delete via <code>employees_delete_record()</code> (detach + transaction + <code>itm_crud_build_soft_delete_sql</code>).</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="equipment">
                    <td><a href="check_equipment_clear_table_delete.php" target="_blank" rel="nofollow noreferrer">check_equipment_clear_table_delete.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">equipment</span></span></td>
                    <td>Static guard: equipment <code>clear_table</code> uses soft-delete via <code>equipment_delete_record()</code> (transaction + <code>itm_crud_build_soft_delete_sql</code>).</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="ui_configuration">
                    <td><a href="check_ui_configuration_coverage.php" target="_blank" rel="nofollow noreferrer">check_ui_configuration_coverage.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">ui_configuration</span></span></td>
                    <td>UI configuration hooks: table actions, new button, export toolbar, back/save on forms.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="audit_logs">
                    <td><a href="check_audit_logs_coverage.php" target="_blank" rel="nofollow noreferrer">check_audit_logs_coverage.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">audit_logs</span></span></td>
                    <td>Audit trail for mutations: <code>itm_run_query</code>, <code>itm_log_audit</code>, bulk helpers, or <code>trg_{table}_audit_*</code> in <code>db/03_triggers.sql</code> (tables from <code>db/01_schema.sql</code>).</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="sql_injection_matrix_test.php" target="_blank" rel="nofollow noreferrer">sql_injection_matrix_test.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Fixed payload matrix against <code>lib/sql_injection_detector.php</code>.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="db_field_active.php" target="_blank" rel="nofollow noreferrer">db_field_active.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Identifies tables missing the mandatory <code>active</code> column and detects code mismatches where queries expect this field on tables that lack it.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
            </tbody>
        </table></div>
        <p class="scripts-muted" style="margin-top:12px;">
            Library (not run directly): <code>lib/sql_injection_detector.php</code>, <code>lib/equipment_type_modules.php</code> (canonical <code>modules/is_*</code> allowlist; safe removal of <code>*_itm_eqdct_*</code> / <code>*_itm_edct_*</code> test scaffolds only), <code>lib/script_cli_output.php</code> (wraps browser output in <code>&lt;pre&gt;</code> + shared nav), <code>lib/script_browser_nav.php</code>, <code>lib/utf8_file.php</code> (UTF-8 BOM file I/O), <code>lib/mbqa_import_helpers.php</code> (QA import resolution), <code>lib/mbqa_report_paths.php</code> (QA report naming), <code>lib/mbqa_step_display.php</code> (QA result display) (<strong>← Scripts index</strong>, relative module / table→module links).
        </p>
    </div>


    <div class="scripts-section scripts-catalog-section" id="admin-tools">
        <h2>Administrative Tools</h2>
        <div class="scripts-catalog-grid"><table class="scripts-catalog">
            <colgroup>
                <col class="scripts-col-script">
                <col class="scripts-col-access">
                <col class="scripts-col-tags">
                <col class="scripts-col-what">
                <col class="scripts-col-how">
            </colgroup>
            <thead>
                <tr>
                    <th>Script</th>
                    <th class="scripts-access-col">Access</th>
                    <th>Tags</th>
                    <th>What it does</th>
                    <th>How to use</th>
                </tr>
            </thead>
            <tbody>
                <tr data-tags="modules_registry">
                    <td><a href="sync_modules_registry.php" target="_blank" rel="nofollow noreferrer">sync_modules_registry.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">modules_registry</span></span></td>
                    <td>Upserts <code>modules_registry</code> rows from filesystem module folders and sidebar-excluded slugs; seeds <code>company_module_access</code> for new registry rows. Sidebar discovery also auto-registers new tables/folders on page load — use this script for bulk backfill after deploy or when icons/labels need catalog sync.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="verify_ops_report.php" target="_blank" rel="nofollow noreferrer">verify_ops_report.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>Regression for <code>modules/ops_report/</code>: D-2 edit lock (today/yesterday editable; D-2+ locked unless admin), daily <code>ops_report</code> CRUD, child-row cascade delete, cross-date hit line format, audit triggers on all <code>ops_report*</code> tables, and <code>modules_registry</code> slug <code>ops_report</code>.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="verify_finance_sample_data_seed.php" target="_blank" rel="nofollow noreferrer">verify_finance_sample_data_seed.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>Add sample data regression for finance modules on a disposable tenant (AP/AR headers, lookups, budgets, <code>currency_code</code> length, sidebar <code>finance</code> section map).</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="check_crud_sample_data_live_row_gate.php" target="_blank" rel="nofollow noreferrer">check_crud_sample_data_live_row_gate.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Static gate: finance <code>index.php</code> empty check uses <code>itm_seed_tenant_row_count()</code>. Templates stay in <code>db/02_data_sample.sql</code>.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="apply_crud_sample_data_live_row_gate.php" target="_blank" rel="nofollow noreferrer">apply_crud_sample_data_live_row_gate.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span><span class="scripts-badge scripts-badge-browser">Browser</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Optional PHP gate maintenance only (not <code>db/</code>): legacy <code>COUNT(*)</code> → <code>itm_seed_tenant_row_count()</code> before <code>itm_seed_table_from_database_sql()</code>.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="verify_expenses_ap.php" target="_blank" rel="nofollow noreferrer">verify_expenses_ap.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>Regression for extended <code>expenses</code> AP fields, <code>includes/itm_expenses_ap.php</code>, and Posted/Paid budget-actual filter semantics.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="verify_bills.php" target="_blank" rel="nofollow noreferrer">verify_bills.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>Seed bill header/lines rollup, supplier FK, post-to-expenses (`bill_id`, `invoice_number`), duplicate-post guard.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="expenses invoices">
                    <td><a href="verify_invoices.php" target="_blank" rel="nofollow noreferrer">verify_invoices.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">expenses</span><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">invoices</span></span></td>
                    <td>Seed invoice header/lines rollup, post-to-expenses (`invoice_id`, `invoice_number`), duplicate-post guard.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="finance_attachments">
                    <td><a href="verify_finance_attachments.php" target="_blank" rel="nofollow noreferrer">verify_finance_attachments.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">finance_attachments</span></span></td>
                    <td>Finance module multi-file attachments (storage path, allowed types, table presence).</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="bills finance_payment_allocations">
                    <td><a href="verify_finance_payment_allocations.php" target="_blank" rel="nofollow noreferrer">verify_finance_payment_allocations.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">bills</span><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">finance_payment_allocations</span></span></td>
                    <td>Payment allocation insert and <code>amount_due</code> rollup on seed bill.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="expenses expense_recurrence">
                    <td><a href="verify_expense_recurrence.php" target="_blank" rel="nofollow noreferrer">verify_expense_recurrence.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">expenses</span><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">expense_recurrence</span></span></td>
                    <td>Recurrence lookup seeds and <code>itm_expense_recurrence_advance_date</code>.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="customers invoices">
                    <td><a href="verify_customers.php" target="_blank" rel="nofollow noreferrer">verify_customers.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">customers</span><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">invoices</span></span></td>
                    <td>Seed customer and invoice <code>customer_id</code> link.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="integration_accounts">
                    <td><a href="verify_integration_accounts.php" target="_blank" rel="nofollow noreferrer">verify_integration_accounts.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">integration_accounts</span></span></td>
                    <td>Light insert probe for integration_accounts.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="bank_accounts">
                    <td><a href="verify_bank_accounts.php" target="_blank" rel="nofollow noreferrer">verify_bank_accounts.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">bank_accounts</span></span></td>
                    <td>Light insert probe for bank_accounts.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="ops_report">
                    <td><a href="verify_ops_report_sample_data.php" target="_blank" rel="nofollow noreferrer">verify_ops_report_sample_data.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">ops_report</span></span></td>
                    <td>
                        MySQL regression for <strong>Add sample data</strong> on all seven <code>ops_report_*</code> child modules when the tenant has no parent <code>ops_report</code> rows. <strong>Mutates DB:</strong> deletes and re-seeds company <code>4</code> ops report tables during the test.
                    </td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="ops_report ops_report_guest_experience">
                    <td><a href="seed_ops_report_search_demo.php" target="_blank" rel="nofollow noreferrer">seed_ops_report_search_demo.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">ops_report</span><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">ops_report_guest_experience</span></span></td>
                    <td>Seeds company 1 Ops Report demo rows on two past dates with keyword <code>DemoManager</code> (header + guest experience child) for manual QA and screenshot capture; prints expected cross-date hit lines.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Python">
                    <td>verify_ops_report_search_screenshot.py</td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli-only">CLI-only</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="python">Python</span></span></td>
                    <td>Seeds demo data, bypass login, captures five human-flow Ops Report search PNGs under <code>qa-reports/ops_report_search/</code> (all-dates hits, section filter, sort, this-day navigation, search bar). Requires Playwright + local Apache.</td>
                    <td><code>python scripts/verify_ops_report_search_screenshot.py</code>. Env: <code>ITM_SCREENSHOT_BASE_URL</code>, <code>ITM_PHP_BIN</code>, <code>ITM_OPS_SEARCH_DEMO_KEYWORD</code>.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="verify_bookmarks_import.php" target="_blank" rel="nofollow noreferrer">verify_bookmarks_import.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>Regression for <code>modules/bookmarks/import.php</code> HTML folder paths: nested <code>L1/L2</code> placement, duplicate URL skips without orphan empty folders, legacy <code>name_hash</code> folder lookup, CSV folder target, and vault-gated private import. Uses disposable script employee + <code>scripts/data/bookmarks_import_sample.html</code>.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="verify_bookmarks_folder_move.php" target="_blank" rel="nofollow noreferrer">verify_bookmarks_folder_move.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>Regression for bookmarks folder drag/move merge: duplicate same-named siblings can reparent without merge or merge bookmarks/subfolders into the existing folder via <code>bkm_move_folder()</code> / <code>bkm_merge_folder_into()</code>.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="booking_rooms_types hotel_bookings">
                    <td><a href="seed_hotel_booking_sample_photos.php" target="_blank" rel="nofollow noreferrer">seed_hotel_booking_sample_photos.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">booking_rooms_types</span><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">hotel_bookings</span></span></td>
                    <td>Copy TechCorp Retreat demo hotel photos to <code>booking/images/{hotel_id}/hotel_photos/</code> and room-type samples to <code>booking/images/{hotel_id}/room_types_photos/</code>; upsert photo rows (<code>--apply</code> writes).</td>
                    <td class="scripts-catalog-how-stub"><code>php scripts/seed_hotel_booking_sample_photos.php --apply</code></td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="run_hotel_booking_distribution_webhook_queue.php" target="_blank" rel="nofollow noreferrer">run_hotel_booking_distribution_webhook_queue.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">hotel_booking_distribution_webhook_queue</span></span></td>
                    <td>Retry pending/failed outbound distribution webhook deliveries with exponential backoff; rows exceeding <code>max_attempts</code> move to <code>dead</code>.</td>
                    <td class="scripts-catalog-how-stub"><code>php scripts/run_hotel_booking_distribution_webhook_queue.php</code></td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="verify_hotel_booking_distribution_http.php" target="_blank" rel="nofollow noreferrer">verify_hotel_booking_distribution_http.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">hotel_booking_distribution_channels</span></span></td>
                    <td>HTTP regression for distribution API: probe auth (401/200), availability GET via curl with disposable channel API key. Browser delegates to CLI subprocess (avoids gateway timeout).</td>
                    <td class="scripts-catalog-how-stub"><code>php scripts/verify_hotel_booking_distribution_http.php</code></td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="verify_hotel_booking_distribution_opentravel_coverage.php" target="_blank" rel="nofollow noreferrer">verify_hotel_booking_distribution_opentravel_coverage.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">hotel_booking_distribution_channels</span></span></td>
                    <td>OpenTravel OTA message coverage: AvailRQ, ResNotifRQ, AvailNotifRQ (ari_push), PingRQ parse + RS encode.</td>
                    <td class="scripts-catalog-how-stub"><code>php scripts/verify_hotel_booking_distribution_opentravel_coverage.php</code></td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="verify_hotel_booking_distribution_booking_com_cert.php" target="_blank" rel="nofollow noreferrer">verify_hotel_booking_distribution_booking_com_cert.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">hotel_booking_distribution_channels</span></span></td>
                    <td>Booking.com Connectivity offline certification checklist (ACK/NACK, notify normalize, rates payload; no live API).</td>
                    <td class="scripts-catalog-how-stub"><code>php scripts/verify_hotel_booking_distribution_booking_com_cert.php</code></td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="report_hotel_booking_distribution_webhook_ops.php" target="_blank" rel="nofollow noreferrer">report_hotel_booking_distribution_webhook_ops.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">hotel_booking_distribution_webhook_queue</span></span></td>
                    <td>Ops dead-letter report for outbound webhook queue; exit <code>1</code> when <code>dead</code> rows exist unless <code>--allow-dead</code>.</td>
                    <td class="scripts-catalog-how-stub"><code>php scripts/report_hotel_booking_distribution_webhook_ops.php</code></td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="run_hotel_booking_distribution_ari_sync.php" target="_blank" rel="nofollow noreferrer">run_hotel_booking_distribution_ari_sync.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">hotel_booking_distribution_channels</span></span></td>
                    <td>POST outbound ARI snapshots to every active distribution channel <code>webhook_url</code> (OpenTravel XML / Booking.com / OHIP JSON per <code>standard</code>).</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="verify_hotel_booking_distribution.php" target="_blank" rel="nofollow noreferrer">verify_hotel_booking_distribution.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">hotel_booking_distribution_channels</span></span></td>
                    <td>Regression for partner distribution API: <code>hotel_booking_distribution_*</code> tables (phase 3 queue/restrictions/rate-plan mappings), API key hash/lookup, signature/checksum/ACK helpers, availability builder, and <code>modules/hotel_booking_api/api.php</code> auth bypass.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="verify_hotel_booking.php" target="_blank" rel="nofollow noreferrer">verify_hotel_booking.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>Regression for hospitality bundle: core hotel booking tables, <code>itm_hotel_booking_resolve_segment()</code>, company 1 <code>PENDING</code> future status seed, and subprocess render probe for all 13 Hospitality sidebar modules.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="hotel_bookings">
                    <td><a href="check_hotel_bookings_rate_plan_form.php" target="_blank" rel="nofollow noreferrer">check_hotel_bookings_rate_plan_form.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">hotel_bookings</span></span></td>
                    <td>Static gate: <code>modules/hotel_bookings</code> portal rate plan <code>__add_new__</code> option, <code>hb_booking_end_form_page()</code> modal placement outside <code>.content</code>, and <code>js/hotel-bookings-rate-plan-select.js</code> quick-add handler.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="check_hospitality_date_format.php" target="_blank" rel="nofollow noreferrer">check_hospitality_date_format.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Hospitality subset of <code>check_date_format.php</code> — <code>modules/hotel*</code> + <code>booking/</code> stay dates (<code>d/M/Y</code>).</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="check_date_format.php" target="_blank" rel="nofollow noreferrer">check_date_format.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Project-wide date format gate: UK <code>dd/mm/yyyy</code>, hospitality <code>d/M/Y</code> (<code>31/Aug/2026</code>), audit stamps, hospitality static scan, scaffold cell-hook info.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="appointments modules_registry">
                    <td><a href="verify_appointment.php" target="_blank" rel="nofollow noreferrer">verify_appointment.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">appointments</span><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">modules_registry</span></span></td>
                    <td>Regression for <code>modules/appointment/</code>: audit triggers on five <code>appointment_*</code> tables (including <code>appointment_type</code>), <code>booking_lock</code> unique index, company 1 settings/reasons/types seeds, weekly slot builder, and <code>modules_registry</code> slug <code>appointment</code>.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="verify_command_palette_search.php" target="_blank" rel="nofollow noreferrer">verify_command_palette_search.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>Regression for global command palette: <code>includes/itm_command_palette_search.php</code>, <code>includes/itm_search_index.php</code>, <code>modules/search/api.php</code>, header <code>Ctrl+K</code> wiring, RBAC gates (employees admin-only), <code>search_index</code> FULLTEXT sync, and backfill/remove probes. Sidebar slug coverage: <code>verify_command_palette_sidebar_slugs.php</code>.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="verify_command_palette_sidebar_slugs.php" target="_blank" rel="nofollow noreferrer">verify_command_palette_sidebar_slugs.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>Regression that every module slug visible in the live Admin sidebar (company 1) is findable via command-palette module navigation (<code>itm_command_palette_sidebar_visible_module_slugs()</code> + unified <strong>Modules</strong> group). Shared lib: <code>scripts/lib/itm_command_palette_sidebar_verify.php</code>.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="apply_search_index_backfill.php" target="_blank" rel="nofollow noreferrer">apply_search_index_backfill.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>Backfill <code>search_index</code> rows for command-palette phase 2 (employees, equipment, tickets, ip_addresses, catalogs). Dry-run default; <code>--apply</code> / <code>?apply=1</code> (Admin). Optional <code>--company=</code> and <code>--module=</code>.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="verify_chatbot.php" target="_blank" rel="nofollow noreferrer">verify_chatbot.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>Regression for IT Support Chatbot + <code>modules/knowledge_base/chat_api.php</code>: rate limit + CSRF guards, <code>escapeHtml()</code> before message <code>innerHTML</code>, <code>enable_chatbot</code> asset gating, audit triggers, and tenant-scoped <code>knowledge_base</code> search.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="verify_request_password.php" target="_blank" rel="nofollow noreferrer">verify_request_password.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>Regression for <code>modules/request_password/</code>: RBAC guard, HMAC approval links, list import/Actions markers, creator-only soft-delete guard, audit triggers, and disposable-row delete-authorization check.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="modules_registry">
                    <td><a href="verify_reports_hub.php" target="_blank" rel="nofollow noreferrer">verify_reports_hub.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">modules_registry</span></span></td>
                    <td>Regression for <code>modules/reports/</code> Reports Hub: exercises every <code>api/helpers.php</code> chart payload, Hotel Operations MTD metrics (<code>ops_report</code>, <code>ops_report_fb_outlet</code>), budget vs actual / YoY totals, <code>modules_registry</code> slug <code>reports</code>, and core Chart.js canvas ids in <code>index.php</code>. Expects <code>db/</code> split bundle Reports Hub sample seeds.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="benchmark_sidebar_module_access.php" target="_blank" rel="nofollow noreferrer">benchmark_sidebar_module_access.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>Read-only benchmark for sidebar generation: measures MySQL <code>Questions</code> delta for the live path (<code>itm_sidebar_structure()</code> + <code>has_module_access()</code> filter) vs an uncached legacy N+1 simulation (per-slug registry + admin + CMA queries and per-slug registry ensure). Reports median query count, timing, and reduction percentage. Requires prefetch cache in <code>includes/itm_company_module_access.php</code>.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="verify_company_module_access.php" target="_blank" rel="nofollow noreferrer">verify_company_module_access.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>Regression for registry coverage, opt-out deny, sidebar-excluded slugs in the admin matrix, company icon overrides, and disposable sidebar discovery probes: registry-only (no <code>modules/{slug}/</code>), new MySQL table (auto-scaffold), folder-only (<code>index.php</code>), registry + folder (single entry), neither (absent + denied). Uses <code>itm_sidebar_discovery_probe_cleanup()</code> for probe teardown.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="verify_roles_permissions.php" target="_blank" rel="nofollow noreferrer">verify_roles_permissions.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>Regression for <code>modules/roles_permissions/</code>: registry row, module entry + matrix JS, RBAC-exempt slug, Admin <code>ALL</code> wildcard with six flags, seeded roles and <code>role_hierarchy</code> for company 1, <code>can_import</code>/<code>can_export</code> columns, role sidebar <code>active_count</code> (role_id + HR Active).</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="employees employee_roles">
                    <td><a href="verify_demo_module_restrictions.php" target="_blank" rel="nofollow noreferrer">verify_demo_module_restrictions.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">employees</span><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">employee_roles</span></span></td>
                    <td>Regression for demo users <code>demo1</code>–<code>demo5</code> (single-module access + Settings/Dashboard) and seed admins <code>Admin</code>/<code>Admin2</code>–<code>Admin5</code>: <code>password_verify</code>, <code>itm_is_admin()</code>, <code>has_module_access</code>, RBAC <code>can_view</code>, subprocess module <code>index.php</code> probes. Contract: <code>lib/itm_demo_module_restrictions_contract.php</code>.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="verify_employee_contact_email.php" target="_blank" rel="nofollow noreferrer">verify_employee_contact_email.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>Regression: at least one of <code>work_email</code> / <code>personal_email</code> on employee create/edit/import (<code>includes/itm_employee_contact_email.php</code>); helper checks, create/edit wiring, <code>fast_create_acc</code> both email fields, disposable employee create.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="fast_create_acc.php" target="_blank" rel="nofollow noreferrer">fast_create_acc.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>Admin fast-create UI: browser at <code>scripts/fast_create_acc.php</code>, <code>scripts/fast_create_acc_browser.php</code> (alias), or <code>modules/employees/fast_create_acc.php</code> (Employees 🚀). Shared markup: <code>modules/employees/fast_create_acc_browser.php</code> (include). CLI <code>--seed-demo-bundle</code>: <code>lib/itm_demo_module_users_seed.php</code>.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="employees">
                    <td><a href="verify_dashboard_active_employees.php" target="_blank" rel="nofollow noreferrer">verify_dashboard_active_employees.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">employees</span></span></td>
                    <td>Regression for <code>admin.php</code> row-2 <strong>Active</strong> and <strong>On Leave</strong> stats: helper call-sites, no leftover join-predicate SQL, soft-delete-aware counts (optional <code>ITM_TEST_COMPANY_ID</code>); employee <code>dashboard.php</code> must not duplicate company counts.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="employees">
                    <td><a href="verify_dashboard_online_employees.php" target="_blank" rel="nofollow noreferrer">verify_dashboard_online_employees.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">employees</span></span></td>
                    <td>Regression for <code>admin.php</code> row-2 <strong>Online now</strong> stat: session presence helper, <code>config/config.php</code> touch hook, count after touch.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="employees">
                    <td><a href="verify_employee_dashboard.php" target="_blank" rel="nofollow noreferrer">verify_employee_dashboard.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">employees</span></span></td>
                    <td>Regression for employee <code>dashboard.php</code>: hero + grouped stat cards, <code>includes/itm_employee_dashboard.php</code> loader, no company switcher or employment-status counts.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="verify_admin_page_gate.php" target="_blank" rel="nofollow noreferrer">verify_admin_page_gate.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Regression for <code>admin.php</code> admin-only gate: <code>itm_is_admin()</code> and redirect to <code>dashboard.php</code> for non-admins.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="verify_settings_admin_buttons.php" target="_blank" rel="nofollow noreferrer">verify_settings_admin_buttons.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Regression for Settings: admin toolbar (<code>ADMIN</code> → <code>admin.php</code>, <code>SCRIPTS</code> → <code>scripts/scripts.php</code>), <strong>All roles</strong> chatbot block, <strong>System (Admin Role only)</strong> flags, and non-admin save preservation.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="apply_new_company_module_share_capable_seed.php" target="_blank" rel="nofollow noreferrer">apply_new_company_module_share_capable_seed.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>Backfills <code>company_module_share</code> for share-capable slugs only (same as <code>db/migrations/company_module_share_capable_seed.sql</code>): DELETE non-capable rows, <code>INSERT IGNORE</code> <code>enabled=1</code> for active companies × capable registry modules. Slugs match <code>itm_qr_share_capable_module_slugs()</code>. Dry-run default.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="seed_company_module_access.php" target="_blank" rel="nofollow noreferrer">seed_company_module_access.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>Backfills <code>company_module_access</code> rows as <code>enabled=1</code> for active companies (all modules or one <code>company_id</code>). Calls <code>sync_modules_registry.php</code> first when seeding a single company.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><code><a href="bypass_login.php" target="_blank" rel="nofollow noreferrer">bypass_login.php</a></code></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli-only">CLI-only</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>Bypasses the login screen by manually establishing an authenticated Admin session in the database and returning the session ID. Sets up Admin user, TechCorp Global company, and Vault master key.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><code><a href="bypass_v2.php" target="_blank" rel="nofollow noreferrer">bypass_v2.php</a></code></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli-only">CLI-only</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>CLI-only Admin session hijack for dev/Playwright (non-admin users rejected via <code>itm_is_admin()</code>). Sets up Admin user, TechCorp Global company, and Vault master key.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="sql_insert.php" target="_blank" rel="nofollow noreferrer">sql_insert.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Allows administrators to paste and execute raw SQL <code>INSERT</code> commands with optional Foreign Key check toggling. Maintains audit logging.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Python">
                    <td><code>take_screenshots_passwords.py</code></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli-only">CLI-only</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="python">Python</span></span></td>
                    <td>Automated UI screenshot utility. Authenticates as Admin and captures key states of Bookmarks and Passwords modules. Requires Playwright.</td>
                    <td><code>python3 scripts/take_screenshots_passwords.py</code></td>
                </tr>
                <tr data-tags="Python">
                    <td><code>take_screenshots_modules.py</code></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli-only">CLI-only</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="python">Python</span></span></td>
                    <td>Playwright screenshots for README module images. Default slugs: <code>todo</code>, <code>notes</code>, <code>roles_permissions</code>, <code>system_status</code>. Uses <code>bypass_login.php</code> + <code>sudo chown www-data</code> on the sess file; cookie domain follows the base URL hostname; waits for <code>#rp-permission-matrix</code> (Roles &amp; Permissions) and <code>#system-info-content</code> (System Status) before saving.</td>
                    <td><code>ITM_SCREENSHOT_ONLY=roles_permissions python3 scripts/take_screenshots_modules.py</code> · <code>ITM_SCREENSHOT_ONLY=system_status python3 scripts/take_screenshots_modules.py</code> · optional <code>ITM_SCREENSHOT_BASE_URL</code>, <code>ITM_SCREENSHOT_MODULES</code> (see <code>scripts/SCRIPTS.md</code>).</td>
                </tr>
                <tr data-tags="Python">
                    <td><code>take_screenshots_modules_all.py</code></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli-only">CLI-only</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="python">Python</span></span></td>
                    <td>Bulk UI screenshot utility for all modules. Requires Playwright.</td>
                    <td><code>python3 scripts/take_screenshots_modules_all.py</code></td>
                </tr>
                <tr data-tags="Python">
                    <td><code>test_notes_human.py</code></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli-only">CLI-only</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="python">Python</span></span></td>
                    <td>Playwright-based human-flow regression for Notes module.</td>
                    <td><code>python3 scripts/test_notes_human.py</code></td>
                </tr>
                <tr data-tags="Python">
                    <td><code>update_display.py</code></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli-only">CLI-only</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="python">Python</span></span></td>
                    <td>Display update utility.</td>
                    <td><code>python3 scripts/update_display.py</code></td>
                </tr>
                <tr data-tags="Python">
                    <td><code>verify_dnd.py</code></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli-only">CLI-only</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="python">Python</span></span></td>
                    <td>Verifies Drag and Drop functionality in UI.</td>
                    <td><code>python3 scripts/verify_dnd.py</code></td>
                </tr>
                <tr data-tags="Python">
                    <td>verify_todo.py</td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli-only">CLI-only</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="python">Python</span></span></td>
                    <td>Verifies Todo module functionality via Playwright.</td>
                    <td><code>python3 scripts/verify_todo.py</code></td>
                </tr>
                <tr data-tags="Python">
                    <td>verify_todo_categories.py</td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli-only">CLI-only</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="python">Python</span></span></td>
                    <td>Verifies Todo categories functionality via Playwright.</td>
                    <td><code>python3 scripts/verify_todo_categories.py</code></td>
                </tr>
                <tr data-tags="Python">
                    <td><code>verify_notes_ui.py</code></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli-only">CLI-only</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="python">Python</span></span></td>
                    <td>Verifies Notes module functionality via Playwright.</td>
                    <td><code>python3 scripts/verify_notes_ui.py</code></td>
                </tr>
                <tr data-tags="todo">
                    <td><a href="repro_bug.php" target="_blank" rel="nofollow noreferrer">repro_bug.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">todo</span></span></td>
                    <td>Reproduction and verification script for Todo module visibility and security bugs (multi-assignment and IDOR).</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="repro_vulnerabilities.php" target="_blank" rel="nofollow noreferrer">repro_vulnerabilities.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>Reproduction and verification script for Explorer RCE, User Privilege Escalation, and Unauthorized Access to Role Module Permissions. Subprocess spawn uses <code>escapeshellarg()</code>.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="repro_bac.php" target="_blank" rel="nofollow noreferrer">repro_bac.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>PoC for Broken Access Control in IDFs API.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="repro_bac_updated.php" target="_blank" rel="nofollow noreferrer">repro_bac_updated.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>Validation for IDFs API BAC fix.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="repro_rce.php" target="_blank" rel="nofollow noreferrer">repro_rce.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>PoC for RCE in Floor Designer via 'save_as_floor_plan' action.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="repro_rce_updated.php" target="_blank" rel="nofollow noreferrer">repro_rce_updated.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>Validation for Floor Designer RCE fix.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="repro_sqli.php" target="_blank" rel="nofollow noreferrer">repro_sqli.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>PoC for SQL Injection in Floor Designer via 'dir' parameter.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="repro_sqli_updated.php" target="_blank" rel="nofollow noreferrer">repro_sqli_updated.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>Validation for Floor Designer SQLi fix.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="repro_request_password_bypass.php" target="_blank" rel="nofollow noreferrer">repro_request_password_bypass.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>Reproduction script for request_password module bypass (static RBAC string check). Named regression: <a href="verify_request_password.php" target="_blank" rel="nofollow noreferrer">verify_request_password.php</a>.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="repro_select_options_rbac.php" target="_blank" rel="nofollow noreferrer">repro_select_options_rbac.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Reproduction script for Select Options RBAC bypass.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="repro_equip_issues.php" target="_blank" rel="nofollow noreferrer">repro_equip_issues.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>Reproduction script for Equipment edit issues. Mocks a POST request to equipment/edit.php.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="repro_esa_vulnerability.php" target="_blank" rel="nofollow noreferrer">repro_esa_vulnerability.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>PoC for Employee System Access Broken Access Control (non-admin access to edit page).</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="benchmark_stats_optimized.php" target="_blank" rel="nofollow noreferrer">benchmark_stats_optimized.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Benchmark for user-config.php stats gathering optimization. Compares performance of individual queries vs one consolidated query.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="benchmark_user_config.php" target="_blank" rel="nofollow noreferrer">benchmark_user_config.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Benchmark for user-config.php redundant query removal. Compares performance of individual queries vs consolidated query results.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
            </tbody>
        </table></div>
    </div>
    <div class="scripts-section scripts-catalog-section" id="system-status">
        <h2>System Status</h2>
        <div class="scripts-catalog-grid"><table class="scripts-catalog">
            <colgroup>
                <col class="scripts-col-script">
                <col class="scripts-col-access">
                <col class="scripts-col-tags">
                <col class="scripts-col-what">
                <col class="scripts-col-how">
            </colgroup>
            <thead>
                <tr>
                    <th>Script</th>
                    <th class="scripts-access-col">Access</th>
                    <th>Tags</th>
                    <th>What it does</th>
                    <th>How to use</th>
                </tr>
            </thead>
            <tbody>
                <tr data-tags="modules_registry system_status">
                    <td><a href="verify_system_status.php" target="_blank" rel="nofollow noreferrer">verify_system_status.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">modules_registry</span><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">system_status</span></span></td>
                    <td>Regression for <code>modules/system_status/</code>: file layout, registry row, native API payloads, storage tree + active DB table reports, <code>information_schema</code> query; Windows also checks <code>is_readable()</code> on each <code>includes/*.ps1</code> and runs <code>test_*.php</code> PowerShell wrappers.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="system_status">
                    <td><a href="system_status_api.php" target="_blank" rel="nofollow noreferrer">system_status_api.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">system_status</span></span></td>
					<td>Admin JSON API dispatcher (<code>?action=system_info</code>, etc.). PHP/MySQL actions always native; Windows hardware uses <code>includes/*.ps1</code> via allowlisted <code>itm_system_status_run_powershell_action()</code>. Invalid <code>action</code> → HTTP 400. Documented in <a href="api.php">api.php</a>.</td>
					<td class="scripts-catalog-how-stub">Open in browser for usage.</td>
				</tr>
                <tr data-tags="system_status">
                    <td><a href="system_status_phpinfo.php" target="_blank" rel="nofollow noreferrer">system_status_phpinfo.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">system_status</span></span></td>
					<td>Admin-only <code>phpinfo()</code> for the active Apache PHP runtime (linked from System Status → PHP Settings).</td><td class="scripts-catalog-how-stub">Open in browser for usage.</td>
				</tr>
                <tr data-tags="Codebase">
                    <td><a href="test_system_info.php" target="_blank" rel="nofollow noreferrer">test_system_info.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
					<td>Tests <code>includes/system_info.ps1</code> JSON output (Windows Laragon).</td>
					<td class="scripts-catalog-how-stub">Open in browser for usage.</td>
				</tr>
                <tr data-tags="Codebase">
                    <td><a href="test_cpu_usage.php" target="_blank" rel="nofollow noreferrer">test_cpu_usage.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
					<td>Tests cpu_usage.ps1 output.</td>
					<td class="scripts-catalog-how-stub">Open in browser for usage.</td>
				</tr>
                <tr data-tags="Codebase">
                    <td><a href="test_ram_usage.php" target="_blank" rel="nofollow noreferrer">test_ram_usage.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
					<td>Tests ram_usage.ps1 output.</td>
					<td class="scripts-catalog-how-stub">Open in browser for usage.</td>
				</tr>
                <tr data-tags="Codebase">
                    <td><a href="test_disk_usage.php" target="_blank" rel="nofollow noreferrer">test_disk_usage.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
					<td>Tests disk_usage.ps1 output.</td>
					<td class="scripts-catalog-how-stub">Open in browser for usage.</td>
				</tr>
                <tr data-tags="Codebase">
                    <td><a href="test_uptime.php" target="_blank" rel="nofollow noreferrer">test_uptime.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
					<td>Tests uptime.ps1 output.</td>
					<td class="scripts-catalog-how-stub">Open in browser for usage.</td>
				</tr>
                <tr data-tags="Codebase">
                    <td><a href="test_php_version.php" target="_blank" rel="nofollow noreferrer">test_php_version.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
					<td>Tests php_version.ps1 output.</td>
					<td class="scripts-catalog-how-stub">Open in browser for usage.</td>
				</tr>
                <tr data-tags="Codebase">
                    <td><a href="test_php_extensions.php" target="_blank" rel="nofollow noreferrer">test_php_extensions.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
					<td>Tests php_extensions.ps1 output.</td>
					<td class="scripts-catalog-how-stub">Open in browser for usage.</td>
				</tr>
                <tr data-tags="Codebase">
                    <td><a href="test_php_ini_values.php" target="_blank" rel="nofollow noreferrer">test_php_ini_values.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
					<td>Tests php_ini_values.ps1 output.</td>
					<td class="scripts-catalog-how-stub">Open in browser for usage.</td>
				</tr>
                <tr data-tags="Codebase">
                    <td><a href="test_mysql_status.php" target="_blank" rel="nofollow noreferrer">test_mysql_status.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
					<td>Tests mysql_status.ps1 output.</td>
					<td class="scripts-catalog-how-stub">Open in browser for usage.</td>
				</tr>
                <tr data-tags="Codebase">
                    <td><a href="test_mysql_version.php" target="_blank" rel="nofollow noreferrer">test_mysql_version.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
					<td>Tests mysql_version.ps1 output.</td>
					<td class="scripts-catalog-how-stub">Open in browser for usage.</td>
				</tr>
                <tr data-tags="Codebase">
                    <td><a href="test_mysql_databases.php" target="_blank" rel="nofollow noreferrer">test_mysql_databases.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
					<td>Tests mysql_databases.ps1 output.</td>
					<td class="scripts-catalog-how-stub">Open in browser for usage.</td>
				</tr>
                <tr data-tags="Codebase">
                    <td><a href="test_mysql_size.php" target="_blank" rel="nofollow noreferrer">test_mysql_size.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
					<td>Tests mysql_size.ps1 output.</td>
					<td class="scripts-catalog-how-stub">Open in browser for usage.</td>
				</tr>
            </tbody>
        </table></div>
    </div>
    <div class="scripts-section scripts-catalog-section" id="verification">
        <h2>Verification</h2>
        <div class="scripts-catalog-grid"><table class="scripts-catalog">
            <colgroup>
                <col class="scripts-col-script">
                <col class="scripts-col-access">
                <col class="scripts-col-tags">
                <col class="scripts-col-what">
                <col class="scripts-col-how">
            </colgroup>
            <thead>
                <tr>
                    <th>Script</th>
                    <th class="scripts-access-col">Access</th>
                    <th>Tags</th>
                    <th>What it does</th>
                    <th>How to use</th>
                </tr>
            </thead>
            <tbody>
                <tr data-tags="Mixed">
                    <td><a href="apitest_tier_free.php" target="_blank" rel="nofollow noreferrer">apitest_tier_free.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>Regression: disposable <code>ui_configuration</code> row on <strong>Free</strong> tier (no <code>api_key</code>) stays unlimited; in-process resolve via disposable test user (<code>itm_script_with_test_session_context()</code>); isolated HTTP probe session (<code>itm_script_publish_isolated_http_session()</code>). Prints keyless probe URL <code>scripts/api.php?rate_limit=1</code>. Browser: Admin login via <code>lib/itm_script_regression_entry.php</code>.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="apitest_tier_basic.php" target="_blank" rel="nofollow noreferrer">apitest_tier_basic.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>Regression: disposable <code>ui_configuration</code> row on <strong>Basic</strong> tier allows the final hourly request then blocks the next. Auto-generates API key and prints browser URL <code>scripts/api.php?rate_limit=1&amp;api_key=…</code>.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="verify_select_options_escalation.php" target="_blank" rel="nofollow noreferrer">verify_select_options_escalation.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>Regression check: Select Options API blocks admin employee quick-add via <code>employees</code> table (expects PASS).</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="verify_user_idor.php" target="_blank" rel="nofollow noreferrer">verify_user_idor.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>PoC for unauthorized user account deletion via IDOR.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="verify_crud_fk_label_search.php" target="_blank" rel="nofollow noreferrer">verify_crud_fk_label_search.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>Regression: Employees <code>?search=Active</code> matches <code>employee_statuses.name</code>; <code>?search=FNB</code> matches <code>departments.code</code>; equipment <code>?search=FNB</code> matches linked <code>departments.code</code>; license_management search matches <code>license_types.name</code>; shared FK EXISTS helper; bespoke modules (switch_ports, todo, notes, private_contacts, ip_subnets, bookmarks, passwords) label search.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="verify_employees_equipment_search_coverage.php" target="_blank" rel="nofollow noreferrer">verify_employees_equipment_search_coverage.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>Regression: dedicated employees + equipment index search probes — <code>first_name</code>, <code>last_name</code>, <code>username</code>, full name, FK codes (<code>FNB</code>, <code>LOC-NY-01</code>, <code>SUP-001</code>, <code>RACK-A</code>), position description, manager username, assignee identity on equipment. Disposable employee + equipment rows; optional <code>ITM_TEST_COMPANY_ID</code> (default <code>1</code>).</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="verify_employees_sensitive_view.php" target="_blank" rel="nofollow noreferrer">verify_employees_sensitive_view.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>Regression check: Employees list/view HTML omits password and reset-token columns (<code>itm_employees_auth_filter_ui_columns()</code>).</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="departments">
                    <td><a href="check_department_select_quick_add.php" target="_blank" rel="nofollow noreferrer">check_department_select_quick_add.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">departments</span></span></td>
                    <td>Static audit: every department FK <code>&lt;select&gt;</code> in <code>modules/</code> and <code>scripts/</code> must include trailing <code>&lt;option value="__add_new__"&gt;➕&lt;/option&gt;</code> (per-select block, not file-wide).</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="check_fast_create_acc_select_quick_add.php" target="_blank" rel="nofollow noreferrer">check_fast_create_acc_select_quick_add.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Static audit: FK <code>&lt;select&gt;</code> elements in <code>modules/employees/fast_create_acc_browser.php</code> include <code>__add_new__</code> ➕ (exempt <code>module_slugs[]</code>).</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="departments explorer">
                    <td><a href="verify_explorer_department_access.php" target="_blank" rel="nofollow noreferrer">verify_explorer_department_access.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">departments</span><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">explorer</span></span></td>
                    <td>Regression for Explorer <code>Departments/</code> listing (all code folders visible) and per-code ACL (assigned folders allowed, others blocked). Requires <code>employee_departments</code> migration on live DBs.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="verify_explorer_zip_leak.php" target="_blank" rel="nofollow noreferrer">verify_explorer_zip_leak.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>Regression for Explorer <code>downloadZip</code>: Step 1 blocks roots. Step 2 allows only exact <code>Private/{username}_{employee_id}</code>. Step 3 blocks all other paths (own subfolders, <code>Common</code>/<code>Departments</code>, other users). Subprocess uses Laragon CLI <code>php.exe</code> and session before <code>config.php</code>.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="verify_company_deletion.php" target="_blank" rel="nofollow noreferrer">verify_company_deletion.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>PoC for unauthorized company deletion by regular employees.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="companies">
                    <td><a href="repro_auth_bypass_v3.php" target="_blank" rel="nofollow noreferrer">repro_auth_bypass_v3.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">companies</span></span></td>
                    <td>Reproduction script for Auth Bypass v3 vulnerability. Subprocess spawn uses <code>escapeshellarg()</code>.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="empty_folders.php" target="_blank" rel="nofollow noreferrer">empty_folders.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Backfill empty <code>index.html</code> on <strong>every</strong> folder under the project root (skips <code>.git</code>, <code>.github</code>, and other dot dirs). Lists only <strong>new or changed</strong> repo-relative <code>path/index.html</code> paths before the summary. Upload paths (<code>images/</code>, <code>tickets_photos/</code>, <code>floor_plans/</code>, <code>backups/</code>, <code>files/</code>) also receive managed <code>.htaccess</code> (idempotent).</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="ensure_files_htaccess_chain.php" target="_blank" rel="nofollow noreferrer">ensure_files_htaccess_chain.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Backfill <code>deny_http</code> managed <code>.htaccess</code> and empty <code>index.html</code> on every directory segment under <code>files/</code> only. Lists only <strong>new or changed</strong> segments before the summary (idempotent).</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="verify_explorer_rce_htaccess.php" target="_blank" rel="nofollow noreferrer">verify_explorer_rce_htaccess.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>PoC for .htaccess-based RCE in the Explorer module.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="verify_explorer_rce_marker.php" target="_blank" rel="nofollow noreferrer">verify_explorer_rce_marker.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>PoC for .htaccess-based RCE in the Explorer module using a specific marker to bypass filters.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="explorer">
                    <td><a href="test_explorer_paths.php" target="_blank" rel="nofollow noreferrer">test_explorer_paths.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">explorer</span></span></td>
                    <td>Regression tests for Explorer path validation logic including sensitive root folder blocking.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="explorer">
                    <td><a href="test_explorer_preview.php" target="_blank" rel="nofollow noreferrer">test_explorer_preview.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">explorer</span></span></td>
                    <td>Regression tests for Explorer preview routing so JPG/PNG/PDF use <code>file.php</code> and text types use the open API.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="verify_audit_logs_disclosure.php" target="_blank" rel="nofollow noreferrer">verify_audit_logs_disclosure.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>Three-step regression for employees audit disclosure: (1) static <code>db/</code> split bundle trigger scan — <code>trg_employees_audit_*</code> must omit <code>password</code>, <code>vault_key_hash</code>, <code>reset_token*</code>; (2) live disposable employee UPDATE probe via <code>itm_script_test_employee.php</code>; (3) retro scan of last 25 <code>employees</code> audit rows. Prints each step with <code>[PASS]</code>/<code>[FAIL]</code>. Optional env: <code>ITM_TEST_COMPANY_ID</code> (default <code>1</code>).</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="auth_register_reset_human_test.php" target="_blank" rel="nofollow noreferrer">auth_register_reset_human_test.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>Human-style auth regression: invitation create, register INSERT with tenant Active employment status, login lookup + <code>password_verify</code>, reset-token password change, and <code>mysqli_stmt_bind_param</code> contracts on public auth pages. <strong>Mutates DB:</strong> disposable invitations and <code>script-*</code> employees (teardown on exit).</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="verify_password_reset_flow.php" target="_blank" rel="nofollow noreferrer">verify_password_reset_flow.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>Password reset token regression: store with MySQL <code>DATE_ADD</code> expiry (24 hours), hash lookup, legacy plain <code>reset_token</code> fallback, and completion UPDATE. <strong>Mutates DB:</strong> disposable script-test employee (teardown on exit).</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="verify_invitations_escalation.php" target="_blank" rel="nofollow noreferrer">verify_invitations_escalation.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Verification script for authorization bypass and privilege escalation in registration invitations.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="repro_zip_slip.php" target="_blank" rel="nofollow noreferrer">repro_zip_slip.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Reproduction script for Zip Slip (path traversal during extraction) vulnerability.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="employees notes">
                    <td><a href="repro_notes_traversal.php" target="_blank" rel="nofollow noreferrer">repro_notes_traversal.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">employees</span><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">notes</span></span></td>
                    <td>PoC for Path Traversal and arbitrary file read via Notes ZIP download.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="repro_notes_idor.php" target="_blank" rel="nofollow noreferrer">repro_notes_idor.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>PoC for Cross-user IDOR (unauthorized view/delete) in the Notes module.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="verify_metadata_column_cache.php" target="_blank" rel="nofollow noreferrer">verify_metadata_column_cache.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Regression for table-level <code>information_schema</code> caching in <code>itm_table_has_column()</code> and <code>itm_table_column_is_nullable()</code> (<code>includes/bootstrap_helpers.php</code>). Cold batch expects schema <code>Questions</code> delta 1–2; warm repeat expects schema delta 0 (trailing <code>SHOW STATUS</code> excluded from delta). Optional env: <code>ITM_META_CACHE_TABLE</code> (default <code>switch_ports</code>).</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="verify_update_port_zero_row.php" target="_blank" rel="nofollow noreferrer">verify_update_port_zero_row.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>Regression for <code>includes/update_port.php</code>: zero-row tenant-scoped UPDATE returns HTTP 404 before any IDF auto-sync (idf_ports row count unchanged). Creates disposable probe equipment in a transaction when the tenant has no switch_ports rows. Subprocess seeds session tenant before <code>config.php</code>, uses <code>ITM_HTTP_ENDPOINT_CONTRACT_TEST</code>, and stubs <code>itm_api_json_response()</code> to capture HTTP status. Optional env: <code>ITM_TEST_COMPANY_ID</code> (default <code>1</code>).</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="verify_notes_ajax_contract.php" target="_blank" rel="nofollow noreferrer">verify_notes_ajax_contract.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>Regression for Notes AJAX mutations: blocked single_delete returns HTTP 404 with ok:false when affected_rows is zero.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="debug_peer_options.php" target="_blank" rel="nofollow noreferrer">debug_peer_options.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Debug Live Chat Chat-with peer list: <code>chat_same_tenant</code>, accessible companies, merged <code>list_employees</code> options (excludes self).</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="verify_live_chat.php" target="_blank" rel="nofollow noreferrer">verify_live_chat.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>Regression for Live Chat: schema tables, SLA on ticket create, chat_with ACL, employee notifications, ticket activity/comments helpers.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="verify_notes_vault.php" target="_blank" rel="nofollow noreferrer">verify_notes_vault.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>Regression for Notes vault encryption: private title/content encrypt at rest, shared notes stay plaintext, label hash helpers, hydrate when vault locked/unlocked. Disposable script employee.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="verify_todo_vault.php" target="_blank" rel="nofollow noreferrer">verify_todo_vault.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>Regression for Todo vault encryption: private title/description encrypt at rest, company-global tasks stay plaintext, hydrate when vault locked/unlocked. Disposable script employee.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="verify_events_vault.php" target="_blank" rel="nofollow noreferrer">verify_events_vault.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>Regression for Events vault encryption: private title/description/location encrypt at rest, shared events stay plaintext, hydrate when vault locked/unlocked. Disposable script employee.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="verify_private_contacts_vault.php" target="_blank" rel="nofollow noreferrer">verify_private_contacts_vault.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>Regression for Private Contacts vault encryption: PII fields encrypt at rest, hydrate when vault unlocked, locked vault hides list fields, legacy plaintext tolerated. Disposable script employee.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="verify_notes_share.php" target="_blank" rel="nofollow noreferrer">verify_notes_share.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>Regression for Notes QR/code share sessions: create session, token/code lookup, payload snapshot, join URL, asset filename guard. Requires <code>share_sessions</code> in <code>db/</code> split bundle.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="apply_crud_record_share_modules.php" target="_blank" rel="nofollow noreferrer">apply_crud_record_share_modules.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Maintenance: wire <code>join.php</code>, AJAX <code>create_share_session</code>, view share buttons, and QR modal for CRUD record share rollout modules (<code>includes/itm_crud_record_share.php</code>). <strong>Default = dry-run</strong>; writes with CLI <code>--apply</code> or browser <code>?apply=1</code> (Admin). Inventory: <code>docs/CRUD_RECORD_SHARE.md</code>.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="notes">
                    <td><a href="patch_crud_share_agent_notes.php" target="_blank" rel="nofollow noreferrer">patch_crud_share_agent_notes.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">notes</span></span></td>
                    <td>Maintenance: append <strong>Share (temporary QR / code)</strong> section to each CRUD record share module <code>AGENT_NOTES.md</code>. <strong>Default = dry-run</strong>; writes with CLI <code>--apply</code> or browser <code>?apply=1</code> (Admin).</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="verify_qr_share_modules.php" target="_blank" rel="nofollow noreferrer">verify_qr_share_modules.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>Regression for Passwords, Bookmarks, Todo, Events, Private Contacts, Explorer, Floor Plans, Rack Planner, and CRUD record (<code>departments</code>) QR/code share sessions: create session, payload snapshot, token lookup, join URL. Requires unified <code>share_sessions</code> in <code>db/01_schema.sql</code>.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="verify_module_share.php" target="_blank" rel="nofollow noreferrer">verify_module_share.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>Regression for <code>company_module_share</code> opt-out matrix and <code>has_module_share_access()</code> used by <code>itm_qr_share_create_session()</code>. Admin UI: <code>modules/share_modules/</code>.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="verify_whatsapp_share.php" target="_blank" rel="nofollow noreferrer">verify_whatsapp_share.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Regression for WhatsApp share deep links: message body (join URL + 6-digit code + expiry copy) and <code>wa.me</code> URL encoding via <code>includes/itm_whatsapp_share.php</code>.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="verify_outlook_share.php" target="_blank" rel="nofollow noreferrer">verify_outlook_share.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Regression for Outlook/mail share compose helpers: subject/body text, <code>mailto:</code> URL, and Outlook web compose deep link via <code>includes/itm_outlook_share.php</code>.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="catalogs events">
                    <td><a href="verify_json_import_validation.php" target="_blank" rel="nofollow noreferrer">verify_json_import_validation.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">catalogs</span><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">events</span></span></td>
                    <td>Regression for JSON import: invalid numeric, date/datetime, and enum column values are rejected instead of silent NULL inserts.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="verify_maintenance_scripts_rbac.php" target="_blank" rel="nofollow noreferrer">verify_maintenance_scripts_rbac.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Regression for Admin browser gate on maintenance scripts (MBQA runner, compare_database_sql_modules, test_sql_injection).</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="repro_audit_disclosure.php" target="_blank" rel="nofollow noreferrer">repro_audit_disclosure.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>PoC for Sensitive Information Disclosure (reset tokens) in Audit Logs. Uses a disposable script test user (not seed Admin id 1).</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="check_crud_rbac_coverage.php" target="_blank" rel="nofollow noreferrer">check_crud_rbac_coverage.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Static audit for server-side CRUD RBAC on flattened <code>modules/*/index.php</code> delete/create/edit handlers. Exit <code>1</code> when guards are missing.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="apply_crud_rbac_guards.php" target="_blank" rel="nofollow noreferrer">apply_crud_rbac_guards.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Bulk repair — insert <code>itm_require_crud_role_module_permission()</code> on flattened CRUD index handlers. <strong>Default = dry-run</strong>; writes with CLI <code>--apply</code> or browser <code>?apply=1</code> (Admin). Lists scanned / changed / skipped modules (exempt slugs skipped). Idempotent.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="repro_rbac_bypass.php" target="_blank" rel="nofollow noreferrer">repro_rbac_bypass.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>PoC for RBAC bypass on Expenses <code>delete.php</code>: read-only role must get HTTP 403 and retain the row. Uses a free <code>cost_centers</code> slot (<code>uq_expenses_company_scope</code>). Subprocess spawn uses <code>escapeshellarg()</code>; do not stub <code>cr_require_valid_csrf_token()</code>.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="repro_employee_companies_leak.php" target="_blank" rel="nofollow noreferrer">repro_employee_companies_leak.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>PoC for Multi-Tenant Data Leak in Employees module. Subprocess spawn uses <code>escapeshellarg()</code>.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="repro_employee_companies_bac.php" target="_blank" rel="nofollow noreferrer">repro_employee_companies_bac.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>PoC for Broken Access Control in Employees module. Subprocess spawn uses <code>escapeshellarg()</code>.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="repro_audit_token_leak.php" target="_blank" rel="nofollow noreferrer">repro_audit_token_leak.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>Verification for Audit Log Sensitive Data Exposure. Disposable test user; prepared <code>UPDATE employees</code> for <code>reset_token</code> fields.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="repro_destructive_import.php" target="_blank" rel="nofollow noreferrer">repro_destructive_import.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>Reproduction — destructive employee import scenario for company 1. <strong>Browser + CLI dry-run default</strong>; <code>--apply</code> / <code>?apply=1</code> (Admin) seeds disposable Keep/Delete Me rows, imports only Keep Me, asserts Delete Me survives, tears down disposable rows.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="repro_todo_user_leak.php" target="_blank" rel="nofollow noreferrer">repro_todo_user_leak.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>Reproduction script for multi-tenant username leak in Todo module.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="repro_cross_tenant_admin.php" target="_blank" rel="nofollow noreferrer">repro_cross_tenant_admin.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>PoC — company-2 Admin Employees list must not include a disposable company-1 username. Seeds via <code>itm_script_test_employee_create_session_actor()</code>; CLI subprocess HTML probe. Browser: <code>?run=1</code>.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="repro_explorer_path_bypass_v4.php" target="_blank" rel="nofollow noreferrer">repro_explorer_path_bypass_v4.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>Regression — Explorer <code>./Private</code> path bypass blocked after normalization.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="explorer">
                    <td><a href="repro_explorer_traversal.php" target="_blank" rel="nofollow noreferrer">repro_explorer_traversal.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">explorer</span></span></td>
                    <td>Reproduction script for Explorer Path Traversal vulnerability via 'item' parameter.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="explorer">
                    <td><a href="verify_explorer_fix.php" target="_blank" rel="nofollow noreferrer">verify_explorer_fix.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">explorer</span></span></td>
                    <td>Verification script for Explorer Path Traversal fix.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="explorer">
                    <td><a href="verify_explorer_fix_updated.php" target="_blank" rel="nofollow noreferrer">verify_explorer_fix_updated.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">explorer</span></span></td>
                    <td>Updated verification script for Explorer Path Traversal fix.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="explorer">
                    <td><a href="verify_explorer_fix_web.php" target="_blank" rel="nofollow noreferrer">verify_explorer_fix_web.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">explorer</span></span></td>
                    <td>Web-friendly verification for Explorer Path Traversal fix.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="explorer">
                    <td><a href="verify_explorer_fix_standalone.php" target="_blank" rel="nofollow noreferrer">verify_explorer_fix_standalone.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">explorer</span></span></td>
                    <td>Standalone verification for Explorer Path Traversal fix (HTML UI).</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="attempts">
                    <td><a href="repro_attempts_data_leak_v2.php" target="_blank" rel="nofollow noreferrer">repro_attempts_data_leak_v2.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">attempts</span></span></td>
                    <td>Regression — password-like login identifiers redacted in <code>attempts.email</code> (disposable secret per run; checks only the row inserted by this request).</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="repro_explorer_zip_slip_v2.php" target="_blank" rel="nofollow noreferrer">repro_explorer_zip_slip_v2.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>Regression — Zip Slip traversal entries blocked during Explorer unzip.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="repro_select_options_unauthorized_v2.php" target="_blank" rel="nofollow noreferrer">repro_select_options_unauthorized_v2.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>Regression — <code>companies</code> quick-add blocked for regular employees. Scenario matrix + live API subprocess (browser uses Laragon CLI <code>php.exe</code>, not <code>php-cgi</code>); policy fallback if harness still fails.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="employees password_entries">
                    <td><a href="repro_vault_corruption.php" target="_blank" rel="nofollow noreferrer">repro_vault_corruption.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">employees</span><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">password_entries</span></span></td>
                    <td>Regression — vault master key re-encryption rollback (atomicity).</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
				<tr data-tags="Mixed">
                    <td><a href="repro_db_integrity.php" target="_blank" rel="nofollow noreferrer">repro_db_integrity.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>Regression — expenses UNIQUE allows different <code>posting_date</code>/<code>invoice_number</code> (tenant <code>paid_status_id</code>); documents assignment-history one-row-per-employee unique as WARN; Admin bookmark trigger via disposable Admin. Transaction + rollback. Browser: <code>?run=1</code>.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
				<tr data-tags="Codebase">
                    <td><a href="test_multi_tenant.php" target="_blank" rel="nofollow noreferrer">test_multi_tenant.php</td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
				    <td>Verifies multi-tenant integrity by checking company_id distribution and foreign keys across all tables.</td>
				    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
				</tr>
				<tr data-tags="Codebase">
                    <td><a href="verify_audit_columns.php" target="_blank" rel="nofollow noreferrer">verify_audit_columns.php</td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Schema gate: mandatory audit/soft-delete columns on live tables (including <code>live_chat_typing</code>). Chat message/typing tables stay private-data exempt from <code>audit_logs</code>.</td>
				    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
				</tr>
				<tr data-tags="Codebase">
                    <td><a href="apply_crud_audit_soft_delete.php" target="_blank" rel="nofollow noreferrer">apply_crud_audit_soft_delete.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
				    <td>Apply soft-delete + audit meta UI patches to scaffold modules in <code>docs/list_soft-delete.txt</code>. <strong>Default = dry-run</strong>; writes with CLI <code>--apply</code> or browser <code>?apply=1</code> (Admin). After counts, lists inventory / skip / missing / patch / compliant modules (real newlines for browser <code>&lt;pre&gt;</code>). Idempotent; skips status-driven modules.</td>
				    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
				</tr>
				<tr data-tags="Codebase">
                    <td><a href="check_crud_audit_soft_delete.php" target="_blank" rel="nofollow noreferrer">check_crud_audit_soft_delete.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
				    <td>Static gate for scaffold soft-delete / audit UI contracts (list hide, view columns, not-deleted filter, soft-delete helper).</td>
				    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
				</tr>
                <tr data-tags="employees">
                    <td><a href="repro_employee_dataloss.php" target="_blank" rel="nofollow noreferrer">repro_employee_dataloss.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">employees</span></span></td>
                    <td>Regression test for employee Excel import. Verifies that columns missing from the import payload are not wiped in the database during update.</td>
                   <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="departments employees">
                    <td><a href="verify_import_fix_updated.php" target="_blank" rel="nofollow noreferrer">verify_import_fix_updated.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">departments</span><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">employees</span></span></td>
                    <td>Verification script for Employee Import Department Data Loss Fix.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="departments">
                    <td><a href="repro_generic_dataloss.php" target="_blank" rel="nofollow noreferrer">repro_generic_dataloss.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">departments</span></span></td>
                    <td>Regression test for generic table Excel import. Verifies that columns missing from the import payload are not wiped in the database during update.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="repro_contacts_idor.php" target="_blank" rel="nofollow noreferrer">repro_contacts_idor.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>Reproduction script for IDOR in contacts API inline edit; disposable employees via shared helper (clears stale audit actor before INSERT).</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="repro_select_options.php" target="_blank" rel="nofollow noreferrer">repro_select_options.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>Reproduction script for RBAC bypass in select options API.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="repro_status_leak.php" target="_blank" rel="nofollow noreferrer">repro_status_leak.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>Reproduction script for employee status cross-tenant leak.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="repro_visitors_bac.php" target="_blank" rel="nofollow noreferrer">repro_visitors_bac.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>Reproduction script for Broken Access Control in visitors access log.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="repro_visitors_sqli.php" target="_blank" rel="nofollow noreferrer">repro_visitors_sqli.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>Reproduction script for SQL Injection in visitors access log inline edit.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="verify_audit_updated.php" target="_blank" rel="nofollow noreferrer">verify_audit_updated.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Verification script for audit log redaction of sensitive fields.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="verify_clear_table_fix.php" target="_blank" rel="nofollow noreferrer">verify_clear_table_fix.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>Verification script for employees clear-table soft-delete (employee audit row remains; bookmarks detached).</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="explorer">
                    <td><a href="verify_explorer_updated.php" target="_blank" rel="nofollow noreferrer">verify_explorer_updated.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">explorer</span></span></td>
                    <td>Verification script for Explorer file extension whitelisting.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Codebase">
                    <td><a href="verify_rbac_updated.php" target="_blank" rel="nofollow noreferrer">verify_rbac_updated.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                    <td>Verification script for RBAC protection guards in module handlers.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="visitors_access_log">
                    <td><a href="verify_sqli_updated.php" target="_blank" rel="nofollow noreferrer">verify_sqli_updated.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">visitors_access_log</span></span></td>
                    <td>Verification script for SQL Injection fix in visitors access log.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="verify_status_leak_fixed.php" target="_blank" rel="nofollow noreferrer">verify_status_leak_fixed.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>Verification script for employee status cross-tenant leak fix.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="verify_visitors_bac_fix.php" target="_blank" rel="nofollow noreferrer">verify_visitors_bac_fix.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>Verification script for Broken Access Control fix in visitors access log.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="verify_visitors_sqli_fix.php" target="_blank" rel="nofollow noreferrer">verify_visitors_sqli_fix.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>Verification script for SQL Injection fix in visitors access log against fixed files.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="Mixed">
                    <td><a href="repro_birthdays_resignations_rbac.php" target="_blank" rel="nofollow noreferrer">repro_birthdays_resignations_rbac.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="mixed">Mixed</span></span></td>
                    <td>Reproduction & verification script for Birthdays & Resignations RBAC View Bypass vulnerability.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
                <tr data-tags="ui_configuration">
                    <td><a href="verify_auto_scaffolding.php" target="_blank" rel="nofollow noreferrer">verify_auto_scaffolding.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="table">ui_configuration</span></span></td>
                    <td>Verification script for Dynamic Auto-Scaffolding toggled via <code>enable_auto_scaffolding</code>. Checks both disabled and enabled scaffolding on dummy tables.</td>
                    <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
                </tr>
            </tbody>
        </table></div>
    </div>
    <div class="scripts-section scripts-catalog-section" id="deployment">
        <h2>Deployment &amp; Git</h2>
        <div class="scripts-catalog-grid"><table class="scripts-catalog">
            <colgroup>
                <col class="scripts-col-script">
                <col class="scripts-col-access">
                <col class="scripts-col-tags">
                <col class="scripts-col-what">
                <col class="scripts-col-how">
            </colgroup>
            <thead>
                <tr>
                    <th>Script</th>
                    <th class="scripts-access-col">Access</th>
                    <th>Tags</th>
                    <th>What it does</th>
                    <th>How to use</th>
                </tr>
            </thead>
            <tbody>
             <tr data-tags="Codebase">
                    <td><a href="http://myhome.dynip.sapo.pt/deletev2.php" target="_blank" rel="nofollow noreferrer">deletev2.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                <td>Clone Github + Import Database</td>
                <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
            </tr>
            <tr data-tags="Codebase">
                    <td><a href="http://myhome.dynip.sapo.pt/it-management/reset_git_history.php" target="_blank" rel="nofollow noreferrer">reset_git_history.php</a></td>
                    <td class="scripts-access-cell"><span class="scripts-access-badges"><span class="scripts-badge scripts-badge-web">Browser</span><span class="scripts-badge scripts-badge-cli">CLI</span></span></td>
                    <td class="scripts-tags-cell"><span class="scripts-tag-badges"><span class="scripts-badge scripts-badge-tag" data-tag-kind="codebase">Codebase</span></span></td>
                <td>Completely reset Git history and force push a clean master branch. <strong>BETA only</strong> — destructive; rewrites Git history and force-pushes. Used in development to purge history or reset a branch to a clean state.</td>
                <td class="scripts-catalog-how-stub">Open in browser for usage.</td>
            </tr>
        </tbody>
        </table></div>
    </div>

    <div class="scripts-footer">
        <p class="scripts-muted" style="margin:0;">
            Back to app: <a href="../index.php">Home</a> ·
            Catalog: <a href="scripts.php">scripts.php</a>
        </p>
    </div>
</div>
<script>
(function () {
    var rows = Array.prototype.slice.call(document.querySelectorAll('.scripts-catalog-section .scripts-catalog tbody tr'));

    function wrapCatalogCellClamp(cell, innerClass) {
        if (!cell || cell.querySelector('.scripts-cell-clamp')) {
            return;
        }
        var wrap = document.createElement('div');
        wrap.className = 'scripts-cell-clamp ' + innerClass;
        while (cell.firstChild) {
            wrap.appendChild(cell.firstChild);
        }
        cell.appendChild(wrap);
    }

    function ensureCatalogPickColumns() {
        document.querySelectorAll('.scripts-catalog').forEach(function (table) {
            var cg = table.querySelector('colgroup');
            if (cg && !cg.querySelector('.scripts-col-pick')) {
                var col = document.createElement('col');
                col.className = 'scripts-col-pick';
                cg.insertBefore(col, cg.firstChild);
            }
            var headRow = table.querySelector('thead tr');
            if (headRow && !headRow.querySelector('th.scripts-pick-col')) {
                var th = document.createElement('th');
                th.className = 'scripts-pick-col';
                th.setAttribute('scope', 'col');
                th.setAttribute('aria-label', 'Select');
                th.textContent = '\u200b';
                headRow.insertBefore(th, headRow.firstChild);
            }
        });
    }

    function injectCatalogRowPickColumn(row) {
        if (row.querySelector('td.scripts-cell-pick')) {
            return;
        }
        var scriptCell = row.cells[0];
        if (!scriptCell) {
            return;
        }
        var link = scriptCell.querySelector('a');
        var pickTd = document.createElement('td');
        pickTd.className = 'scripts-cell-pick';
        if (link) {
            var scriptName = (link.textContent || '').replace(/^\s+|\s+$/g, '');
            var existingCb = scriptCell.querySelector('.scripts-catalog-row-cb');
            if (existingCb) {
                pickTd.appendChild(existingCb);
            } else {
                var cb = document.createElement('input');
                cb.type = 'checkbox';
                cb.className = 'scripts-catalog-row-cb';
                cb.setAttribute('aria-label', scriptName ? ('Select ' + scriptName) : 'Select script');
                pickTd.appendChild(cb);
            }
            scriptCell.classList.add('scripts-cell-script');
        }
        row.insertBefore(pickTd, scriptCell);
    }

    ensureCatalogPickColumns();
    rows.forEach(function (row) {
        injectCatalogRowPickColumn(row);
        var cells = row.querySelectorAll('td');
        if (cells.length >= 5) {
            var whatCell = cells[4];
            var howCell = cells[5];
            if (whatCell) {
                whatCell.classList.add('scripts-cell-what');
                wrapCatalogCellClamp(whatCell, 'scripts-cell-what-inner');
            }
            if (howCell && !howCell.classList.contains('scripts-catalog-how-stub') && whatCell) {
                var whatWrap = whatCell.querySelector('.scripts-cell-clamp');
                if (whatWrap && howCell.textContent.replace(/\s+/g, ' ').trim() !== '') {
                    var howInline = document.createElement('div');
                    howInline.className = 'scripts-catalog-how-inline';
                    howInline.innerHTML = howCell.innerHTML;
                    whatWrap.appendChild(howInline);
                }
            }
        }
    });

    document.querySelectorAll('.scripts-catalog').forEach(function (table) {
        table.classList.add('scripts-catalog-hide-how-col');
    });

    var filterInput = document.getElementById('scripts-catalog-filter');
    var countEl = document.getElementById('scripts-catalog-filter-count');
    var tagBar = document.getElementById('scripts-tag-filter-bar');
    var tableTagSelect = document.getElementById('scripts-table-tag-select');
    var searchForm = document.getElementById('scripts-catalog-search-form');
    var clearBtn = document.getElementById('scripts-catalog-filter-clear');
    var emptyEl = document.getElementById('scripts-catalog-empty');
    var KIND_TAGS = { Codebase: 1, Python: 1, Server: 1, Info: 1, Markdown: 1, Mixed: 1 };
    if (!filterInput) {
        return;
    }
    var total = rows.length;
    var activeTag = '';
    var activeTableTag = '';

    function syncQueryToUrl() {
        var query = filterInput.value.replace(/^\s+|\s+$/g, '');
        var params = new URLSearchParams(window.location.search);
        if (query === '') {
            params.delete('q');
        } else {
            params.set('q', query);
        }
        var next = params.toString();
        var nextUrl = window.location.pathname + (next ? ('?' + next) : '') + window.location.hash;
        if (window.history && typeof window.history.replaceState === 'function') {
            window.history.replaceState(null, '', nextUrl);
        }
    }

    function resetTagChips() {
        if (!tagBar) {
            return;
        }
        var chips = tagBar.querySelectorAll('.scripts-tag-chip');
        for (var i = 0; i < chips.length; i++) {
            var chipTag = chips[i].getAttribute('data-tag') || '';
            chips[i].classList.toggle('is-active', chipTag === '');
        }
    }

    function updateSectionVisibility() {
        var sections = document.querySelectorAll('.scripts-catalog-section');
        for (var i = 0; i < sections.length; i++) {
            var section = sections[i];
            var tbody = section.querySelector('.scripts-catalog tbody');
            if (!tbody) {
                section.classList.remove('scripts-catalog-section-empty');
                continue;
            }
            var sectionRows = tbody.querySelectorAll('tr');
            var anyVisible = false;
            for (var j = 0; j < sectionRows.length; j++) {
                if (!sectionRows[j].classList.contains('scripts-catalog-hidden')) {
                    anyVisible = true;
                    break;
                }
            }
            section.classList.toggle('scripts-catalog-section-empty', !anyVisible);
        }
    }

    function collectTableTags() {
        var tagSet = {};
        rows.forEach(function (row) {
            var raw = row.getAttribute('data-tags') || '';
            raw.split(/\s+/).forEach(function (tag) {
                if (tag && !KIND_TAGS[tag]) {
                    tagSet[tag] = true;
                }
            });
        });
        return Object.keys(tagSet).sort(function (a, b) {
            return a.localeCompare(b);
        });
    }

    function buildTagBar() {
        if (!tagBar) {
            return;
        }
        tagBar.innerHTML = '';
        var allBtn = document.createElement('button');
        allBtn.type = 'button';
        allBtn.className = 'scripts-tag-chip is-active';
        allBtn.setAttribute('data-tag', '');
        allBtn.textContent = 'All';
        tagBar.appendChild(allBtn);

        ['Codebase', 'Python', 'Server', 'Info', 'Markdown', 'Mixed'].forEach(function (tag) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'scripts-tag-chip';
            btn.setAttribute('data-tag', tag);
            btn.textContent = tag;
            tagBar.appendChild(btn);
        });

        [
            { tag: '*.json', label: '*.json', title: 'Documentation JSON data files' },
            { tag: '*.txt', label: '*.txt', title: 'Documentation TXT data files' },
            { tag: '*.md', label: '*.md', title: 'Documentation Markdown files' }
        ].forEach(function (alias) {
            var aliasBtn = document.createElement('button');
            aliasBtn.type = 'button';
            aliasBtn.className = 'scripts-tag-chip scripts-tag-chip-alias';
            aliasBtn.setAttribute('data-tag', alias.tag);
            aliasBtn.setAttribute('title', alias.title);
            aliasBtn.textContent = alias.label;
            tagBar.appendChild(aliasBtn);
        });

        tagBar.addEventListener('click', function (event) {
            var chip = event.target;
            if (!chip || !chip.classList || !chip.classList.contains('scripts-tag-chip')) {
                return;
            }
            activeTag = chip.getAttribute('data-tag') || '';
            var chips = tagBar.querySelectorAll('.scripts-tag-chip');
            for (var i = 0; i < chips.length; i++) {
                chips[i].classList.toggle('is-active', chips[i] === chip);
            }
            updateFilter();
        });
    }

    function buildTableTagSelect() {
        if (!tableTagSelect) {
            return;
        }
        collectTableTags().forEach(function (tag) {
            var opt = document.createElement('option');
            opt.value = tag;
            opt.textContent = tag;
            tableTagSelect.appendChild(opt);
        });
        tableTagSelect.addEventListener('change', function () {
            activeTableTag = tableTagSelect.value || '';
            updateFilter();
        });
    }

    function rowCatalogHref(row) {
        var link = row.querySelector('td.scripts-cell-script a') || row.querySelector('td:nth-child(2) a');
        if (!link) {
            return '';
        }
        return (link.getAttribute('href') || link.textContent || '').replace(/^\s+|\s+$/g, '').toLowerCase();
    }

    function rowHasExtension(row, ext) {
        var href = rowCatalogHref(row);
        return href.length >= ext.length && href.slice(-ext.length) === ext;
    }

    function rowIsInfoDataFile(row) {
        return rowHasExtension(row, '.json') || rowHasExtension(row, '.txt');
    }

    function rowIsMarkdownDocFile(row) {
        return rowHasExtension(row, '.md');
    }

    function rowMatchesTag(row) {
        if (activeTableTag) {
            var tableTags = (row.getAttribute('data-tags') || '').split(/\s+/);
            var tableHit = false;
            for (var t = 0; t < tableTags.length; t++) {
                if (tableTags[t] === activeTableTag) {
                    tableHit = true;
                    break;
                }
            }
            if (!tableHit) {
                return false;
            }
        }
        if (!activeTag) {
            return true;
        }
        if (activeTag === '*.json') {
            return rowHasExtension(row, '.json');
        }
        if (activeTag === '*.txt') {
            return rowHasExtension(row, '.txt');
        }
        if (activeTag === '*.md') {
            return rowHasExtension(row, '.md');
        }
        if (activeTag === 'Info') {
            return rowIsInfoDataFile(row);
        }
        if (activeTag === 'Markdown') {
            return rowIsMarkdownDocFile(row);
        }
        var tags = (row.getAttribute('data-tags') || '').split(/\s+/);
        for (var i = 0; i < tags.length; i++) {
            if (tags[i] === activeTag) {
                return true;
            }
        }
        return false;
    }

    function rowCatalogFilename(row) {
        var href = rowCatalogHref(row);
        if (href === '') {
            return '';
        }
        var parts = href.split('/');
        return parts[parts.length - 1];
    }

    function catalogLikePatternToRegExp(pattern) {
        if (pattern.indexOf('%') === -1 && pattern.indexOf('_') === -1) {
            return null;
        }
        var escaped = pattern.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        escaped = escaped.replace(/%/g, '.*').replace(/_/g, '.');
        return new RegExp('^' + escaped + '$', 'i');
    }

    function rowMatchesLikePattern(row, pattern) {
        var likeRe = catalogLikePatternToRegExp(pattern);
        if (!likeRe) {
            return false;
        }
        var href = rowCatalogHref(row);
        var filename = rowCatalogFilename(row);
        var link = row.querySelector('td.scripts-cell-script a') || row.querySelector('td:nth-child(2) a');
        var linkText = link ? (link.textContent || '').replace(/^\s+|\s+$/g, '').toLowerCase() : '';
        return likeRe.test(href) || likeRe.test(filename) || likeRe.test(linkText);
    }

    function rowTextMatchesQuery(row, query, tagsAttr) {
        if (query === '*.json' || query === '.json') {
            return rowHasExtension(row, '.json');
        }
        if (query === '*.txt' || query === '.txt') {
            return rowHasExtension(row, '.txt');
        }
        if (query === '*.md' || query === '.md') {
            return rowHasExtension(row, '.md');
        }
        if (query === '') {
            return true;
        }
        if (query.indexOf('%') !== -1 || query.indexOf('_') !== -1) {
            return rowMatchesLikePattern(row, query);
        }
        var href = rowCatalogHref(row);
        var filename = rowCatalogFilename(row);
        return href.indexOf(query) !== -1
            || filename.indexOf(query) !== -1
            || row.textContent.toLowerCase().indexOf(query) !== -1
            || tagsAttr.indexOf(query) !== -1;
    }

    function updateFilter() {
        var query = filterInput.value.replace(/^\s+|\s+$/g, '').toLowerCase();
        var visible = 0;
        rows.forEach(function (row) {
            var tagsAttr = (row.getAttribute('data-tags') || '').toLowerCase();
            var textMatch = rowTextMatchesQuery(row, query, tagsAttr);
            var tagMatch = rowMatchesTag(row);
            var match = textMatch && tagMatch;
            row.classList.toggle('scripts-catalog-hidden', !match);
            if (match) {
                visible += 1;
            }
        });
        if (countEl) {
            var suffix = '';
            if (activeTag) {
                suffix += ' (kind: ' + activeTag + ')';
            }
            if (activeTableTag) {
                suffix += ' (table: ' + activeTableTag + ')';
            }
            countEl.textContent = query === '' && !activeTag && !activeTableTag
                ? (total + ' scripts')
                : (visible + ' of ' + total + ' shown' + suffix);
        }
        if (emptyEl) {
            emptyEl.hidden = visible > 0;
        }
        updateSectionVisibility();
    }

    buildTagBar();
    buildTableTagSelect();
    filterInput.addEventListener('input', function () {
        syncQueryToUrl();
        updateFilter();
    });
    if (searchForm) {
        searchForm.addEventListener('submit', function (event) {
            event.preventDefault();
            syncQueryToUrl();
            updateFilter();
            filterInput.focus();
        });
    }
    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            filterInput.value = '';
            activeTag = '';
            activeTableTag = '';
            if (tableTagSelect) {
                tableTagSelect.value = '';
            }
            resetTagChips();
            syncQueryToUrl();
            updateFilter();
            filterInput.focus();
        });
    }
    updateFilter();
})();
</script>
</body>
</html>

<!-- Standard: scripts/SCRIPTS.md -->
<!-- Newline: (php_sapi_name() === 'cli' ? "\n" : "<br><br>") -->
