<?php
declare(strict_types=1);

/**
 * Browser/CLI helper to run the same cleanup that module_browser_qa_runner.php
 * executes before/after QA runs.
 *
 * CLI:
 *   php scripts/module_clean_tests_qa_runner.php
 *   php scripts/module_clean_tests_qa_runner.php --help
 *
 * Browser:
 *   scripts/module_clean_tests_qa_runner.php
 *   submit POST run form (CSRF-protected)
 */

require_once __DIR__ . '/lib/script_browser_nav.php';
require_once __DIR__ . '/lib/mbqa_report_paths.php';

function mbqa_clean_tests_is_cli(): bool
{
    return PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg';
}

/**
 * @return array{run:bool, help:bool}
 */
function mbqa_clean_tests_parse_options(): array
{
    $options = [
        'run' => false,
        'help' => false,
    ];

    if (mbqa_clean_tests_is_cli()) {
        $options['run'] = true;
        foreach (array_slice($GLOBALS['argv'] ?? [], 1) as $arg) {
            if ($arg === '--help' || $arg === '-h') {
                $options['help'] = true;
                $options['run'] = false;
            }
        }

        return $options;
    }

    $options['run'] = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['run']);
    $options['help'] = isset($_GET['help']);

    return $options;
}

/**
 * @return array{md_href:string,xlsx_href:string,clean_href:string,rebuild_href:string,rerun_href:string,runner_href:string}
 */
function mbqa_clean_tests_action_hrefs(string $root): array
{
    $latestJson = mbqa_report_find_latest_json_path($root);
    $xlsxHref = '';
    if ($latestJson !== '') {
        $files = mbqa_report_files_from_json_path($latestJson);
        if ($files !== null && isset($files['xlsx_basename']) && $files['xlsx_basename'] !== '') {
            $xlsxHref = '../qa-reports/' . $files['xlsx_basename'];
        }
    }

    return [
        'clean_href' => 'module_clean_tests_qa_runner.php',
        'md_href' => '../qa-reports/' . mbqa_report_markdown_basename(),
        'xlsx_href' => $xlsxHref,
        'rebuild_href' => 'module_browser_qa_build_report.php?run=1',
        'rerun_href' => 'module_browser_qa_runner.php?autostart=1',
        'runner_href' => 'module_browser_qa_runner.php',
    ];
}

/**
 * @param array{md_href:string,xlsx_href:string,clean_href:string,rebuild_href:string,rerun_href:string,runner_href:string} $hrefs
 */
function mbqa_clean_tests_action_links_html(array $hrefs): string
{
    $links = [];
    $links[] = '<a href="' . htmlspecialchars($hrefs['clean_href'], ENT_QUOTES, 'UTF-8') . '">Clean Tests</a>';
    $links[] = '<a href="' . htmlspecialchars($hrefs['md_href'], ENT_QUOTES, 'UTF-8') . '">Open markdown file</a>';
    if ($hrefs['xlsx_href'] !== '') {
        $links[] = '<a href="' . htmlspecialchars($hrefs['xlsx_href'], ENT_QUOTES, 'UTF-8') . '">Download XLSX</a>';
    } else {
        $links[] = '<span style="color:#57606a;">Download XLSX (not found)</span>';
    }
    $links[] = '<a href="' . htmlspecialchars($hrefs['rebuild_href'], ENT_QUOTES, 'UTF-8') . '">Rebuild report</a>';
    $links[] = '<a href="' . htmlspecialchars($hrefs['rerun_href'], ENT_QUOTES, 'UTF-8') . '">Re-Run Test</a>';
    $links[] = '<a href="' . htmlspecialchars($hrefs['runner_href'], ENT_QUOTES, 'UTF-8') . '">Run QA runner</a>';

    return implode(' &middot; ', $links);
}

function mbqa_clean_tests_cli_help(): void
{
    echo "Module QA clean tests helper\n\n";
    echo "Runs the same cleanup used by module_browser_qa_runner.php before/after runs.\n\n";
    echo "Usage:\n";
    echo "  php scripts/module_clean_tests_qa_runner.php\n";
    echo "  php scripts/module_clean_tests_qa_runner.php --help\n";
}

/**
 * @return array{ok:bool,dirs_removed:int,companies_deleted:int,types_deleted:int,sidebar_deleted:int,canonical_ensured:int,errors:array<int,string>}
 */
function mbqa_clean_tests_run_cleanup(): array
{
    if (mbqa_clean_tests_is_cli()) {
        define('ITM_CLI_SCRIPT', true);
    }

    require_once dirname(__DIR__) . '/config/config.php';
    require_once __DIR__ . '/lib/equipment_type_modules.php';

    $modulesRoot = dirname(__DIR__) . '/modules';

    return itm_run_equipment_test_module_artifacts_cleanup($conn, $modulesRoot);
}

$options = mbqa_clean_tests_parse_options();
$root = realpath(__DIR__ . '/..') ?: dirname(__DIR__);
$hrefs = mbqa_clean_tests_action_hrefs($root);

if (!mbqa_clean_tests_is_cli()) {
    require_once dirname(__DIR__) . '/config/config.php';
}

if ($options['help']) {
    if (mbqa_clean_tests_is_cli()) {
        mbqa_clean_tests_cli_help();
        exit(0);
    }

    header('Content-Type: text/html; charset=utf-8');
    itm_script_browser_nav_echo();
    echo '<main style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Helvetica,Arial,sans-serif;max-width:860px;margin:16px;line-height:1.5;">';
    echo '<h1>Clean tests for module QA runner</h1>';
    echo '<p>Runs the same cleanup used automatically at the end of <code>module_browser_qa_runner.php</code>:</p>';
    echo '<ul>';
    echo '<li>Remove temporary equipment scaffold module folders under <code>modules/</code>.</li>';
    echo '<li>Delete QA/test rows in <code>equipment_types</code>, test company rows, and sidebar artifacts.</li>';
    echo '<li>Re-ensure canonical <code>modules/is_*</code> facades.</li>';
    echo '</ul>';
    echo '<p><a href="module_clean_tests_qa_runner.php">Back to clean tests page</a></p>';
    echo '</main>';
    exit(0);
}

