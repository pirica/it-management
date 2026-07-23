<?php
/**
 * Compute and apply catalog table tags on scripts/scripts.php rows.
 *
 * Browser + CLI. Default dry-run; writes with CLI --apply or browser ?apply=1 (Admin).
 *
 * Usage:
 *   php scripts/apply_script_catalog_tags.php
 *   php scripts/apply_script_catalog_tags.php --apply
 */
require_once __DIR__ . '/lib/itm_apply_script_bootstrap.php';
require_once __DIR__ . '/lib/itm_script_catalog_tags.php';

$boot = itm_apply_script_bootstrap('Apply Script Catalog Tags');
$apply = $boot['apply'];
$nl = $boot['nl'];
$root = rtrim($boot['root'], '/\\');

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
$tagCounts = [];
$newManifest = isset($manifest['_defaults']) ? ['_defaults' => $manifest['_defaults']] : [
    '_defaults' => [
        'rules' => '0 tables=Codebase; .py=Python; .sh=Server; .json/.txt=Info; .md=Markdown; filename tokens; 1-2=table names; 3+=Mixed. Scan: entry + transitive scripts/ requires + one-level spawn targets + scripts/data|scripts/*.json|txt|md.',
    ],
];
$changed = [];
$unchanged = [];
$patchedHtml = $catalogHtml;

foreach ($rows as $row) {
    $result = itm_script_catalog_tags_for_slug($row['slug'], $root, $schemaTables, $overrides);
    $tags = $result['tags'];
    foreach ($tags as $tag) {
        $tagCounts[$tag] = ($tagCounts[$tag] ?? 0) + 1;
    }

    $slug = $result['slug'];
    if (!isset($overrides[$slug]) || empty($overrides[$slug]['override'])) {
        $newManifest[$slug] = [
            'tags' => $tags,
            'tables' => $result['tables'],
            'override' => false,
        ];
    } else {
        $newManifest[$slug] = $overrides[$slug];
        $tags = is_array($overrides[$slug]['tags'] ?? null) ? $overrides[$slug]['tags'] : $tags;
    }

    $newRow = itm_script_catalog_tags_patch_row($row['attrs'], $row['row_html'], $tags);
    if ($newRow !== $row['full']) {
        $patchedHtml = str_replace($row['full'], $newRow, $patchedHtml);
        $changed[] = $slug . ' → ' . implode(', ', $tags);
    } else {
        $unchanged[] = $slug;
    }
}

$modeLabel = $apply ? 'Updated' : 'Would update';
echo $modeLabel . ' ' . count($changed) . ' catalog row(s); ' . count($unchanged) . ' unchanged.' . $nl . $nl;

ksort($tagCounts, SORT_NATURAL | SORT_FLAG_CASE);
echo 'Tag distribution:' . $nl;
foreach ($tagCounts as $tag => $count) {
    echo '  ' . $tag . ': ' . $count . $nl;
}
echo $nl;

itm_apply_script_echo_list($modeLabel . ' rows', $changed);
itm_apply_script_echo_list('Unchanged rows', array_slice($unchanged, 0, 20));
if (count($unchanged) > 20) {
    echo '  … and ' . (count($unchanged) - 20) . ' more unchanged.' . $nl;
}

if ($apply) {
    if (!itm_script_catalog_tags_save_manifest($root, $newManifest)) {
        echo 'FAIL: could not write scripts/data/script_catalog_tags.json' . $nl;
        itm_script_output_end();
        exit(1);
    }
    if (file_put_contents($catalogPath, $patchedHtml) === false) {
        echo 'FAIL: could not write scripts/scripts.php' . $nl;
        itm_script_output_end();
        exit(1);
    }
    echo $nl . 'Wrote scripts/data/script_catalog_tags.json and scripts/scripts.php.' . $nl;
}

itm_apply_script_finish_hint($apply, $boot['is_cli'], count($changed), $nl, 'apply_script_catalog_tags.php');
itm_script_output_end();
exit(0);
