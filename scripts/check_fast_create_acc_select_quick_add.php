<?php
/**
 * Static audit: employees fast_create_acc.php FK selects must include __add_new__ quick-add.
 *
 * Exempt: module_slugs[] (registry catalog).
 *
 * CLI: php scripts/check_fast_create_acc_select_quick_add.php
 */

declare(strict_types=1);

if (PHP_SAPI === 'cli') {
    define('ITM_CLI_SCRIPT', true);
}

require_once __DIR__ . '/lib/script_cli_output.php';

$nl = itm_script_output_nl();
$path = dirname(__DIR__) . '/modules/employees/fast_create_acc.php';
$contents = is_file($path) ? (string)file_get_contents($path) : '';

if ($contents === '') {
    echo colorText('[FAIL] modules/employees/fast_create_acc.php not found.', 'fail') . $nl;
    exit(1);
}

$failures = [];
$exemptNamePattern = '/\bname=(["\'])module_slugs\[\]\1/';

if (!preg_match_all('/<select\b[^>]*>.*?<\/select>/is', $contents, $matches, PREG_OFFSET_CAPTURE)) {
    echo colorText('[FAIL] No <select> elements found in modules/employees/fast_create_acc.php.', 'fail') . $nl;
    exit(1);
}

foreach ($matches[0] as $match) {
    $selectHtml = (string)($match[0] ?? '');
    if ($selectHtml === '') {
        continue;
    }
    if (preg_match($exemptNamePattern, $selectHtml) === 1) {
        continue;
    }
    if (strpos($selectHtml, '__add_new__') === false) {
        $offset = (int)($match[1] ?? 0);
        $line = substr_count(substr($contents, 0, $offset), "\n") + 1;
        $failures[] = 'modules/employees/fast_create_acc.php:' . $line;
    }
}

if (strpos($contents, 'itm_department_option_label') !== false && strpos($contents, 'itm_fk_option_labels.php') === false) {
    $failures[] = 'modules/employees/fast_create_acc.php: missing require for includes/itm_fk_option_labels.php';
}

if ($failures === []) {
    echo colorText('[PASS] modules/employees/fast_create_acc.php FK selects include __add_new__ quick-add.', 'pass') . $nl;
    exit(0);
}

foreach ($failures as $failure) {
    echo colorText('[FAIL] ' . $failure, 'fail') . $nl;
}

exit(1);
