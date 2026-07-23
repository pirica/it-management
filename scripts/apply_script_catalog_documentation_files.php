<?php
/**
 * Insert scripts/data and scripts/*.md catalog rows under Documentation.
 *
 * CLI: php scripts/apply_script_catalog_documentation_files.php [--apply]
 * Browser: ?apply=1 (Admin)
 */
require_once __DIR__ . '/lib/itm_apply_script_bootstrap.php';
require_once __DIR__ . '/lib/itm_script_catalog_documentation_files.php';

$boot = itm_apply_script_bootstrap('Apply Script Catalog Documentation Files');
$apply = $boot['apply'];
$nl = $boot['nl'];
$root = rtrim($boot['root'], '/\\');
$scriptsRoot = $root . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR;
$catalogPath = $scriptsRoot . 'scripts.php';

$catalogHtml = file_get_contents($catalogPath);
if (!is_string($catalogHtml)) {
    echo 'FAIL: could not read scripts/scripts.php' . $nl;
    itm_script_output_end();
    exit(1);
}

$result = itm_script_catalog_documentation_files_patch_catalog($catalogHtml, $scriptsRoot);
$discover = itm_script_catalog_documentation_files_discover($scriptsRoot);

echo 'Documentation data files discovered: ' . count($discover) . $nl;
echo 'Would add: ' . count($result['added']) . '; already cataloged: ' . count($result['skipped']) . $nl . $nl;

if ($result['added'] !== []) {
    echo 'New rows:' . $nl;
    foreach ($result['added'] as $path) {
        echo '  + ' . $path . $nl;
    }
    echo $nl;
}

if ($apply && $result['html'] !== $catalogHtml) {
    file_put_contents($catalogPath, $result['html']);
    echo 'Wrote scripts/scripts.php. Re-run apply_script_catalog_tags.php --apply next.' . $nl;
} elseif (!$apply && $result['html'] !== $catalogHtml) {
    echo 'Dry-run only — use --apply or ?apply=1 to write.' . $nl;
} else {
    echo 'No catalog changes required.' . $nl;
}

itm_script_output_end();
exit(0);
