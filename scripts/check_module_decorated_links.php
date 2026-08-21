<?php
/**
 * Tier 2 gate: fail when modules render decorated inline <a> links (default blue styling).
 *
 * Why: Inline links must use itm-plain-link or sort-header color:inherit — see list_module_decorated_links.php.
 *
 * CLI: php scripts/check_module_decorated_links.php
 * Browser: scripts/check_module_decorated_links.php (plain-text report; no DB)
 */

declare(strict_types=1);

/**
 * Browser catalog: How to use (shown on landing before run=1).
 */
function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
Browser: plain-text report. CLI: <code>php scripts/check_module_decorated_links.php</code> — exit <code>0</code> when no decorated links remain. Repair: <code>php scripts/apply_module_decorated_plain_links.php --apply</code>.
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

require_once __DIR__ . '/lib/itm_module_decorated_links_report.php';
require_once __DIR__ . '/lib/script_cli_output.php';

$root = dirname(__DIR__);
$nl = itm_script_output_nl();

if (PHP_SAPI !== 'cli') {
    require_once __DIR__ . '/lib/itm_script_browser_usage.php';
    itm_script_browser_usage_maybe_gate(['title' => 'Module decorated links check']);
}

itm_script_output_begin('Module decorated links check');

$rows = itm_module_decorated_links_collect_report($root, []);
$grouped = itm_module_decorated_links_group_by_slug($rows);

if ($rows === []) {
    echo 'PASS: No decorated inline module links (0 findings).' . $nl;
    itm_script_output_end();
    exit(0);
}

echo 'FAIL: ' . count($rows) . ' decorated link(s) in ' . count($grouped) . ' module(s).' . $nl . $nl;

foreach ($grouped as $slug => $findings) {
    echo '[FAIL] link=' . $slug . $nl;
    foreach ($findings as $finding) {
        echo '  ' . itm_module_decorated_links_format_finding_line($finding) . $nl;
    }
    echo $nl;
}

echo 'Repair: php scripts/apply_module_decorated_plain_links.php --apply' . $nl;
itm_script_output_end();
exit(1);
