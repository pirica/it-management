<?php
/**
 * Remove duplicated inline bulk-delete selection scripts after bulk-delete-selection.js loads globally.
 *
 * Why: header.php now ships cancel/exit UX once; inline copies caused double handlers and no Cancel button.
 *
 * CLI: php scripts/apply_bulk_delete_cancel_ux.php
 *      php scripts/apply_bulk_delete_cancel_ux.php --dry-run
 */

require_once __DIR__ . '/lib/script_cli_output.php';
$nl = itm_script_output_nl();


if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg') {
    itm_script_output_begin('Bulk Delete Cancel UX Fix');
    echo '<p><strong>CLI only.</strong></p><pre>php scripts/apply_bulk_delete_cancel_ux.php [--dry-run]</pre>';
    exit(1);
}

$root = dirname(__DIR__);
$dryRun = in_array('--dry-run', $argv ?? [], true);
// Why: do not match across </script> — some pages keep import/color helpers in an earlier script tag.
$pattern = '/<script>\s*(?:\/\*\*[\s\S]*?\*\/\s*)?\(function\s*\(\)\s*\{(?:(?!<\/script>)[\s\S])*?let\s+selectionMode\s*=\s*false;(?:(?!<\/script>)[\s\S])*?\}\)\(\);\s*<\/script>\s*/i';

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root . '/modules', RecursiveDirectoryIterator::SKIP_DOTS)
);
$changed = 0;
$skipped = 0;

foreach ($iterator as $fileInfo) {
    if (!$fileInfo->isFile() || strtolower($fileInfo->getExtension()) !== 'php') {
        continue;
    }
    $path = $fileInfo->getPathname();
    $content = file_get_contents($path);
    if (!is_string($content) || stripos($content, 'let selectionMode = false') === false) {
        continue;
    }
    if (preg_match($pattern, $content) !== 1) {
        $skipped++;
        fwrite(STDERR, "skip (pattern mismatch): {$path}\n");
        continue;
    }
    $next = preg_replace($pattern, '', $content, 1);
    if (!is_string($next) || $next === $content) {
        $skipped++;
        continue;
    }
    if (!$dryRun) {
        file_put_contents($path, $next);
    }
    $changed++;
    $rel = str_replace('\\', '/', substr($path, strlen($root) + 1));
    echo ($dryRun ? '[dry-run] ' : '') . "updated: {$rel}" . PHP_EOL;
}

echo "\nDone. updated={$changed} skipped_pattern={$skipped}" . ($dryRun ? ' (dry-run)' : '') . PHP_EOL;

itm_script_output_end();
