<?php
/**
 * Static gate: catalog tags match computed scan and every row has tag markup.
 *
 * Browser: scripts/check_script_catalog_tags.php (Administrator session).
 * CLI: php scripts/check_script_catalog_tags.php
 */
require_once __DIR__ . '/lib/itm_script_access_helpers.php';
require_once __DIR__ . '/lib/itm_script_catalog_tags.php';

$nl = itm_check_script_begin_browser_admin('Script catalog tags');

$root = dirname(__DIR__);
$catalogPath = $root . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'scripts.php';
$catalogHtml = file_get_contents($catalogPath);
if (!is_string($catalogHtml)) {
    echo 'FAIL: could not read scripts/scripts.php' . $nl;
    itm_script_output_end();
    exit(1);
}

$schemaTables = itm_script_catalog_tags_schema_tables($root);
$manifest = itm_script_catalog_tags_load_manifest($root);
$overrides = $manifest;
unset($overrides['_defaults']);

$rows = itm_script_catalog_tags_parse_catalog_rows($catalogHtml);
$failures = [];

foreach ($rows as $row) {
    $slug = $row['slug'];
    $result = itm_script_catalog_tags_for_slug($slug, $root, $schemaTables, $overrides, $row['row_html']);
    $expectedTags = $result['tags'];

    if (!preg_match('/\bdata-tags=["\']([^"\']*)["\']/i', $row['full'], $dataMatch)) {
        $failures[] = $slug . ': missing data-tags on <tr>';
        continue;
    }

    $actualTags = array_values(array_filter(preg_split('/\s+/', trim($dataMatch[1])) ?: []));
    $expectedSorted = $expectedTags;
    sort($expectedSorted, SORT_NATURAL | SORT_FLAG_CASE);
    sort($actualTags, SORT_NATURAL | SORT_FLAG_CASE);

    if ($actualTags !== $expectedSorted) {
        $failures[] = $slug . ': data-tags mismatch (expected ' . implode(' ', $expectedSorted) . ', got ' . implode(' ', $actualTags) . ')';
    }

    if (strpos($row['full'], 'scripts-tags-cell') === false) {
        $failures[] = $slug . ': missing scripts-tags-cell markup';
    }

    foreach ($expectedTags as $tag) {
        if (strpos($row['full'], 'scripts-badge-tag') === false) {
            $failures[] = $slug . ': missing scripts-badge-tag pill';
            break;
        }
        $escaped = htmlspecialchars((string)$tag, ENT_QUOTES, 'UTF-8');
        if (strpos($row['full'], '>' . $escaped . '</span>') === false && strpos($row['full'], '>' . $tag . '</span>') === false) {
            $failures[] = $slug . ': missing visible tag pill for ' . $tag;
        }
    }
}

if (!empty($failures)) {
    echo 'FAIL: ' . count($failures) . ' issue(s):' . $nl;
    foreach ($failures as $msg) {
        echo ' - ' . $msg . $nl;
    }
    itm_script_output_end();
    exit(1);
}

echo 'PASS: ' . count($rows) . ' catalog row(s) have correct table tags.' . $nl;
itm_script_output_end();
exit(0);
