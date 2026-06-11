<?php
/**
 * CLI audit: fail when index.php uses $displayFieldColumns in foreach without assigning it.
 */
if (PHP_SAPI !== 'cli') {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><body><p>CLI only: <code>php scripts/check_display_field_columns_search.php</code></p>';
    echo '<p><a href="scripts.php">← Scripts index</a></p></body></html>';
    exit(0);
}

require_once __DIR__ . '/lib/script_cli_output.php';
itm_script_output_begin();

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
    echo "PASS: All module index.php files assign \$displayFieldColumns before use.\n";
    exit(0);
}

echo "FAIL: " . count($failures) . " issue(s):\n";
foreach ($failures as $msg) {
    echo "  - {$msg}\n";
}
exit(1);
