<?php
/**
 * Static audit: employees fast_create_acc_browser.php FK selects must include __add_new__ quick-add.
 *
 * Exempt: module_slugs[] (registry catalog), bundle_company_id, company_ids[] (tenant pickers — not FK quick-add).
 *
 * CLI: php scripts/check_fast_create_acc_select_quick_add.php
 */


declare(strict_types=1);

/**
 * Browser catalog: How to use (shown on landing before run=1).
 */
function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
<code>php scripts/check_fast_create_acc_select_quick_add.php</code>
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}
if (PHP_SAPI === 'cli') {
    define('ITM_CLI_SCRIPT', true);
}

require_once __DIR__ . '/lib/script_cli_output.php';

$nl = itm_script_output_nl();
$path = dirname(__DIR__) . '/modules/employees/fast_create_acc_browser.php';
$contents = is_file($path) ? (string)file_get_contents($path) : '';

if ($contents === '') {
    echo colorText('[FAIL] modules/employees/fast_create_acc_browser.php not found.', 'fail') . $nl;
    exit(1);
}

$failures = [];
// Why: Only scaffold FK dropdowns need ➕ quick-add; tenant/module pickers are not select_options_api targets.
$exemptSelectPatterns = [
    '/\bname=(["\'])module_slugs\[\]\1/',
    '/\bid=(["\'])module_slugs\1/',
    '/\bname=(["\'])bundle_company_id\1/',
    '/\bid=(["\'])bundle_company_id\1/',
    '/\bname=(["\'])company_ids\[\]\1/',
    '/\bid=(["\'])company_ids\1/',
];

if (!preg_match_all('/<select\b[^>]*>.*?<\/select>/is', $contents, $matches, PREG_OFFSET_CAPTURE)) {
    echo colorText('[FAIL] No <select> elements found in modules/employees/fast_create_acc_browser.php.', 'fail') . $nl;
    exit(1);
}

foreach ($matches[0] as $match) {
    $selectHtml = (string)($match[0] ?? '');
    if ($selectHtml === '') {
        continue;
    }
    $exempt = false;
    foreach ($exemptSelectPatterns as $pattern) {
        if (preg_match($pattern, $selectHtml) === 1) {
            $exempt = true;
            break;
        }
    }
    if ($exempt) {
        continue;
    }
    if (strpos($selectHtml, '__add_new__') === false) {
        $offset = (int)($match[1] ?? 0);
        $line = substr_count(substr($contents, 0, $offset), "\n") + 1;
        $failures[] = 'modules/employees/fast_create_acc_browser.php:' . $line;
    }
}

if (strpos($contents, 'itm_department_option_label') !== false) {
    $entryPath = dirname(__DIR__) . '/modules/employees/fast_create_acc.php';
    $scriptsEntryPath = __DIR__ . '/fast_create_acc.php';
    $entrySource = (is_file($entryPath) ? (string)file_get_contents($entryPath) : '')
        . (is_file($scriptsEntryPath) ? (string)file_get_contents($scriptsEntryPath) : '');
    if (strpos($entrySource, 'itm_fk_option_labels.php') === false) {
        $failures[] = 'modules/employees/fast_create_acc_browser.php: missing require for includes/itm_fk_option_labels.php in entry scripts';
    }
}

if ($failures === []) {
    echo colorText('[PASS] modules/employees/fast_create_acc_browser.php FK selects include __add_new__ quick-add.', 'pass') . $nl;
    exit(0);
}

foreach ($failures as $failure) {
    echo colorText('[FAIL] ' . $failure, 'fail') . $nl;
}

exit(1);
