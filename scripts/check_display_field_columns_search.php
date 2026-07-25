<?php
/**
 * CLI audit: fail when index.php uses $displayFieldColumns in foreach without assigning it.
 *
 * Browser: open scripts/check_display_field_columns_search.php (Administrator session).
 * CLI: php scripts/check_display_field_columns_search.php
 */

/**
 * Browser catalog: How to use (shown on landing before run=1).
 */
function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
Browser: plain-text report. CLI: <code>php scripts/check_display_field_columns_search.php</code> — run after bulk CRUD/search changes; exit <code>1</code> on failure.
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}
require_once __DIR__ . '/lib/itm_script_access_helpers.php';

$nl = itm_check_script_begin_browser_admin('Display field columns search');

$root = dirname(__DIR__);
$failures = [];

foreach (glob($root . '/modules/*/index.php') as $path) {
    $rel = str_replace($root . DIRECTORY_SEPARATOR, '', $path);
    $content = file_get_contents($path);
    if ($content === false || strpos($content, 'foreach ($displayFieldColumns') === false) {
        continue;
    }
    if (!preg_match('/\$displayFieldColumns\s*=\s*/', $content)) {
        $failures[] = $rel . ' uses $displayFieldColumns without assignment';
    }
}

if (empty($failures)) {
    echo "PASS: All module index.php files assign \$displayFieldColumns before use." . $nl;
    itm_script_output_end();
    exit(0);
}

echo "FAIL: " . count($failures) . " issue(s):" . $nl;
foreach ($failures as $msg) {
    echo ' - ' . $msg . $nl;
}
itm_script_output_end();
exit(1);