if (!$options['run']) {
    if (mbqa_clean_tests_is_cli()) {
        mbqa_clean_tests_cli_help();
        exit(0);
    }

    header('Content-Type: text/html; charset=utf-8');
    itm_script_browser_nav_echo();
    echo '<main style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Helvetica,Arial,sans-serif;max-width:860px;margin:16px;line-height:1.5;">';
    echo '<h1>Clean tests for module QA runner</h1>';
    echo '<p><strong>Destructive (local dev DB):</strong> runs the same cleanup used by the QA runner and removes known test artifacts only.</p>';
    echo '<p>This does <strong>not</strong> remove canonical equipment modules like <code>is_switch</code> or <code>is_server</code>.</p>';
    echo '<form method="post" action="module_clean_tests_qa_runner.php" style="margin:16px 0;">';
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars((string)itm_get_csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
    echo '<input type="hidden" name="run" value="1">';
    echo '<button type="submit" style="padding:10px 16px;font-weight:600;">Run Clean Tests</button>';
    echo '</form>';
    echo '<p style="font-size:0.95rem;">' . mbqa_clean_tests_action_links_html($hrefs) . '</p>';
    echo '<p style="font-size:0.9rem;"><a href="module_clean_tests_qa_runner.php?help=1">Help</a></p>';
    echo '</main>';
    exit(0);
}

$isBrowserRun = !mbqa_clean_tests_is_cli() && $options['run'];
if ($isBrowserRun) {
    itm_require_post_csrf();
}

$cleanup = mbqa_clean_tests_run_cleanup();
$exitCode = $cleanup['ok'] ? 0 : 1;

if (mbqa_clean_tests_is_cli()) {
    if ($cleanup['dirs_removed'] > 0) {
        fwrite(STDOUT, "[OK] Removed {$cleanup['dirs_removed']} regression-test / QA scaffold module folder(s)\n");
    } else {
        fwrite(STDOUT, "[OK] No regression-test module folders to remove\n");
    }

    if ($cleanup['companies_deleted'] > 0) {
        $noun = $cleanup['companies_deleted'] === 1 ? 'y' : 'ies';
        fwrite(STDOUT, "[OK] Removed {$cleanup['companies_deleted']} ITM test compan{$noun}\n");
    }

    if ($cleanup['types_deleted'] > 0) {
        fwrite(STDOUT, "[OK] Removed {$cleanup['types_deleted']} equipment_types test row(s)\n");
    } elseif ($cleanup['ok']) {
        fwrite(STDOUT, "[OK] No equipment_types test rows to remove\n");
    }

    if ($cleanup['sidebar_deleted'] > 0) {
        fwrite(STDOUT, "[OK] Removed {$cleanup['sidebar_deleted']} user_sidebar_preferences test row(s)\n");
    }

    fwrite(STDOUT, "[OK] Verified canonical modules/is_* facades ({$cleanup['canonical_ensured']} scaffold pass(es))\n");

    foreach ($cleanup['errors'] as $errorLine) {
        fwrite(STDERR, '[FAIL] ' . $errorLine . "\n");
    }

    fwrite(STDOUT, "\nSummary: {$cleanup['dirs_removed']} test/QA scaffold folder(s) removed; canonical is_* modules preserved.\n");
    exit($exitCode);
}

header('Content-Type: text/html; charset=utf-8');
itm_script_browser_nav_echo();
echo '<main style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Helvetica,Arial,sans-serif;max-width:860px;margin:16px;line-height:1.5;">';
echo '<h1>Clean tests completed</h1>';
if ($cleanup['ok']) {
    echo '<p style="color:#1a7f37;"><strong>Cleanup finished.</strong> Test artifacts were removed where found.</p>';
} else {
    echo '<p style="color:#cf222e;"><strong>Cleanup finished with errors.</strong> Review details below.</p>';
}

echo '<table border="1" cellpadding="8" cellspacing="0" style="border-collapse:collapse;width:100%;max-width:780px;">';
echo '<thead><tr><th>Metric</th><th>Value</th></tr></thead><tbody>';
echo '<tr><td>Scaffold folders removed</td><td>' . (int)$cleanup['dirs_removed'] . '</td></tr>';
echo '<tr><td>Test companies removed</td><td>' . (int)$cleanup['companies_deleted'] . '</td></tr>';
echo '<tr><td>equipment_types rows removed</td><td>' . (int)$cleanup['types_deleted'] . '</td></tr>';
echo '<tr><td>Sidebar test rows removed</td><td>' . (int)$cleanup['sidebar_deleted'] . '</td></tr>';
echo '<tr><td>Canonical facade ensure passes</td><td>' . (int)$cleanup['canonical_ensured'] . '</td></tr>';
echo '</tbody></table>';

if (!empty($cleanup['errors'])) {
    echo '<h2>Errors</h2><ul>';
    foreach ($cleanup['errors'] as $errorLine) {
        echo '<li style="color:#cf222e;">' . htmlspecialchars((string)$errorLine, ENT_QUOTES, 'UTF-8') . '</li>';
    }
    echo '</ul>';
}

echo '<p style="font-size:0.95rem;">' . mbqa_clean_tests_action_links_html($hrefs) . '</p>';
echo '</main>';
exit($exitCode);
