<?php
/**
 * Static audit: every department FK <select> must include __add_new__ quick-add.
 *
 * CLI: php scripts/check_department_select_quick_add.php
 */

declare(strict_types=1);

if (PHP_SAPI === 'cli') {
    define('ITM_CLI_SCRIPT', true);
}

require_once __DIR__ . '/lib/script_cli_output.php';

$nl = itm_script_output_nl();
$isCli = PHP_SAPI === 'cli';
if (!$isCli) {
    require_once __DIR__ . '/../config/config.php';
    itm_script_require_admin_script_or_exit($GLOBALS['conn'] ?? null, 'Administrator access required.');
    itm_script_output_begin('Department select quick-add audit');
}

$roots = [
    dirname(__DIR__) . '/modules',
    dirname(__DIR__) . '/scripts',
];

$failures = [];

foreach ($roots as $root) {
    if (!is_dir($root)) {
        continue;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $fileInfo) {
        if (!$fileInfo->isFile() || strtolower($fileInfo->getExtension()) !== 'php') {
            continue;
        }
        $path = str_replace('\\', '/', $fileInfo->getPathname());
        $contents = file_get_contents($path);
        if ($contents === false) {
            continue;
        }

        if (!preg_match_all('/<select\b[^>]*>.*?<\/select>/is', $contents, $matches, PREG_OFFSET_CAPTURE)) {
            continue;
        }

        foreach ($matches[0] as $match) {
            $selectHtml = (string)($match[0] ?? '');
            if ($selectHtml === '') {
                continue;
            }
            $isDepartmentSelect = preg_match(
                '/\bname=(["\'])department_id(?:\[\])?\1|\bdata-add-table=(["\'])departments\2/',
                $selectHtml
            ) === 1;
            if (!$isDepartmentSelect) {
                continue;
            }
            if (strpos($selectHtml, '__add_new__') === false) {
                $offset = (int)($match[1] ?? 0);
                $line = substr_count(substr($contents, 0, $offset), "\n") + 1;
                $failures[] = $path . ':' . $line;
            }
        }
    }
}

if ($failures === []) {
    $line = '[PASS] All department selects include __add_new__ quick-add.';
    echo colorText($line, 'pass') . $nl;
    exit(0);
}

foreach ($failures as $failurePath) {
    $relative = str_replace(str_replace('\\', '/', dirname(__DIR__)) . '/', '', $failurePath);
    $line = '[FAIL] Missing __add_new__ on department select: ' . $relative;
    echo colorText($line, 'fail') . $nl;
}

exit(1);
