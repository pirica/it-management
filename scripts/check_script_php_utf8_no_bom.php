<?php
declare(strict_types=1);

/**
 * Static gate: tracked script PHP entry files must be UTF-8 without BOM.
 *
 * Why: A leading BOM before <?php breaks declare(strict_types=1) and php -l on PHP 7.4.
 *
 * CLI: php scripts/check_script_php_utf8_no_bom.php
 */

/**
 * Browser catalog: How to use (shown on landing before run=1).
 */
function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
<code>php scripts/check_script_php_utf8_no_bom.php</code> — exit <code>1</code> when any <code>scripts/**/*.php</code> file starts with a UTF-8 BOM (<code>EF BB BF</code>).
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

require_once __DIR__ . '/lib/itm_script_stdio.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Script PHP UTF-8 BOM gate');
$nl = itm_script_output_nl();

$root = __DIR__;
$violations = [];
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
$skipBasenames = [
    'check_script_php_utf8_no_bom.php',
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
    $head = (string) file_get_contents($path, false, null, 0, 3);
    if ($head === "\xEF\xBB\xBF") {
        $violations[] = $relative . ' — UTF-8 BOM at file start';
    }
}

if ($violations === []) {
    echo itm_script_format_status_line('[PASS] No UTF-8 BOM in scripts/**/*.php.') . $nl;
    itm_script_output_end();
    exit(0);
}

echo colorText('[FAIL] UTF-8 BOM in script PHP files (strip BOM; source must be UTF-8 without BOM per AGENTS.md):', 'fail') . $nl;
foreach ($violations as $line) {
    echo '  - ' . $line . $nl;
}
itm_script_output_end();
exit(1);
