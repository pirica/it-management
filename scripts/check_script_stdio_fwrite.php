<?php
/**
 * Static gate: scripts must not call fwrite(STDOUT|STDERR) directly (undefined under Apache).
 *
 * Use itm_script_write_stdout() / itm_script_write_stderr() from scripts/lib/itm_script_stdio.php.
 *
 * CLI: php scripts/check_script_stdio_fwrite.php
 */

declare(strict_types=1);

/**
 * Browser catalog: How to use (shown on landing before run=1).
 */
function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
<code>php scripts/check_script_stdio_fwrite.php</code> — exit <code>1</code> when any <code>scripts/**/*.php</code> file uses raw <code>fwrite(STDOUT</code> or <code>fwrite(STDERR</code>.
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

require_once __DIR__ . '/lib/itm_script_stdio.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Script STDIO fwrite gate');
$nl = itm_script_output_nl();

$root = __DIR__;
$violations = [];
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
$skipBasenames = [
    'check_script_stdio_fwrite.php',
    'scripts.php',
];

foreach ($rii as $fileInfo) {
    if (!$fileInfo->isFile() || substr($fileInfo->getFilename(), -4) !== '.php') {
        continue;
    }
    $base = $fileInfo->getFilename();
    if (in_array($base, $skipBasenames, true)) {
        continue;
    }
    $path = $fileInfo->getPathname();
    if (strpos($path, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR) !== false) {
        continue;
    }
    $relative = str_replace('\\', '/', substr($path, strlen(dirname($root)) + 1));
    $contents = (string) file_get_contents($path);
    if (preg_match('/fwrite\s*\(\s*STDOUT\b/s', $contents)) {
        $violations[] = $relative . ' — fwrite(STDOUT)';
    }
    if (preg_match('/fwrite\s*\(\s*STDERR\b/', $contents)) {
        $violations[] = $relative . ' — fwrite(STDERR)';
    }
}

if ($violations === []) {
    echo itm_script_format_status_line('[PASS] No raw fwrite(STDOUT|STDERR) in scripts/.') . $nl;
    itm_script_output_end();
    exit(0);
}

echo colorText('[FAIL] Raw STDIO fwrite usage (use itm_script_write_stdout/stderr):', 'fail') . $nl;
foreach ($violations as $line) {
    echo '  - ' . $line . $nl;
}
itm_script_output_end();
exit(1);
