<?php
/**
 * Static audit: browser scripts must not render duplicate ← Scripts index links.
 *
 * Why: itm_script_output_begin() already calls itm_script_browser_nav_echo(); a second
 * call in the same browser response stacks two identical back links.
 *
 * Browser: scripts/check_script_browser_nav_duplicate.php
 * CLI: php scripts/check_script_browser_nav_duplicate.php
 */
declare(strict_types=1);

require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Script browser nav duplicate audit');
$nl = itm_script_output_nl();

/**
 * @return array<int, string>
 */
function itm_check_script_nav_duplicate_issues(string $content): array
{
    $issues = [];

    if (strpos($content, 'itm_script_output_begin') === false) {
        return [];
    }
    if (strpos($content, 'itm_script_browser_nav_echo') === false
        && strpos($content, 'itm_script_browser_nav_html') === false) {
        return [];
    }

    if (preg_match(
        '/itm_script_output_close_pre\s*\(\s*\)\s*;[^\n]*\n(?:[^\n]*\n){0,8}[^\n]*itm_script_browser_nav_(?:echo|html)/i',
        $content
    )) {
        $issues[] = 'itm_script_browser_nav_* immediately after itm_script_output_close_pre()';
    }

    if (preg_match(
        '/if\s*\(\s*!\s*\$?(?:isCli|itmIsCli)\s*\)\s*\{[^}]*itm_script_output_begin[^}]*itm_script_browser_nav_(?:echo|html)/is',
        $content
    )) {
        $issues[] = 'browser-only block calls itm_script_browser_nav_* after itm_script_output_begin()';
    }

    if (preg_match(
        '/itm_script_output_begin\s*\([^)]*\)\s*;\s*\n\s*itm_script_browser_nav_echo/is',
        $content
    )) {
        $issues[] = 'itm_script_browser_nav_echo() directly after itm_script_output_begin()';
    }

    if (preg_match(
        '/itm_script_output_begin\s*\([^)]*\)\s*;[\s\S]{0,400}?if\s*\(\s*!\s*\$?(?:isCli|itmIsCli)\s*\)\s*\{[^}]*itm_script_browser_nav_echo/is',
        $content
    )) {
        $issues[] = 'itm_script_output_begin() then browser branch repeats itm_script_browser_nav_echo()';
    }

    if (preg_match(
        '/if\s*\(\s*!\s*\$?(?:isCli|itmIsCli)\s*\)\s*\{[^}]*itm_script_browser_nav_echo[^}]*\}[\s\S]{0,120}?itm_script_output_begin/is',
        $content
    )) {
        $issues[] = 'itm_script_browser_nav_echo() in browser block before itm_script_output_begin()';
    }

    return array_values(array_unique($issues));
}

/**
 * @return array<int, string>
 */
function itm_check_script_nav_duplicate_collect_failures(string $scriptsRoot): array
{
    $failures = [];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($scriptsRoot, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $fileInfo) {
        if (!$fileInfo->isFile() || $fileInfo->getExtension() !== 'php') {
            continue;
        }

        $path = $fileInfo->getPathname();
        if (strpos(str_replace('\\', '/', $path), '/lib/') !== false) {
            continue;
        }

        $basename = $fileInfo->getFilename();
        if ($basename === 'check_script_browser_nav_duplicate.php' || $basename === 'scripts.php') {
            continue;
        }

        $content = file_get_contents($path);
        if ($content === false) {
            continue;
        }

        $issues = itm_check_script_nav_duplicate_issues($content);
        if ($issues === []) {
            continue;
        }

        $rel = 'scripts/' . $basename;
        foreach ($issues as $issue) {
            $failures[] = $rel . ': ' . $issue;
        }
    }

    sort($failures, SORT_STRING);

    return $failures;
}

$failures = itm_check_script_nav_duplicate_collect_failures(__DIR__);

if ($failures === []) {
    $line = '[PASS] No duplicate ← Scripts index patterns in scripts/*.php.';
    echo (PHP_SAPI === 'cli' ? colorText($line . PHP_EOL, 'pass') : itm_script_format_status_line($line) . $nl);
    itm_script_output_end();
    exit(0);
}

$header = '[FAIL] ' . count($failures) . ' duplicate nav pattern(s):';
echo (PHP_SAPI === 'cli' ? colorText($header . PHP_EOL, 'fail') : itm_script_format_status_line($header) . $nl);
foreach ($failures as $line) {
    echo '  - ' . htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . $nl;
}

itm_script_output_end();
exit(1);
